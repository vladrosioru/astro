<?php

/**
 * One-shot deploy extractor.
 *
 * The CI/CD pipeline (.github/workflows/cicd.yml) uploads a single build
 * archive (app.zip) over FTPS — far faster than syncing thousands of vendor/
 * files one at a time — along with this bootstrap copy of extract.php and the
 * generated .env. This endpoint unzips app.zip in place; the pipeline then
 * calls public/deploy.php for migrations and caches.
 *
 * Auth: DEPLOY_TOKEN from .env, sent in the `X-Deploy-Token` header (query
 * `?token=` accepted as a fallback). Read straight from the .env file rather
 * than via env(), so a cached config can't lock this endpoint out.
 *
 * It only ever extracts our own CI-built archive (trusted). Extraction
 * overlays files; the server .env and storage/ are not in the archive, so they
 * survive. Safe to leave deployed: with no valid token it returns 403.
 */
$appRoot = dirname(__DIR__);
$archive = $appRoot.'/app.zip';

/** Read a single key from a .env file without booting the framework. */
function extract_env_value(string $key, string $path): ?string
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

$expected = (string) (extract_env_value('DEPLOY_TOKEN', $appRoot.'/.env') ?? '');
$given = (string) ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? $_GET['token'] ?? '');

if ($expected === '' || ! hash_equals($expected, $given)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit("Forbidden\n");
}

header('Content-Type: text/plain');

if (! class_exists('ZipArchive')) {
    http_response_code(500);
    exit("ERROR: the zip extension is not available on this server.\n");
}

if (! is_readable($archive)) {
    http_response_code(500);
    exit("ERROR: app.zip not found at {$archive}\n");
}

$zip = new ZipArchive;
$open = $zip->open($archive);
if ($open !== true) {
    http_response_code(500);
    exit("ERROR: could not open app.zip (code {$open})\n");
}

$count = $zip->numFiles;
if (! $zip->extractTo($appRoot)) {
    $zip->close();
    http_response_code(500);
    exit("ERROR: extraction failed\n");
}
$zip->close();

@unlink($archive);

echo "Extracted {$count} entries into {$appRoot}\n";
echo "Archive removed. Now call deploy.php for migrate + caches.\n";
