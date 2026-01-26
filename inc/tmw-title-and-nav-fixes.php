<?php
if (!defined('ABSPATH')) { exit; }

/**
 * [TMW-TITLE-FIX] Ensure model archive titles don't leak single-post titles.
 */
function tmw_models_archive_title(): string
{
    $archive_title = post_type_archive_title('', false);
    $archive_title = $archive_title !== '' ? $archive_title : 'Models';
    $separator = apply_filters('document_title_separator', '-');
    $separator = trim((string) $separator);
    if ($separator === '') {
        $separator = '-';
    }
    $site_name = get_bloginfo('name');
    $title = trim($archive_title) . ' ' . $separator . ' ' . $site_name;

    if (defined('WP_DEBUG') && WP_DEBUG) {
        static $logged = false;
        if (!$logged) {
            error_log('[TMW-TITLE-FIX] models_archive_title="' . $title . '"');
            $logged = true;
        }
    }

    return $title;
}

add_filter('rank_math/frontend/title', function ($title) {
    if (!is_post_type_archive('model')) {
        return $title;
    }

    return tmw_models_archive_title();
}, 999);

add_filter('pre_get_document_title', function ($title) {
    if (!is_post_type_archive('model')) {
        return $title;
    }

    return tmw_models_archive_title();
}, 999);

/**
 * [TMW-NAV-ICON] Add star icon to Models menu item.
 */
add_filter('nav_menu_item_title', function ($title, $item, $args, $depth) {
    if (is_object($args) && !empty($args->theme_location)) {
        $location = (string) $args->theme_location;
        if (!in_array($location, ['primary', 'main'], true)) {
            return $title;
        }
    }

    $type = isset($item->type) ? (string) $item->type : '';
    $object = isset($item->object) ? (string) $item->object : '';
    $matches_models_item = ($type === 'post_type_archive' && $object === 'model');

    if (!$matches_models_item) {
        $url = isset($item->url) ? (string) $item->url : '';
        $path = $url !== '' ? wp_parse_url($url, PHP_URL_PATH) : '';
        $path = trim((string) $path, '/');
        if ($path !== '') {
            $segments = explode('/', $path);
            $last = strtolower((string) end($segments));
            $matches_models_item = ($last === 'models');
        }
    }

    if (!$matches_models_item) {
        return $title;
    }

    if (strpos($title, '★') !== false || stripos($title, '<i') !== false) {
        return $title;
    }

    return '<span class="tmw-star" aria-hidden="true">★</span> ' . $title;
}, 10, 4);
