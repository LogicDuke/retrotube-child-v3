<?php
/**
 * Model Schema - Person and ProfilePage JSON-LD for model pages.
 * 
 * Outputs proper structured data for model/performer profile pages
 * following Google's guidelines for ProfilePage and Person schema.
 *
 * @package retrotube-child
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_model_schema_log_once')) {
    /**
     * Log a message once per request.
     *
     * @param string $message Log message.
     */
    function tmw_model_schema_log_once(string $message): void {
        static $logged = [];

        if (!empty($logged[$message])) {
            return;
        }

        $logged[$message] = true;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TMW-MODEL-SCHEMA] ' . $message);
        }
    }
}

if (!function_exists('tmw_model_schema_get_description')) {
    /**
     * Get a clean description for the model.
     *
     * @param WP_Post $post Model post object.
     * @return string
     */
    function tmw_model_schema_get_description(WP_Post $post): string {
        // Try excerpt first
        if (has_excerpt($post)) {
            return wp_strip_all_tags($post->post_excerpt);
        }

        // Try Rank Math description
        $rm_desc = get_post_meta($post->ID, 'rank_math_description', true);
        if (!empty($rm_desc)) {
            return wp_strip_all_tags($rm_desc);
        }

        // Fallback to content excerpt
        $content = $post->post_content;
        if (!empty($content)) {
            $clean = wp_strip_all_tags(strip_shortcodes($content));
            return wp_trim_words($clean, 30, '...');
        }

        return '';
    }
}

if (!function_exists('tmw_model_schema_get_image')) {
    /**
     * Get the best available image URL for the model.
     *
     * @param WP_Post $post Model post object.
     * @return string|null
     */
    function tmw_model_schema_get_image(WP_Post $post): ?string {
        // Try banner image first
        if (function_exists('tmw_resolve_model_banner_url')) {
            $banner = tmw_resolve_model_banner_url($post->ID);
            if (!empty($banner)) {
                return esc_url_raw($banner);
            }
        }

        // Fallback to featured image
        $thumbnail = get_the_post_thumbnail_url($post->ID, 'large');
        if (!empty($thumbnail)) {
            return esc_url_raw($thumbnail);
        }

        return null;
    }
}

if (!function_exists('tmw_model_schema_get_social_links')) {
    /**
     * Get social profile links for the model (if ACF fields exist).
     *
     * @param WP_Post $post Model post object.
     * @return array
     */
    function tmw_model_schema_get_social_links(WP_Post $post): array {
        $links = [];

        if (!function_exists('get_field')) {
            return $links;
        }

        // Common social field names to check
        $social_fields = [
            'twitter_url',
            'twitter',
            'instagram_url',
            'instagram',
            'tiktok_url',
            'tiktok',
            'onlyfans_url',
            'onlyfans',
            'website_url',
            'website',
            'model_link',
        ];

        foreach ($social_fields as $field) {
            $value = get_field($field, $post->ID);
            if (!empty($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                $links[] = esc_url_raw($value);
            }
        }

        return array_unique($links);
    }
}

if (!function_exists('tmw_model_schema_get_video_count')) {
    /**
     * Get the count of videos featuring this model.
     *
     * @param WP_Post $post Model post object.
     * @return int
     */
    function tmw_model_schema_get_video_count(WP_Post $post): int {
        if (!function_exists('tmw_get_videos_for_model')) {
            return 0;
        }

        $videos = tmw_get_videos_for_model($post->post_name, -1);
        return is_array($videos) ? count($videos) : 0;
    }
}

if (!function_exists('tmw_model_schema_build_person')) {
    /**
     * Build the Person schema object.
     *
     * @param WP_Post $post Model post object.
     * @return array
     */
    function tmw_model_schema_build_person(WP_Post $post): array {
        $name = get_the_title($post);
        $url = get_permalink($post);
        $description = tmw_model_schema_get_description($post);
        $image = tmw_model_schema_get_image($post);
        $social_links = tmw_model_schema_get_social_links($post);

        $person = [
            '@type' => 'Person',
            '@id'   => $url . '#person',
            'name'  => wp_strip_all_tags($name),
            'url'   => $url,
        ];

        if (!empty($description)) {
            $person['description'] = $description;
        }

        if (!empty($image)) {
            $person['image'] = [
                '@type' => 'ImageObject',
                'url'   => $image,
            ];
        }

        if (!empty($social_links)) {
            $person['sameAs'] = $social_links;
        }

        // Add job title if available
        if (function_exists('get_field')) {
            $job_title = get_field('job_title', $post->ID);
            if (!empty($job_title)) {
                $person['jobTitle'] = wp_strip_all_tags($job_title);
            }
        }

        return $person;
    }
}

if (!function_exists('tmw_model_schema_build_profile_page')) {
    /**
     * Build the ProfilePage schema with embedded Person.
     *
     * @param WP_Post $post Model post object.
     * @return array
     */
    function tmw_model_schema_build_profile_page(WP_Post $post): array {
        $url = get_permalink($post);
        $name = get_the_title($post);
        $description = tmw_model_schema_get_description($post);
        $image = tmw_model_schema_get_image($post);

        $profile_page = [
            '@context'    => 'https://schema.org',
            '@type'       => 'ProfilePage',
            '@id'         => $url . '#profilepage',
            'url'         => $url,
            'name'        => wp_strip_all_tags($name) . ' Profile',
            'dateCreated' => get_post_time('c', true, $post),
            'dateModified'=> get_post_modified_time('c', true, $post),
            'mainEntity'  => tmw_model_schema_build_person($post),
        ];

        if (!empty($description)) {
            $profile_page['description'] = $description;
        }

        if (!empty($image)) {
            $profile_page['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url'   => $image,
            ];
        }

        // Add breadcrumb reference
        $profile_page['breadcrumb'] = [
            '@id' => home_url('/') . '#breadcrumb',
        ];

        return $profile_page;
    }
}

if (!function_exists('tmw_model_schema_build_item_list')) {
    /**
     * Build an ItemList schema for model archives/grids.
     *
     * @param array $models Array of model data: [['name' => '', 'url' => ''], ...]
     * @return array|null
     */
    function tmw_model_schema_build_item_list(array $models): ?array {
        if (empty($models)) {
            return null;
        }

        $elements = [];
        $position = 1;

        foreach ($models as $model) {
            $url = isset($model['url']) ? trim((string) $model['url']) : '';
            $name = isset($model['name']) ? trim((string) $model['name']) : '';

            if ($url === '' || $name === '') {
                continue;
            }

            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'url'      => $url,
                'name'     => $name,
                'item'     => [
                    '@type' => 'Person',
                    'name'  => $name,
                    'url'   => $url,
                ],
            ];
        }

        if (empty($elements)) {
            return null;
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => 'Models',
            'numberOfItems'   => count($elements),
            'itemListElement' => $elements,
        ];
    }
}

