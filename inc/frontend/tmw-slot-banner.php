<?php
/**
 * TMW Slot Banner Frontend Renderer - Bulletproof Version
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register widget area
add_action('widgets_init', function () {
    if (!tmw_should_boot_heavy_logic()) {
        return;
    }

    register_sidebar([
        'id'            => 'tmw-model-slot-banner-global',
        'name'          => __('Model Page – Slot Banner (Global)', 'retrotube-child'),
        'description'   => __('Fallback slot banner for model pages.', 'retrotube-child'),
        'before_widget' => '',
        'after_widget'  => '',
        'before_title'  => '',
        'after_title'   => '',
    ]);
});

/**
 * Write a private renderer diagnostic when WordPress debugging is enabled.
 */
function tmw_slot_banner_debug(array $context): void
{
    if (!defined('WP_DEBUG') || WP_DEBUG !== true) {
        return;
    }

    error_log('[TMW-SLOT-BANNER] ' . wp_json_encode($context)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/**
 * Extract the first shortcode tag from a saved shortcode string.
 */
function tmw_slot_banner_shortcode_tag(string $shortcode): string
{
    if (preg_match('/' . get_shortcode_regex() . '/s', $shortcode, $matches) && isset($matches[2])) {
        return (string) $matches[2];
    }

    return '';
}

/**
 * Render the configured model slot banner.
 */
function tmw_render_model_slot_banner_zone(int $post_id): string
{
    $enabled   = get_post_meta($post_id, '_tmw_slot_enabled', true);
    $mode_raw  = get_post_meta($post_id, '_tmw_slot_mode', true);
    $shortcode = trim((string) get_post_meta($post_id, '_tmw_slot_shortcode', true));
    $fallback_shortcode = $shortcode !== '' ? $shortcode : '[tmw_slot_machine]';
    $shortcode_tag = tmw_slot_banner_shortcode_tag($fallback_shortcode);
    $context   = [
        'post_id'                  => $post_id,
        'post_type'                => get_post_type($post_id),
        '_tmw_slot_enabled_raw'    => $enabled,
        '_tmw_slot_mode_raw'       => $mode_raw,
        '_tmw_slot_shortcode_raw'  => $shortcode,
        'shortcode_tag'            => $shortcode_tag,
        'shortcode_registered'     => $shortcode_tag !== '' && shortcode_exists($shortcode_tag),
        'do_shortcode_output_length' => 0,
        'return_reason'            => '',
    ];

    if ($enabled !== '1') {
        $context['return_reason'] = 'slot_not_enabled';
        tmw_slot_banner_debug($context);
        return '';
    }

    $mode = $mode_raw;
    if (!in_array($mode, ['widget', 'shortcode'], true)) {
        $mode = 'shortcode';
    }

    $out = '';

    if ($mode === 'widget') {
        $widget_output = '';
        if (is_active_sidebar('tmw-model-slot-banner-global')) {
            ob_start();
            dynamic_sidebar('tmw-model-slot-banner-global');
            $widget_output = ob_get_clean();
        }

        $widget_output_clean = trim((string) $widget_output);
        if ($widget_output_clean !== '') {
            $out = (string) $widget_output;
        } else {
            $context['return_reason'] = 'widget_empty_using_shortcode_fallback';
        }
    }

    if ($out === '') {
        $tag = $context['shortcode_tag'];
        if ($tag === '') {
            $context['return_reason'] = 'shortcode_tag_not_found';
            tmw_slot_banner_debug($context);
            return '';
        }
        if (!$context['shortcode_registered']) {
            $context['return_reason'] = 'shortcode_not_registered';
            tmw_slot_banner_debug($context);
            return '';
        }

        $out = trim(do_shortcode($fallback_shortcode));
        $context['do_shortcode_output_length'] = strlen($out);
    }

    if ($out === '') {
        $context['return_reason'] = 'shortcode_output_empty';
        tmw_slot_banner_debug($context);
        return '';
    }

    $context['return_reason'] = 'rendered';
    $context['output_source'] = $mode === 'widget' && $context['do_shortcode_output_length'] === 0 ? 'widget' : 'shortcode';
    tmw_slot_banner_debug($context);

    return '<div class="tmw-slot-banner-zone"><div class="tmw-slot-banner">' . $out . '</div></div>';
}

// Backwards compatibility alias
function tmw_render_model_slot_banner(int $post_id): string
{
    return tmw_render_model_slot_banner_zone($post_id);
}

// Helper functions
function tmw_model_slot_is_enabled(int $post_id): bool
{
    return get_post_meta($post_id, '_tmw_slot_enabled', true) === '1';
}

function tmw_model_slot_get_mode(int $post_id): string
{
    $mode = get_post_meta($post_id, '_tmw_slot_mode', true);
    return in_array($mode, ['widget', 'shortcode']) ? $mode : 'shortcode';
}

function tmw_model_slot_get_shortcode(int $post_id): string
{
    return trim(get_post_meta($post_id, '_tmw_slot_shortcode', true));
}
