<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_cat_mirror_log_once')) {
    function tmw_cat_mirror_log_once(string $key, string $message): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        static $seen = [];
        if (isset($seen[$key])) { return; }
        $seen[$key] = true;
        error_log('[TMW-CAT-MIRROR] ' . $message);
    }
}

/**
 * Category hub mirrors tag inventory (slug match).
 *
 * Example:
 *  - /category/cam-porn/ will list posts in category "cam-porn"
 *    OR posts tagged "cam-porn" (big inventory).
 *
 * This works even if the category has 0 assigned posts,
 * as long as the tag exists and has posts.
 */
add_action('pre_get_posts', function ($query) {
    if (is_admin() || wp_doing_ajax() || !($query instanceof WP_Query)) {
        return;
    }

    if (!$query->is_main_query() || !$query->is_category()) {
        return;
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term || $term->taxonomy !== 'category') {
        return;
    }

    // Only mirror when a tag with the same slug exists.
    $tag = get_term_by('slug', $term->slug, 'post_tag');
    if (!$tag instanceof WP_Term) {
        tmw_cat_mirror_log_once(
            'no_tag_' . $term->term_id,
            'No matching tag for category slug="' . $term->slug . '" (term_id=' . $term->term_id . '); leaving default category query.'
        );
        return;
    }

    $tax_query = [
        'relation' => 'OR',
        [
            'taxonomy' => 'category',
            'field'    => 'term_id',
            'terms'    => [(int) $term->term_id],
        ],
        [
            'taxonomy' => 'post_tag',
            'field'    => 'term_id',
            'terms'    => [(int) $tag->term_id],
        ],
    ];

    // Ensure the actual SQL uses this OR tax_query.
    $query->set('tax_query', $tax_query);
    $query->tax_query = new WP_Tax_Query($tax_query);

    tmw_cat_mirror_log_once(
        'applied_' . $term->term_id,
        'Applied OR mirror for category slug="' . $term->slug . '" (cat_term_id=' . $term->term_id . ') using tag_term_id=' . $tag->term_id . '.'
    );
}, 90);
