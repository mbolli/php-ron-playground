<?php

declare(strict_types=1);

/*
 * Copies php-via's bundled client assets into ./public so the app can serve them
 * (the shell loads {{ base_path }}datastar.js). Run automatically on composer
 * install/update. The copied files are git-ignored and regenerated on install.
 */

$root = \dirname(__DIR__);
$vendorPublic = $root . '/vendor/mbolli/php-via/public';
$target = $root . '/public';

if (!is_dir($vendorPublic)) {
    fwrite(STDERR, "copy-assets: php-via package not found at {$vendorPublic} (run composer install first)\n");
    exit(0);
}

if (!is_dir($target) && !mkdir($target, 0o755, true) && !is_dir($target)) {
    fwrite(STDERR, "copy-assets: could not create {$target}\n");
    exit(1);
}

foreach (['datastar.js'] as $file) {
    $src = $vendorPublic . '/' . $file;
    if (!is_file($src)) {
        fwrite(STDERR, "copy-assets: missing {$src}\n");
        continue;
    }
    if (copy($src, $target . '/' . $file)) {
        fwrite(STDOUT, "copy-assets: {$file}\n");
    } else {
        fwrite(STDERR, "copy-assets: failed to copy {$file}\n");
    }
}
