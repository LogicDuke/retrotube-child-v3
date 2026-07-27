<?php
/**
 * TMW Slot Banner Metabox - Bulletproof Version
 * Works with both Classic Editor and Gutenberg Block Editor
 */

if (!defined('ABSPATH')) {
    exit;
}

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
    <script>
    (function () {
        'use strict';

        if (!window.wp || !wp.data || !wp.data.select || !wp.data.dispatch) {
            return;
        }

        var editor = wp.data.select('core/editor');
        if (!editor || !editor.getEditedPostAttribute) {
            return;
        }

        var metabox = document.getElementById('tmw-slot-banner');
        if (!metabox) {
            return;
        }

        var enabled = metabox.querySelector('[name="tmw_slot_enabled"]');
        var modes = metabox.querySelectorAll('[name="tmw_slot_mode"]');
        var shortcode = metabox.querySelector('[name="tmw_slot_shortcode"]');
        var meta = editor.getEditedPostAttribute('meta') || {};
        var owns = Object.prototype.hasOwnProperty;

        if (enabled && owns.call(meta, '_tmw_slot_enabled')) {
            enabled.checked = meta._tmw_slot_enabled === '1';
        }

        if (owns.call(meta, '_tmw_slot_mode') &&
            (meta._tmw_slot_mode === 'widget' || meta._tmw_slot_mode === 'shortcode')) {
            modes.forEach(function (radio) {
                radio.checked = radio.value === meta._tmw_slot_mode;
            });
        }

        if (shortcode && owns.call(meta, '_tmw_slot_shortcode') &&
            typeof meta._tmw_slot_shortcode === 'string') {
            shortcode.value = meta._tmw_slot_shortcode;
        }

        function syncMeta() {
            var selectedMode = metabox.querySelector('[name="tmw_slot_mode"]:checked');

            wp.data.dispatch('core/editor').editPost({
                meta: {
                    _tmw_slot_enabled: enabled && enabled.checked ? '1' : '',
                    _tmw_slot_mode: selectedMode ? selectedMode.value : 'shortcode',
                    _tmw_slot_shortcode: shortcode ? shortcode.value : ''
                }
            });
        }

        if (enabled) {
            enabled.addEventListener('change', syncMeta);
        }
        modes.forEach(function (radio) {
            radio.addEventListener('change', syncMeta);
        });
        if (shortcode) {
            shortcode.addEventListener('input', syncMeta);
            shortcode.addEventListener('change', syncMeta);
        }
    }());
    </script>
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
