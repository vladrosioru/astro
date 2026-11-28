<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class CronDeployTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'astro_cron_deploy_'.bin2hex(random_bytes(4));
        mkdir($this->tempRoot, 0775, true);
        copy(base_path('cron-deploy.php'), $this->tempRoot.DIRECTORY_SEPARATOR.'cron-deploy.php');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempRoot);

        parent::tearDown();
    }

    private function runCron(): Process
    {
        $process = new Process(
            [PHP_BINARY, $this->tempRoot.DIRECTORY_SEPARATOR.'cron-deploy.php', '--skip-artisan'],
            $this->tempRoot
        );
        $process->run();

        return $process;
    }

    public function test_cron_deploy_exits_silently_when_no_deploy_pending_marker_exists(): void
    {
        $run = $this->runCron();

        $this->assertSame(0, $run->getExitCode(), $run->getErrorOutput());
        $this->assertSame('', $run->getOutput());
        $this->assertFileDoesNotExist($this->tempRoot.DIRECTORY_SEPARATOR.'deploy-result.txt');
    }

    public function test_cron_deploy_extracts_archive_writes_result_and_cleans_markers(): void
    {
        $zipPath = $this->tempRoot.DIRECTORY_SEPARATOR.'app.zip';
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('public/hello-cron.txt', 'deployed via cron');
        $zip->close();

        file_put_contents($this->tempRoot.DIRECTORY_SEPARATOR.'.deploy-pending', "run-12345-1\n");

        $run = $this->runCron();

        $this->assertSame(0, $run->getExitCode(), $run->getErrorOutput());
        $this->assertSame('', $run->getOutput(), 'Cron stdout must stay silent so cPanel does not email every deploy');

        $this->assertFileDoesNotExist($zipPath);
        $this->assertFileDoesNotExist($this->tempRoot.DIRECTORY_SEPARATOR.'.deploy-pending');
        $this->assertSame('deployed via cron', file_get_contents($this->tempRoot.'/public/hello-cron.txt'));

        $storageHtaccess = $this->tempRoot.'/public/storage/.htaccess';
        $this->assertFileExists($storageHtaccess);
        $this->assertStringContainsString('php_flag engine off', (string) file_get_contents($storageHtaccess));

        $result = (string) file_get_contents($this->tempRoot.DIRECTORY_SEPARATOR.'deploy-result.txt');
        $this->assertStringStartsWith('OK run-12345-1', $result);
        $this->assertStringContainsString('Extracted 1 entries', $result);
        $this->assertFileExists($this->tempRoot.'/storage/logs/cron-deploy.log');
    }

    public function test_cron_deploy_writes_fail_result_and_cleans_marker_when_archive_missing(): void
    {
        file_put_contents($this->tempRoot.DIRECTORY_SEPARATOR.'.deploy-pending', "run-999-1\n");

        $run = $this->runCron();

        $this->assertSame(1, $run->getExitCode());
        $this->assertFileDoesNotExist($this->tempRoot.DIRECTORY_SEPARATOR.'.deploy-pending');

        $result = (string) file_get_contents($this->tempRoot.DIRECTORY_SEPARATOR.'deploy-result.txt');
        $this->assertStringStartsWith('FAIL run-999-1', $result);
        $this->assertStringContainsString('app.zip not found', $result);
    }
}
