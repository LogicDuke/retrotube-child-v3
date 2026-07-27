<?php
/**
 * Canonical slot-banner meta registration and Gutenberg/REST persistence.
 *
 * This is the only file that registers the three slot-banner meta keys. The
 * metabox file is deliberately limited to UI and Classic Editor persistence.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sanitize a slot-banner enabled value to the one supported truthy value.
 */
function tmw_sanitize_slot_enabled($value): string
{
    return (string) $value === '1' ? '1' : '';
}

/**
 * Sanitize a slot-banner shortcode without allowing markup around it.
 */
function tmw_sanitize_slot_shortcode($value): string
{
    return trim(sanitize_textarea_field((string) $value));
}

add_action('init', function (): void {
    $auth_callback = static function ($allowed, $meta_key, $post_id): bool {
        return current_user_can('edit_post', (int) $post_id);
    };

    register_post_meta('model', '_tmw_slot_enabled', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => [
            'schema' => [
                'type' => 'string',
                'enum' => ['', '1'],
            ],
        ],
        'sanitize_callback' => 'tmw_sanitize_slot_enabled',
        'auth_callback'     => $auth_callback,
    ]);

    register_post_meta('model', '_tmw_slot_mode', [
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => [
            'schema' => [
                'type' => 'string',
                'enum' => ['widget', 'shortcode'],
            ],
        ],
        'auth_callback' => $auth_callback,
    ]);

    register_post_meta('model', '_tmw_slot_shortcode', [
        'type'              => 'string',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'tmw_sanitize_slot_shortcode',
        'auth_callback'     => $auth_callback,
    ]);
});

/**
 * Persist slot-banner fields submitted by a Gutenberg REST save.
 *
 * Gutenberg can submit these values in the REST `meta` object (the metabox JS
 * uses that route). The aliases also support legacy metabox REST payloads. A
 * key must be present before it is touched, preventing unrelated REST updates
 * from resetting existing model settings.
 */
add_action('rest_after_insert_model', function (WP_Post $post, WP_REST_Request $request, bool $creating): void {
    $post_id = (int) $post->ID;
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $params = $request->get_params();
    $meta = isset($params['meta']) && is_array($params['meta']) ? $params['meta'] : [];
    $submitted = [];
    $aliases = [
        '_tmw_slot_enabled'   => 'tmw_slot_enabled',
        '_tmw_slot_mode'      => 'tmw_slot_mode',
        '_tmw_slot_shortcode' => 'tmw_slot_shortcode',
    ];

    foreach ($aliases as $meta_key => $alias) {
        if (array_key_exists($meta_key, $meta)) {
            $submitted[$meta_key] = $meta[$meta_key];
        } elseif (array_key_exists($alias, $params)) {
            $submitted[$meta_key] = $params[$alias];
        }
    }

    // An explicit metabox marker makes an absent checkbox an unchecked value.
    if (
        array_key_exists('tmw_slot_metabox_present', $params) &&
        !array_key_exists('_tmw_slot_enabled', $submitted)
    ) {
        $submitted['_tmw_slot_enabled'] = '';
    }

    if (array_key_exists('_tmw_slot_enabled', $submitted)) {
        if ((string) $submitted['_tmw_slot_enabled'] === '1') {
            update_post_meta($post_id, '_tmw_slot_enabled', '1');
        } else {
            delete_post_meta($post_id, '_tmw_slot_enabled');
        }
    }

    if (array_key_exists('_tmw_slot_mode', $submitted)) {
        $mode = sanitize_text_field((string) $submitted['_tmw_slot_mode']);
        if (in_array($mode, ['widget', 'shortcode'], true)) {
            update_post_meta($post_id, '_tmw_slot_mode', $mode);
        }
    }

    if (array_key_exists('_tmw_slot_shortcode', $submitted)) {
        update_post_meta(
            $post_id,
            '_tmw_slot_shortcode',
            tmw_sanitize_slot_shortcode($submitted['_tmw_slot_shortcode'])
        );
    }
}, 20, 3);
