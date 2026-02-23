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
        $collapsed = isset($attributes['collapsed'])
            ? (bool) $attributes['collapsed']
            : true;

        if (isset($attributes['openByDefault']) && !isset($attributes['collapsed'])) {
            $collapsed = !(bool) $attributes['openByDefault'];
        }

        $open_by_default = !$collapsed;

        $inner_html = (string) $content;
        if (trim($inner_html) === '' && $block instanceof WP_Block && !empty($block->inner_blocks)) {
            $rendered_inner_html = '';
            foreach ($block->inner_blocks as $inner_block) {
                $rendered_inner_html .= $inner_block->render();
            }
            $inner_html = $rendered_inner_html;
        }

        if (strpos($inner_html, '<!-- wp:') !== false) {
            $inner_html = do_blocks($inner_html);
        }
        $inner_html = do_shortcode($inner_html);
        $inner_html = trim($inner_html);

        return tmw_render_home_accordion_frame($title, $inner_html, $open_by_default, 'auto');
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
