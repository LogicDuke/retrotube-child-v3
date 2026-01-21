<?php
/**
 * Banner Performance Optimizations — v4.1.1
 */
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if URL is local (belongs to this WordPress site).
 */
function tmw_is_local_url(string $url): bool {
    if (empty($url)) {
        return false;
    }
    $site_host = parse_url(home_url(), PHP_URL_HOST);
    $image_host = parse_url($url, PHP_URL_HOST);
    if (!is_string($site_host) || $site_host === '') {
        return false;
    }
    if ($image_host === false || $image_host === null) {
        return false;
    }
    if ($image_host === '') {
        return true;
    }
    if (!is_string($image_host)) {
        return false;
    }
    return strcasecmp($site_host, $image_host) === 0;
}

/**
 * Cached attachment ID lookup. Skips external URLs entirely.
 */
function tmw_get_attachment_id_cached(string $url): int {
    if (empty($url) || !tmw_is_local_url($url)) {
        return 0;
    }

    $cache_key = 'tmw_att_' . md5($url);
    $cached = wp_cache_get($cache_key, 'tmw_banner');
    if ($cached !== false) {
        return (int) $cached;
    }

    $attachment_id = attachment_url_to_postid($url);
    wp_cache_set($cache_key, $attachment_id ?: 0, 'tmw_banner', HOUR_IN_SECONDS);
    return (int) $attachment_id;
}

/**
 * Fast image dimensions. Never calls getimagesize() on external URLs.
 */
function tmw_get_image_dimensions_fast(string $url, int $fallback_w = 1035, int $fallback_h = 350): array {
    $defaults = ['width' => $fallback_w, 'height' => $fallback_h];
    if (empty($url) || !tmw_is_local_url($url)) {
        return $defaults;
    }

    $attachment_id = tmw_get_attachment_id_cached($url);
    if ($attachment_id) {
        $meta = wp_get_attachment_metadata($attachment_id);
        if (!empty($meta['width']) && !empty($meta['height'])) {
            return ['width' => (int) $meta['width'], 'height' => (int) $meta['height']];
        }
    }
    return $defaults;
}
