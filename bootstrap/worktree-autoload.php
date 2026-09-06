<?php

/**
 * When `vendor` is a symlink (isolated worktrees), Composer's PSR-4 map
 * points at the symlink target's app/ directory. Prefer this worktree's
 * app/tests/database when the file exists, then fall through to Composer.
 */
$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $map = [
        'App\\' => $root.'/app/',
        'Tests\\' => $root.'/tests/',
        'Database\\' => $root.'/database/',
    ];

    foreach ($map as $prefix => $base) {
        if (! str_starts_with($class, $prefix)) {
            continue;
        }

        $path = $base.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($path)) {
            require $path;
        }

        return;
    }
}, true, true);
