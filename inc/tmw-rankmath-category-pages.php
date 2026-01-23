<?php
if (!defined('ABSPATH')) { exit; }

add_filter('rank_math/post_types', function ($post_types) {
    $cpt = defined('TMW_CATEGORY_PAGE_CPT') ? TMW_CATEGORY_PAGE_CPT : 'tmw_category_page';
    if (!in_array($cpt, $post_types, true)) { $post_types[] = $cpt; }
    return $post_types;
});

add_filter('rank_math/metabox/post_types', function ($post_types) {
    $cpt = defined('TMW_CATEGORY_PAGE_CPT') ? TMW_CATEGORY_PAGE_CPT : 'tmw_category_page';
    if (!in_array($cpt, $post_types, true)) { $post_types[] = $cpt; }
    return $post_types;
});

add_filter('rank_math/rest/enabled_post_types', function ($post_types) {
    $cpt = defined('TMW_CATEGORY_PAGE_CPT') ? TMW_CATEGORY_PAGE_CPT : 'tmw_category_page';
    if (!in_array($cpt, $post_types, true)) { $post_types[] = $cpt; }
    return $post_types;
});

add_filter('rank_math/is_post_type_accessible', function ($is_accessible, $post_type) {
    $cpt = defined('TMW_CATEGORY_PAGE_CPT') ? TMW_CATEGORY_PAGE_CPT : 'tmw_category_page';
    if ($post_type === $cpt) { return true; }
    return $is_accessible;
}, 10, 2);

add_filter('rank_math/excluded_post_types', function ($post_types) {
    if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options') && is_array($post_types)) {
        static $logged = [];
        foreach ($post_types as $slug) {
            if (!is_string($slug) || post_type_exists($slug)) { continue; }
            if (isset($logged[$slug])) { continue; }
            $logged[$slug] = true;
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
            error_log(sprintf('[TMW-RM-AUDIT] invalid_post_type=%s source=rank_math/excluded_post_types uri=%s', $slug, $uri));
        }
    }
    return $post_types;
}, 999);