if (!function_exists('tmw_model_schema_output_item_list')) {
    /**
     * Output ItemList JSON-LD for model archives.
     *
     * @param array $models Array of model data.
     */
    function tmw_model_schema_output_item_list(array $models): void {
        $schema = tmw_model_schema_build_item_list($models);
        if (!$schema) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        tmw_model_schema_log_once('Output ItemList schema with ' . count($models) . ' models.');
    }
}

// ============================================================================
// RANK MATH JSON-LD FILTER FOR MODEL SINGLE PAGES
// ============================================================================

/**
 * Add Person/ProfilePage schema to model single pages.
 */
add_filter('rank_math/json_ld', function ($data) {
    if (!is_singular('model')) {
        return $data;
    }

    $post = get_post();
    if (!$post instanceof WP_Post) {
        return $data;
    }

    // Remove any existing Person schema from Rank Math to avoid duplicates
    foreach ($data as $key => $entry) {
        if (!is_array($entry) || !isset($entry['@type'])) {
            continue;
        }

        $types = is_array($entry['@type']) ? $entry['@type'] : [$entry['@type']];
        if (in_array('Person', $types, true) || in_array('ProfilePage', $types, true)) {
            unset($data[$key]);
        }
    }

    // Add our ProfilePage with Person schema
    $data['ProfilePage'] = tmw_model_schema_build_profile_page($post);

    tmw_model_schema_log_once('Applied ProfilePage/Person schema for model post ' . $post->ID . '.');

    return $data;
}, 25);

// ============================================================================
// COLLECTION PAGE SCHEMA FOR MODEL ARCHIVES
// ============================================================================

/**
 * Add CollectionPage schema to model archive pages.
 */
add_filter('rank_math/json_ld', function ($data) {
    if (!is_post_type_archive('model')) {
        return $data;
    }

    $archive_url = get_post_type_archive_link('model');
    if (!$archive_url) {
        return $data;
    }

    $collection = [
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        '@id'         => $archive_url . '#collectionpage',
        'url'         => $archive_url,
        'name'        => 'Models',
        'description' => 'Browse all models and performers.',
    ];

    // Get models page for custom description
    $models_page = get_page_by_path('models');
    if ($models_page && !empty($models_page->post_excerpt)) {
        $collection['description'] = wp_strip_all_tags($models_page->post_excerpt);
    }

    $data['CollectionPage'] = $collection;

    tmw_model_schema_log_once('Applied CollectionPage schema for model archive.');

    return $data;
}, 25);

// ============================================================================
// HELPER FOR ARCHIVE TEMPLATE
// ============================================================================

if (!function_exists('tmw_model_archive_schema_from_query')) {
    /**
     * Output ItemList schema from the current WP_Query for model archives.
     * Call this in archive-model.php after the loop.
     */
    function tmw_model_archive_schema_from_query(): void {
        global $wp_query;

        if (!is_post_type_archive('model') && !is_page('models')) {
            return;
        }

        $models = [];

        // Try to get models from shortcode output or query
        if (!empty($GLOBALS['tmw_archive_models_for_schema'])) {
            $models = $GLOBALS['tmw_archive_models_for_schema'];
        } elseif ($wp_query->have_posts()) {
            foreach ($wp_query->posts as $post) {
                if ($post->post_type === 'model') {
                    $models[] = [
                        'name' => get_the_title($post),
                        'url'  => get_permalink($post),
                    ];
                }
            }
        }

        if (!empty($models)) {
            tmw_model_schema_output_item_list($models);
        }
    }
}

/**
 * Hook to collect model data from flipbox shortcode for schema.
 */
add_action('tmw_flipbox_rendered', function ($term_id, $name, $url) {
    if (!isset($GLOBALS['tmw_archive_models_for_schema'])) {
        $GLOBALS['tmw_archive_models_for_schema'] = [];
    }

    $GLOBALS['tmw_archive_models_for_schema'][] = [
        'name' => $name,
        'url'  => $url,
    ];
}, 10, 3);
