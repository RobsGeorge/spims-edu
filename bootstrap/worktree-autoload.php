<?php

/**
 * Point Composer at this checkout.
 *
 * Cloud worktrees share /workspace/vendor; dump-autoload records those
 * absolute paths (classmap wins over PSR-4). Rebind so artisan and PHPUnit
 * load *this* tree without a second composer install.
 */
$root = dirname(__DIR__);
$loader = require $root.'/vendor/autoload.php';

if ($loader instanceof Composer\Autoload\ClassLoader) {
    $loader->setPsr4('App\\', $root.'/app');
    $loader->setPsr4('Database\\Factories\\', $root.'/database/factories');
    $loader->setPsr4('Database\\Seeders\\', $root.'/database/seeders');
    $loader->setPsr4('Tests\\', $root.'/tests');

    $shared = realpath('/workspace') ?: '/workspace';
    $remap = static function (string $file) use ($root, $shared): string {
        $resolved = realpath($file) ?: $file;
        foreach (['/app/', '/tests/', '/database/'] as $prefix) {
            $needle = rtrim($shared, '/').$prefix;
            if (str_starts_with($resolved, $needle)) {
                $local = $root.$prefix.substr($resolved, strlen($needle));

                return is_file($local) ? $local : $resolved;
            }
        }

        return $resolved;
    };

    $reflect = new ReflectionClass($loader);
    foreach (['classMap'] as $property) {
        if (! $reflect->hasProperty($property)) {
            continue;
        }
        $prop = $reflect->getProperty($property);
        $prop->setAccessible(true);
        $map = $prop->getValue($loader);
        if (! is_array($map)) {
            continue;
        }
        foreach ($map as $class => $file) {
            if (is_string($file)) {
                $map[$class] = $remap($file);
            }
        }
        $prop->setValue($loader, $map);
    }
}

return $loader;
