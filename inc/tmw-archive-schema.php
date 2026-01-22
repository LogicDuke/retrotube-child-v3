<?php
/**
 * Archive/Listing Page Schemas.
 * 
 * Provides ItemList and CollectionPage schema for archive and listing pages
 * including video listings, category archives, and tag archives.
 *
 * @package retrotube-child
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_archive_schema_log_once')) {
    /**
     * Log a message once per request.
     *
     * @param string $message Log message.
     */
    function tmw_archive_schema_log_once(string $message): void {
        static $logged = [];

        if (!empty($logged[$message])) {
            return;
        }

        $logged[$message] = true;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TMW-ARCHIVE-SCHEMA] ' . $message);
        }
    }
}

if (!function_exists('tmw_archive_schema_build_video_item_list')) {
    /**
     * Build ItemList schema for video listings.
     *
     * @param array  $videos Array of video data: [['name' => '', 'url' => '', 'thumbnail' => '', 'duration' => ''], ...]
     * @param string $list_name Name of the list.
     * @return array|null
     */
    function tmw_archive_schema_build_video_item_list(array $videos, string $list_name = 'Videos'): ?array {
        if (empty($videos)) {
            return null;
        }

        $elements = [];
        $position = 1;

        foreach ($videos as $video) {
            $url = isset($video['url']) ? trim((string) $video['url']) : '';
            $name = isset($video['name']) ? trim((string) $video['name']) : '';

            if ($url === '' || $name === '') {
                continue;
            }

            $item = [
                '@type' => 'VideoObject',
                'name'  => $name,
                'url'   => $url,
            ];

            // Optional fields
            if (!empty($video['thumbnail'])) {
                $item['thumbnailUrl'] = $video['thumbnail'];
            }

            if (!empty($video['duration'])) {
                $item['duration'] = $video['duration'];
            }

            if (!empty($video['description'])) {
                $item['description'] = wp_trim_words(wp_strip_all_tags($video['description']), 20, '...');
            }

            if (!empty($video['upload_date'])) {
                $item['uploadDate'] = $video['upload_date'];
            }

            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'item'     => $item,
            ];
        }

        if (empty($elements)) {
            return null;
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => $list_name,
            'numberOfItems'   => count($elements),
            'itemListElement' => $elements,
        ];
    }
}

if (!function_exists('tmw_archive_schema_output_video_item_list')) {
    /**
     * Output video ItemList JSON-LD.
     *
     * @param array  $videos    Array of video data.
     * @param string $list_name Name of the list.
     */
    function tmw_archive_schema_output_video_item_list(array $videos, string $list_name = 'Videos'): void {
        $schema = tmw_archive_schema_build_video_item_list($videos, $list_name);
        if (!$schema) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        tmw_archive_schema_log_once('Output video ItemList schema with ' . count($videos) . ' items.');
    }
}

if (!function_exists('tmw_archive_schema_collect_videos_from_query')) {
    /**
     * Collect video data from a WP_Query for schema output.
     *
     * @param WP_Query|null $query Query object (defaults to global $wp_query).
     * @return array
     */
    function tmw_archive_schema_collect_videos_from_query($query = null): array {
        if (!$query) {
            global $wp_query;
            $query = $wp_query;
        }

        if (!$query->have_posts()) {
            return [];
        }

        $videos = [];

        foreach ($query->posts as $post) {
            $duration = null;
            $duration_raw = get_post_meta($post->ID, 'duration', true);
            if (!empty($duration_raw) && function_exists('tmw_video_schema_normalize_duration')) {
                $duration = tmw_video_schema_normalize_duration($duration_raw);
            }

            $videos[] = [
                'name'        => get_the_title($post),
                'url'         => get_permalink($post),
                'thumbnail'   => get_the_post_thumbnail_url($post, 'medium'),
                'duration'    => $duration,
                'description' => $post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($post->post_content), 20, '...'),
                'upload_date' => get_post_time('c', true, $post),
            ];
        }

        return $videos;
    }
}

// ============================================================================
// CATEGORY ARCHIVE SCHEMA
// ============================================================================

/**
 * Add CollectionPage + ItemList schema to category archives.
 */
