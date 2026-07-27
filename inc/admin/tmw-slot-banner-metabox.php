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
    <?php if (current_user_can('manage_options')) : ?>
        <hr />
        <p><strong><?php esc_html_e('Temporary database diagnostic', 'retrotube-child'); ?></strong></p>
        <dl style="margin:0; overflow-wrap:anywhere;">
            <dt><code>_tmw_slot_enabled</code></dt>
            <dd><code><?php echo esc_html(var_export(get_post_meta($post->ID, '_tmw_slot_enabled', true), true)); ?></code></dd>
            <dt><code>_tmw_slot_mode</code></dt>
            <dd><code><?php echo esc_html(var_export(get_post_meta($post->ID, '_tmw_slot_mode', true), true)); ?></code></dd>
            <dt><code>_tmw_slot_shortcode</code></dt>
            <dd><code><?php echo esc_html(var_export(get_post_meta($post->ID, '_tmw_slot_shortcode', true), true)); ?></code></dd>
        </dl>
    <?php endif; ?>
    <script>
    (function () {
        var root = window.parent && window.parent !== window ? window.parent : window;
        var fields = document.querySelectorAll('[name="tmw_slot_enabled"], [name="tmw_slot_mode"], [name="tmw_slot_shortcode"]');
        function syncSlotMeta() {
            if (!root.wp || !root.wp.data || !root.wp.data.dispatch) { return; }
            var enabled = document.querySelector('[name="tmw_slot_enabled"]');
            var mode = document.querySelector('[name="tmw_slot_mode"]:checked');
            var shortcode = document.querySelector('[name="tmw_slot_shortcode"]');
            root.wp.data.dispatch('core/editor').editPost({ meta: {
                _tmw_slot_enabled: enabled && enabled.checked ? '1' : '',
                _tmw_slot_mode: mode ? mode.value : 'shortcode',
                _tmw_slot_shortcode: shortcode ? shortcode.value : ''
            } });
        }
        Array.prototype.forEach.call(fields, function (field) {
            field.addEventListener(field.tagName === 'TEXTAREA' ? 'input' : 'change', syncSlotMeta);
        });
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

    $enabled = isset($_POST['tmw_slot_enabled']) && wp_unslash($_POST['tmw_slot_enabled']) === '1';

    if (!$enabled) {
        delete_post_meta($post_id, '_tmw_slot_enabled');
    } else {
        update_post_meta($post_id, '_tmw_slot_enabled', '1');
    }

    if (isset($_POST['tmw_slot_mode'])) {
        $mode = sanitize_text_field(wp_unslash($_POST['tmw_slot_mode']));
        if (!in_array($mode, ['widget', 'shortcode'], true)) {
            $mode = 'shortcode';
        }
        update_post_meta($post_id, '_tmw_slot_mode', $mode);
    }

    if (isset($_POST['tmw_slot_shortcode'])) {
        $shortcode = trim(sanitize_textarea_field(wp_unslash($_POST['tmw_slot_shortcode'])));
        update_post_meta($post_id, '_tmw_slot_shortcode', $shortcode);
    }
}, 10, 1);
