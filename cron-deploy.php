<?php

/**
 * Automated cPanel Cron deploy runner.
 *
 * Triggered once per minute by cPanel Cron:
 *   /usr/local/bin/ea-php84 /home/martinis/<app_dir>/cron-deploy.php
 *
 * Designed to stay completely silent on idle ticks (exits 0 with no output)
 * so cPanel does not email every minute. When `.deploy-pending` is uploaded
 * over FTPS after `app.zip`, this script extracts the archive, runs Laravel
 * deploy tasks, removes the trigger/archive, and writes `deploy-result.txt`
 * (`OK <deploy_id>` or `FAIL <deploy_id>`) so CI can retrieve the result and
 * full log over FTPS without hitting Imunify360's HTTP WAF.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

$appRoot = __DIR__;
$pendingFile = $appRoot.'/.deploy-pending';

if (! is_file($pendingFile)) {
    exit(0);
}

$lockHandle = @fopen($appRoot.'/.deploy.lock', 'c+');
if ($lockHandle === false || ! flock($lockHandle, LOCK_EX | LOCK_NB)) {
    if ($lockHandle !== false) {
        fclose($lockHandle);
    }
    exit(0);
}

clearstatcache(true, $pendingFile);
if (! is_file($pendingFile)) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(0);
}

$skipArtisan = in_array('--skip-artisan', $argv ?? [], true);
$deployId = trim((string) @file_get_contents($pendingFile));
if ($deployId === '') {
    $deployId = 'unknown';
}

$lines = [];
$log = static function (string $message) use (&$lines): void {
    $lines[] = '['.gmdate('Y-m-d H:i:s').'Z] '.$message;
};

$failed = false;
$archive = $appRoot.'/app.zip';

$log("Starting cron deploy for {$deployId}");

// --- 1. Extract release archive ---------------------------------------------
if (! class_exists('ZipArchive')) {
    $log('ERROR: ZipArchive extension is not available on this PHP CLI binary.');
    $failed = true;
} elseif (! is_readable($archive)) {
    $log("ERROR: app.zip not found at {$archive}");
    $failed = true;
} else {
    $zip = new ZipArchive;
    $open = $zip->open($archive);
    if ($open !== true) {
        $log("ERROR: could not open app.zip (code {$open})");
        $failed = true;
    } else {
        $count = $zip->numFiles;
        if (! $zip->extractTo($appRoot)) {
            $zip->close();
            $log('ERROR: extraction failed.');
            $failed = true;
        } else {
            $zip->close();
            @unlink($archive);
            $log("Extracted {$count} entries into {$appRoot} and removed app.zip.");
        }
    }
}

// --- 2. Ensure writable runtime directories & storage PHP block -------------
if (! $failed) {
    foreach ([
        'storage/app/public', 'storage/app/private',
        'storage/framework/cache/data', 'storage/framework/sessions',
        'storage/framework/views', 'storage/framework/testing',
        'storage/logs', 'bootstrap/cache',
        'database/backups', 'public/storage',
    ] as $dir) {
        @mkdir($appRoot.'/'.$dir, 0775, true);
    }

    $storageHtaccess = $appRoot.'/public/storage/.htaccess';
    if (! is_file($storageHtaccess)) {
        @file_put_contents($storageHtaccess, implode("\n", [
            '<IfModule mod_php.c>',
            '    php_flag engine off',
            '</IfModule>',
            '<IfModule mod_php8.c>',
            '    php_flag engine off',
            '</IfModule>',
            '<FilesMatch "\.(php|php[0-9]|phtml|phar|cgi|pl|py|jsp|asp|sh)$">',
            '    Require all denied',
            '</FilesMatch>',
            '',
        ]));
    }
}

// --- 3. Boot Laravel and run deploy artisan commands ------------------------
if (! $failed && ! $skipArtisan) {
    try {
        define('LARAVEL_START', microtime(true));

        require $appRoot.'/vendor/autoload.php';

        /** @var Application $app */
        $app = require_once $appRoot.'/bootstrap/app.php';

        /** @var Kernel $kernel */
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();

        $run = static function (string $command, array $params = [], bool $fatal = true) use ($kernel, &$failed, $log): void {
            $log("$ php artisan {$command}");
            try {
                $status = $kernel->call($command, $params);
                $output = trim($kernel->output());
                if ($output !== '') {
                    $log($output);
                }
                if ($status !== 0 && $fatal) {
                    $log("ERROR: {$command} exited with status {$status}");
                    $failed = true;
                }
            } catch (Throwable $e) {
                $log('ERROR: '.$e->getMessage());
                if ($fatal) {
                    $failed = true;
                }
            }
        };

        $run('migrate', ['--force' => true]);
        $run('db:seed', ['--class' => 'Database\\Seeders\\AuthorSeeder', '--force' => true], false);
        $run('view:clear', [], false);
        $run('config:cache');
        $run('route:cache');
        $run('view:cache');
    } catch (Throwable $e) {
        $log('ERROR booting application: '.$e->getMessage());
        $failed = true;
    }
} elseif ($skipArtisan && ! $failed) {
    $log('Skipped artisan commands (--skip-artisan).');
}

// --- 4. Write atomic result & persist log -----------------------------------
$statusWord = $failed ? 'FAIL' : 'OK';
$log($failed ? 'Deploy finished with errors.' : 'Deploy finished.');

$payload = "{$statusWord} {$deployId}\n".implode("\n", $lines)."\n";

@mkdir($appRoot.'/storage/logs', 0775, true);
@file_put_contents($appRoot.'/storage/logs/cron-deploy.log', $payload."\n", FILE_APPEND);

$tmpResult = $appRoot.'/deploy-result.txt.tmp';
$finalResult = $appRoot.'/deploy-result.txt';
if (@file_put_contents($tmpResult, $payload) !== false) {
    @rename($tmpResult, $finalResult);
} else {
    @file_put_contents($finalResult, $payload);
}

@unlink($pendingFile);

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit($failed ? 1 : 0);
