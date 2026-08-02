<?php

namespace Tests\Support;

use Symfony\Component\Process\Process;

/**
 * A local stand-in for the host behind its WAF: it answers the Imunify360-style
 * "One moment, please…" challenge page (HTTP 200, `Set-Cookie`) to any request
 * that arrives without the pass cookie, and the real response to any request
 * that carries it — which is what makes a shared cookie jar, not header shape
 * or patience, the thing that gets a client through.
 *
 * Every stub is per-test-method and identifies itself before the test runs.
 * That is not ceremony: `Process::fromShellCommandline` wraps the server in
 * `sh -c`, so on Linux `stop()` kills the shell and orphans `php -S`. The next
 * test then found the port already open, assumed it was its own server, and was
 * quietly answered by the *previous* test's configuration — green on Windows,
 * red on CI, for reasons nowhere near the code under test.
 */
trait StubsTheWafSite
{
    private string $stubDir;

    private string $stubHits;

    private string $stubToken;

    private ?Process $stubServer = null;

    protected function bootStub(): void
    {
        $suite = str_replace('\\', '-', static::class);
        $this->stubDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'waf-stub-'.getmypid().'-'.$suite;
        if (! is_dir($this->stubDir)) {
            mkdir($this->stubDir, 0777, true);
        }

        // Per-method identity: nothing this test writes or reads can be confused
        // with another test's stub, however the server processes behave.
        $this->stubToken = substr(preg_replace('/[^a-z0-9]+/i', '', $this->name()), -40).'-'.getmypid();
        $this->stubHits = $this->stubDir.DIRECTORY_SEPARATOR.'hits-'.$this->stubToken;
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
        $router = $this->stubDir.DIRECTORY_SEPARATOR.'router-'.$this->stubToken.'.php';

        file_put_contents($router, <<<PHP
        <?php
        // Identity probe: proves to the test that *this* stub is what answers,
        // and is not counted as a hit.
        if (\$_SERVER['REQUEST_URI'] === '/__stub') {
            echo 'stub:{$this->stubToken}';

            return;
        }

        \$n = (int) @file_get_contents('{$hits}');
        file_put_contents('{$hits}', \$n + 1);

        if ({$clears} && ! empty(\$_COOKIE['waf_pass'])) {
            echo "{$body}";

            return;
        }

        header('Set-Cookie: waf_pass=1; Path=/');
        echo "<!DOCTYPE html><html><head><title>One moment, please...</title></head><body></body></html>";
        PHP);

        if ($this->portIsOpen($port)) {
            $this->fail("port {$port} was already in use before this test started its stub — an earlier stub server outlived its test.");
        }

        // Not fromShellCommandline: that runs `sh -c "php -S …"`, and stop()
        // then kills only the shell, leaving the server holding the port.
        // opcache off so a router file rewritten within the revalidation window
        // is never served stale.
        $this->stubServer = new Process([
            PHP_BINARY, '-d', 'opcache.enable_cli=0',
            '-S', '127.0.0.1:'.$port,
            '-t', $this->stubDir,
            $router,
        ]);
        $this->stubServer->start();

        for ($i = 0; $i < 100; $i++) {
            if (! $this->stubServer->isRunning()) {
                $this->fail(
                    "the stub server exited immediately on port {$port}: "
                    .$this->stubServer->getErrorOutput().$this->stubServer->getOutput()
                );
            }
            if ($this->portIsOpen($port)) {
                $this->assertStubAnswers($port);

                return;
            }
            usleep(100_000);
        }

        $this->fail('could not start the stub server on port '.$port);
    }

    private function portIsOpen(int $port): bool
    {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if ($probe === false) {
            return false;
        }
        fclose($probe);

        return true;
    }

    /** Whoever holds the port must be the stub this test just configured. */
    private function assertStubAnswers(int $port): void
    {
        $answer = @file_get_contents("http://127.0.0.1:{$port}/__stub");

        $this->assertSame(
            'stub:'.$this->stubToken,
            $answer,
            "port {$port} is held by a different stub than this test started — an earlier server survived its test and would answer with the wrong configuration."
        );
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
