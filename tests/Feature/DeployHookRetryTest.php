<?php

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\Support\StubsTheWafSite;
use Tests\TestCase;

/**
 * The WAF challenge is not a permanent verdict: the challenge page sets a
 * cookie and its own JS reloads after 5 s, at which point the reload carries
 * the cookie and is proxied to PHP. A one-shot curl can never get past it, so
 * `run-deploy-hook.sh` has to do what the browser does — keep the cookie and
 * ask again — before it declares the deploy failed.
 *
 * These tests drive the real script against a local stub that challenges any
 * request arriving without the cookie it sets.
 */
class DeployHookRetryTest extends TestCase
{
    use StubsTheWafSite;

    private const PORT = 8731;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootStub();
    }

    protected function tearDown(): void
    {
        $this->shutdownStub();

        parent::tearDown();
    }

    private function runHook(): Process
    {
        return $this->runScript(
            sprintf(
                '"%s" .github/scripts/run-deploy-hook.sh "http://127.0.0.1:%d/extract.php" "Archive removed." "test-token" 20',
                $this->bash(),
                self::PORT
            ),
            ['HOOK_RETRY_DELAY' => '1', 'HOOK_ATTEMPTS' => '3']
        );
    }

    public function test_it_retries_past_a_challenge_page_and_succeeds(): void
    {
        $this->startStub(self::PORT, 'Extracted 42 files.\nArchive removed.');

        $hook = $this->runHook();

        $this->assertSame(
            0,
            $hook->getExitCode(),
            "hook gave up on the first challenge:\n".$hook->getOutput().$hook->getErrorOutput()
        );
        $this->assertSame(2, $this->stubHits(), 'expected exactly one retry');
        $this->assertStringContainsString('Verified', $hook->getOutput());
    }

    public function test_it_still_fails_when_every_attempt_is_challenged(): void
    {
        $this->startStub(self::PORT, 'Archive removed.', challengeClears: false);

        $hook = $this->runHook();

        // Retrying must not turn the silent-no-op back into a green deploy.
        $this->assertSame(1, $hook->getExitCode());
        $this->assertSame(3, $this->stubHits());
        $this->assertStringContainsString('::error::', $hook->getOutput().$hook->getErrorOutput());
    }
}
