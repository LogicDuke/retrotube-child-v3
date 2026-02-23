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
        if (! TMW_IS_CRON) {
            return;
        }

        do_action('tmw/cron/run');
    }, 20);
};
