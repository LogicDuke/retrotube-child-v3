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
 * [TMW-NAV-ICON] Add star icon to Models menu item.
 */
add_filter('walker_nav_menu_start_el', function ($item_output, $item, $depth, $args) {
    if (is_object($args) && !empty($args->theme_location)) {
        $location = (string) $args->theme_location;
        if (!in_array($location, ['primary', 'main', 'wpst-main-menu'], true)) {
            return $item_output;
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
        return $item_output;
    }

    if (stripos($item_output, 'fa-star') !== false
        || stripos($item_output, 'tmw-menu-star') !== false) {
        return $item_output;
    }

    // Detect ★ in any encoding form WordPress might use:
    // UTF-8 literal, decimal entity, hex entity.
    $star_patterns = ['★', '&#9733;', '&#x2605;', '&starf;'];
    $found_star = false;
    foreach ($star_patterns as $pattern) {
        if (stripos($item_output, $pattern) !== false) {
            $found_star = $pattern;
            break;
        }
    }

    if ($found_star !== false) {
        $item_output = str_replace(
            $found_star,
            '<span class="tmw-menu-star" aria-hidden="true">★</span>',
            $item_output
        );
        return $item_output;
    }

    $use_fa = function_exists('wp_style_is')
        && (wp_style_is('font-awesome', 'enqueued')
            || wp_style_is('fontawesome', 'enqueued')
            || wp_style_is('fa', 'enqueued'));

    $icon_markup = $use_fa
        ? '<i class="fa fa-star tmw-menu-star" aria-hidden="true"></i>'
        : '<span class="tmw-menu-star" aria-hidden="true">★</span>';

    $anchor_pos = strpos($item_output, '<a');
    if ($anchor_pos === false) {
        return $item_output;
    }

    $anchor_close = strpos($item_output, '>', $anchor_pos);
    if ($anchor_close === false) {
        return $item_output;
    }

    $insertion = ' ' . $icon_markup . ' ';
    $item_output = substr_replace($item_output, $insertion, $anchor_close + 1, 0);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(
            '[TMW-NAV-ICON] injected=models item_id=' . (int) $item->ID . ' url=' . (string) $item->url
        );
    }

    return $item_output;
}, 10, 4);
