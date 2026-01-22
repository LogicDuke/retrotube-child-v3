<?php
/**
 * Slot banner meta registration for Gutenberg/REST.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    $args = [
        'show_in_rest'  => true,
        'single'        => true,
        'type'          => 'string',
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ];

    register_post_meta('model', '_tmw_slot_enabled', $args);
    register_post_meta('model', '_tmw_slot_mode', $args);
    register_post_meta('model', '_tmw_slot_shortcode', $args);
});
