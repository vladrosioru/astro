<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

/**
 * A local stand-in for the host behind its WAF: it answers the Imunify360-style
 * "One moment, please…" challenge page (HTTP 200, `Set-Cookie`) to any request
 * that arrives without the pass cookie, and the real response to any request
 * that carries it — which is what makes a shared cookie jar, not header shape
 * or patience, the thing that gets a client through.
 */
trait StubsTheWafSite
{
    private string $stubDir;

    private string $stubHits;

    private ?Process $stubServer = null;

    protected function bootStub(): void
    {
        $suite = str_replace('\\', '-', static::class);
        $this->stubDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waf-stub-'.getmypid().'-'.$suite;
        if (! is_dir($this->stubDir)) {
            mkdir($this->stubDir, 0777, true);
        }
        $this->stubHits = $this->stubDir.DIRECTORY_SEPARATOR.'hits';
        @unlink($this->stubHits);
    }

    protected function shutdownStub(): void
    {
        $this->stubServer?->stop();
        $this->stubServer = null;
    }

    protected function stubHits(): int
    {
        return (int) @file_get_contents($this->stubHits);
    }

    /**
     * @param  bool  $challengeClears  false = the challenge never lets anyone through, however many cookies they carry
     */
    protected function startStub(int $port, string $body, bool $challengeClears = true): void
    {
        $hits = str_replace('\\', '/', $this->stubHits);
        $clears = $challengeClears ? 'true' : 'false';
        $body = addcslashes($body, '"\\');

        file_put_contents($this->stubDir.'/router.php', <<<PHP
        <?php
        \$n = (int) @file_get_contents('{$hits}');
        file_put_contents('{$hits}', \$n + 1);

        if ({$clears} && ! empty(\$_COOKIE['waf_pass'])) {
            echo "{$body}";

            return;
        }

        header('Set-Cookie: waf_pass=1; Path=/');
        echo "<!DOCTYPE html><html><head><title>One moment, please...</title></head><body></body></html>";
        PHP);

        $this->stubServer = Process::fromShellCommandline(
            sprintf('php -S 127.0.0.1:%d -t "%s" "%s"', $port, $this->stubDir, $this->stubDir.'/router.php')
        );
        $this->stubServer->start();

        for ($i = 0; $i < 100; $i++) {
            $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if ($probe) {
                fclose($probe);

                return;
            }
            usleep(100_000);
        }

        $this->markTestSkipped('could not start the stub server on port '.$port);
    }

    /** On Windows `bash` resolves to WSL, which cannot see the repo's paths — use Git's bash. */
    protected function bash(): string
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return 'bash';
        }

        foreach (['C:\Program Files\Git\bin\bash.exe', 'C:\Program Files (x86)\Git\bin\bash.exe'] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        $this->markTestSkipped('no Git Bash found to run the shell scripts with');
    }

    /** @param array<string, string> $env */
    protected function runScript(string $command, array $env = []): Process
    {
        $process = Process::fromShellCommandline($command, base_path(), $env);
        $process->run();

        return $process;
    }
}
