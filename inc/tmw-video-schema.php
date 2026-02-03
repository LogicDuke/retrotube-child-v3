<?php
/**
 * Enhanced Video Schema - VideoObject JSON-LD for video posts.
 * 
 * Includes:
 * - Post type restriction (only video/post, not model pages)
 * - InteractionStatistic (view count, like count)
 * - ContentUrl support
 * - Performer (model) linking
 *
 * @package retrotube-child
 * @version 2.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_video_schema_log_once')) {
    /**
     * Log a message once per request.
     *
     * @param string $message Log message.
     */
    function tmw_video_schema_log_once(string $message): void {
        static $logged = [];

        if (!empty($logged[$message])) {
            return;
        }

        $logged[$message] = true;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-VIDEO-SCHEMA] ' . $message); }
        }
    }
}

if (!function_exists('tmw_video_schema_normalize_duration')) {
    /**
     * Normalize duration strings into ISO 8601 format.
     *
     * @param string $raw_duration Duration string from meta.
     * @return string|null
     */
    function tmw_video_schema_normalize_duration(string $raw_duration): ?string {
        $duration = trim($raw_duration);
        if ($duration === '') {
            return null;
        }

        if (stripos($duration, 'PT') === 0) {
            return strtoupper($duration);
        }

        if (is_numeric($duration)) {
            $seconds = max(0, (int) $duration);
            return 'PT' . $seconds . 'S';
        }

        if (preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $duration, $matches)) {
            $hours = isset($matches[1]) ? (int) $matches[1] : 0;
            $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
            $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

            $parts = 'PT';
            if ($hours > 0) {
                $parts .= $hours . 'H';
            }
            if ($minutes > 0) {
                $parts .= $minutes . 'M';
            }
            $parts .= $seconds . 'S';

            return $parts;
        }

        return null;
    }
}

if (!function_exists('tmw_video_schema_is_video_post')) {
    /**
     * Check if current post is a video post type.
     *
     * @param WP_Post $post Post object.
     * @return bool
     */
    function tmw_video_schema_is_video_post(WP_Post $post): bool {
        $video_post_types = ['post', 'video'];
        
        // Allow filtering for custom video post types
        $video_post_types = apply_filters('tmw_video_schema_post_types', $video_post_types);
        
        return in_array($post->post_type, $video_post_types, true);
    }
}

