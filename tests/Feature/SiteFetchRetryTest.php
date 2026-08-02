<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\StubsTheWafSite;
use Tests\TestCase;

/**
 * The smoke steps have the same problem the deploy hooks had, one level up:
 * the WAF challenge is cleared by a *cookie*, so a fresh curl per request is
 * challenged from scratch every time. Observed 2026-08-02: `test_dev`'s "Wait
 * for dev to come up" burned all 12 attempts on 12 challenge pages and failed a
 * deploy that had actually landed, while the deploy hook — same runner, same
 * minute, but carrying a cookie jar — went through.
 *
 * So every site-facing request in a job shares one jar, via fetch-site.sh.
 */
class SiteFetchRetryTest extends TestCase
{
    use StubsTheWafSite;

    private const PORT = 8732;

    private string $jar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootStub();
        $this->jar = $this->stubDir.DIRECTORY_SEPARATOR.'cookies.txt';
        @unlink($this->jar);
    }

    protected function tearDown(): void
    {
        $this->shutdownStub();

        parent::tearDown();
    }

    private function fetch(string $path = '/up'): \Symfony\Component\Process\Process
    {
        return $this->runScript(
            sprintf(
                '"%s" .github/scripts/fetch-site.sh "http://127.0.0.1:%d%s" "%s" 10',
                $this->bash(),
                self::PORT,
                $path,
                str_replace('\\', '/', $this->stubDir.'/resp.html')
            ),
            [
                'WAF_COOKIE_JAR' => str_replace('\\', '/', $this->jar),
                'SITE_RETRY_DELAY' => '1',
                'SITE_ATTEMPTS' => '3',
            ]
        );
    }

    public function test_it_clears_the_challenge_and_reports_the_status_code(): void
    {
        $this->startStub(self::PORT, 'Laravel is up.');

        $run = $this->fetch();

        $this->assertSame(0, $run->getExitCode(), $run->getOutput().$run->getErrorOutput());
        $this->assertSame('200', trim($run->getOutput()), 'stdout must be the status code and nothing else');
        $this->assertSame(2, $this->stubHits(), 'expected one challenge then one cleared request');
    }

    public function test_a_later_request_reuses_the_cookie_instead_of_being_challenged_again(): void
    {
        $this->startStub(self::PORT, 'Laravel is up.');

        $this->fetch('/up');
        $second = $this->fetch('/en/about');

        // This is the whole point: without a shared jar every step starts the
        // challenge over, which is how 12 retries produced 12 challenges.
        $this->assertSame(0, $second->getExitCode(), $second->getOutput().$second->getErrorOutput());
        $this->assertSame(3, $this->stubHits(), 'the second request must not be challenged again');
    }

    public function test_it_fails_when_the_challenge_never_clears(): void
    {
        $this->startStub(self::PORT, 'Laravel is up.', challengeClears: false);

        $run = $this->fetch();

        $this->assertSame(1, $run->getExitCode());
        $this->assertSame(3, $this->stubHits());
        $this->assertStringContainsString('::error::', $run->getOutput().$run->getErrorOutput());
    }

    /** @return array<string, array{0: string}> */
    public static function workflowsHittingTheSite(): array
    {
        return [
            'cicd' => ['cicd.yml'],
            'rollback-prod' => ['rollback-prod.yml'],
        ];
    }

    #[DataProvider('workflowsHittingTheSite')]
    public function test_no_workflow_aims_a_raw_curl_at_the_site(string $file): void
    {
        $yaml = (string) file_get_contents(base_path(".github/workflows/{$file}"));

        // FTPS uploads are the one allowed direct curl (different protocol, no
        // WAF); everything speaking HTTP to the site goes through a script that
        // carries the jar.
        foreach (preg_split('/\R/', $yaml) as $line) {
            // An actual invocation, not the setup-php extension list and not
            // prose about the WAF.
            if (! preg_match('/(^|[\s(=|&;])curl\s/', $line) || str_contains($line, 'ftp://')) {
                continue;
            }
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            $this->fail("raw curl aimed at the site in {$file}: ".trim($line));
        }

        $this->assertStringContainsString('fetch-site.sh', $yaml);
        $this->assertStringContainsString('WAF_COOKIE_JAR', $yaml);
    }
}
