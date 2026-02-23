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

    add_action('wp', static function (): void {
        if (TMW_IS_ADMIN) {
            return;
        }

        if (! defined('TMW_MOBILE_HERO_AUDIT_ENABLED') || TMW_MOBILE_HERO_AUDIT_ENABLED !== true) {
            return;
        }

        // Run diagnostic logic here. Keep lightweight to avoid frontend latency.
        do_action('tmw/mobile_hero_audit/run');
    }, 20);
};
