<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_home_accordion_resolve_heading_level')) {
    function tmw_home_accordion_resolve_heading_level(string $mode = 'auto'): string {
        static $home_h1_used = false;

        if ($mode === 'h2') {
            return 'h2';
        }

        if (is_front_page() && !$home_h1_used) {
            $home_h1_used = true;
            return 'h1';
        }

        return 'h2';
    }
}

if (!function_exists('tmw_render_home_accordion_block_v2')) {
    function tmw_render_home_accordion_block_v2(array $attributes, string $content): string {
        $title = isset($attributes['title']) ? trim((string) $attributes['title']) : '';
        if ($title === '') {
            return '';
        }

        $heading_mode = isset($attributes['headingLevel']) ? (string) $attributes['headingLevel'] : 'auto';
        $heading_mode = $heading_mode === 'h2' ? 'h2' : 'auto';
        $heading_level = tmw_home_accordion_resolve_heading_level($heading_mode);

        $lines = isset($attributes['lines']) ? (int) $attributes['lines'] : 1;
        $lines = max(1, $lines);

        $content_html = $content !== '' ? do_shortcode($content) : '';
        $content_html = preg_replace('/<\s*h1\b/i', '<h2', $content_html);
        $content_html = preg_replace('/<\s*\/\s*h1\s*>/i', '</h2>', $content_html);

        if (function_exists('tmw_sanitize_accordion_html')) {
            $content_html = tmw_sanitize_accordion_html((string) $content_html);
        }

        if (!function_exists('tmw_render_accordion')) {
            return '';
        }

        $accordion_html = tmw_render_accordion([
            'content_html' => (string) $content_html,
            'lines'        => $lines,
            'collapsed'    => true,
            'id_base'      => 'tmw-home-accordion-',
        ]);

        if ($accordion_html === '') {
            return '';
        }

        return sprintf(
            '<%1$s class="widget-title">%2$s</%1$s>%3$s',
            $heading_level,
            esc_html($title),
            $accordion_html
        );
    }
}

if (!function_exists('tmw_register_home_accordion_block_v2')) {
    function tmw_register_home_accordion_block_v2(): void {
        register_block_type('tmw/home-accordion', [
            'api_version'     => 2,
            'render_callback' => 'tmw_render_home_accordion_block_v2',
            'attributes'      => [
                'title' => [
                    'type'    => 'string',
                    'default' => '',
                ],
                'headingLevel' => [
                    'type'    => 'string',
                    'default' => 'auto',
                ],
                'lines' => [
                    'type'    => 'number',
                    'default' => 1,
                ],
            ],
            'supports'        => [
                'html' => false,
            ],
        ]);
    }
}
add_action('init', 'tmw_register_home_accordion_block_v2');

if (!function_exists('tmw_enqueue_home_accordion_block_editor_assets')) {
    function tmw_enqueue_home_accordion_block_editor_assets(): void {
        wp_enqueue_script(
            'tmw-home-accordion-block-editor-v2',
            get_stylesheet_directory_uri() . '/js/tmw-home-accordion-block.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n'],
            defined('TMW_CHILD_VERSION') ? TMW_CHILD_VERSION : null,
            true
        );
    }
}
add_action('enqueue_block_editor_assets', 'tmw_enqueue_home_accordion_block_editor_assets');
