<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_rm_strip_invalid_rankmath_post_types')) {
    function tmw_rm_strip_invalid_rankmath_post_types($post_types)
    {
        if (is_admin() || wp_doing_ajax()) {
            return $post_types;
        }

        if (!is_array($post_types)) {
            return $post_types;
        }

        $sanitized = [];
        $removed_count = 0;

        foreach ($post_types as $post_type) {
            if ($post_type === 'category_page') {
                $removed_count++;
                continue;
            }
            $sanitized[] = $post_type;
        }

        if ($removed_count > 0) {
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[TMW-RM-FIX] excluded_post_types_removed=category_page removed_count=%d uri=%s',
                $removed_count,
                $uri
            ));
            }
        }

        $should_audit = current_user_can('manage_options')
            || (isset($_GET['tmw_rm_sanitize']) && $_GET['tmw_rm_sanitize'] === '1');

        if ($should_audit) {
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[TMW-RM-AUDIT] excluded_post_types_final=%s uri=%s',
                implode(',', $sanitized),
                $uri
            ));
            }
        }

        return $removed_count > 0 ? $sanitized : $post_types;
    }
}

add_filter('rank_math/excluded_post_types', 'tmw_rm_strip_invalid_rankmath_post_types', 10000);
