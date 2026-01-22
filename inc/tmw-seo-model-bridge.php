<?php
/**
 * Rank Math SEO bridge for Model pages.
 * 
 * Mirrors tmw-seo-category-bridge.php but for the 'models' taxonomy
 * and 'model' CPT. Pulls SEO meta (title, description, schema) from
 * the model CPT post and applies it to model taxonomy archive pages.
 *
 * @package retrotube-child
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_seo_model_bridge_log_once')) {
    /**
     * Log a message once per request to avoid spam.
     *
     * @param string $tag     Log tag prefix.
     * @param string $message Log message.
     */
    function tmw_seo_model_bridge_log_once(string $tag, string $message): void {
        static $logged = [];

        $key = $tag . '|' . $message;
        if (!empty($logged[$key])) {
            return;
        }

        $logged[$key] = true;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($tag . ' ' . $message);
        }
    }
}

if (!function_exists('tmw_seo_model_bridge_get_term')) {
    /**
     * Get the current model taxonomy term if on a models archive.
     *
     * @return WP_Term|null
     */
    function tmw_seo_model_bridge_get_term(): ?WP_Term {
        if (!is_tax('models')) {
            return null;
        }

        $term = get_queried_object();
        if (!$term instanceof WP_Term) {
            return null;
        }

        return $term;
    }
}

if (!function_exists('tmw_seo_model_bridge_get_model_post')) {
    /**
     * Get the model CPT post linked to the current model taxonomy term.
     *
     * @return WP_Post|null
     */
    function tmw_seo_model_bridge_get_model_post(): ?WP_Post {
        static $cache = [];

        $term = tmw_seo_model_bridge_get_term();
        if (!$term) {
            return null;
        }

        if (array_key_exists($term->term_id, $cache)) {
            return $cache[$term->term_id];
        }

        $post = null;

        // Try the helper function first
        if (function_exists('tmw_get_model_post_for_term')) {
            $post = tmw_get_model_post_for_term($term);
        }

        // Fallback: search by slug/name
        if (!$post && post_type_exists('model')) {
            $post = get_page_by_path($term->slug, OBJECT, 'model');
            if (!$post) {
                $post = get_page_by_title($term->name, OBJECT, 'model');
            }
        }

        if ($post instanceof WP_Post && $post->post_status === 'publish') {
            tmw_seo_model_bridge_log_once(
                '[TMW-SEO-MODEL-BRIDGE]',
                'Resolved model term ' . $term->term_id . ' to post ' . $post->ID . '.'
            );
            $cache[$term->term_id] = $post;
            return $post;
        }

        tmw_seo_model_bridge_log_once(
            '[TMW-SEO-MODEL-FALLBACK]',
            'No model post found for term ' . $term->term_id . '.'
        );
        $cache[$term->term_id] = null;
        return null;
    }
}

