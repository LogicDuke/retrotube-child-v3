<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_hub_redirect_log_once')) {
    function tmw_hub_redirect_log_once(string $key, string $message): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) { return; }
        static $seen = [];
        if (isset($seen[$key])) { return; }
        $seen[$key] = true;
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-HUB-REDIRECT] ' . $message); }
    }
}

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    if (!is_tag()) {
        return;
    }

    $tag = get_queried_object();
    if (!$tag instanceof WP_Term || $tag->taxonomy !== 'post_tag') {
        return;
    }

    // Hub rule: tag slug matches an existing category slug.
    $cat = get_term_by('slug', $tag->slug, 'category');
    if (!$cat instanceof WP_Term) {
        return; // Not a hub tag; keep tag archive.
    }

    $base = get_category_link((int) $cat->term_id);
    if (!is_string($base) || $base === '') {
        return;
    }

    $paged = max(1, (int) get_query_var('paged'));
    $target = $base;

    // Preserve pagination: /page/2/
    if ($paged > 1) {
        $target = trailingslashit($base) . user_trailingslashit('page/' . $paged . '/', 'paged');
    }

    // Safety: prevent redirect loops if something odd happens.
    $current = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    if ($current === $target) {
        return;
    }

    tmw_hub_redirect_log_once(
        'hub_' . $tag->term_id . '_p' . $paged,
        'Redirect tag hub /tag/' . $tag->slug . '/ (tag_id=' . $tag->term_id . ') to ' . $target
    );

    wp_safe_redirect($target, 301);
    exit;
}, 10);
