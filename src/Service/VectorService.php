<?php

declare(strict_types=1);

namespace Memex\Service;

use PDO;
use RuntimeException;

class VectorService
{
    private PDO $db;
    private string $ollamaUrl = 'http://localhost:11434';
    private string $embeddingModel = 'nomic-embed-text';

    public function __construct(string $knowledgeBasePath)
    {
        $varDir = $knowledgeBasePath . '/var';
        
        if (!is_dir($varDir)) {
            mkdir($varDir, 0755, true);
        }
        
        $dbPath = $varDir . '/embeddings.db';
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
                name TEXT NOT NULL,
                title TEXT,
                tags TEXT,
                content TEXT NOT NULL,
                vector BLOB NOT NULL,
                metadata TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT
            );
            
            CREATE INDEX IF NOT EXISTS idx_type ON embeddings(type);
            CREATE INDEX IF NOT EXISTS idx_slug ON embeddings(slug);
        ');
    }

    public function index(string $slug, array $compiled): void
    {
        $vector = $this->embedWithOllama($compiled['content']);
        
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO embeddings 
            (id, type, slug, name, title, tags, content, vector, metadata, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $now = date('c');
        $stmt->execute([
            $slug,
            $compiled['metadata']['type'] ?? 'guide',
            $slug,
            $compiled['name'],
            $compiled['metadata']['title'] ?? $compiled['name'],
            json_encode($compiled['metadata']['tags'] ?? []),
            $compiled['content'],
            $this->serializeVector($vector),
            json_encode($compiled),
            $compiled['metadata']['created'] ?? $now,
            $now,
        ]);
        
        foreach ($compiled['sections'] as $i => $section) {
            if (empty(trim($section['content']))) {
                continue;
            }
            
            $sectionText = $section['title'] . "\n\n" . $section['content'];
            $sectionVector = $this->embedWithOllama($sectionText);
            
            $stmt->execute([
                "{$slug}_section_{$i}",
                'section',
                $slug,
                $compiled['name'],
                $section['title'],
                json_encode([]),
                $section['content'],
                $this->serializeVector($sectionVector),
                json_encode([
                    'parent_slug' => $slug,
                    'section_index' => $i,
                    'section_title' => $section['title'],
                ]),
                $now,
                $now,
            ]);
        }
    }

    public function search(string $query, int $limit = 5, float $threshold = 0.5): array
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
        
        return array_slice($results, 0, $limit);
    }

    public function listAll(?string $type = null): array
    {
        $sql = 'SELECT * FROM embeddings WHERE type != "section"';
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
                'name' => $row['name'],
                'title' => $row['title'],
                'tags' => json_decode($row['tags'], true),
                'content' => $row['content'],
                'metadata' => json_decode($row['metadata'], true),
            ];
        }
        
        return $results;
    }

    public function delete(string $slug): void
    {
        $stmt = $this->db->prepare('
            DELETE FROM embeddings 
            WHERE slug = ? OR id LIKE ?
        ');
        $stmt->execute([$slug, "{$slug}_section_%"]);
    }

    private function embedWithOllama(string $text): array
    {
        $ch = curl_init("{$this->ollamaUrl}/api/embeddings");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $this->embeddingModel,
                'prompt' => $text,
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
