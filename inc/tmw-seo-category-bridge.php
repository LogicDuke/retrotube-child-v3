<?php
/**
 * Rank Math SEO bridge for category archives.
 *
 * @package retrotube-child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_seo_category_bridge_log_once')) {
    function tmw_seo_category_bridge_log_once(string $tag, string $message): void {
        static $logged = [];

        $key = $tag . '|' . $message;
        if (!empty($logged[$key])) {
            return;
        }

        $logged[$key] = true;
        error_log($tag . ' ' . $message);
    }
}

if (!function_exists('tmw_seo_category_bridge_get_term')) {
    function tmw_seo_category_bridge_get_term(): ?WP_Term {
        if (!is_category()) {
            return null;
        }

        $term = get_queried_object();
        if (!$term instanceof WP_Term) {
            return null;
        }

        return $term;
    }
}

if (!function_exists('tmw_seo_category_bridge_get_category_page_post')) {
    function tmw_seo_category_bridge_get_category_page_post(): ?WP_Post {
        static $cache = [];

        $term = tmw_seo_category_bridge_get_term();
        if (!$term) {
            return null;
        }

        if (array_key_exists($term->term_id, $cache)) {
            return $cache[$term->term_id];
        }

        $post = null;
        if (function_exists('tmw_get_category_page_post')) {
            $post = tmw_get_category_page_post($term);
        } else {
            $post_type = defined('TMW_CATEGORY_PAGE_CPT') ? TMW_CATEGORY_PAGE_CPT : 'tmw_category_page';
            $posts = get_posts([
                'post_type'      => $post_type,
                'posts_per_page' => 1,
                'post_status'    => ['publish', 'draft', 'pending', 'private'],
                'meta_query'     => [
                    [
                        'key'   => '_tmw_linked_term_id',
                        'value' => $term->term_id,
                    ],
                    [
                        'key'   => '_tmw_linked_taxonomy',
                        'value' => 'category',
                    ],
                ],
            ]);

            if (!empty($posts)) {
                $post = $posts[0];
            }
        }

        if ($post instanceof WP_Post) {
            tmw_seo_category_bridge_log_once(
                '[TMW-SEO-CAT-BRIDGE]',
                'Resolved category term ' . $term->term_id . ' to post ' . $post->ID . '.'
            );
            $cache[$term->term_id] = $post;
            return $post;
        }

        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-FALLBACK]',
            'No category page post found for term ' . $term->term_id . '.'
        );
        $cache[$term->term_id] = null;
        return null;
    }
}

if (!function_exists('tmw_seo_category_bridge_replace_vars')) {
    function tmw_seo_category_bridge_replace_vars(string $value, int $post_id): string {
        if ($value === '') {
            return $value;
        }

        if (function_exists('rank_math_replace_vars')) {
            return rank_math_replace_vars($value, $post_id);
        }

        if (function_exists('rank_math')) {
            $rank_math = rank_math();
            if (is_object($rank_math) && isset($rank_math->variables) && method_exists($rank_math->variables, 'replace')) {
                return $rank_math->variables->replace($value, $post_id);
            }
        }

        return $value;
    }
}

if (!function_exists('tmw_seo_category_bridge_get_term_link')) {
    function tmw_seo_category_bridge_get_term_link(): ?string {
        $term = tmw_seo_category_bridge_get_term();
        if (!$term) {
            return null;
        }

        $link = get_term_link($term);
        if (is_wp_error($link)) {
            return null;
        }

        return $link;
    }
}

if (!function_exists('tmw_seo_category_bridge_get_schema')) {
    function tmw_seo_category_bridge_get_schema(int $post_id): ?array {
        if (class_exists('\RankMath\Frontend\JsonLD')) {
            $jsonld = \RankMath\Frontend\JsonLD::get();
            if (is_object($jsonld) && method_exists($jsonld, 'get_json_ld')) {
                $schema = $jsonld->get_json_ld($post_id);
                if (is_array($schema) && !empty($schema)) {
                    return $schema;
                }
            }
        }

        $schema = get_post_meta($post_id, 'rank_math_schema', true);
        if (is_string($schema) && $schema !== '') {
            $decoded = json_decode($schema, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $schema = $decoded;
            }
        }

        if (is_array($schema) && !empty($schema)) {
            return $schema;
        }

        return null;
    }
}

add_filter('rank_math/frontend/title', function ($title) {
    if (!is_category()) {
        return $title;
    }

    $post = tmw_seo_category_bridge_get_category_page_post();
    if (!$post instanceof WP_Post) {
        return $title;
    }

    $meta = (string) get_post_meta($post->ID, 'rank_math_title', true);
    if ($meta === '') {
        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-FALLBACK]',
            'Rank Math title empty for post ' . $post->ID . '.'
        );
        return $title;
    }

    $meta = tmw_seo_category_bridge_replace_vars($meta, $post->ID);
    tmw_seo_category_bridge_log_once(
        '[TMW-SEO-CAT-META]',
        'Applied Rank Math title for post ' . $post->ID . '.'
    );
    return $meta;
}, 20);

add_filter('rank_math/frontend/description', function ($description) {
    if (!is_category()) {
        return $description;
    }

    $post = tmw_seo_category_bridge_get_category_page_post();
    if (!$post instanceof WP_Post) {
        return $description;
    }

    $meta = (string) get_post_meta($post->ID, 'rank_math_description', true);
    if ($meta === '') {
        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-FALLBACK]',
            'Rank Math description empty for post ' . $post->ID . '.'
        );
        return $description;
    }

    $meta = tmw_seo_category_bridge_replace_vars($meta, $post->ID);
    tmw_seo_category_bridge_log_once(
        '[TMW-SEO-CAT-META]',
        'Applied Rank Math description for post ' . $post->ID . '.'
    );
    return $meta;
}, 20);

add_filter('rank_math/frontend/canonical', function ($canonical) {
    if (!is_category()) {
        return $canonical;
    }

    $post = tmw_seo_category_bridge_get_category_page_post();
    if (!$post instanceof WP_Post) {
        return $canonical;
    }

    $term_link = tmw_seo_category_bridge_get_term_link();
    if (!$term_link) {
        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-FALLBACK]',
            'Unable to resolve term link for canonical.'
        );
        return $canonical;
    }

    $custom = (string) get_post_meta($post->ID, 'rank_math_canonical_url', true);
    $canonical_url = $custom !== '' ? $custom : $term_link;

    tmw_seo_category_bridge_log_once(
        '[TMW-SEO-CAT-META]',
        'Applied canonical URL for post ' . $post->ID . '.'
    );
    return $canonical_url;
}, 20);

add_filter('rank_math/frontend/robots', function ($robots) {
    if (!is_category()) {
        return $robots;
    }

    $post = tmw_seo_category_bridge_get_category_page_post();
    if (!$post instanceof WP_Post) {
        return $robots;
    }

    $meta = get_post_meta($post->ID, 'rank_math_robots', true);
    if (empty($meta)) {
        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-FALLBACK]',
            'Rank Math robots empty for post ' . $post->ID . '.'
        );
        return $robots;
    }

    if (is_string($meta)) {
        $meta = array_filter(array_map('trim', explode(',', $meta)));
    }

    if (empty($meta)) {
        return $robots;
    }

    tmw_seo_category_bridge_log_once(
        '[TMW-SEO-CAT-META]',
        'Applied robots rules for post ' . $post->ID . '.'
    );
    return $meta;
}, 20);

add_filter('rank_math/opengraph/url', function ($url) {
    if (!is_category()) {
        return $url;
    }

    $post = tmw_seo_category_bridge_get_category_page_post();
    if (!$post instanceof WP_Post) {
        return $url;
    }

    $term_link = tmw_seo_category_bridge_get_term_link();
    if (!$term_link) {
        return $url;
    }

    tmw_seo_category_bridge_log_once(
        '[TMW-SEO-CAT-META]',
        'Applied opengraph URL for post ' . $post->ID . '.'
    );
    return $term_link;
}, 20);

add_filter('rank_math/twitter/url', function ($url) {
    if (!is_category()) {
        return $url;
    }

    $post = tmw_seo_category_bridge_get_category_page_post();
    if (!$post instanceof WP_Post) {
        return $url;
    }

    $term_link = tmw_seo_category_bridge_get_term_link();
    if (!$term_link) {
        return $url;
    }

    tmw_seo_category_bridge_log_once(
        '[TMW-SEO-CAT-META]',
        'Applied twitter URL for post ' . $post->ID . '.'
    );
    return $term_link;
}, 20);

add_filter('rank_math/json_ld', function ($data) {
    if (!is_category()) {
        return $data;
    }

    $post = tmw_seo_category_bridge_get_category_page_post();
    if (!$post instanceof WP_Post) {
        return $data;
    }

    $schema = tmw_seo_category_bridge_get_schema($post->ID);
    if (!$schema) {
        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-FALLBACK]',
            'Rank Math schema empty for post ' . $post->ID . '.'
        );
        return $data;
    }

    tmw_seo_category_bridge_log_once(
        '[TMW-SEO-CAT-META]',
        'Applied schema for post ' . $post->ID . '.'
    );
    return $schema;
}, 20);
