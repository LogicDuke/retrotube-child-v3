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

    add_action('admin_init', static function (): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        if (! isset($_GET['tmw_slot_diagnostic'])) {
            return;
        }

        // Execute on explicit request only to prevent admin slowdowns/timeouts.
        do_action('tmw/slot_diagnostic/run');
    }, 20);
};
