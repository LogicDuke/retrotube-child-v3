<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

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

    error_log(sprintf('[TMW-RM-AUDIT] accessible_post_types=%s', implode(',', $accessible_post_types)));
    foreach ($accessible_post_types as $post_type) {
        if (!get_post_type_object($post_type)) {
            error_log(sprintf('[TMW-RM-AUDIT] INVALID post_type=%s', $post_type));
        }
    }

    return $excluded_post_types;
}, 9999);
