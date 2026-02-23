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

if (! defined('TMW_IS_ADMIN')) {
    define('TMW_IS_ADMIN', is_admin());
}

if (! defined('TMW_IS_AJAX')) {
    define('TMW_IS_AJAX', function_exists('wp_doing_ajax') && wp_doing_ajax());
}

if (! defined('TMW_IS_CRON')) {
    define('TMW_IS_CRON', function_exists('wp_doing_cron') && wp_doing_cron());
}

if (! defined('TMW_IS_CLI')) {
    define('TMW_IS_CLI', (defined('WP_CLI') && WP_CLI) || PHP_SAPI === 'cli');
}

if (! defined('TMW_DEV_MODE')) {
    define('TMW_DEV_MODE', false);
}

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

        $bootstrap = require_once $file;

        if (! is_callable($bootstrap)) {
            continue;
        }

        $bootstrap();
        $bootstrapped_modules[$file] = true;
    }
}

/**
 * Resolve module files for core + context specific directories.
 *
 * @return list<string>
 */
function tmw_mu_resolve_module_files(): array
{
    $directories = [
        TMW_MU_MODULES_DIR . '/core',
    ];

    $directories[] = TMW_IS_ADMIN
        ? TMW_MU_MODULES_DIR . '/admin'
        : TMW_MU_MODULES_DIR . '/frontend';

    if (TMW_DEV_MODE === true) {
        $directories[] = TMW_MU_MODULES_DIR . '/dev';
    }

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
