<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Inject an inline icon for the “Videos” and “Models” top-nav items.
 * Changes:
 * - No restriction by theme_location (works with any slug).
 * - Top-level items only (depth === 0) so footer/secondary menus stay untouched.
 * - Icon injected *inside* the <a> tag.
 * - Skips items that already contain an icon.
 * - Matches /videos (and /xx/videos/) by last path segment.
 * - Includes FA4 + FA5 classes for maximum compatibility.
 */
add_filter('walker_nav_menu_start_el', function ($item_output, $item, $depth, $args) {
    // Header menus are usually top-level items; ignore submenus.
    if ($depth !== 0) {
        return $item_output;
    }

    // If the item already contains an icon (<i> or <svg>), do nothing (avoid duplicates).
    if (stripos($item_output, '<i ') !== false || stripos($item_output, '<svg') !== false) {
        return $item_output;
    }

    // Robust detector: is this URL the "Videos" page?
    $is_videos = static function ($url): bool {
        if (!$url) return false;
        $home = trailingslashit(home_url('/'));
        $url  = preg_replace('#^' . preg_quote($home, '#') . '#i', '/', $url);
        $url  = strtok($url, '?#');
        $path = trim(urldecode((string) $url), '/');
        if ($path === '') return false;
        $segments = explode('/', $path);
        $last     = strtolower(end($segments));
        return ($last === 'videos');
    };

    $is_models = static function ($url): bool {
        if (!$url) return false;
        $home = trailingslashit(home_url('/'));
        $url  = preg_replace('#^' . preg_quote($home, '#') . '#i', '/', $url);
        $url  = strtok($url, '?#');
        $path = trim(urldecode((string) $url), '/');
        if ($path === '') return false;
        $segments = explode('/', $path);
        $last     = strtolower(end($segments));
        return ($last === 'models');
    };

    $item_url = $item->url ?? '';
    $matches_videos = $is_videos($item_url);
    $matches_models = $is_models($item_url);

    if (!$matches_videos && !$matches_models) {
        return $item_output;
    }

    // Find the opening <a ...> and inject right after its '>'.
    $a_open = stripos($item_output, '<a ');
    if ($a_open === false) {
        return $item_output; // unexpected markup; fail safe
    }
    $a_gt = strpos($item_output, '>', $a_open);
    if ($a_gt === false) {
        return $item_output; // unexpected markup; fail safe
    }

    // Both FA4 and FA5 classes; whichever stack is present will render.
    $icon_html = $matches_models
        ? '<i class="fa fa-star fas fa-star" aria-hidden="true" role="img"></i> '
        : '<i class="fa fa-video-camera fas fa-video" aria-hidden="true" role="img"></i> ';
    return substr($item_output, 0, $a_gt + 1) . $icon_html . substr($item_output, $a_gt + 1);
}, 10, 4);

add_filter('nav_menu_css_class', function ($classes, $item, $args, $depth) {
    // Top-level only (matches how the theme styles main nav)
    if ((int) $depth !== 0) {
        return $classes;
    }

    // Are we in the Models section?
    $is_models_context =
        is_page('models')
        || is_page_template('page-models-grid.php')
        || is_post_type_archive('model')
        || is_singular('model')
        || (function_exists('is_tax') && is_tax('models'));

    if (!$is_models_context) {
        return $classes;
    }

    // Normalize URL to check the last path segment.
    $url = isset($item->url) ? (string) $item->url : '';
    $home = trailingslashit(home_url('/'));
    $rel  = preg_replace('#^' . preg_quote($home, '#') . '#i', '/', $url);
    $rel  = strtok($rel, '?#');
    $path = trim(urldecode((string) $rel), '/');
    $segments = $path !== '' ? explode('/', $path) : [];
    $last = $segments ? strtolower((string) end($segments)) : '';

    $title = isset($item->title) ? strtolower(trim(wp_strip_all_tags((string) $item->title))) : '';

    // Match the Models menu item by URL or title.
    $matches_models_item = ($last === 'models') || ($title === 'models');

    if (!$matches_models_item) {
        return $classes;
    }

    foreach (['current-menu-item', 'current_page_item'] as $c) {
        if (!in_array($c, $classes, true)) {
            $classes[] = $c;
        }
    }

    return $classes;
}, 10, 4);
