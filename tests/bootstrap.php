<?php

require __DIR__.'/../vendor/autoload.php';

$root = dirname(__DIR__);
$vendor = $root.DIRECTORY_SEPARATOR.'vendor';
$vendorLinked = is_link($vendor);

// Windows directory junctions are not always reported by is_link().
if (! $vendorLinked) {
    $vendorReal = realpath($vendor);
    $rootReal = realpath($root);
    if ($vendorReal !== false && $rootReal !== false) {
        $vendorNorm = strtolower(str_replace('\\', '/', $vendorReal));
        $rootNorm = strtolower(str_replace('\\', '/', $rootReal)).'/';
        $vendorLinked = ! str_starts_with($vendorNorm, $rootNorm);
    }
}

if ($vendorLinked && is_file($root.'/bootstrap/worktree-autoload.php')) {
    require $root.'/bootstrap/worktree-autoload.php';
}
