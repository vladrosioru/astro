<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The WAF challenge is not a permanent verdict: the challenge page sets a
 * cookie and its own JS reloads after 5 s, at which point the reload carries
 * the cookie and is proxied to PHP. A one-shot curl can never get past it, so
 * `run-deploy-hook.sh` has to do what the browser does — keep the cookie and
 * ask again — before it declares the deploy failed.
 *
 * These tests drive the real script against a local stub that challenges first
 * and answers properly afterwards.
 */
class DeployHookRetryTest extends TestCase
{
    private const PORT = 8731;

    private string $dir;

    private string $counter;

    private ?Process $server = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'hook-stub-'.getmypid();
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
        $this->counter = $this->dir.DIRECTORY_SEPARATOR.'hits';
        @unlink($this->counter);
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->server = null;

        parent::tearDown();
    }

    /** Serves the WAF challenge page for the first $challenges hits, the hook's real output after. */
    private function startStub(int $challenges): void
    {
        $counter = str_replace('\\', '/', $this->counter);
        file_put_contents($this->dir.'/router.php', <<<PHP
        <?php
        \$n = (int) @file_get_contents('{$counter}');
        file_put_contents('{$counter}', \$n + 1);
        if (\$n < {$challenges}) {
            header('Set-Cookie: imunify360_test=1');
            echo "<!DOCTYPE html><html><head><title>One moment, please...</title></head><body></body></html>";
        } else {
            echo "Extracted 42 files.\\nArchive removed.\\n";
        }
        PHP);

        $this->server = Process::fromShellCommandline(
            sprintf('php -S 127.0.0.1:%d -t "%s" "%s"', self::PORT, $this->dir, $this->dir.'/router.php')
        );
        $this->server->start();

        for ($i = 0; $i < 100; $i++) {
            $probe = @fsockopen('127.0.0.1', self::PORT, $errno, $errstr, 0.2);
            if ($probe) {
                fclose($probe);

                return;
            }
            usleep(100_000);
        }

        $this->markTestSkipped('could not start the stub server on port '.self::PORT);
    }

    /** On Windows `bash` resolves to WSL, which cannot see the repo's paths — use Git's bash. */
    private function bash(): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return 'bash';
        }

        foreach (['C:\Program Files\Git\bin\bash.exe', 'C:\Program Files (x86)\Git\bin\bash.exe'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $this->markTestSkipped('no Git Bash found to run the hook script with');
    }

    private function runHook(): Process
    {
        $hook = Process::fromShellCommandline(
            sprintf(
                '"%s" .github/scripts/run-deploy-hook.sh "http://127.0.0.1:%d/extract.php" "Archive removed." "test-token" 20',
                $this->bash(),
                self::PORT
            ),
            base_path(),
            ['HOOK_RETRY_DELAY' => '1']
        );
        $hook->run();

        return $hook;
    }

    public function test_it_retries_past_a_challenge_page_and_succeeds(): void
    {
        $this->startStub(challenges: 1);

        $hook = $this->runHook();

        $this->assertSame(
            0,
            $hook->getExitCode(),
            "hook gave up on the first challenge:\n".$hook->getOutput().$hook->getErrorOutput()
        );
        $this->assertSame(2, (int) file_get_contents($this->counter), 'expected exactly one retry');
        $this->assertStringContainsString('Verified', $hook->getOutput());
    }

    public function test_it_still_fails_when_every_attempt_is_challenged(): void
    {
        $this->startStub(challenges: 99);

        $hook = $this->runHook();

        // Retrying must not turn the silent-no-op back into a green deploy.
        $this->assertSame(1, $hook->getExitCode());
        $this->assertGreaterThan(1, (int) file_get_contents($this->counter));
        $this->assertStringContainsString('::error::', $hook->getOutput().$hook->getErrorOutput());
    }
}
