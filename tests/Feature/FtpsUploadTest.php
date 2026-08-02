<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The FTPS upload is the one place CI is allowed to talk to the host directly
 * (different protocol, no WAF in front of it — see the curl rule in CLAUDE.md),
 * and it is the step everything downstream depends on.
 *
 * Observed 2026-08-02: `curl: (28) Failed to connect to *** port 21 after
 * 134234 ms`. A connect *timeout*, not a refusal — the host either had FTP down
 * or was blackholing the runner's IP. A single-shot upload turns any such blip
 * into a failed deploy after more than two minutes of waiting.
 */
class FtpsUploadTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function workflowsUploading(): array
    {
        return [
            'cicd' => ['cicd.yml'],
            'rollback-prod' => ['rollback-prod.yml'],
        ];
    }

    private function uploadLines(string $file): array
    {
        $yaml = (string) file_get_contents(base_path(".github/workflows/{$file}"));

        $lines = array_filter(
            preg_split('/\R/', $yaml),
            fn (string $line) => str_contains($line, 'ftp://') && ! str_starts_with(trim($line), '#')
        );

        $this->assertNotEmpty($lines, "no FTPS upload found in {$file}");

        return $lines;
    }

    #[DataProvider('workflowsUploading')]
    public function test_the_upload_retries_instead_of_dying_on_one_bad_connect(string $file): void
    {
        foreach ($this->uploadLines($file) as $line) {
            // Without --connect-timeout curl waits out the OS default (~134 s
            // observed) before it even reports the failure.
            $this->assertStringContainsString('--connect-timeout', $line, "no --connect-timeout in {$file}");
            $this->assertStringContainsString('--retry', $line, "no --retry in {$file}");
            $this->assertStringContainsString('--retry-connrefused', $line, "no --retry-connrefused in {$file}");
        }
    }

    #[DataProvider('workflowsUploading')]
    public function test_the_upload_still_demands_tls(string $file): void
    {
        foreach ($this->uploadLines($file) as $line) {
            // --ssl-reqd is what stops curl falling back to plaintext FTP with
            // the deploy credentials on the wire. Never trade it for reliability.
            $this->assertStringContainsString('--ssl-reqd', $line, "FTPS upload without --ssl-reqd in {$file}");
        }
    }
}
