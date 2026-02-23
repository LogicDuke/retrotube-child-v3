<?php

return static function (): void {
    if (!defined('TMW_DEV_MODE') || TMW_DEV_MODE !== true) {
        return;
    }

    if (!defined('TMW_TRASH_DEBUG') || TMW_TRASH_DEBUG !== true) {
        return;
    }

    if (!is_admin()) {
        return;
    }

    add_filter('pre_trash_post', static function ($override, $post) {
        $post_id = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
        error_log('[TRASH DEBUG] pre_trash_post post_id=' . $post_id);

        return $override;
    }, 10, 2);

    add_action('trashed_post', static function ($post_id): void {
        error_log('[TRASH DEBUG] trashed_post post_id=' . (int) $post_id);
    });
};
