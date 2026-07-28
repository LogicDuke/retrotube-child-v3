<?php
/**
 * TMW Slot Banner Metabox
 * Works with both Classic Editor and Gutenberg Block Editor.
 * Supports all post types returned by tmw_slot_banner_post_types().
 *
 * In Gutenberg the metabox renders in the document sidebar ('side' position)
 * and an inline script synchronises its controls with the editor's REST meta
 * store so that Gutenberg's native Save persists the values.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ─── Metabox registration ────────────────────────────────────────────────────

add_action('add_meta_boxes', function () {
    $post_types = function_exists('tmw_slot_banner_post_types')
        ? tmw_slot_banner_post_types()
        : ['model'];

    foreach ($post_types as $pt) {
        add_meta_box(
            'tmw-slot-banner',
            __('Slot Banner', 'retrotube-child'),
            'tmw_render_slot_banner_metabox',
            $pt,
            'side',
            'default'
        );
    }
});

// ─── Metabox render ──────────────────────────────────────────────────────────

function tmw_render_slot_banner_metabox($post)
{
    $supported = function_exists('tmw_slot_banner_post_types')
        ? tmw_slot_banner_post_types()
        : ['model'];

    if (!$post || !in_array($post->post_type, $supported, true)) {
        return;
    }

    $enabled   = get_post_meta($post->ID, '_tmw_slot_enabled', true) === '1';
    $mode      = get_post_meta($post->ID, '_tmw_slot_mode', true);
    $shortcode = get_post_meta($post->ID, '_tmw_slot_shortcode', true);

    if (!in_array($mode, ['widget', 'shortcode'])) {
        $mode = 'shortcode';
    }
    if ($shortcode === '') {
        $shortcode = '[tmw_slot_machine]';
    }

    // Contextual label: "model page" vs "video page"
    $page_label = $post->post_type === 'video'
        ? __('Enable slot banner on this video page', 'retrotube-child')
        : __('Enable slot banner on this model page', 'retrotube-child');

    wp_nonce_field('tmw_slot_banner_save', 'tmw_slot_banner_nonce');
    ?>
    <input type="hidden" name="tmw_slot_metabox_present" value="1" />

    <p>
        <label>
            <input type="checkbox" name="tmw_slot_enabled" value="1" <?php checked($enabled); ?> />
            <?php echo esc_html($page_label); ?>
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

        var initialized = false;
        var attempts = 0;
        var maxAttempts = 50;

        function initSlotBannerMetaSync() {
            if (initialized) {
                return;
            }

            attempts += 1;

            if (!window.wp || !wp.data || !wp.data.select || !wp.data.dispatch) {
                if (attempts < maxAttempts) {
                    window.setTimeout(initSlotBannerMetaSync, 100);
                }
                return;
            }

            var editor = wp.data.select('core/editor');
            var metabox = document.getElementById('tmw-slot-banner');

            if (!editor || !editor.getEditedPostAttribute || !metabox) {
                if (attempts < maxAttempts) {
                    window.setTimeout(initSlotBannerMetaSync, 100);
                }
                return;
            }

            initialized = true;

            var enabled = metabox.querySelector('[name="tmw_slot_enabled"]');
            var modes = metabox.querySelectorAll('[name="tmw_slot_mode"]');
            var shortcode = metabox.querySelector('[name="tmw_slot_shortcode"]');
            var meta = editor.getEditedPostAttribute('meta') || {};
            var owns = Object.prototype.hasOwnProperty;

            if (enabled && owns.call(meta, '_tmw_slot_enabled')) {
                enabled.checked = String(meta._tmw_slot_enabled) === '1';
            }

            if (owns.call(meta, '_tmw_slot_mode') &&
                (meta._tmw_slot_mode === 'widget' || meta._tmw_slot_mode === 'shortcode')) {
                Array.prototype.forEach.call(modes, function (radio) {
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
            Array.prototype.forEach.call(modes, function (radio) {
                radio.addEventListener('change', syncMeta);
            });
            if (shortcode) {
                shortcode.addEventListener('input', syncMeta);
                shortcode.addEventListener('change', syncMeta);
            }
        }

        initSlotBannerMetaSync();
    }());
    </script>
    <?php
}

// ─── Classic Editor save (shared callback) ───────────────────────────────────

/**
 * Saves Slot Banner meta from the Classic Editor / Gutenberg meta-box-loader.
 *
 * @param int $post_id
 */
function tmw_slot_banner_classic_save( int $post_id ): void {
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
        } else {
            delete_post_meta($post_id, '_tmw_slot_shortcode');
        }
    }
}

// Hook the shared callback for each supported post type.
add_action('save_post_model', 'tmw_slot_banner_classic_save', 10, 1);
add_action('save_post_video', 'tmw_slot_banner_classic_save', 10, 1);
