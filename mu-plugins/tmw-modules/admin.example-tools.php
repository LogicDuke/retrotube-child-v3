<?php
/**
 * Admin module example.
 */

defined('ABSPATH') || exit;

final class TMW_Admin_Example_Tools
{
    private static bool $registered = false;
    private const MIGRATION_OPTION = 'tmw_mu_migrations_version';
    private const MIGRATION_LOCK_OPTION = 'tmw_mu_migrations_lock';

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        add_action('admin_notices', [self::class, 'show_notice']);
        add_action('init', [self::class, 'maybe_run_migrations'], 1);
    }

    public static function show_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-info"><p>TMW admin module active.</p></div>';
    }

    public static function maybe_run_migrations(): void
    {
        $target_version = defined('TMW_MU_MIGRATIONS_VERSION') ? TMW_MU_MIGRATIONS_VERSION : '0';
        $current_version = (string) get_option(self::MIGRATION_OPTION, '0');

        if (version_compare($current_version, $target_version, '>=')) {
            return;
        }

        if ((int) get_option(self::MIGRATION_LOCK_OPTION, 0) === 1) {
            return;
        }

        update_option(self::MIGRATION_LOCK_OPTION, 1, false);

        try {
            // Put idempotent migration routines here.
            // Example:
            // tmw_register_missing_caps();
            // tmw_backfill_model_meta();

            update_option(self::MIGRATION_OPTION, $target_version, false);
        } finally {
            delete_option(self::MIGRATION_LOCK_OPTION);
        }
    }
}

add_action('tmw_mu_modules_register', static function (string $context): void {
    if ($context !== 'admin') {
        return;
    }

    TMW_Admin_Example_Tools::register();
}, 10, 1);
