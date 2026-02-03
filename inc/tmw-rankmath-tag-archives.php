<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_rm_tag_archives_log_once')) {
    function tmw_rm_tag_archives_log_once(string $key, string $message): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        static $seen = [];
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        if (defined('WP_DEBUG') && WP_DEBUG) { error_log($message); }
    }
}

/**
 * Force tag archives to be noindex,follow (but keep links crawlable).
 * This intentionally targets ONLY WP tags: is_tag() == post_tag archive.
 */
add_filter('rank_math/frontend/robots', function ($robots) {
    if (!is_tag()) {
        return $robots;
    }

    // Rank Math sometimes uses associative robots arrays in integrations.
    if (is_array($robots) && (isset($robots['index']) || isset($robots['follow']))) {
        $robots['index']  = 'noindex';
        $robots['follow'] = 'follow';

        tmw_rm_tag_archives_log_once(
            'rm_tag_robots_assoc',
            '[TMW-RM-TAG] Applied robots noindex,follow to tag archive (assoc array).'
        );
        return $robots;
    }

    tmw_rm_tag_archives_log_once(
        'rm_tag_robots_list',
        '[TMW-RM-TAG] Applied robots noindex,follow to tag archive.'
    );
    return ['noindex', 'follow'];
}, 50);

/**
 * Remove tag archives from Rank Math sitemap entirely.
 */
add_filter('rank_math/sitemap/exclude_taxonomy', function ($exclude, $taxonomy) {
    if ($taxonomy === 'post_tag') {
        tmw_rm_tag_archives_log_once(
            'rm_tag_sitemap_exclude',
            '[TMW-RM-TAG] Excluded taxonomy=post_tag from Rank Math sitemap.'
        );
        return true;
    }

    return $exclude;
}, 20, 2);
