<?php

declare(strict_types=1);

namespace Memex\Service;

use PDO;
use RuntimeException;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\Component\Uid\Uuid;

class VectorService
{
    private PDO $db;
    private string $ollamaUrl = 'http://localhost:11434';
    private string $embeddingModel = 'nomic-embed-text';

    public function __construct(
        string $knowledgeBasePath,
        private readonly TextSplitTransformer $chunker = new TextSplitTransformer(
            chunkSize: 2000,
            overlap: 200
        ),
        private readonly int $numCtx = 512
    ) {
        $vectorsDir = $knowledgeBasePath . '/.vectors';
        
        if (!is_dir($vectorsDir)) {
            mkdir($vectorsDir, 0755, true);
        }
        
        $dbPath = $vectorsDir . '/embeddings.db';
        $this->db = new PDO("sqlite:{$dbPath}");
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initialize();
    }

    private function initialize(): void
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS embeddings (
                id TEXT PRIMARY KEY,
                type TEXT NOT NULL,
                slug TEXT NOT NULL,
                uuid TEXT,
                name TEXT NOT NULL,
                title TEXT,
                tags TEXT,
                content TEXT NOT NULL,
                vector BLOB NOT NULL,
                metadata TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT,
                parent_id TEXT,
                chunk_index INTEGER
            );
            
