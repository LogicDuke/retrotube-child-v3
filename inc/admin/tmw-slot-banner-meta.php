<?php
/**
 * Slot banner meta registration for Gutenberg/REST.
 *
 * Canonical registration for the three _tmw_slot_* keys. Requires the
 * 'model' CPT to declare 'custom-fields' support (tmw-model-register.php),
 * otherwise the REST posts controller omits the `meta` property entirely
 * and silently discards these values on save.
 */

if (!defined('ABSPATH')) {
    exit;
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

    register_post_meta('model', '_tmw_slot_enabled', $base + [
        'sanitize_callback' => function ($v) { return $v === '1' ? '1' : ''; },
    ]);
    register_post_meta('model', '_tmw_slot_mode', $base + [
        'default' => 'shortcode',
        'sanitize_callback' => function ($v) { return in_array($v, ['widget', 'shortcode'], true) ? $v : 'shortcode'; },
    ]);
    register_post_meta('model', '_tmw_slot_shortcode', $base + [
        'sanitize_callback' => 'sanitize_textarea_field',
    ]);
});
