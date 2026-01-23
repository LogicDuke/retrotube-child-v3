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