add_filter('rank_math/json_ld', function ($data) {
    if (!is_category()) {
        return $data;
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return $data;
    }

    $term_link = get_term_link($term);
    if (is_wp_error($term_link)) {
        return $data;
    }

    // CollectionPage schema
    $collection = [
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        '@id'         => $term_link . '#collectionpage',
        'url'         => $term_link,
        'name'        => $term->name,
        'description' => !empty($term->description) ? wp_strip_all_tags($term->description) : 'Browse ' . $term->name . ' videos.',
    ];

    $data['CollectionPage'] = $collection;

    // Collect videos for ItemList
    global $wp_query;
    $videos = tmw_archive_schema_collect_videos_from_query($wp_query);

    if (!empty($videos)) {
        $item_list = tmw_archive_schema_build_video_item_list($videos, $term->name . ' Videos');
        if ($item_list) {
            // Remove @context since it's part of the main data array
            unset($item_list['@context']);
            $data['ItemList'] = $item_list;
        }
    }

    tmw_archive_schema_log_once('Applied CollectionPage schema for category ' . $term->term_id . '.');

    return $data;
}, 25);

// ============================================================================
// TAG ARCHIVE SCHEMA
// ============================================================================

/**
 * Add CollectionPage schema to tag archives.
 */
add_filter('rank_math/json_ld', function ($data) {
    if (!is_tag()) {
        return $data;
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return $data;
    }

    $term_link = get_term_link($term);
    if (is_wp_error($term_link)) {
        return $data;
    }

    $collection = [
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        '@id'         => $term_link . '#collectionpage',
        'url'         => $term_link,
        'name'        => $term->name,
        'description' => !empty($term->description) ? wp_strip_all_tags($term->description) : 'Videos tagged with ' . $term->name . '.',
    ];

    $data['CollectionPage'] = $collection;

    tmw_archive_schema_log_once('Applied CollectionPage schema for tag ' . $term->term_id . '.');

    return $data;
}, 25);

// ============================================================================
// VIDEO ARCHIVE/LISTING PAGE SCHEMA
// ============================================================================

/**
 * Add schema to video listing pages (page-videos.php).
 */
add_filter('rank_math/json_ld', function ($data) {
    // Check if on the videos page
    if (!is_page('videos') && !is_page('all-videos')) {
        return $data;
    }

    $page_url = get_permalink();
    $page_title = get_the_title();

    $collection = [
        '@context'    => 'https://schema.org',
        '@type'       => 'CollectionPage',
        '@id'         => $page_url . '#collectionpage',
        'url'         => $page_url,
        'name'        => $page_title,
        'description' => 'Browse all videos.',
    ];

    // Get excerpt for description
    $page = get_post();
    if ($page && has_excerpt($page)) {
        $collection['description'] = wp_strip_all_tags($page->post_excerpt);
    }

    $data['CollectionPage'] = $collection;

    tmw_archive_schema_log_once('Applied CollectionPage schema for videos page.');

    return $data;
}, 25);

// ============================================================================
// HELPER FOR CUSTOM VIDEO GRIDS
// ============================================================================

if (!function_exists('tmw_video_grid_schema_start')) {
    /**
     * Start collecting video items for schema.
     * Call before outputting a video grid.
     */
    function tmw_video_grid_schema_start(): void {
        $GLOBALS['tmw_video_grid_items'] = [];
    }
}

if (!function_exists('tmw_video_grid_schema_add')) {
    /**
     * Add a video item to the collection.
     * Call for each video in the grid.
     *
     * @param int|WP_Post $post Post ID or object.
     */
    function tmw_video_grid_schema_add($post): void {
        if (!isset($GLOBALS['tmw_video_grid_items'])) {
            return;
        }

        if (is_numeric($post)) {
            $post = get_post($post);
        }

        if (!$post instanceof WP_Post) {
            return;
        }

        $duration = null;
        $duration_raw = get_post_meta($post->ID, 'duration', true);
        if (!empty($duration_raw) && function_exists('tmw_video_schema_normalize_duration')) {
            $duration = tmw_video_schema_normalize_duration($duration_raw);
        }

        $GLOBALS['tmw_video_grid_items'][] = [
            'name'        => get_the_title($post),
            'url'         => get_permalink($post),
            'thumbnail'   => get_the_post_thumbnail_url($post, 'medium'),
            'duration'    => $duration,
            'upload_date' => get_post_time('c', true, $post),
        ];
    }
}

if (!function_exists('tmw_video_grid_schema_output')) {
    /**
     * Output the collected video schema.
     * Call after outputting a video grid.
     *
     * @param string $list_name Name for the ItemList.
     */
    function tmw_video_grid_schema_output(string $list_name = 'Videos'): void {
        if (empty($GLOBALS['tmw_video_grid_items'])) {
            return;
        }

        tmw_archive_schema_output_video_item_list($GLOBALS['tmw_video_grid_items'], $list_name);
        unset($GLOBALS['tmw_video_grid_items']);
    }
}
