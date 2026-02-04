<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_render_home_accordion_frame_block')) {
    function tmw_render_home_accordion_frame_block(array $attributes, string $content, $block = null): string {
        if (!function_exists('tmw_render_home_accordion_frame')) {
            return '';
        }

        $title = isset($attributes['title']) ? (string) $attributes['title'] : '';
        $open_by_default = isset($attributes['openByDefault'])
            ? (bool) $attributes['openByDefault']
            : false;

        $inner_html = (string) $content;
        if (strpos($inner_html, '<!-- wp:') !== false) {
            $inner_html = do_blocks($inner_html);
        }
        $inner_html = do_shortcode($inner_html);
        $inner_html = trim($inner_html);

        return tmw_render_home_accordion_frame($title, $inner_html, $open_by_default);
    }
}

if (!function_exists('tmw_register_home_accordion_frame_block')) {
    function tmw_register_home_accordion_frame_block(): void {
        register_block_type(
            __DIR__,
            [
                'render_callback' => 'tmw_render_home_accordion_frame_block',
            ]
        );
    }
}

add_action('init', 'tmw_register_home_accordion_frame_block');
