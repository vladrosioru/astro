<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/**
 * One-shot post-deploy hook.
 *
 * The CI/CD pipeline (.github/workflows/cicd.yml) uploads the built app over
 * FTP, then calls this endpoint. The host has no SSH, so this is the only way
 * to run `artisan` on the server after a deploy.
 *
 * It runs: ensure the writable storage tree exists -> migrate --force ->
 * storage:link -> config/route/view cache.
 *
 * Auth: the caller must send the DEPLOY_TOKEN (from .env) in the
 * `X-Deploy-Token` request header (query `?token=` also accepted as a
 * fallback). The token is read straight from the .env file rather than via
 * env(), because once config is cached Laravel stops loading .env and env()
 * would return null — locking this endpoint out.
 *
 * Lives in public/ (the web docroot). Safe to leave in place: with no valid
 * token it returns 403 and touches nothing.
 */
$appRoot = dirname(__DIR__);

/** Read a single key from a .env file without booting the framework. */
function deploy_env_value(string $key, string $path): ?string
{
    if (! is_readable($path)) {
        return null;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        if (trim($k) === $key) {
            return trim($v, " \t\"'");
        }
    }

    return null;
}

// --- Auth (before booting anything) ---------------------------------------
$expected = (string) (deploy_env_value('DEPLOY_TOKEN', $appRoot.'/.env') ?? '');
$given = (string) ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? $_GET['token'] ?? '');

if ($expected === '' || ! hash_equals($expected, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit("Forbidden\n");
}

header('Content-Type: text/plain');

// --- Ensure the writable runtime tree exists (FTP sync skips empty dirs) ---
foreach ([
    'storage/app/public', 'storage/app/private',
    'storage/framework/cache/data', 'storage/framework/sessions',
    'storage/framework/views', 'storage/framework/testing',
    'storage/logs', 'bootstrap/cache',
] as $dir) {
    @mkdir($appRoot.'/'.$dir, 0775, true);
}

// --- Boot the framework and run deploy commands ----------------------------
define('LARAVEL_START', microtime(true));

require $appRoot.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

/**
 * Run an artisan command, echo its output. A non-zero exit on a `$fatal`
 * command makes the whole hook return HTTP 500 so `curl -f` fails the CI job.
 */
$run = function (string $command, array $params = [], bool $fatal = true) use ($kernel): void {
    echo "\n$ php artisan {$command}\n";
    try {
        $status = $kernel->call($command, $params);
        echo $kernel->output();
        if ($status !== 0 && $fatal) {
            http_response_code(500);
        }
    } catch (Throwable $e) {
        echo 'ERROR: '.$e->getMessage()."\n";
        if ($fatal) {
            http_response_code(500);
        }
    }
};

$run('migrate', ['--force' => true]);      // fatal: bad DB config must fail the deploy
$run('storage:link', [], false);           // non-fatal: link may exist or symlink() be restricted
$run('config:cache');
$run('route:cache');
$run('view:cache');

echo "\nDeploy hook finished.\n";
