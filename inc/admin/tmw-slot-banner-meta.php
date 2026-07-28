<?php
/**
 * Slot banner meta registration for Gutenberg/REST.
 *
 * Canonical registration for the three _tmw_slot_* keys on every post type
 * that supports the Slot Banner.  Requires each CPT to declare
 * 'custom-fields' support so the REST posts controller exposes the `meta`
 * property (see tmw-model-register.php for model, setup.php for video).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post types that support the per-post Slot Banner.
 *
 * @return string[]
 */
function tmw_slot_banner_post_types(): array {
    return ['model', 'video', 'post'];
}

/**
 * Determine whether a post is a video eligible for the native Slot Banner UI.
 *
 * Eligibility follows the metadata contract used by live RetroTube imports:
 *
 * 1. The `video` CPT is always eligible.
 * 2. A `post` is eligible when its normalized `partner` value is LiveJasmin.
 * 3. A `post` may fall back to a non-empty embed plus a LiveJasmin thumbnail.
 * 4. Legacy non-empty wpslj_* import markers remain compatibility fallbacks.
 *
 * Generic `embed` or `feed` metadata alone is deliberately insufficient, so
 * an ordinary blog post cannot become eligible merely by containing embeds.
 *
 * @param WP_Post|int|null $post Post object or ID.
 * @return bool
 */
function tmw_slot_banner_is_video_post($post): bool {
    $post = get_post($post);

    if (!$post instanceof WP_Post) {
        return false;
    }

    if ($post->post_type === 'video') {
        return true;
    }

    if ($post->post_type !== 'post') {
        return false;
    }

    $partner = strtolower(trim((string) get_post_meta($post->ID, 'partner', true)));
    if ($partner === 'livejasmin') {
        return true;
    }

    // [TMW-SLOT-VIDEO] Narrow fallback for older LiveJasmin-shaped imports.
    $embed = trim((string) get_post_meta($post->ID, 'embed', true));
    $thumbnail_url = trim((string) get_post_meta($post->ID, 'lvjm_thumb_url', true));
    $thumbnail_attachment_id = absint(get_post_meta($post->ID, 'lvjm_thumb_attachment_id', true));

    if ($embed !== '' && ($thumbnail_url !== '' || $thumbnail_attachment_id > 0)) {
        return true;
    }

    // Compatibility with import versions referenced by tmw_detect_livejasmin_post_type().
    foreach (['wpslj_video_id', 'wpslj_model', 'wpslj_stream'] as $meta_key) {
        if (trim((string) get_post_meta($post->ID, $meta_key, true)) !== '') {
            return true;
        }
    }

    return false;
}

add_action('init', function () {
    $base = [
        'show_in_rest'  => true,
        'single'        => true,
        'type'          => 'string',
        'auth_callback' => function ($allowed, $meta_key, $post_id) {
            return $post_id > 0 ? current_user_can('edit_post', $post_id) : current_user_can('edit_posts');
        },
    ];

    $keys = [
        '_tmw_slot_enabled' => $base + [
            'sanitize_callback' => function ($v) { return $v === '1' ? '1' : ''; },
        ],
        '_tmw_slot_mode' => $base + [
            'default' => 'shortcode',
            'sanitize_callback' => function ($v) { return in_array($v, ['widget', 'shortcode'], true) ? $v : 'shortcode'; },
        ],
        '_tmw_slot_shortcode' => $base + [
            'sanitize_callback' => 'sanitize_textarea_field',
        ],
    ];

    foreach (tmw_slot_banner_post_types() as $pt) {
        foreach ($keys as $key => $args) {
            register_post_meta($pt, $key, $args);
        }
    }
});
