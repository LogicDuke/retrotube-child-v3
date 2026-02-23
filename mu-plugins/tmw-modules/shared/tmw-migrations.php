<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

return static function (): void {
    static $registered = false;

    if ($registered) {
        return;
    }
    $registered = true;

    add_action('init', static function (): void {
        $target_version = '2026.02.0';
        $version_option = 'tmw_mu_migrations_version';

        if (get_option($version_option) === $target_version) {
            return;
        }

        // Run idempotent migration steps here.
        do_action('tmw/migrations/run', $target_version);

        update_option($version_option, $target_version, false);
    }, 1);
};
