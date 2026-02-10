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
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-TITLE-FIX] models_archive_title="' . $title . '"'); }
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
}, PHP_INT_MAX);

add_filter('pre_get_document_title', function ($title) {
    if (!is_post_type_archive('model')) {
        return $title;
    }

    return tmw_models_archive_title();
}, PHP_INT_MAX);

add_filter('document_title_parts', function ($parts) {
    if (!is_post_type_archive('model')) {
        return $parts;
    }

    $archive_title = post_type_archive_title('', false);
    $archive_title = $archive_title !== '' ? $archive_title : 'Models';
    $parts['title'] = $archive_title;
    $parts['site'] = get_bloginfo('name');
    unset($parts['tagline'], $parts['page']);
    return $parts;
}, PHP_INT_MAX);

/**
 * [TMW-FIX] Replace a leading literal star in the primary Models menu item
 * with the same FontAwesome icon style used by other header nav items.
 */
add_filter('nav_menu_item_title', function ($title, $item, $args, $depth) {
    if ((int) $depth !== 0 || !is_object($args)) {
        return $title;
    }

    $location = isset($args->theme_location) ? (string) $args->theme_location : '';
    if ($location !== 'wpst-main-menu') {
        return $title;
    }

    $item_title = isset($item->title) ? wp_strip_all_tags((string) $item->title) : '';
    $item_title = trim($item_title);
    $url = isset($item->url) ? (string) $item->url : '';
    $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
    $segments = $path !== '' ? explode('/', $path) : [];
    $last = $segments ? strtolower((string) end($segments)) : '';

    $is_models_item = ($item_title === '★ Models') || ($item_title === 'Models') || ($last === 'models');
    if (!$is_models_item) {
        return $title;
    }

    if (stripos($title, 'fa-star') !== false || stripos($title, 'tmw-menu-star') !== false) {
        return $title;
    }

    $clean_title = preg_replace('/^(?:★|&#9733;|&#x2605;|&starf;)\s*/u', '', (string) $title);
    $clean_title = ltrim((string) $clean_title);

    return '<i class="fa fa-star tmw-menu-star" aria-hidden="true"></i> ' . $clean_title;
}, 10, 4);