if (!function_exists('tmw_video_schema_extract_embed_url')) {
    /**
     * Pull the best available video embed URL for a post.
     *
     * @param WP_Post $post Post object.
     * @return string|null
     */
    function tmw_video_schema_extract_embed_url(WP_Post $post): ?string {
        $meta_keys = [
            'video_embed_url',
            'video_url',
            'embed_url',
            'tmw_video_url',
            'tmw_embed_url',
            'wpslj_stream',
        ];

        foreach ($meta_keys as $meta_key) {
            $value = get_post_meta($post->ID, $meta_key, true);
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value) {
                $url = esc_url_raw($value);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        $content = $post->post_content;
        if (is_string($content) && $content !== '') {
            if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
                $url = esc_url_raw($matches[1]);
                if ($url !== '') {
                    return $url;
                }
            }

            if (preg_match('/<source[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
                $url = esc_url_raw($matches[1]);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return null;
    }
}

if (!function_exists('tmw_video_schema_extract_content_url')) {
    /**
     * Extract direct video content URL if available.
     *
     * @param WP_Post $post Post object.
     * @return string|null
     */
    function tmw_video_schema_extract_content_url(WP_Post $post): ?string {
        // Check for direct video file URLs in meta
        $content_keys = [
            'content_url',
            'video_file_url',
            'video_mp4',
            'wpslj_stream',
        ];

        foreach ($content_keys as $key) {
            $value = get_post_meta($post->ID, $key, true);
            if (!empty($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                // Only return if it looks like a video file
                if (preg_match('/\.(mp4|webm|ogg|m3u8)(\?|$)/i', $value)) {
                    return esc_url_raw($value);
                }
            }
        }

        // Check post content for video source tags
        if (preg_match('/<source[^>]+src=["\']([^"\']+\.(mp4|webm|ogg))["\'][^>]*>/i', $post->post_content, $matches)) {
            return esc_url_raw($matches[1]);
        }

        return null;
    }
}

if (!function_exists('tmw_video_schema_get_interaction_stats')) {
    /**
     * Get interaction statistics for the video.
     *
     * @param WP_Post $post Post object.
     * @return array
     */
    function tmw_video_schema_get_interaction_stats(WP_Post $post): array {
        $stats = [];

        // View count
        $views = 0;
        if (function_exists('tmw_get_post_views_count')) {
            $views = (int) tmw_get_post_views_count($post->ID);
        } elseif (function_exists('wpst_get_post_views')) {
            $views = (int) wpst_get_post_views($post->ID);
        } else {
            $views = (int) get_post_meta($post->ID, 'post_views_count', true);
        }

        if ($views > 0) {
            $stats[] = [
                '@type'                => 'InteractionCounter',
                'interactionType'      => ['@type' => 'WatchAction'],
                'userInteractionCount' => $views,
            ];
        }

        // Like count
        $likes = 0;
        if (function_exists('tmw_get_post_likes_count')) {
            $likes = (int) tmw_get_post_likes_count($post->ID);
        } elseif (function_exists('wpst_get_post_likes')) {
            $likes = (int) wpst_get_post_likes($post->ID);
        } else {
            $likes = (int) get_post_meta($post->ID, 'likes_count', true);
        }

        if ($likes > 0) {
            $stats[] = [
                '@type'                => 'InteractionCounter',
                'interactionType'      => ['@type' => 'LikeAction'],
                'userInteractionCount' => $likes,
            ];
        }

        // Comment count
        $comments = (int) get_comments_number($post->ID);
        if ($comments > 0) {
            $stats[] = [
                '@type'                => 'InteractionCounter',
                'interactionType'      => ['@type' => 'CommentAction'],
                'userInteractionCount' => $comments,
            ];
        }

        return $stats;
    }
}

if (!function_exists('tmw_video_schema_get_performers')) {
    /**
     * Get performer (model) data for the video.
     *
     * @param WP_Post $post Post object.
     * @return array
     */
    function tmw_video_schema_get_performers(WP_Post $post): array {
        $performers = [];

        // Try 'models' taxonomy first, then 'actors'
        $terms = wp_get_post_terms($post->ID, 'models');
        if (empty($terms) || is_wp_error($terms)) {
            $terms = wp_get_post_terms($post->ID, 'actors');
        }

        if (empty($terms) || is_wp_error($terms)) {
            return $performers;
        }

        foreach ($terms as $term) {
            $model_url = '';
            
            // Try to get model CPT link
            if (function_exists('tmw_get_model_link_for_term')) {
                $model_url = tmw_get_model_link_for_term($term);
            }
            
            if (empty($model_url)) {
                $model_url = get_term_link($term);
                if (is_wp_error($model_url)) {
                    $model_url = '';
                }
            }

            $performer = [
                '@type' => 'Person',
                'name'  => $term->name,
            ];

            if (!empty($model_url)) {
                $performer['url'] = $model_url;
            }

            $performers[] = $performer;
        }

        return $performers;
    }
}

if (!function_exists('tmw_video_schema_build')) {
    /**
     * Build the VideoObject schema array.
     *
     * @param WP_Post $post      Post object.
     * @param string  $embed_url Embed URL.
     * @return array
     */
    function tmw_video_schema_build(WP_Post $post, string $embed_url): array {
        $title = get_the_title($post);
        $description = has_excerpt($post)
            ? $post->post_excerpt
            : wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 40, '');
        $description = wp_strip_all_tags((string) $description);

        $thumbnail = get_the_post_thumbnail_url($post, 'full');
        $thumbnail = $thumbnail ? esc_url_raw($thumbnail) : '';

        $permalink = get_permalink($post);

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'VideoObject',
            '@id'        => $permalink . '#video',
            'name'       => wp_strip_all_tags((string) $title),
            'description'=> $description,
            'uploadDate' => get_post_time('c', true, $post),
            'embedUrl'   => $embed_url,
            'url'        => $permalink,
        ];

        // Thumbnail
        if ($thumbnail !== '') {
            $schema['thumbnailUrl'] = [$thumbnail];
        }

        // Duration
        $duration_raw = get_post_meta($post->ID, 'duration', true);
        if (is_string($duration_raw) && $duration_raw !== '') {
            $duration = tmw_video_schema_normalize_duration($duration_raw);
            if ($duration) {
                $schema['duration'] = $duration;
            }
        }

        // Content URL (direct video file)
        $content_url = tmw_video_schema_extract_content_url($post);
        if ($content_url) {
            $schema['contentUrl'] = $content_url;
        }

        // Interaction statistics (views, likes, comments)
        $interaction_stats = tmw_video_schema_get_interaction_stats($post);
        if (!empty($interaction_stats)) {
            $schema['interactionStatistic'] = $interaction_stats;
        }

        // Performers (models)
        $performers = tmw_video_schema_get_performers($post);
        if (!empty($performers)) {
            $schema['actor'] = count($performers) === 1 ? $performers[0] : $performers;
        }

        // Author/Publisher
        $author_name = get_the_author_meta('display_name', $post->post_author);
        if (!empty($author_name)) {
            $schema['author'] = [
                '@type' => 'Person',
                'name'  => $author_name,
            ];
        }

        // Date modified
        $modified = get_post_modified_time('c', true, $post);
        if ($modified && $modified !== $schema['uploadDate']) {
            $schema['dateModified'] = $modified;
        }

        return $schema;
    }
}

/**
 * Apply VideoObject schema to video posts via Rank Math.
 */
add_filter('rank_math/json_ld', function ($data) {
    // FIXED: Only apply to video post types, not all singular pages
    if (!is_singular(['post', 'video'])) {
        return $data;
    }

    $post = get_post();
    if (!$post instanceof WP_Post) {
        return $data;
    }

    // Double-check post type to prevent schema on model pages
    if (!tmw_video_schema_is_video_post($post)) {
        return $data;
    }

    $embed_url = tmw_video_schema_extract_embed_url($post);
    if (!$embed_url) {
        return $data;
    }

    // Remove any existing VideoObject schema
    foreach ($data as $key => $entry) {
        $types = [];
        if (is_array($entry) && isset($entry['@type'])) {
            $types = is_array($entry['@type']) ? $entry['@type'] : [$entry['@type']];
        }

        if (in_array('VideoObject', $types, true)) {
            unset($data[$key]);
        }
    }

    if (isset($data['VideoObject'])) {
        unset($data['VideoObject']);
    }

    $data['VideoObject'] = tmw_video_schema_build($post, $embed_url);

    tmw_video_schema_log_once('Applied VideoObject schema for post ' . $post->ID . '.');

    return $data;
}, 20);
