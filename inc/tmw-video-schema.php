<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

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

if (!function_exists('tmw_video_schema_build')) {
    /**
     * Build the VideoObject schema array.
     *
     * @param WP_Post $post Post object.
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

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => wp_strip_all_tags((string) $title),
            'description' => $description,
            'uploadDate' => get_post_time('c', true, $post),
            'embedUrl' => $embed_url,
            'url' => get_permalink($post),
        ];

        if ($thumbnail !== '') {
            $schema['thumbnailUrl'] = [$thumbnail];
        }

        $duration_raw = get_post_meta($post->ID, 'duration', true);
        if (is_string($duration_raw) && $duration_raw !== '') {
            $duration = tmw_video_schema_normalize_duration($duration_raw);
            if ($duration) {
                $schema['duration'] = $duration;
            }
        }

        return $schema;
    }
}

add_filter('rank_math/json_ld', function ($data) {
    if (!is_singular()) {
        return $data;
    }

    $post = get_post();
    if (!$post instanceof WP_Post) {
        return $data;
    }

    $embed_url = tmw_video_schema_extract_embed_url($post);
    if (!$embed_url) {
        return $data;
    }

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

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[TMW-VIDEO-SCHEMA] Applied VideoObject schema for post ' . $post->ID . '.');
    }

    return $data;
}, 20);
