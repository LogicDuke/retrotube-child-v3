<?php
/**
 * Canonical URL authority layer.
 *
 * @package retrotube-child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_canonical_log_once')) {
    function tmw_canonical_log_once(string $message): void {
        static $logged = [];

        if (!empty($logged[$message])) {
            return;
        }

        $logged[$message] = true;
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-CANONICAL] ' . $message); }
    }
}

if (!function_exists('tmw_canonical_maybe_add_paged_to_url')) {
    function tmw_canonical_maybe_add_paged_to_url(string $base_url): string {
        $paged = max(1, (int) get_query_var('paged'));

        if ($paged <= 1) {
            return $base_url;
        }

        if (!get_option('permalink_structure')) {
            return add_query_arg('paged', (string) $paged, $base_url);
        }

        return trailingslashit($base_url) . user_trailingslashit('page/' . $paged . '/', 'paged');
    }
}

if (!function_exists('tmw_canonical_get_category_term_link')) {
    function tmw_canonical_get_category_term_link(): ?string {
        if (!is_category()) {
            return null;
        }

        $term = get_queried_object();
        if (!$term instanceof WP_Term) {
            return null;
        }

        

        if (function_exists('tmw_seo_category_bridge_get_category_page_post')) {
            $post = tmw_seo_category_bridge_get_category_page_post();
            if ($post instanceof WP_Post) {
                $custom = (string) get_post_meta($post->ID, 'rank_math_canonical_url', true);
                if ($custom !== '') {
                    return $custom;
                }
            }
        }
$link = get_term_link($term);
        if (is_wp_error($link)) {
            return null;
        }

        return tmw_canonical_maybe_add_paged_to_url($link);
    }
}

if (!function_exists('tmw_canonical_get_category_page_link')) {
    function tmw_canonical_get_category_page_link(): ?string {
        $category_cpt = defined('TMW_CATEGORY_PAGE_CPT') ? TMW_CATEGORY_PAGE_CPT : 'tmw_category_page';
        if (!is_singular($category_cpt)) {
            return null;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return null;
        }

        

        $custom = (string) get_post_meta($post_id, 'rank_math_canonical_url', true);
        if ($custom !== '') {
            return $custom;
        }
if (function_exists('tmw_category_page_get_linked_term_url')) {
            $term_link = tmw_category_page_get_linked_term_url($post_id);
            if ($term_link) {
                return $term_link;
            }
        }

        $term_id = (int) get_post_meta($post_id, '_tmw_linked_term_id', true);
        $taxonomy = (string) get_post_meta($post_id, '_tmw_linked_taxonomy', true);

        if (!$term_id || $taxonomy !== 'category') {
            return null;
        }

        $term = get_term($term_id, 'category');
        if (!$term instanceof WP_Term) {
            return null;
        }

        $link = get_term_link($term);
        if (is_wp_error($link)) {
            return null;
        }

        return tmw_canonical_maybe_add_paged_to_url($link);
    }
}

if (!function_exists('tmw_canonical_get_model_term_link')) {
    function tmw_canonical_get_model_term_link(): ?string {
        if (!is_tax('models')) {
            return null;
        }

        $term = get_queried_object();
        if (!$term instanceof WP_Term) {
            return null;
        }

        $post = null;
        if (function_exists('tmw_get_model_post_for_term')) {
            $post = tmw_get_model_post_for_term($term);
        }

        if (!$post && post_type_exists('model')) {
            $post = get_page_by_path($term->slug, OBJECT, 'model');
            if (!$post) {
                $post = get_page_by_title($term->name, OBJECT, 'model');
            }
        }

        if ($post instanceof WP_Post) {
            
            $custom = (string) get_post_meta($post->ID, 'rank_math_canonical_url', true);
            if ($custom !== '') {
                return $custom;
            }
$link = get_permalink($post);
            if ($link) {
                return $link;
            }
        }

        $link = get_term_link($term);
        if (is_wp_error($link)) {
            return null;
        }

        return tmw_canonical_maybe_add_paged_to_url($link);
    }
}

if (!function_exists('tmw_canonical_get_model_post_link')) {
    function tmw_canonical_get_model_post_link(): ?string {
        if (!is_singular('model')) {
            return null;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return null;
        }

        

        $custom = (string) get_post_meta($post_id, 'rank_math_canonical_url', true);
        if ($custom !== '') {
            return $custom;
        }
$link = get_permalink($post_id);
        return $link ?: null;
    }
}

if (!function_exists('tmw_canonical_get_video_link')) {
    function tmw_canonical_get_video_link(): ?string {
        if (!is_singular('video')) {
            return null;
        }

        $post_id = get_queried_object_id();
        if (!$post_id) {
            return null;
        }

        

        $custom = (string) get_post_meta($post_id, 'rank_math_canonical_url', true);
        if ($custom !== '') {
            return $custom;
        }
$link = get_permalink($post_id);
        return $link ?: null;
    }
}

if (!function_exists('tmw_resolve_canonical_url')) {
    function tmw_resolve_canonical_url(): ?string {
        $category_page = tmw_canonical_get_category_page_link();
        if ($category_page) {
            return $category_page;
        }

        $category = tmw_canonical_get_category_term_link();
        if ($category) {
            return $category;
        }

        $model_post = tmw_canonical_get_model_post_link();
        if ($model_post) {
            return $model_post;
        }

        $model_term = tmw_canonical_get_model_term_link();
        if ($model_term) {
            return $model_term;
        }

        $video = tmw_canonical_get_video_link();
        if ($video) {
            return $video;
        }

        return null;
    }
}

if (!function_exists('tmw_canonical_normalize_for_compare')) {
    function tmw_canonical_normalize_for_compare(string $url): string {
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return '';
        }

        $host = strtolower($parts['host']);
        $path = isset($parts['path']) ? untrailingslashit($parts['path']) : '';

        return $host . $path;
    }
}

if (!function_exists('tmw_canonical_current_url')) {
    function tmw_canonical_current_url(): ?string {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $request = $_SERVER['REQUEST_URI'] ?? '';

        if ($host === '' || $request === '') {
            return null;
        }

        $scheme = is_ssl() ? 'https' : 'http';
        return esc_url_raw($scheme . '://' . $host . $request);
    }
}

add_filter('get_canonical_url', function ($canonical, $post) {
    $resolved = tmw_resolve_canonical_url();
    if (!$resolved) {
        return $canonical;
    }

    if ($canonical !== $resolved) {
        tmw_canonical_log_once('get_canonical_url resolved to ' . $resolved . '.');
    }

    return $resolved;
}, 20, 2);

add_filter('rank_math/frontend/canonical', function ($canonical) {
    $resolved = tmw_resolve_canonical_url();
    if (!$resolved) {
        return $canonical;
    }

    if ($canonical !== $resolved) {
        tmw_canonical_log_once('Rank Math canonical resolved to ' . $resolved . '.');
    }

    return $resolved;
}, 30);

add_filter('wpseo_canonical', function ($canonical) {
    $resolved = tmw_resolve_canonical_url();
    if (!$resolved) {
        return $canonical;
    }

    if ($canonical !== $resolved) {
        tmw_canonical_log_once('Yoast canonical resolved to ' . $resolved . '.');
    }

    return $resolved;
}, 20);

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || is_feed() || is_preview()) {
        return;
    }

    if (is_paged()) {
        return;
    }

    $canonical = tmw_resolve_canonical_url();
    if (!$canonical) {
        return;
    }

    $current = tmw_canonical_current_url();
    if (!$current) {
        return;
    }

    $current_key = tmw_canonical_normalize_for_compare($current);
    $canonical_key = tmw_canonical_normalize_for_compare($canonical);

    if ($current_key === '' || $canonical_key === '' || $current_key === $canonical_key) {
        return;
    }

    tmw_canonical_log_once('Redirecting to canonical ' . $canonical . '.');
    wp_safe_redirect($canonical, 301);
    exit;
}, 20);
