<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_rm_sanitize_post_types')) {
    function tmw_rm_sanitize_post_types($post_types)
    {
        $post_types = is_array($post_types) ? $post_types : [];
        $post_types = array_values(array_filter($post_types, 'is_string'));

        $valid_post_types = [];
        $removed_post_types = [];
        foreach ($post_types as $post_type) {
            if (post_type_exists($post_type)) {
                $valid_post_types[] = $post_type;
            } else {
                $removed_post_types[] = $post_type;
            }
        }

        $valid_post_types = array_values(array_unique($valid_post_types));

        if (!empty($removed_post_types) && defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
            static $did_log = false;
            if (!$did_log) {
                $did_log = true;
                $removed_post_types = array_values(array_unique($removed_post_types));
                error_log(sprintf('[TMW-RM-FIX] removed_invalid_post_types=%s', implode(',', $removed_post_types)));
            }
        }

        return $valid_post_types;
    }
}

add_filter('rank_math/excluded_post_types', function ($excluded_post_types) {
    return tmw_rm_sanitize_post_types($excluded_post_types);
}, 5);

add_filter('rank_math/post_types', function ($post_types) {
    return tmw_rm_sanitize_post_types($post_types);
});

add_filter('rank_math/metabox/post_types', function ($post_types) {
    return tmw_rm_sanitize_post_types($post_types);
});

add_filter('rank_math/rest/enabled_post_types', function ($post_types) {
    return tmw_rm_sanitize_post_types($post_types);
});

add_filter('rank_math/excluded_post_types', function ($excluded_post_types) {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return $excluded_post_types;
    }

    static $did_log = false;
    if ($did_log) {
        return $excluded_post_types;
    }
    $did_log = true;

    $accessible_post_types = null;
    if (class_exists('\\RankMath\\Helper') && method_exists('\\RankMath\\Helper', 'get_accessible_post_types')) {
        $accessible_post_types = \RankMath\Helper::get_accessible_post_types();
    }

    if (empty($accessible_post_types) || !is_array($accessible_post_types)) {
        $all_post_types = get_post_types([], 'names');
        $excluded_post_types = is_array($excluded_post_types) ? $excluded_post_types : [];
        $accessible_post_types = array_values(array_diff($all_post_types, $excluded_post_types));
    }

    $accessible_post_types = array_values(array_unique(array_filter($accessible_post_types, 'is_string')));

    if (!empty($accessible_post_types)) {
        error_log(sprintf('[TMW-RM-AUDIT] accessible_post_types=%s', implode(',', $accessible_post_types)));
        foreach ($accessible_post_types as $post_type) {
            if (!get_post_type_object($post_type)) {
                error_log(sprintf('[TMW-RM-AUDIT] INVALID post_type=%s', $post_type));
            }
        }
    }

    return tmw_rm_sanitize_post_types($excluded_post_types);
}, 9999);
