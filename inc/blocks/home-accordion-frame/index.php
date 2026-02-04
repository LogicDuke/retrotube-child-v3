<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_render_home_accordion_frame_block')) {
    function tmw_render_home_accordion_frame_block(array $attributes, string $content): string {
        if (!function_exists('tmw_render_home_accordion_frame')) {
            return '';
        }

        $title = isset($attributes['title']) ? (string) $attributes['title'] : '';
        $open_by_default = isset($attributes['openByDefault'])
            ? (bool) $attributes['openByDefault']
            : false;

        $content_html = $content;

        return tmw_render_home_accordion_frame($title, $content_html, $open_by_default);
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
