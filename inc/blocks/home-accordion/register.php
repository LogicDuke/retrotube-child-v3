<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_render_home_accordion_block')) {
    function tmw_render_home_accordion_block(array $attributes, string $content): string {
        $title = isset($attributes['title']) ? trim((string) $attributes['title']) : '';
        if ($title === '') {
            return '';
        }

        $content_html = $content !== '' ? do_shortcode($content) : '';

        $has_heading = (bool) preg_match('/<h[1-6][^>]*>/i', $content_html);
        if (!$has_heading) {
            $auto_level = 'h2';
            $auto_heading_text = $title . ' Webcam Directory';
            $auto_heading_html = sprintf(
                '<%1$s class="tmw-accordion-auto-h1 tmw-accordion-auto-h2">%2$s</%1$s>',
                esc_attr($auto_level),
                esc_html($auto_heading_text)
            );

            $paragraph_close = stripos($content_html, '</p>');
            if ($paragraph_close !== false) {
                $insert_at = $paragraph_close + strlen('</p>');
                $content_html = substr($content_html, 0, $insert_at)
                    . $auto_heading_html
                    . substr($content_html, $insert_at);
            } else {
                $content_html = $auto_heading_html . $content_html;
            }
        }

        if (function_exists('tmw_sanitize_accordion_html')) {
            $content_html = tmw_sanitize_accordion_html($content_html);
        }

        if (!function_exists('tmw_render_accordion')) {
            return '';
        }

        $accordion_html = tmw_render_accordion([
            'content_html' => $content_html,
            'lines'        => 1,
            'collapsed'    => true,
            'id_base'      => 'tmw-home-accordion-',
        ]);

        if ($accordion_html === '') {
            return '';
        }

        static $home_accordion_count = 0;
        $is_home_context = (is_front_page() || (is_home() && get_option('show_on_front') === 'posts'));
        if ($is_home_context) {
            $home_accordion_count++;
            $heading_level = ($home_accordion_count === 1) ? 'h1' : 'h2';
        } else {
            $heading_level = 'h2';
        }

        return sprintf(
            '<%1$s class="widget-title">%2$s</%1$s>%3$s',
            $heading_level,
            esc_html($title),
            $accordion_html
        );
    }
}

if (!function_exists('tmw_register_home_accordion_block')) {
    function tmw_register_home_accordion_block(): void {
        $block_path = __DIR__ . '/block.json';
        if (!file_exists($block_path)) {
            return;
        }

        if (is_admin()) {
            wp_register_script(
                'tmw-home-accordion-block-editor',
                get_stylesheet_directory_uri() . '/inc/blocks/home-accordion/editor.js',
                [ 'wp-blocks', 'wp-element', 'wp-block-editor' ],
                filemtime(__DIR__ . '/editor.js')
            );
        }

        register_block_type(
            $block_path,
            [
                'editor_script'  => 'tmw-home-accordion-block-editor',
                'render_callback' => 'tmw_render_home_accordion_block',
            ]
        );
    }
}

add_action('init', 'tmw_register_home_accordion_block');
