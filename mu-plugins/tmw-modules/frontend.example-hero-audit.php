<?php
/**
 * Frontend module example.
 */

defined('ABSPATH') || exit;

final class TMW_Frontend_Example_Hero_Audit
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;
        add_action('wp_footer', [self::class, 'render_debug_comment'], 100);
    }

    public static function render_debug_comment(): void
    {
        if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
            return;
        }

        echo "\n<!-- TMW frontend module loaded: hero audit -->\n";
    }
}

add_action('tmw_mu_modules_register', static function (string $context): void {
    if ($context !== 'frontend') {
        return;
    }

    TMW_Frontend_Example_Hero_Audit::register();
}, 10, 1);
