<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_video_thumb_post_types')) {
    /**
     * Post types that should keep legacy thumb meta and featured image in sync.
     *
     * @return string[]
     */
    function tmw_video_thumb_post_types(): array {
        return ['post', 'video', 'videos', 'wpsc-video', 'wp-script-video', 'wpws_video'];
    }
}

if (!function_exists('tmw_is_video_thumb_post_type')) {
    function tmw_is_video_thumb_post_type(string $post_type): bool {
        return in_array($post_type, tmw_video_thumb_post_types(), true);
    }
}

if (!function_exists('tmw_attachment_id_from_url')) {
    function tmw_attachment_id_from_url(string $url): int {
        if ($url === '') {
            return 0;
        }

        $id = attachment_url_to_postid($url);
        return $id ? (int) $id : 0;
    }
}

add_action('save_post', function (int $post_id, WP_Post $post, bool $update): void {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) { return; }
    if (!$post instanceof WP_Post || !tmw_is_video_thumb_post_type($post->post_type)) { return; }
    if (!current_user_can('edit_post', $post_id)) { return; }

    $featured_id = (int) get_post_thumbnail_id($post_id);
    if ($featured_id > 0) {
        $featured_url = wp_get_attachment_image_url($featured_id, 'full');
        if (is_string($featured_url) && $featured_url !== '') {
            update_post_meta($post_id, 'thumb', esc_url_raw($featured_url));
        }
        return;
    }

    $legacy_thumb = get_post_meta($post_id, 'thumb', true);
    $legacy_thumb = is_string($legacy_thumb) ? trim($legacy_thumb) : '';
    if ($legacy_thumb === '') {
        return;
    }

    $attachment_id = tmw_attachment_id_from_url($legacy_thumb);
    if ($attachment_id > 0) {
        set_post_thumbnail($post_id, $attachment_id);
    }
}, 20, 3);
