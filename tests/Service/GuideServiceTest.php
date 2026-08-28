<?php

declare(strict_types=1);

namespace Memex\Tests\Service;

use Memex\Service\GuideService;
use Memex\Service\PatternCompilerService;
use Memex\Service\VectorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use RuntimeException;

final class GuideServiceTest extends TestCase
{
    private string $testKbPath;
    private GuideService $service;

    protected function setUp(): void
    {
        $this->testKbPath = sys_get_temp_dir() . '/memex-test-' . uniqid();
        mkdir($this->testKbPath, 0755, true);
        mkdir($this->testKbPath . '/guides', 0755, true);
        
        $compiler = new PatternCompilerService();
        $vectorService = $this->createMock(VectorService::class);
        
        $this->service = new GuideService($this->testKbPath, $compiler, $vectorService);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->testKbPath);
    }

    public function testWriteCreatesGuideFile(): void
    {
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $result = $this->service->write($uuid, 'Test Guide', 'Content here', ['tag1', 'tag2']);
        
        $this->assertSame($uuid, $result['uuid']);
        $this->assertSame('test-guide', $result['slug']);
        $this->assertFileExists($this->testKbPath . '/guides/test-guide.md');
    }

    public function testWriteThrowsOnEmptyTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title contains invalid characters');
        
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $this->service->write($uuid, '', 'Content');
    }

    public function testWriteThrowsOnInvalidTitleCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title contains invalid characters');
        
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $this->service->write($uuid, 'Test@Guide#Invalid', 'Content');
    }

    public function testWriteThrowsOnTooLongTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title too long');
        
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $this->service->write($uuid, str_repeat('a', 201), 'Content');
    }

    public function testWriteThrowsOnEmptyContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content cannot be empty');
        
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $this->service->write($uuid, 'Title', '');
    }

    public function testWriteThrowsOnTooLargeContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Content too large');
        
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $this->service->write($uuid, 'Title', str_repeat('a', 1048577));
    }

    public function testWriteThrowsOnExistingFileWithoutOverwrite(): void
    {
        $vectorService = $this->createMock(VectorService::class);
        $vectorService->expects($this->exactly(2))
            ->method('getByUuid')
            ->willReturnOnConsecutiveCalls(
                null,
                ['uuid' => 'test-uuid', 'slug' => 'test-guide', 'content' => 'Content']
            );
        
        $compiler = new PatternCompilerService();
        $service = new GuideService($this->testKbPath, $compiler, $vectorService);
        
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $service->write($uuid, 'Test Guide', 'Content');
        
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');
        
        $service->write($uuid, 'Test Guide', 'New Content');
    }

    public function testWriteOverwritesExistingFileWithFlag(): void
    {
        $vectorService = $this->createMock(VectorService::class);
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $vectorService->expects($this->exactly(2))
            ->method('getByUuid')
            ->willReturnOnConsecutiveCalls(
                null,
                ['uuid' => $uuid, 'slug' => 'test-guide', 'content' => 'Content']
            );
        
        $compiler = new PatternCompilerService();
        $service = new GuideService($this->testKbPath, $compiler, $vectorService);
        
        $service->write($uuid, 'Test Guide', 'Content');
        $result = $service->write($uuid, 'Test Guide', 'New Content', [], true);
        
        $this->assertSame('test-guide', $result['slug']);
        $content = file_get_contents($this->testKbPath . '/guides/test-guide.md');
        $this->assertStringContainsString('New Content', $content);
        $this->assertStringContainsString('updated:', $content);
    }

    public function testDeleteRemovesIndexedGuideByUuidAfterStagingFileAndCleaningTypedIndex(): void
    {
        $uuid = Uuid::v4()->toString();
        $filePath = $this->createGuideFile('indexed-guide', $uuid, 'Indexed Guide');
        $originalContent = file_get_contents($filePath);
        $stagedDuringCleanup = false;
        $vectorService = $this->createMock(VectorService::class);
        $vectorService->expects($this->once())
            ->method('delete')
            ->with('indexed-guide', 'guide')
            ->willReturnCallback(function () use ($filePath, $originalContent, &$stagedDuringCleanup): void {
                $entries = array_values(array_diff(scandir(dirname($filePath)), ['.', '..']));
                $stagedDuringCleanup = !file_exists($filePath)
                    && count($entries) === 1
                    && file_get_contents(dirname($filePath) . '/' . $entries[0]) === $originalContent;
            });
        $service = new GuideService($this->testKbPath, new PatternCompilerService(), $vectorService);

        $result = $service->deleteByUuid($uuid);

        $this->assertTrue($result['success']);
        $this->assertSame($uuid, $result['uuid']);
        $this->assertSame('indexed-guide', $result['slug']);
        $this->assertSame('guide', $result['type']);
        $this->assertTrue($stagedDuringCleanup, 'The guide must be renamed out of its live path before SQLite cleanup.');
        $this->assertFileDoesNotExist($filePath);
    }

    public function testDeleteByUuidRestoresStagedGuideWhenIndexCleanupFails(): void
    {
        $uuid = Uuid::v4()->toString();
        $filePath = $this->createGuideFile('rollback-guide', $uuid, 'Rollback Guide');
        $originalContent = file_get_contents($filePath);
        $stagedDuringCleanup = false;
        $vectorService = $this->createMock(VectorService::class);
        $vectorService->expects($this->once())
            ->method('delete')
            ->with('rollback-guide', 'guide')
            ->willReturnCallback(function () use ($filePath, $originalContent, &$stagedDuringCleanup): void {
                $entries = array_values(array_diff(scandir(dirname($filePath)), ['.', '..']));
                $stagedDuringCleanup = !file_exists($filePath)
                    && count($entries) === 1
                    && file_get_contents(dirname($filePath) . '/' . $entries[0]) === $originalContent;

                throw new RuntimeException('forced index cleanup failure');
            });
        $service = new GuideService($this->testKbPath, new PatternCompilerService(), $vectorService);

        try {
            $service->deleteByUuid($uuid);
            $this->fail('Index cleanup failure must abort UUID deletion.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('forced index cleanup failure', $error->getMessage());
        }

        $this->assertTrue($stagedDuringCleanup, 'The guide must be staged before SQLite cleanup.');
        $this->assertFileExists($filePath);
        $this->assertSame($originalContent, file_get_contents($filePath));
        $this->assertSame(['rollback-guide.md'], array_values(array_diff(scandir(dirname($filePath)), ['.', '..'])));
    }

    public function testDeleteByUuidDoesNotDeleteConcurrentReplacementAtOriginalPath(): void
    {
        $uuid = Uuid::v4()->toString();
        $replacementUuid = Uuid::v4()->toString();
        $filePath = $this->createGuideFile('replaced-guide', $uuid, 'Original Guide');
        $originalContent = file_get_contents($filePath);
        $replacementContent = $this->guideMarkdown($replacementUuid, 'Concurrent Replacement');
        $stagedDuringCleanup = false;
        $vectorService = $this->createMock(VectorService::class);
        $vectorService->expects($this->once())
            ->method('delete')
            ->with('replaced-guide', 'guide')
            ->willReturnCallback(function () use ($filePath, $originalContent, $replacementContent, &$stagedDuringCleanup): void {
                $entries = array_values(array_diff(scandir(dirname($filePath)), ['.', '..']));
                $stagedDuringCleanup = !file_exists($filePath)
                    && count($entries) === 1
                    && file_get_contents(dirname($filePath) . '/' . $entries[0]) === $originalContent;
                file_put_contents($filePath, $replacementContent);
            });
        $service = new GuideService($this->testKbPath, new PatternCompilerService(), $vectorService);

        $result = $service->deleteByUuid($uuid);

        $this->assertTrue($result['success']);
        $this->assertTrue($stagedDuringCleanup, 'The matched guide must be staged before concurrent replacement is possible.');
        $this->assertFileExists($filePath);
        $this->assertSame($replacementContent, file_get_contents($filePath));
        $this->assertSame(['replaced-guide.md'], array_values(array_diff(scandir(dirname($filePath)), ['.', '..'])));
    }

    public function testDeleteRemovesUnindexedGuideByScanningFiles(): void
    {
        $uuid = Uuid::v4()->toString();
        $filePath = $this->createGuideFile('unindexed-guide', $uuid, 'Unindexed Guide');
        $vectorService = $this->createMock(VectorService::class);
        $vectorService->expects($this->once())
            ->method('delete')
            ->with('unindexed-guide', 'guide');
        $service = new GuideService($this->testKbPath, new PatternCompilerService(), $vectorService);

        $result = $service->deleteByUuid($uuid);

        $this->assertSame($uuid, $result['uuid']);
        $this->assertSame('unindexed-guide', $result['slug']);
        $this->assertFileDoesNotExist($filePath);
    }

    public function testDeleteRejectsInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID v4 format');

        $this->service->deleteByUuid('not-a-uuid');
    }

    public function testDeleteThrowsWhenUuidIsAbsent(): void
    {
        $uuid = Uuid::v4()->toString();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("guide not found with UUID: {$uuid}");

        $this->service->deleteByUuid($uuid);
    }

    public function testDeleteRejectsDuplicateGuideUuidWithoutDeletingEitherFile(): void
    {
        $uuid = Uuid::v4()->toString();
        $firstPath = $this->createGuideFile('first-guide', $uuid, 'First Guide');
        $secondPath = $this->createGuideFile('second-guide', $uuid, 'Second Guide');

        try {
            $this->service->deleteByUuid($uuid);
            $this->fail('A duplicated guide UUID must not select an arbitrary file.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Duplicate guide UUID', $error->getMessage());
            $this->assertStringContainsString($uuid, $error->getMessage());
        }

        $this->assertFileExists($firstPath);
        $this->assertFileExists($secondPath);
    }

    public function testContextUuidDoesNotDeleteAnyGuide(): void
    {
        $contextUuid = Uuid::v4()->toString();
        $guideUuid = Uuid::v4()->toString();
        $guidePath = $this->createGuideFile('unrelated-guide', $guideUuid, 'Unrelated Guide');
        $vectorService = $this->createMock(VectorService::class);
        $vectorService->expects($this->never())->method('delete');
        $service = new GuideService($this->testKbPath, new PatternCompilerService(), $vectorService);

        try {
            $service->deleteByUuid($contextUuid);
            $this->fail('A context UUID must not resolve as a guide.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString("guide not found with UUID: {$contextUuid}", $error->getMessage());
        }

        $this->assertFileExists($guidePath);
    }

    public function testDeleteRejectsMatchingFileWithInconsistentType(): void
    {
        $uuid = Uuid::v4()->toString();
        $filePath = $this->createGuideFile('wrong-type', $uuid, 'Wrong Type', 'context');

        try {
            $this->service->deleteByUuid($uuid);
            $this->fail('A context frontmatter in guides/ must not be deleted as a guide.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('wrong-type.md', $error->getMessage());
            $this->assertStringContainsString('type', $error->getMessage());
            $this->assertStringContainsString('guide', $error->getMessage());
        }

        $this->assertFileExists($filePath);
    }

    public function testDeleteReportsGuideWithMissingUuidFrontmatter(): void
    {
        $requestedUuid = Uuid::v4()->toString();
        $filePath = $this->testKbPath . '/guides/missing-uuid.md';
        file_put_contents($filePath, "---\ntitle: Missing UUID\ntype: guide\n---\nContent");

        try {
            $this->service->deleteByUuid($requestedUuid);
            $this->fail('An invalid guide frontmatter must be reported during UUID resolution.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('missing-uuid.md', $error->getMessage());
            $this->assertStringContainsString('uuid', strtolower($error->getMessage()));
        }

        $this->assertFileExists($filePath);
    }

    public function testDeleteRejectsSymlinkedGuide(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Symlink test skipped on Windows');
        }

        $uuid = Uuid::v4()->toString();
        $outsidePath = $this->testKbPath . '/outside.md';
        file_put_contents($outsidePath, $this->guideMarkdown($uuid, 'Outside Guide'));
        $symlinkPath = $this->testKbPath . '/guides/symlinked-guide.md';
        if (!symlink($outsidePath, $symlinkPath)) {
            $this->markTestSkipped('Unable to create symlink');
        }

        try {
            $this->service->deleteByUuid($uuid);
            $this->fail('A symlinked guide must not be followed or deleted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('symlink', strtolower($error->getMessage()));
        }

        $this->assertFileExists($outsidePath);
        $this->assertFileExists($symlinkPath);
    }

    public function testSlugifyConvertsToLowerCase(): void
    {
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $result = $this->service->write($uuid, 'TEST GUIDE', 'Content');
        $this->assertSame('test-guide', $result['slug']);
    }

    public function testSlugifyRemovesSpecialCharacters(): void
    {
        $uuid = \Symfony\Component\Uid\Uuid::v4()->toString();
        $result = $this->service->write($uuid, 'Test Guide 123', 'Content');
        $this->assertSame('test-guide-123', $result['slug']);
    }

    private function createGuideFile(string $slug, string $uuid, string $title, string $type = 'guide'): string
    {
        $filePath = $this->testKbPath . '/guides/' . $slug . '.md';
        file_put_contents($filePath, $this->guideMarkdown($uuid, $title, $type));

        return $filePath;
    }

    private function guideMarkdown(string $uuid, string $title, string $type = 'guide'): string
    {
        return "---\nuuid: {$uuid}\ntitle: {$title}\ntype: {$type}\n---\nContent";
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