            CREATE INDEX IF NOT EXISTS idx_type ON embeddings(type);
            CREATE INDEX IF NOT EXISTS idx_slug ON embeddings(slug);
            CREATE INDEX IF NOT EXISTS idx_uuid ON embeddings(uuid);
            CREATE INDEX IF NOT EXISTS idx_parent_id ON embeddings(parent_id);
        ');
    }

    public function index(string $slug, string $uuid, array $compiled): void
    {
        $now = date('c');
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO embeddings 
            (id, type, slug, uuid, name, title, tags, content, vector, metadata, created_at, updated_at, parent_id, chunk_index)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $contentForEmbedding = mb_strlen($compiled['content']) <= 4000
            ? $compiled['content']
            : mb_substr($compiled['content'], 0, 4000);

        $vector = $this->embedWithOllama($contentForEmbedding);

        $stmt->execute([
            $slug,
            $compiled['metadata']['type'] ?? 'guide',
            $slug,
            $uuid,
            $compiled['name'],
            $compiled['metadata']['title'] ?? $compiled['name'],
            json_encode($compiled['metadata']['tags'] ?? []),
            $compiled['content'],
            $this->serializeVector($vector),
            json_encode($compiled),
            $compiled['metadata']['created'] ?? $now,
            $now,
            null,
            null,
        ]);
        
        foreach ($compiled['sections'] as $i => $section) {
            if (empty(trim($section['content']))) {
                continue;
            }
            
            $sectionText = $section['title'] . "\n\n" . $section['content'];
            $sectionId = "{$slug}_section_{$i}";

            $doc = new TextDocument(
                Uuid::v4(),
                $sectionText,
                new Metadata(['section_title' => $section['title']])
            );

            $chunks = $this->chunker->transform([$doc]);
            $chunkIndex = 0;

            foreach ($chunks as $chunkDoc) {
                $chunkContent = $chunkDoc->getContent();
                $chunkVector = $this->embedWithOllama($chunkContent);

                $metadata = $chunkDoc->getMetadata();
                $isChunk = isset($metadata[Metadata::KEY_PARENT_ID]);

                $stmt->execute([
                    $isChunk ? "{$sectionId}_chunk_{$chunkIndex}" : $sectionId,
                    $isChunk ? 'chunk' : 'section',
                    $slug,
                    null,
                    $compiled['name'],
                    $section['title'],
                    json_encode([]),
                    $chunkContent,
                    $this->serializeVector($chunkVector),
                    json_encode([
                        'parent_slug' => $slug,
                        'section_index' => $i,
                        'section_title' => $section['title'],
                        'is_chunk' => $isChunk,
                    ]),
                    $now,
                    $now,
                    $isChunk ? $sectionId : null,
                    $isChunk ? $chunkIndex : null,
                ]);

                $chunkIndex++;
            }
        }
    }

    public function search(string $query, int $limit = 5, float $threshold = 0.5, bool $returnParents = true): array
    {
        $queryVector = $this->embedWithOllama($query);
        
        $stmt = $this->db->query('SELECT * FROM embeddings');
        $results = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vector = $this->deserializeVector($row['vector']);
            $similarity = $this->cosineSimilarity($queryVector, $vector);
            
            if ($similarity >= $threshold) {
                $results[] = [
                    'score' => round($similarity, 4),
                    'id' => $row['id'],
                    'type' => $row['type'],
                    'slug' => $row['slug'],
                    'name' => $row['name'],
                    'title' => $row['title'],
                    'tags' => json_decode($row['tags'], true),
                    'content' => $row['content'],
                    'metadata' => json_decode($row['metadata'], true),
                ];
            }
        }
        
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        if (!$returnParents) {
            return array_slice($results, 0, $limit);
        }

        $matches = [];
        foreach ($results as $result) {
            $slug = $result['slug'];

            if (!isset($matches[$slug]) || $matches[$slug]['score'] < $result['score']) {
                $matches[$slug] = [
                    'score' => $result['score'],
                    'slug' => $slug,
                    'matched_content' => $result['content'],
                ];
            }
        }

        $parentStmt = $this->db->prepare('SELECT * FROM embeddings WHERE slug = ? AND (type = "guide" OR type = "context") LIMIT 1');
        $parentResults = [];

        foreach ($matches as $match) {
            $parentStmt->execute([$match['slug']]);
            $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);

            if ($parent) {
                $parentResults[] = [
                    'score' => $match['score'],
                    'id' => $parent['id'],
                    'type' => $parent['type'],
                    'slug' => $parent['slug'],
                    'name' => $parent['name'],
                    'title' => $parent['title'],
                    'tags' => json_decode($parent['tags'], true),
                    'content' => $match['matched_content'],
                    'metadata' => json_decode($parent['metadata'], true),
                ];
            }
        }

        usort($parentResults, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($parentResults, 0, $limit);
    }

    public function listAll(?string $type = null): array
    {
        $sql = 'SELECT * FROM embeddings WHERE (type = "guide" OR type = "context")';
        $params = [];
        
        if ($type !== null) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }
        
        $sql .= ' ORDER BY created_at DESC';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'slug' => $row['slug'],
                'uuid' => $row['uuid'],
                'name' => $row['name'],
                'title' => $row['title'],
                'tags' => json_decode($row['tags'], true),
                'content' => $row['content'],
                'metadata' => json_decode($row['metadata'], true),
            ];
        }
        
        return $results;
    }

    public function getByUuid(string $uuid): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM embeddings 
            WHERE uuid = ? AND (type = "guide" OR type = "context")
            LIMIT 1
        ');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return [
            'id' => $row['id'],
            'type' => $row['type'],
            'slug' => $row['slug'],
            'uuid' => $row['uuid'],
            'name' => $row['name'],
            'title' => $row['title'],
            'tags' => json_decode($row['tags'], true),
            'content' => $row['content'],
            'metadata' => json_decode($row['metadata'], true),
        ];
    }

    public function existsByUuid(string $uuid): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM embeddings WHERE uuid = ?');
        $stmt->execute([$uuid]);
        return $stmt->fetchColumn() > 0;
    }

    public function delete(string $slug): void
    {
        $stmt = $this->db->prepare('
            DELETE FROM embeddings 
            WHERE slug = ? OR id LIKE ?
        ');
        $stmt->execute([$slug, "{$slug}_section_%"]);
    }

    public function exists(string $slug): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM embeddings WHERE slug = ? AND type != "section"');
        $stmt->execute([$slug]);
        return $stmt->fetchColumn() > 0;
    }

    private function embedWithOllama(string $text): array
    {
        $ch = curl_init("{$this->ollamaUrl}/api/embeddings");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->embeddingModel,
                'prompt' => $text,
                'options' => [
                    'num_ctx' => $this->numCtx,
                ],
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || $response === false) {
            throw new RuntimeException(
                "Failed to get embeddings from Ollama. Make sure Ollama is running and model '{$this->embeddingModel}' is installed."
            );
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['embedding']) || empty($data['embedding'])) {
            throw new RuntimeException("Invalid response from Ollama: " . $response);
        }
        
        return $data['embedding'];
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        
        $count = min(count($a), count($b));
        
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        
        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }
        
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    private function serializeVector(array $vector): string
    {
        return pack('f*', ...$vector);
    }

    private function deserializeVector(string $binary): array
    {
        $unpacked = unpack('f*', $binary);
        return $unpacked ? array_values($unpacked) : [];
    }
}
