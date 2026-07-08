<?php

/**
 * Build a single deployable zip of the app for the CI/CD pipeline.
 *
 * The pipeline uploads this one file over FTPS (far faster than syncing
 * thousands of vendor/ files) and public/extract.php unzips it on the server.
 *
 * Excludes dev-only, runtime, and secret paths — mirrors the archive contract
 * the tar build used. Only TOP-LEVEL matches are excluded, so per-package
 * dirs inside vendor/ (e.g. vendor/x/tests) are kept — the app needs them.
 *
 * Usage: php make-archive.php /abs/out.zip [sourceRoot=cwd]
 */
$out = $argv[1] ?? null;
$root = rtrim($argv[2] ?? getcwd(), '/\\');

if ($out === null) {
    fwrite(STDERR, "usage: make-archive.php OUT.zip [ROOT]\n");
    exit(2);
}

$excludeTopDirs = [
    '.git', '.github', '.claude', '.superpowers',
    'tests', 'node_modules', 'storage', 'docs',
];
$excludeExact = [
    'deploy_VPS.yml', 'database/database.sqlite', '.env',
    'CLAUDE.md', 'README.md', 'phpunit.xml',
    '.editorconfig', '.gitattributes', '.gitignore', '.phpunit.result.cache',
    // Theme docs/schema that would otherwise ship inside the web docroot.
    'public/themes/AUTHORING.md', 'public/themes/theme.schema.json',
];
$excludePrefixes = ['.env.']; // .env.backup, .env.production, ...

$zip = new ZipArchive;
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "cannot create {$out}\n");
    exit(1);
}

$rootLen = strlen($root) + 1;
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$count = 0;
foreach ($iter as $path) {
    $rel = str_replace('\\', '/', substr((string) $path, $rootLen));

    if ($rel === '' || in_array(explode('/', $rel)[0], $excludeTopDirs, true)) {
        continue;
    }
    if (in_array($rel, $excludeExact, true)) {
        continue;
    }
    foreach ($excludePrefixes as $p) {
        if (str_starts_with($rel, $p)) {
            continue 2;
        }
    }

    if ($path->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile((string) $path, $rel);
    }
    $count++;
}

$zip->close();
fwrite(STDOUT, "archived {$count} entries -> {$out}\n");
