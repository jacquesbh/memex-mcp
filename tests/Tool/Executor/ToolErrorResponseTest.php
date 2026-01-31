<?php

declare(strict_types=1);

namespace Memex\Tests\Tool\Executor;

use InvalidArgumentException;
use Memex\Tool\Executor\ToolErrorResponse;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ToolErrorResponseTest extends TestCase
{
    public function testBuildsValidationErrorWithoutDebug(): void
    {
        $previousEnv = $_ENV['MEMEX_ERROR_DETAIL'] ?? null;
        $previousServer = $_SERVER['MEMEX_ERROR_DETAIL'] ?? null;
        $previousGetenv = getenv('MEMEX_ERROR_DETAIL');

        unset($_ENV['MEMEX_ERROR_DETAIL'], $_SERVER['MEMEX_ERROR_DETAIL']);
        putenv('MEMEX_ERROR_DETAIL');

        try {
            $result = ToolErrorResponse::fromThrowable(new InvalidArgumentException('Invalid input'), ['tool' => 'test']);

            $this->assertFalse($result['success']);
            $this->assertSame(InvalidArgumentException::class, $result['error']['type']);
            $this->assertSame('Invalid input', $result['error']['message']);
            $this->assertSame('validation', $result['error']['details']['category']);
            $this->assertArrayNotHasKey('debug', $result['error']['details']);
        } finally {
            if ($previousEnv !== null) {
                $_ENV['MEMEX_ERROR_DETAIL'] = $previousEnv;
            } else {
                unset($_ENV['MEMEX_ERROR_DETAIL']);
            }
            if ($previousServer !== null) {
                $_SERVER['MEMEX_ERROR_DETAIL'] = $previousServer;
            } else {
                unset($_SERVER['MEMEX_ERROR_DETAIL']);
            }
            if ($previousGetenv !== false) {
                putenv('MEMEX_ERROR_DETAIL=' . $previousGetenv);
            } else {
                putenv('MEMEX_ERROR_DETAIL');
            }
        }
    }

    public function testBuildsRuntimeErrorWithDebug(): void
    {
        $previousEnv = $_ENV['MEMEX_ERROR_DETAIL'] ?? null;
        $previousServer = $_SERVER['MEMEX_ERROR_DETAIL'] ?? null;
        $previousGetenv = getenv('MEMEX_ERROR_DETAIL');

        $_ENV['MEMEX_ERROR_DETAIL'] = '1';
        $_SERVER['MEMEX_ERROR_DETAIL'] = '1';
        putenv('MEMEX_ERROR_DETAIL=1');

        try {
            $result = ToolErrorResponse::fromThrowable(new RuntimeException('Failure'), ['tool' => 'test']);

            $this->assertFalse($result['success']);
            $this->assertSame(RuntimeException::class, $result['error']['type']);
            $this->assertSame('Failure', $result['error']['message']);
            $this->assertSame('runtime', $result['error']['details']['category']);
            $this->assertArrayHasKey('debug', $result['error']['details']);
            $this->assertArrayHasKey('file', $result['error']['details']['debug']);
            $this->assertArrayHasKey('line', $result['error']['details']['debug']);
            $this->assertArrayHasKey('trace', $result['error']['details']['debug']);
        } finally {
            if ($previousEnv !== null) {
                $_ENV['MEMEX_ERROR_DETAIL'] = $previousEnv;
            } else {
                unset($_ENV['MEMEX_ERROR_DETAIL']);
            }
            if ($previousServer !== null) {
                $_SERVER['MEMEX_ERROR_DETAIL'] = $previousServer;
            } else {
                unset($_SERVER['MEMEX_ERROR_DETAIL']);
            }
            if ($previousGetenv !== false) {
                putenv('MEMEX_ERROR_DETAIL=' . $previousGetenv);
            } else {
                putenv('MEMEX_ERROR_DETAIL');
            }
        }
    }
}
