<?php
/**
 * Plugin Name: TMW MU Loader
 * Description: Single entrypoint for all TMW mu-plugin modules.
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (defined('TMW_MU_LOADER_BOOTSTRAPPED')) {
    return;
}
define('TMW_MU_LOADER_BOOTSTRAPPED', true);

define('TMW_MU_LOADER_DIR', __DIR__);
define('TMW_MU_MODULES_DIR', TMW_MU_LOADER_DIR . '/tmw-modules');

/**
 * Include module files and execute their bootstrap callable once.
 *
 * Each module file MUST return a callable with signature fn(): void.
 * Modules must only register hooks inside that callable.
 *
 * @param list<string> $files
 */
function tmw_mu_bootstrap_modules(array $files): void
{
    static $bootstrapped_modules = [];

    foreach ($files as $file) {
        if (! is_file($file) || isset($bootstrapped_modules[$file])) {
            continue;
        }

        $bootstrap = require $file;

        if (! is_callable($bootstrap)) {
            error_log(sprintf('[tmw-loader] Module did not return callable: %s', $file));
            continue;
        }

        $bootstrap();
        $bootstrapped_modules[$file] = true;
    }
}

/**
 * Resolve module files for shared + context specific directories.
 *
 * @return list<string>
 */
function tmw_mu_resolve_module_files(): array
{
    $directories = [
        TMW_MU_MODULES_DIR . '/shared',
        is_admin() ? TMW_MU_MODULES_DIR . '/admin' : TMW_MU_MODULES_DIR . '/frontend',
    ];

    $files = [];

    foreach ($directories as $directory) {
        if (! is_dir($directory)) {
            continue;
        }

        $matched = glob($directory . '/*.php');

        if ($matched === false) {
            continue;
        }

        sort($matched, SORT_NATURAL);
        $files = array_merge($files, $matched);
    }

    return $files;
}

tmw_mu_bootstrap_modules(tmw_mu_resolve_module_files());
