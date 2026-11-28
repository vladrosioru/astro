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
    public function test_every_workflow_triggers_deploys_through_ftps_and_cpanel_cron(string $file): void
    {
        $yaml = $this->workflow($file);

        // HTTP deploy hooks are intercepted by the host WAF on GitHub Actions IPs,
        // so automated pipelines upload app.zip + cron-deploy.php + .deploy-pending
        // over FTPS and poll deploy-result.txt via wait-cron-deploy.sh.
        $this->assertDoesNotMatchRegularExpression('/curl[^\n]*\$\{?APP_URL\}?\/extract\.php/', $yaml);
        $this->assertDoesNotMatchRegularExpression('/curl[^\n]*\$\{?APP_URL\}?\/deploy\.php/', $yaml);

        $this->assertStringContainsString('up "cron-deploy.php"', $yaml);
        $this->assertStringContainsString('.deploy-pending', $yaml);
        $this->assertStringContainsString('wait-cron-deploy.sh', $yaml);
    }

    public function test_cron_deploy_script_and_wait_helper_exist_and_archive_excludes_cron_script(): void
    {
        $this->assertFileExists(base_path('cron-deploy.php'));
        $this->assertFileExists(base_path('.github/scripts/wait-cron-deploy.sh'));

        $archiveScript = (string) file_get_contents(base_path('.github/scripts/make-archive.php'));
        $this->assertStringContainsString("'cron-deploy.php'", $archiveScript);
    }

    public function test_the_asserted_markers_are_the_strings_the_hooks_actually_print(): void
    {
        $extract = (string) file_get_contents(base_path('public/extract.php'));
        $deploy = (string) file_get_contents(base_path('public/deploy.php'));

        $this->assertStringContainsString('Archive removed.', $extract);
        $this->assertStringContainsString('Deploy hook finished.', $deploy);
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

    public function test_deploy_hooks_reject_query_string_token_and_storage_blocks_php(): void
    {
        $extract = (string) file_get_contents(base_path('public/extract.php'));
        $deploy = (string) file_get_contents(base_path('public/deploy.php'));

        $this->assertStringNotContainsString("\$_GET['token']", $extract);
        $this->assertStringNotContainsString("\$_GET['token']", $deploy);

        $storageHtaccess = public_path('storage/.htaccess');
        $this->assertFileExists($storageHtaccess);
        $this->assertStringContainsString('php_flag engine off', (string) file_get_contents($storageHtaccess));
    }
}
