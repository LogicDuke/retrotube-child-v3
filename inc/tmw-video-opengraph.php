<?php
/**
 * Video OpenGraph & Twitter Card Meta Tags.
 * 
 * Adds proper og:video and twitter:player meta tags for video posts
 * to enable rich video previews in social media shares.
 *
 * @package retrotube-child
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_video_og_log_once')) {
    /**
     * Log a message once per request.
     *
     * @param string $message Log message.
     */
    function tmw_video_og_log_once(string $message): void {
        static $logged = [];

        if (!empty($logged[$message])) {
            return;
        }

        $logged[$message] = true;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-VIDEO-OG] ' . $message); }
        }
    }
}

if (!function_exists('tmw_video_og_get_embed_url')) {
    /**
     * Get the video embed URL for a post.
     *
     * @param int $post_id Post ID.
     * @return string|null
     */
    function tmw_video_og_get_embed_url(int $post_id): ?string {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        // Use the schema function if available
        if (function_exists('tmw_video_schema_extract_embed_url')) {
            return tmw_video_schema_extract_embed_url($post);
        }

        // Fallback extraction
        $meta_keys = ['video_embed_url', 'video_url', 'embed_url', 'wpslj_stream'];
        foreach ($meta_keys as $key) {
            $value = get_post_meta($post_id, $key, true);
            if (!empty($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                return esc_url_raw($value);
            }
        }

        return null;
    }
}

if (!function_exists('tmw_video_og_get_video_dimensions')) {
    /**
     * Get video dimensions (width/height).
     *
     * @param int $post_id Post ID.
     * @return array ['width' => int, 'height' => int]
     */
    function tmw_video_og_get_video_dimensions(int $post_id): array {
        // Try to get from meta
        $width = (int) get_post_meta($post_id, 'video_width', true);
        $height = (int) get_post_meta($post_id, 'video_height', true);

        // Default to 16:9 ratio
        if ($width <= 0) {
            $width = 1280;
        }
        if ($height <= 0) {
            $height = 720;
        }

        return [
            'width'  => $width,
            'height' => $height,
        ];
    }
}

if (!function_exists('tmw_video_og_get_duration_seconds')) {
    /**
     * Get video duration in seconds.
     *
     * @param int $post_id Post ID.
     * @return int|null
     */
    function tmw_video_og_get_duration_seconds(int $post_id): ?int {
        $duration = get_post_meta($post_id, 'duration', true);
        if (empty($duration)) {
            return null;
        }

        // Already seconds
        if (is_numeric($duration)) {
            return (int) $duration;
        }

        // Parse HH:MM:SS or MM:SS format
        if (preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $duration, $matches)) {
            $hours = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];
            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        return null;
    }
}

// ============================================================================
// OPENGRAPH VIDEO META TAGS
// ============================================================================

/**
 * Add og:video meta tags via Rank Math filter.
 */
add_action('rank_math/opengraph/facebook', function () {
    if (!is_singular(['post', 'video'])) {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }

    $embed_url = tmw_video_og_get_embed_url($post_id);
    if (!$embed_url) {
        return;
    }

    $dimensions = tmw_video_og_get_video_dimensions($post_id);
    $duration = tmw_video_og_get_duration_seconds($post_id);

    // Output OpenGraph video tags
    echo '<meta property="og:type" content="video.other" />' . "\n";
    echo '<meta property="og:video" content="' . esc_url($embed_url) . '" />' . "\n";
    echo '<meta property="og:video:secure_url" content="' . esc_url($embed_url) . '" />' . "\n";
    echo '<meta property="og:video:type" content="text/html" />' . "\n";
    echo '<meta property="og:video:width" content="' . esc_attr($dimensions['width']) . '" />' . "\n";
    echo '<meta property="og:video:height" content="' . esc_attr($dimensions['height']) . '" />' . "\n";

    if ($duration !== null) {
        echo '<meta property="video:duration" content="' . esc_attr($duration) . '" />' . "\n";
    }

    // Release date
    $release_date = get_post_time('c', true, $post_id);
    if ($release_date) {
        echo '<meta property="video:release_date" content="' . esc_attr($release_date) . '" />' . "\n";
    }

    // Tags
    $tags = get_the_tags($post_id);
    if (!empty($tags) && !is_wp_error($tags)) {
        foreach (array_slice($tags, 0, 5) as $tag) {
            echo '<meta property="video:tag" content="' . esc_attr($tag->name) . '" />' . "\n";
        }
    }

    tmw_video_og_log_once('Output OpenGraph video meta for post ' . $post_id . '.');
}, 30);

// ============================================================================
// TWITTER PLAYER CARD
// ============================================================================

/**
 * Add Twitter Player card meta tags.
 */
add_action('rank_math/opengraph/twitter', function () {
    if (!is_singular(['post', 'video'])) {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }

    $embed_url = tmw_video_og_get_embed_url($post_id);
    if (!$embed_url) {
        return;
    }

    $dimensions = tmw_video_og_get_video_dimensions($post_id);

    // Override card type to player
    echo '<meta name="twitter:card" content="player" />' . "\n";
    echo '<meta name="twitter:player" content="' . esc_url($embed_url) . '" />' . "\n";
    echo '<meta name="twitter:player:width" content="' . esc_attr($dimensions['width']) . '" />' . "\n";
    echo '<meta name="twitter:player:height" content="' . esc_attr($dimensions['height']) . '" />' . "\n";

    tmw_video_og_log_once('Output Twitter player card meta for post ' . $post_id . '.');
}, 30);

// ============================================================================
// OVERRIDE RANK MATH OG TYPE FOR VIDEOS
// ============================================================================

/**
 * Set og:type to video.other for video posts.
 */
add_filter('rank_math/opengraph/facebook/og_type', function ($type) {
    if (!is_singular(['post', 'video'])) {
        return $type;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return $type;
    }

    $embed_url = tmw_video_og_get_embed_url($post_id);
    if (!$embed_url) {
        return $type;
    }

    return 'video.other';
}, 20);

/**
 * Ensure video image is used as og:image.
 */
add_filter('rank_math/opengraph/facebook/image', function ($image) {
    if (!is_singular(['post', 'video'])) {
        return $image;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return $image;
    }

    // Check if this is actually a video post
    $embed_url = tmw_video_og_get_embed_url($post_id);
    if (!$embed_url) {
        return $image;
    }

    // Try to get custom thumb from meta first
    $thumb = get_post_meta($post_id, 'thumb', true);
    if (!empty($thumb) && filter_var($thumb, FILTER_VALIDATE_URL)) {
        return esc_url_raw($thumb);
    }

    // Fallback to featured image
    $featured = get_the_post_thumbnail_url($post_id, 'large');
    if (!empty($featured)) {
        return $featured;
    }

    return $image;
}, 15);
