<?php

declare(strict_types=1);

namespace Memex\Tests\Cli;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Uid\Uuid;

final class DeleteGuideCommandTest extends TestCase
{
    private string $knowledgeBasePath;
    private string $projectPath;

    protected function setUp(): void
    {
        $this->projectPath = dirname(__DIR__, 2);
        $this->knowledgeBasePath = sys_get_temp_dir() . '/memex-cli-test-' . uniqid();
        mkdir($this->knowledgeBasePath . '/guides', 0755, true);
        mkdir($this->knowledgeBasePath . '/contexts', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRemoveDirectory($this->knowledgeBasePath);
    }

    public function testForceDeletesGuideWithoutReadingInput(): void
    {
        $uuid = Uuid::v4()->toString();
        $guidePath = $this->createGuide($uuid);
        $process = $this->runCommand([$uuid, '--force'], 'input that must not be read');

        $this->assertSame(0, $process->getExitCode(), $this->processOutput($process));
        $this->assertFileDoesNotExist($guidePath);
        $this->assertStringContainsString($uuid, $this->processOutput($process));
        $this->assertStringContainsString('Process Guide', $this->processOutput($process));
        $this->assertStringContainsString('process-guide', $this->processOutput($process));
    }

    public function testDefaultNegativeConfirmationDoesNotDeleteGuide(): void
    {
        $uuid = Uuid::v4()->toString();
        $guidePath = $this->createGuide($uuid);
        $process = $this->runCommand([$uuid], "\n");
        $output = $this->processOutput($process);

        $this->assertSame(0, $process->getExitCode(), $output);
        $this->assertFileExists($guidePath);
        $this->assertStringContainsString($uuid, $output);
        $this->assertStringContainsString($this->knowledgeBasePath, $output);
        $this->assertStringContainsString('Deletion cancelled', $output);
    }

    public function testInteractiveConfirmationDeletesGuide(): void
    {
        $uuid = Uuid::v4()->toString();
        $guidePath = $this->createGuide($uuid);
        $process = $this->runCommand([$uuid], "yes\n");

        $this->assertSame(0, $process->getExitCode(), $this->processOutput($process));
        $this->assertFileDoesNotExist($guidePath);
    }

    public function testNonInteractiveModeRequiresForceWithoutMutation(): void
    {
        $uuid = Uuid::v4()->toString();
        $guidePath = $this->createGuide($uuid);
        $process = $this->runCommand([$uuid, '--no-interaction']);
        $output = $this->processOutput($process);

        $this->assertNotSame(0, $process->getExitCode(), $output);
        $this->assertFileExists($guidePath);
        $this->assertStringContainsString('non-interactive mode', $output);
        $this->assertStringContainsString('--force', $output);
    }

    public function testInvalidUuidFailsClearly(): void
    {
        $process = $this->runCommand(['not-a-uuid'], "no\n");
        $output = $this->processOutput($process);

        $this->assertNotSame(0, $process->getExitCode(), $output);
        $this->assertStringContainsString('Invalid UUID v4 format', $output);
        $this->assertStringNotContainsString('Delete guide with UUID', $output);
    }

    public function testAbsentGuideFailsClearly(): void
    {
        $uuid = Uuid::v4()->toString();
        $process = $this->runCommand([$uuid, '--force']);
        $output = $this->processOutput($process);

        $this->assertNotSame(0, $process->getExitCode(), $output);
        $this->assertStringContainsString('guide not found with UUID:', $output);
        $this->assertStringContainsString($uuid, $output);
    }

    public function testMissingUuidFailsClearly(): void
    {
        $process = $this->runCommand(['--force']);

        $this->assertNotSame(0, $process->getExitCode(), $this->processOutput($process));
        $this->assertStringContainsString('uuid', strtolower($this->processOutput($process)));
    }

    private function runCommand(array $arguments, ?string $input = null): Process
    {
        $process = new Process([
            PHP_BINARY,
            $this->projectPath . '/vendor/bin/castor',
            'delete-guide',
            ...$arguments,
            '--kb=' . $this->knowledgeBasePath,
        ], $this->projectPath);
        $process->setInput($input);
        $process->run();

        return $process;
    }

    private function createGuide(string $uuid): string
    {
        $path = $this->knowledgeBasePath . '/guides/process-guide.md';
        file_put_contents(
            $path,
            "---\nuuid: {$uuid}\ntitle: Process Guide\ntype: guide\n---\nProcess content"
        );

        return $path;
    }

    private function processOutput(Process $process): string
    {
        return $process->getOutput() . $process->getErrorOutput();
    }

    private function recursiveRemoveDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = array_diff(scandir($directory) ?: [], ['.', '..']);
        foreach ($entries as $entry) {
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($directory);
    }
}
