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

if (!function_exists('tmw_seo_category_bridge_is_list')) {
    function tmw_seo_category_bridge_is_list(array $items): bool {
        $index = 0;
        foreach ($items as $key => $_value) {
            if ($key !== $index) {
                return false;
            }
            $index++;
        }

        return true;
    }
}

if (!function_exists('tmw_seo_category_bridge_normalize_url')) {
    function tmw_seo_category_bridge_normalize_url(string $url): string {
        $url = explode('#', $url)[0];
        return untrailingslashit($url);
    }
}

if (!function_exists('tmw_seo_category_bridge_get_schema_nodes')) {
    function tmw_seo_category_bridge_get_schema_nodes(array $data): array {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            return $data['@graph'];
        }

        if (tmw_seo_category_bridge_is_list($data)) {
            return $data;
        }

        if (isset($data['@type'])) {
            return [$data];
        }

        return [];
    }
}

if (!function_exists('tmw_seo_category_bridge_has_archive_schema_node')) {
    function tmw_seo_category_bridge_has_archive_schema_node(array $data, string $term_link): bool {
        $normalized_term_link = tmw_seo_category_bridge_normalize_url($term_link);
        $nodes = tmw_seo_category_bridge_get_schema_nodes($data);

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            $types = $node['@type'] ?? [];
            if (is_string($types)) {
                $types = [$types];
            }

            if (!in_array('CollectionPage', $types, true) && !in_array('WebPage', $types, true)) {
                continue;
            }

            $url = null;
            if (!empty($node['url']) && is_string($node['url'])) {
                $url = $node['url'];
            } elseif (!empty($node['@id']) && is_string($node['@id'])) {
                $url = $node['@id'];
            }

            if ($url === null) {
                continue;
            }

            if (tmw_seo_category_bridge_normalize_url($url) === $normalized_term_link) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('tmw_seo_category_bridge_get_archive_description')) {
    function tmw_seo_category_bridge_get_archive_description(WP_Term $term, ?WP_Post $post): string {
        if ($post instanceof WP_Post) {
            $meta = (string) get_post_meta($post->ID, 'rank_math_description', true);
            if ($meta !== '') {
                $meta = tmw_seo_category_bridge_replace_vars($meta, $post->ID);
                return trim(wp_strip_all_tags($meta));
            }
        }

        $description = term_description($term);
        if (!is_string($description)) {
            $description = '';
        }

        return trim(wp_strip_all_tags($description));
    }
}

if (!function_exists('tmw_seo_category_bridge_build_collection_page_schema')) {
    function tmw_seo_category_bridge_build_collection_page_schema(WP_Term $term, ?WP_Post $post): ?array {
        $term_link = tmw_seo_category_bridge_get_term_link();
        if (!$term_link) {
            return null;
        }

        $title = single_cat_title('', false);
        if ($title === '') {
            $title = $term->name;
        }

        $description = tmw_seo_category_bridge_get_archive_description($term, $post);

        $schema = [
            '@type' => 'CollectionPage',
            'url'   => $term_link,
            'name'  => $title,
        ];

        if ($description !== '') {
            $schema['description'] = $description;
        }

        return $schema;
    }
}

if (!function_exists('tmw_seo_category_bridge_inject_collection_page_schema')) {
    function tmw_seo_category_bridge_inject_collection_page_schema(array $data, array $schema): array {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            $data['@graph'][] = $schema;
            return $data;
        }

        if (tmw_seo_category_bridge_is_list($data)) {
            $data[] = $schema;
            return $data;
        }

        if (empty($data)) {
            return [
                '@context' => 'https://schema.org',
                '@graph'   => [$schema],
            ];
        }

        $context = $data['@context'] ?? 'https://schema.org';
        return [
            '@context' => $context,
            '@graph'   => [$data, $schema],
        ];
    }
}

if (!function_exists('tmw_seo_category_bridge_ensure_collection_page_schema')) {
    function tmw_seo_category_bridge_ensure_collection_page_schema(array $data, WP_Term $term, ?WP_Post $post): array {
        $term_link = tmw_seo_category_bridge_get_term_link();
        if (!$term_link) {
            return $data;
        }

        if (tmw_seo_category_bridge_has_archive_schema_node($data, $term_link)) {
            return $data;
        }

        $schema = tmw_seo_category_bridge_build_collection_page_schema($term, $post);
        if (!$schema) {
            return $data;
        }

        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-SCHEMA]',
            'injected=1 term_id=' . $term->term_id
        );

        return tmw_seo_category_bridge_inject_collection_page_schema($data, $schema);
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

    $term = tmw_seo_category_bridge_get_term();
    if (!$term instanceof WP_Term) {
        return $robots;
    }

    $post = tmw_seo_category_bridge_get_category_page_post();
    if (!$post instanceof WP_Post) {
        return $robots;
    }

    $meta = get_post_meta($post->ID, 'rank_math_robots', true);
    if (empty($meta)) {
        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-ROBOTS]',
            'fallback=noindex term_id=' . $term->term_id . ' post_id=' . $post->ID
        );
        return ['noindex', 'follow'];
    }

    if (is_string($meta)) {
        $meta = array_filter(array_map('trim', explode(',', $meta)));
    }

    if (empty($meta)) {
        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-ROBOTS]',
            'fallback=noindex term_id=' . $term->term_id . ' post_id=' . $post->ID
        );
        return ['noindex', 'follow'];
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

    $term = tmw_seo_category_bridge_get_term();
    if (!$term instanceof WP_Term) {
        return $data;
    }

    $post = tmw_seo_category_bridge_get_category_page_post();
    $schema = $post instanceof WP_Post ? tmw_seo_category_bridge_get_schema($post->ID) : null;

    if ($schema && $post instanceof WP_Post) {
        tmw_seo_category_bridge_log_once(
            '[TMW-SEO-CAT-META]',
            'Applied schema for post ' . $post->ID . '.'
        );
    }

    $schema_data = is_array($schema) ? $schema : (is_array($data) ? $data : []);

    if (!is_array($schema_data)) {
        return $data;
    }

    return tmw_seo_category_bridge_ensure_collection_page_schema($schema_data, $term, $post);
}, 20);
