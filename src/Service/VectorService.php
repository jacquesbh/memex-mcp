<?php

declare(strict_types=1);

namespace Memex\Service;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\Component\Uid\Uuid;

class VectorService
{
    private const SCHEMA_VERSION = 2;

    private PDO $db;
    private string $ollamaUrl = 'http://localhost:11434';
    private string $embeddingModel = 'nomic-embed-text';

    public function __construct(
        string $knowledgeBasePath,
        private readonly TextSplitTransformer $chunker = new TextSplitTransformer(
            chunkSize: 700,
            overlap: 200
        ),
        private readonly int $numCtx = 512
    ) {
        $vectorsDir = $knowledgeBasePath . '/.vectors';

        if (!is_dir($vectorsDir)) {
            if (!mkdir($vectorsDir, 0755, true) && !is_dir($vectorsDir)) {
                throw new RuntimeException("Failed to create vectors directory: {$vectorsDir}");
            }
        }

        $dbPath = $vectorsDir . '/embeddings.db';
        try {
            $this->db = new PDO("sqlite:{$dbPath}");
        } catch (\PDOException $error) {
            throw new RuntimeException("Failed to open embeddings database: {$dbPath}", 0, $error);
        }
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initialize();
    }

    private function initialize(): void
    {
        $version = (int) $this->db->query('PRAGMA user_version')->fetchColumn();

        if ($version > self::SCHEMA_VERSION) {
            throw new RuntimeException("Unsupported embeddings database schema version: {$version}");
        }

        if ($version === self::SCHEMA_VERSION && $this->hasContentTypeConstraint()) {
            return;
        }

        if (!$this->db->beginTransaction()) {
            throw new RuntimeException('Failed to start schema migration transaction');
        }

        try {
            if ($this->tableExists('embeddings')) {
                $this->migrateSchema();
            } else {
                $this->createSchema();
            }

            $this->db->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
            if (!$this->db->commit()) {
                throw new RuntimeException('Failed to commit schema migration transaction');
            }
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $error;
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
        $stmt->execute([$table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function hasContentTypeConstraint(): bool
    {
        if (!$this->tableExists('embeddings')) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
        $stmt->execute(['embeddings']);
        $sql = $stmt->fetchColumn();

        return is_string($sql)
            && preg_match('/content_type\s+TEXT\s+NOT\s+NULL\s+CHECK\s*\(\s*content_type\s+IN\s*\(\s*[\"\']guide[\"\']\s*,\s*[\"\']context[\"\']\s*\)\s*\)/i', $sql) === 1;
    }

    private function createSchema(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS embeddings (
                id TEXT PRIMARY KEY,
                type TEXT NOT NULL,
                content_type TEXT NOT NULL CHECK (content_type IN ('guide', 'context')),
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
            )
        ");

        $this->createIndexes();
    }

    private function createIndexes(): void
    {
        $this->db->exec('
            CREATE INDEX IF NOT EXISTS idx_embeddings_content_type_type ON embeddings(content_type, type);
            CREATE INDEX IF NOT EXISTS idx_embeddings_content_type_slug ON embeddings(content_type, slug);
            CREATE INDEX IF NOT EXISTS idx_embeddings_uuid ON embeddings(uuid);
            CREATE INDEX IF NOT EXISTS idx_embeddings_parent_id ON embeddings(parent_id);
            CREATE UNIQUE INDEX IF NOT EXISTS idx_embeddings_parent ON embeddings(content_type, slug)
                WHERE type IN ("guide", "context");
        ');
    }

    private function migrateSchema(): void
    {
        $columns = $this->db->query('PRAGMA table_info(embeddings)')->fetchAll(PDO::FETCH_ASSOC);
        $hasContentType = in_array('content_type', array_column($columns, 'name'), true);

        $this->db->exec('
            CREATE TABLE embeddings_migrated (
                id TEXT PRIMARY KEY,
                type TEXT NOT NULL,
                content_type TEXT NOT NULL CHECK (content_type IN ("guide", "context")),
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
            )
        ');

        $contentType = $hasContentType ? 'legacy.content_type' : '
            CASE
                WHEN legacy.type IN ("guide", "context") THEN legacy.type
                ELSE COALESCE(
                    (
                        SELECT parent.type
                        FROM embeddings parent
                        WHERE parent.slug = legacy.slug
                            AND parent.type IN ("guide", "context")
                        LIMIT 1
                    ),
                    "guide"
                )
            END
        ';
        $id = $hasContentType ? 'legacy.id' : "({$contentType}) || ':' || legacy.id";
        $parentId = $hasContentType
            ? 'legacy.parent_id'
            : "CASE WHEN legacy.parent_id IS NULL THEN NULL ELSE ({$contentType}) || ':' || legacy.parent_id END";

        $this->db->exec("
            INSERT INTO embeddings_migrated
                (id, type, content_type, slug, uuid, name, title, tags, content, vector, metadata, created_at, updated_at, parent_id, chunk_index)
            SELECT
                {$id},
                legacy.type,
                {$contentType},
                legacy.slug,
                legacy.uuid,
                legacy.name,
                legacy.title,
                legacy.tags,
                legacy.content,
                legacy.vector,
                legacy.metadata,
                legacy.created_at,
                legacy.updated_at,
                {$parentId},
                legacy.chunk_index
            FROM embeddings legacy
        ");

        $this->db->exec('DROP TABLE embeddings');
        $this->db->exec('ALTER TABLE embeddings_migrated RENAME TO embeddings');
        $this->createIndexes();
    }

    public function index(string $slug, string $uuid, array $compiled): void
    {
        $contentType = $compiled['metadata']['type'] ?? null;
        if (!is_string($contentType) || !in_array($contentType, ['guide', 'context'], true)) {
            throw new InvalidArgumentException('Content type must be guide or context');
        }

        $now = date('c');
        $contentForEmbedding = mb_strlen($compiled['content']) <= 700
            ? $compiled['content']
            : mb_substr($compiled['content'], 0, 700);

        $vector = $this->embedWithOllama($contentForEmbedding);
        $rows = [[
            "{$contentType}:{$slug}",
            $contentType,
            $contentType,
            $slug,
            $uuid,
            $compiled['name'],
            $compiled['metadata']['title'] ?? $compiled['name'],
            json_encode($compiled['metadata']['tags'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $compiled['content'],
            $this->serializeVector($vector),
            json_encode($compiled, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $compiled['metadata']['created'] ?? $now,
            $now,
            null,
            null,
        ]];

        foreach ($compiled['sections'] as $i => $section) {
            if (empty(trim($section['content']))) {
                continue;
            }

            $sectionText = $section['title'] . "\n\n" . $section['content'];
            $sectionId = "{$contentType}:{$slug}:section:{$i}";

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

                $rows[] = [
                    $isChunk ? "{$sectionId}:chunk:{$chunkIndex}" : $sectionId,
                    $isChunk ? 'chunk' : 'section',
                    $contentType,
                    $slug,
                    null,
                    $compiled['name'],
                    $section['title'],
                    json_encode([], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    $chunkContent,
                    $this->serializeVector($chunkVector),
                    json_encode([
                        'parent_slug' => $slug,
                        'section_index' => $i,
                        'section_title' => $section['title'],
                        'is_chunk' => $isChunk,
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    $now,
                    $now,
                    $isChunk ? $sectionId : null,
                    $isChunk ? $chunkIndex : null,
                ];

                $chunkIndex++;
            }
        }

        if (!$this->db->beginTransaction()) {
            throw new RuntimeException('Failed to start index replacement transaction');
        }

        try {
            $deleteStmt = $this->db->prepare('DELETE FROM embeddings WHERE content_type = ? AND slug = ?');
            $deleteStmt->execute([$contentType, $slug]);
            $insertStmt = $this->db->prepare('
                INSERT INTO embeddings
                (id, type, content_type, slug, uuid, name, title, tags, content, vector, metadata, created_at, updated_at, parent_id, chunk_index)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            foreach ($rows as $row) {
                $insertStmt->execute($row);
            }

            if (!$this->db->commit()) {
                throw new RuntimeException('Failed to commit index replacement transaction');
            }
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $error;
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
                    'content_type' => $row['content_type'],
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
            $contentType = $result['content_type'];
            $key = "{$contentType}:{$slug}";

            if (!isset($matches[$key]) || $matches[$key]['score'] < $result['score']) {
                $matches[$key] = [
                    'score' => $result['score'],
                    'slug' => $slug,
                    'content_type' => $contentType,
                    'matched_content' => $result['content'],
                ];
            }
        }

        $parentStmt = $this->db->prepare('SELECT * FROM embeddings WHERE content_type = ? AND slug = ? AND type = content_type LIMIT 1');
        $parentResults = [];

        foreach ($matches as $match) {
            $parentStmt->execute([$match['content_type'], $match['slug']]);
            $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);

            if ($parent) {
                $parentResults[] = [
                    'score' => $match['score'],
                    'id' => $parent['id'],
                    'type' => $parent['type'],
                    'content_type' => $parent['content_type'],
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
        $sql = 'SELECT * FROM embeddings WHERE type = content_type';
        $params = [];

        if ($type !== null) {
            $sql .= ' AND content_type = ?';
            $params[] = $type;
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $metadata = json_decode($row['metadata'], true);
            $uuid = $row['uuid'] ?? $metadata['metadata']['uuid'] ?? null;

            $results[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'slug' => $row['slug'],
                'uuid' => $uuid,
                'name' => $row['name'],
                'title' => $row['title'],
                'tags' => json_decode($row['tags'], true),
                'content' => $row['content'],
                'metadata' => $metadata,
            ];
        }

        return $results;
    }

    public function getByUuid(string $uuid): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM embeddings 
            WHERE uuid = ? AND type = content_type
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

    public function delete(string $slug, ?string $contentType = null): void
    {
        if (!$this->db->beginTransaction()) {
            throw new RuntimeException('Failed to start index cleanup transaction');
        }

        try {
            if ($contentType === null) {
                $stmt = $this->db->prepare('DELETE FROM embeddings WHERE slug = ?');
                $stmt->execute([$slug]);
            } else {
                $stmt = $this->db->prepare('DELETE FROM embeddings WHERE content_type = ? AND slug = ?');
                $stmt->execute([$contentType, $slug]);
            }

            if (!$this->db->commit()) {
                throw new RuntimeException('Failed to commit index cleanup transaction');
            }
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $error;
        }
    }

    public function exists(string $slug, ?string $contentType = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM embeddings WHERE slug = ? AND type = content_type';
        $params = [$slug];

        if ($contentType !== null) {
            $sql .= ' AND content_type = ?';
            $params[] = $contentType;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
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
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(
                sprintf('Failed to contact Ollama at %s. Curl error: %s', $this->ollamaUrl, $curlError)
            );
        }

        if ($httpCode !== 200) {
            $errorMessage = $this->extractOllamaError($response);
            $suffix = $errorMessage !== '' ? " Error: {$errorMessage}" : '';
            throw new RuntimeException(
                sprintf("Failed to get embeddings from Ollama. Make sure Ollama is running and model '%s' is installed.%s", $this->embeddingModel, $suffix)
            );
        }

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException("Invalid JSON response from Ollama: {$response}", 0, $error);
        }

        if (!isset($data['embedding']) || empty($data['embedding'])) {
            throw new RuntimeException("Invalid response from Ollama: " . $response);
        }

        return $data['embedding'];
    }

    private function extractOllamaError(string $response): string
    {
        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return trim($response);
        }

        if (!is_array($data)) {
            return trim($response);
        }

        $error = $data['error'] ?? null;
        if ($error === null) {
            return trim($response);
        }

        if (is_string($error)) {
            return $error;
        }

        try {
            return json_encode($error, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return trim($response);
        }
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
