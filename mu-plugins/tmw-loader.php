<?php
/**
 * Plugin Name: TMW MU Loader
 * Description: Single-entry MU plugin loader for TMW modules.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

if (defined('TMW_MU_LOADER_BOOTED')) {
    return;
}
define('TMW_MU_LOADER_BOOTED', true);

if (!defined('TMW_MU_MODULES_DIR')) {
    define('TMW_MU_MODULES_DIR', __DIR__ . '/tmw-modules');
}

if (!defined('TMW_MU_MIGRATIONS_VERSION')) {
    define('TMW_MU_MIGRATIONS_VERSION', '2026.02.0');
}

/**
 * Load module files by context without executing module logic on include.
 *
 * Naming convention:
 * - shared.*.php   => loaded on all requests
 * - admin.*.php    => loaded only in wp-admin
 * - frontend.*.php => loaded only outside wp-admin
 */
function tmw_mu_loader_include_modules(string $context): void
{
    static $included = [];

    $patterns = [
        'shared.*.php',
        $context . '.*.php',
    ];

    foreach ($patterns as $pattern) {
        $files = glob(TMW_MU_MODULES_DIR . '/' . $pattern);
        if ($files === false) {
            continue;
        }

        sort($files, SORT_STRING);

        foreach ($files as $file) {
            if (isset($included[$file])) {
                continue;
            }

            require_once $file;
            $included[$file] = true;
        }
    }
}

/**
 * Initialize modules exactly once per request.
 */
function tmw_mu_loader_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;

    $context = is_admin() ? 'admin' : 'frontend';
    tmw_mu_loader_include_modules($context);

    /**
     * Modules attach hooks from this action.
     * They should also guard their register() methods with a static boolean.
     */
    do_action('tmw_mu_modules_register', $context);
}

tmw_mu_loader_bootstrap();