if (!function_exists('tmw_seo_model_bridge_replace_vars')) {
    /**
     * Replace Rank Math variables in a string.
     *
     * @param string $value   The string with variables.
     * @param int    $post_id The post ID for context.
     * @return string
     */
    function tmw_seo_model_bridge_replace_vars(string $value, int $post_id): string {
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

if (!function_exists('tmw_seo_model_bridge_get_model_link')) {
    /**
     * Get the canonical URL for the model (prefers CPT permalink).
     *
     * @return string|null
     */
    function tmw_seo_model_bridge_get_model_link(): ?string {
        $post = tmw_seo_model_bridge_get_model_post();
        if ($post instanceof WP_Post) {
            $link = get_permalink($post);
            if ($link && !is_wp_error($link)) {
                return $link;
            }
        }

        // Fallback to term link
        $term = tmw_seo_model_bridge_get_term();
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

if (!function_exists('tmw_seo_model_bridge_get_schema')) {
    /**
     * Get Rank Math schema data for a post.
     *
     * @param int $post_id Post ID.
     * @return array|null
     */
    function tmw_seo_model_bridge_get_schema(int $post_id): ?array {
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

// ============================================================================
// RANK MATH FILTERS FOR MODEL TAXONOMY ARCHIVES
// ============================================================================

/**
 * Filter: SEO Title for model taxonomy archives.
 */
add_filter('rank_math/frontend/title', function ($title) {
    if (!is_tax('models')) {
        return $title;
    }

    $post = tmw_seo_model_bridge_get_model_post();
    if (!$post instanceof WP_Post) {
        return $title;
    }

    $meta = (string) get_post_meta($post->ID, 'rank_math_title', true);
    if ($meta === '') {
        tmw_seo_model_bridge_log_once(
            '[TMW-SEO-MODEL-FALLBACK]',
            'Rank Math title empty for model post ' . $post->ID . '.'
        );
        return $title;
    }

    $meta = tmw_seo_model_bridge_replace_vars($meta, $post->ID);
    tmw_seo_model_bridge_log_once(
        '[TMW-SEO-MODEL-META]',
        'Applied Rank Math title for model post ' . $post->ID . '.'
    );
    return $meta;
}, 20);

/**
 * Filter: Meta description for model taxonomy archives.
 */
add_filter('rank_math/frontend/description', function ($description) {
    if (!is_tax('models')) {
        return $description;
    }

    $post = tmw_seo_model_bridge_get_model_post();
    if (!$post instanceof WP_Post) {
        return $description;
    }

    $meta = (string) get_post_meta($post->ID, 'rank_math_description', true);
    if ($meta === '') {
        tmw_seo_model_bridge_log_once(
            '[TMW-SEO-MODEL-FALLBACK]',
            'Rank Math description empty for model post ' . $post->ID . '.'
        );
        return $description;
    }

    $meta = tmw_seo_model_bridge_replace_vars($meta, $post->ID);
    tmw_seo_model_bridge_log_once(
        '[TMW-SEO-MODEL-META]',
        'Applied Rank Math description for model post ' . $post->ID . '.'
    );
    return $meta;
}, 20);

/**
 * Filter: Canonical URL for model taxonomy archives.
 */
add_filter('rank_math/frontend/canonical', function ($canonical) {
    if (!is_tax('models')) {
        return $canonical;
    }

    $model_link = tmw_seo_model_bridge_get_model_link();
    if (!$model_link) {
        tmw_seo_model_bridge_log_once(
            '[TMW-SEO-MODEL-FALLBACK]',
            'Unable to resolve model link for canonical.'
        );
        return $canonical;
    }

    $post = tmw_seo_model_bridge_get_model_post();
    if ($post instanceof WP_Post) {
        $custom = (string) get_post_meta($post->ID, 'rank_math_canonical_url', true);
        if ($custom !== '') {
            $model_link = $custom;
        }
    }

    tmw_seo_model_bridge_log_once(
        '[TMW-SEO-MODEL-META]',
        'Applied canonical URL: ' . $model_link . '.'
    );
    return $model_link;
}, 20);

/**
 * Filter: Robots directives for model taxonomy archives.
 */
add_filter('rank_math/frontend/robots', function ($robots) {
    if (!is_tax('models')) {
        return $robots;
    }

    $post = tmw_seo_model_bridge_get_model_post();
    if (!$post instanceof WP_Post) {
        return $robots;
    }

    $meta = get_post_meta($post->ID, 'rank_math_robots', true);
    if (empty($meta)) {
        tmw_seo_model_bridge_log_once(
            '[TMW-SEO-MODEL-FALLBACK]',
            'Rank Math robots empty for model post ' . $post->ID . '.'
        );
        return $robots;
    }

    if (is_string($meta)) {
        $meta = array_filter(array_map('trim', explode(',', $meta)));
    }

    if (empty($meta)) {
        return $robots;
    }

    tmw_seo_model_bridge_log_once(
        '[TMW-SEO-MODEL-META]',
        'Applied robots rules for model post ' . $post->ID . '.'
    );
    return $meta;
}, 20);

/**
 * Filter: OpenGraph URL for model taxonomy archives.
 */
add_filter('rank_math/opengraph/url', function ($url) {
    if (!is_tax('models')) {
        return $url;
    }

    $model_link = tmw_seo_model_bridge_get_model_link();
    if (!$model_link) {
        return $url;
    }

    tmw_seo_model_bridge_log_once(
        '[TMW-SEO-MODEL-META]',
        'Applied OpenGraph URL: ' . $model_link . '.'
    );
    return $model_link;
}, 20);

/**
 * Filter: Twitter URL for model taxonomy archives.
 */
add_filter('rank_math/twitter/url', function ($url) {
    if (!is_tax('models')) {
        return $url;
    }

    $model_link = tmw_seo_model_bridge_get_model_link();
    if (!$model_link) {
        return $url;
    }

    tmw_seo_model_bridge_log_once(
        '[TMW-SEO-MODEL-META]',
        'Applied Twitter URL: ' . $model_link . '.'
    );
    return $model_link;
}, 20);

/**
 * Filter: JSON-LD Schema for model taxonomy archives.
 */
add_filter('rank_math/json_ld', function ($data) {
    if (!is_tax('models')) {
        return $data;
    }

    $post = tmw_seo_model_bridge_get_model_post();
    if (!$post instanceof WP_Post) {
        return $data;
    }

    $schema = tmw_seo_model_bridge_get_schema($post->ID);
    if (!$schema) {
        tmw_seo_model_bridge_log_once(
            '[TMW-SEO-MODEL-FALLBACK]',
            'Rank Math schema empty for model post ' . $post->ID . '.'
        );
        return $data;
    }

    tmw_seo_model_bridge_log_once(
        '[TMW-SEO-MODEL-META]',
        'Applied schema for model post ' . $post->ID . '.'
    );
    return $schema;
}, 20);

// ============================================================================
// OPENGRAPH IMAGE FOR MODEL PAGES
// ============================================================================

/**
 * Filter: OpenGraph image for model single pages and taxonomy archives.
 * Uses the model banner image as the social share image.
 */
add_filter('rank_math/opengraph/facebook/image', function ($image) {
    $post = null;

    if (is_singular('model')) {
        $post = get_post();
    } elseif (is_tax('models')) {
        $post = tmw_seo_model_bridge_get_model_post();
    }

    if (!$post instanceof WP_Post) {
        return $image;
    }

    // Try to get banner image
    $banner_url = '';
    if (function_exists('tmw_resolve_model_banner_url')) {
        $banner_url = tmw_resolve_model_banner_url($post->ID);
    }

    // Fallback to featured image
    if (empty($banner_url)) {
        $banner_url = get_the_post_thumbnail_url($post->ID, 'large');
    }

    if (!empty($banner_url)) {
        tmw_seo_model_bridge_log_once(
            '[TMW-SEO-MODEL-OG]',
            'Applied OpenGraph image for model post ' . $post->ID . '.'
        );
        return $banner_url;
    }

    return $image;
}, 20);

/**
 * Filter: Twitter image for model pages.
 */
add_filter('rank_math/opengraph/twitter/image', function ($image) {
    $post = null;

    if (is_singular('model')) {
        $post = get_post();
    } elseif (is_tax('models')) {
        $post = tmw_seo_model_bridge_get_model_post();
    }

    if (!$post instanceof WP_Post) {
        return $image;
    }

    $banner_url = '';
    if (function_exists('tmw_resolve_model_banner_url')) {
        $banner_url = tmw_resolve_model_banner_url($post->ID);
    }

    if (empty($banner_url)) {
        $banner_url = get_the_post_thumbnail_url($post->ID, 'large');
    }

    if (!empty($banner_url)) {
        tmw_seo_model_bridge_log_once(
            '[TMW-SEO-MODEL-TWITTER]',
            'Applied Twitter image for model post ' . $post->ID . '.'
        );
        return $banner_url;
    }

    return $image;
}, 20);
