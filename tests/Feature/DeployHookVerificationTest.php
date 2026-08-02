<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The host's WAF answers clients it doesn't recognise with an HTTP 200
 * "One moment, please…" JS challenge page instead of proxying to PHP. Because
 * the status is 200, `curl -fsS` treats it as success — so a challenged deploy
 * reported green in CI while the archive was never extracted and the site kept
 * serving the previous build (observed 2026-08-01: deploy_dev succeeded in 19s,
 * body was the challenge page, dev stayed on a build 3.5 h older than the
 * commit).
 *
 * Two defences, both pinned here:
 *   1. the hook call sends browser-shaped headers, which the WAF passes through;
 *   2. the response must contain the hook's own success marker, or the step fails.
 */
class DeployHookVerificationTest extends TestCase
{
    private function hookScript(): string
    {
        return (string) file_get_contents(base_path('.github/scripts/run-deploy-hook.sh'));
    }

    private function workflow(string $name): string
    {
        return (string) file_get_contents(base_path(".github/workflows/{$name}"));
    }

    public function test_the_hook_runner_fails_when_the_success_marker_is_absent(): void
    {
        $script = $this->hookScript();

        // A 200 that isn't the hook's own output means the hook never ran.
        $this->assertStringContainsString('grep -qF', $script);
        $this->assertStringContainsString('exit 1', $script);
    }

    public function test_the_hook_runner_sends_browser_shaped_headers(): void
    {
        $script = $this->hookScript();

        $this->assertStringContainsString('Mozilla/5.0', $script);
        $this->assertStringNotContainsString('astro-deploy-bot', $script);
        $this->assertMatchesRegularExpression('/Accept: text\/html/', $script);
    }

    public function test_the_hook_runner_forwards_the_deploy_token(): void
    {
        $this->assertStringContainsString('X-Deploy-Token', $this->hookScript());
    }

    /** @return array<string, array{0: string}> */
    public static function workflowsCallingTheHooks(): array
    {
        return [
            'cicd' => ['cicd.yml'],
            'rollback-prod' => ['rollback-prod.yml'],
        ];
    }

    #[DataProvider('workflowsCallingTheHooks')]
    public function test_every_workflow_calls_the_hooks_through_the_verifying_runner(string $file): void
    {
        $yaml = $this->workflow($file);

        // No bare curl straight at a hook: that is the shape that went green on
        // a challenge page.
        $this->assertDoesNotMatchRegularExpression('/curl[^\n]*\$\{?APP_URL\}?\/extract\.php/', $yaml);
        $this->assertDoesNotMatchRegularExpression('/curl[^\n]*\$\{?APP_URL\}?\/deploy\.php/', $yaml);

        $this->assertStringContainsString('run-deploy-hook.sh "${APP_URL}/extract.php"', $yaml);
        $this->assertStringContainsString('run-deploy-hook.sh "${APP_URL}/deploy.php"', $yaml);
    }

    public function test_the_asserted_markers_are_the_strings_the_hooks_actually_print(): void
    {
        $extract = (string) file_get_contents(base_path('public/extract.php'));
        $deploy = (string) file_get_contents(base_path('public/deploy.php'));

        // Guards against the marker drifting out of sync with the hook output,
        // which would turn every deploy red for no reason.
        $this->assertStringContainsString('Archive removed.', $extract);
        $this->assertStringContainsString('Deploy hook finished.', $deploy);

        foreach (['cicd.yml', 'rollback-prod.yml'] as $file) {
            $yaml = $this->workflow($file);
            $this->assertStringContainsString('"Archive removed."', $yaml);
            $this->assertStringContainsString('"Deploy hook finished."', $yaml);
        }
    }

    #[DataProvider('workflowsCallingTheHooks')]
    public function test_the_public_smoke_tests_detect_a_challenge_page(string $file): void
    {
        $yaml = $this->workflow($file);

        // Same failure mode as the deploy hooks: a challenge page is a 200, so
        // a smoke test that only reads the status code passes against a site
        // the deploy never reached.
        $this->assertStringNotContainsString('astro-deploy-bot', $yaml);
        $this->assertStringContainsString('WAF_CHALLENGE_MARKER', $yaml);

        // The headers and the body check now live in fetch-site.sh, which the
        // smoke steps call — see SiteFetchRetryTest for the rest of that
        // contract. What the workflow still owns is the UA it hands over.
        $this->assertStringContainsString('fetch-site.sh', $yaml);
        $this->assertStringContainsString('UA:', $yaml);

        $script = (string) file_get_contents(base_path('.github/scripts/fetch-site.sh'));
        $this->assertStringContainsString('-A "$ua"', $script);
        $this->assertStringContainsString('grep -qF "$marker"', $script);

        // Every site-facing status check must write a body somewhere greppable
        // rather than throwing it away.
        $this->assertStringNotContainsString('-o /dev/null -w "%{http_code}" --max-time', $yaml);
    }
}
