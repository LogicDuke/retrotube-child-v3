<?php
/**
 * TMW Slot Banner Metabox - Bulletproof Version
 * Works with both Classic Editor and Gutenberg Block Editor
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    register_post_meta('model', '_tmw_slot_enabled', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);

    register_post_meta('model', '_tmw_slot_mode', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);

    register_post_meta('model', '_tmw_slot_shortcode', [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_textarea_field',
        'auth_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);
});

// Add metabox
add_action('add_meta_boxes', function () {
    add_meta_box(
        'tmw-slot-banner',
        __('Slot Banner', 'retrotube-child'),
        'tmw_render_slot_banner_metabox',
        'model',
        'side',
        'default'
    );
});

function tmw_render_slot_banner_metabox($post)
{
    if (!$post || $post->post_type !== 'model') {
        return;
    }

    $enabled = get_post_meta($post->ID, '_tmw_slot_enabled', true) === '1';
    $mode = get_post_meta($post->ID, '_tmw_slot_mode', true);
    $shortcode = get_post_meta($post->ID, '_tmw_slot_shortcode', true);

    // Smart defaults
    if (!in_array($mode, ['widget', 'shortcode'])) {
        $mode = 'shortcode';
    }
    if ($shortcode === '') {
        $shortcode = '[tmw_slot_machine]';
    }

    wp_nonce_field('tmw_slot_banner_save', 'tmw_slot_banner_nonce');
    ?>
    <input type="hidden" name="tmw_slot_metabox_present" value="1" />

    <p>
        <label>
            <input type="checkbox" name="tmw_slot_enabled" value="1" <?php checked($enabled); ?> />
            <?php esc_html_e('Enable slot banner on this model page', 'retrotube-child'); ?>
        </label>
    </p>

    <p style="margin-top:10px;">
        <strong><?php esc_html_e('Banner source', 'retrotube-child'); ?></strong><br />
        <label style="display:block; margin-top:6px;">
            <input type="radio" name="tmw_slot_mode" value="widget" <?php checked($mode, 'widget'); ?> />
            <?php esc_html_e('Use Global Widget Area', 'retrotube-child'); ?>
        </label>
        <label style="display:block; margin-top:6px;">
            <input type="radio" name="tmw_slot_mode" value="shortcode" <?php checked($mode, 'shortcode'); ?> />
            <?php esc_html_e('Use Custom Shortcode', 'retrotube-child'); ?>
        </label>
    </p>

    <p style="margin-top:10px;">
        <label for="tmw_slot_shortcode" style="font-weight:600;">
            <?php esc_html_e('Shortcode:', 'retrotube-child'); ?>
        </label>
        <textarea id="tmw_slot_shortcode" name="tmw_slot_shortcode"
                  style="width:100%; min-height:60px;"><?php echo esc_textarea($shortcode); ?></textarea>
    </p>

    <p class="description">
        <?php esc_html_e('Default: [tmw_slot_machine]', 'retrotube-child'); ?>
    </p>
    <?php
}

// Classic Editor save
add_action('save_post_model', function ($post_id) {
    // Skip if not from our metabox
    if (!isset($_POST['tmw_slot_metabox_present'])) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!isset($_POST['tmw_slot_banner_nonce']) ||
        !wp_verify_nonce($_POST['tmw_slot_banner_nonce'], 'tmw_slot_banner_save')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $enabled = isset($_POST['tmw_slot_enabled']) && $_POST['tmw_slot_enabled'] === '1';
    $mode = '';
    $shortcode = '';

    if (!$enabled) {
        delete_post_meta($post_id, '_tmw_slot_enabled');
        delete_post_meta($post_id, '_tmw_slot_mode');
        delete_post_meta($post_id, '_tmw_slot_shortcode');
    } else {
        update_post_meta($post_id, '_tmw_slot_enabled', '1');

        $mode = isset($_POST['tmw_slot_mode']) ? sanitize_text_field($_POST['tmw_slot_mode']) : 'shortcode';
        if (!in_array($mode, ['widget', 'shortcode'], true)) {
            $mode = 'shortcode';
        }
        update_post_meta($post_id, '_tmw_slot_mode', $mode);

        $shortcode = isset($_POST['tmw_slot_shortcode']) ? sanitize_textarea_field($_POST['tmw_slot_shortcode']) : '';
        $shortcode = trim($shortcode);
        if ($shortcode !== '') {
            update_post_meta($post_id, '_tmw_slot_shortcode', $shortcode);
        }
    }

}, 10, 1);
