<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_hub_link_log_once')) {
    function tmw_hub_link_log_once(string $key, string $message): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) { return; }
        static $seen = [];
        if (isset($seen[$key])) { return; }
        $seen[$key] = true;
        error_log('[TMW-HUB-LINK] ' . $message);
    }
}

add_filter('term_link', function ($termlink, $term, $taxonomy) {
    if (is_admin() || wp_doing_ajax()) {
        return $termlink;
    }

    // Only rewrite links on single video/model pages.
    if (!(is_singular('video') || is_singular('model'))) {
        return $termlink;
    }

    if ($taxonomy !== 'post_tag' || !$term instanceof WP_Term) {
        return $termlink;
    }

    // Hub rule: tag slug matches an existing category slug.
    $cat = get_term_by('slug', $term->slug, 'category');
    if (!$cat instanceof WP_Term) {
        return $termlink;
    }

    $cat_link = get_category_link((int) $cat->term_id);
    if (!is_string($cat_link) || $cat_link === '') {
        return $termlink;
    }

    tmw_hub_link_log_once(
        'hub_' . $term->term_id,
        'Rewrote hub tag link "' . $term->slug . '" to category URL on singular.'
    );

    return $cat_link;
}, 20, 3);
