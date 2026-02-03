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

add_filter('rank_math/excluded_post_types', function ($excluded) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $is_rest = (defined('REST_REQUEST') && REST_REQUEST);
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        if (
            !is_admin()
            && !wp_doing_ajax()
            && !wp_doing_cron()
            && !$is_rest
            && $uri !== ''
            && strpos($uri, '/category/') === 0
        ) {
            if (is_array($excluded)) {
                $list = implode(',', $excluded);
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-RM-EXCL-AUDIT] stage=post_theme priority=10001 list=' . $list . ' uri=' . $uri); }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-RM-EXCL-AUDIT] stage=post_theme priority=10001 type=' . gettype($excluded) . ' uri=' . $uri); }
            }
        }
    }

    return $excluded;
}, 10001);
