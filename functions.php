<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

// [TMW-LINK-GUARD] loader (v3.6.2)
// Remove legacy guards if Codex finds them:
/*
DELETE_BLOCK_START TMW-LINK-GUARD v<=3.6.1
DELETE_BLOCK_END
*/
$__codex_link_guard = __DIR__ . '/CODEX_video_link_guard.php';
if (file_exists($__codex_link_guard)) {
    require_once $__codex_link_guard;
}

// [TMW-FILTER-LINKS] loader (v3.6.4)
/*
DELETE_BLOCK_START TMW-FILTER-CANONICAL v<=3.6.3
// require_once __DIR__ . '/inc/tmw-filter-canonical.php';
DELETE_BLOCK_END
*/
if (!defined('ABSPATH')) { exit; }
$__tmw_filter_links = __DIR__ . '/inc/tmw-filter-links.php';
if (file_exists($__tmw_filter_links)) { require_once $__tmw_filter_links; }
$__tmw_filter_canonical = __DIR__ . '/inc/tmw-filter-canonical.php';
if (file_exists($__tmw_filter_canonical)) { require_once $__tmw_filter_canonical; }

/**
 * RetroTube Child (Flipbox Edition) v3 — Bootstrap
 * v4.2.1: logic moved into /inc (no behavior change).
 */
define('TMW_CHILD_VERSION', '4.2.1');
define('TMW_CHILD_PATH', get_stylesheet_directory());
define('TMW_CHILD_URL',  get_stylesheet_directory_uri());

require_once get_stylesheet_directory() . '/inc/breadcrumbs.php';
require_once get_stylesheet_directory() . '/inc/tmw-video-breadcrumbs.php';
require_once get_stylesheet_directory() . '/inc/tmw-a11y-viewport-audit.php';

// Single include: all logic is now in /inc/bootstrap.php
require_once TMW_CHILD_PATH . '/inc/bootstrap.php';
require_once get_stylesheet_directory() . '/inc/tmw-rankmath-category-pages.php';
require_once get_stylesheet_directory() . '/inc/tmw-rankmath-excluded-sanitizer.php';
require_once get_stylesheet_directory() . '/inc/tmw-rankmath-sanity.php';
require_once get_stylesheet_directory() . '/inc/tmw-rankmath-content-analysis-home.php';
if (defined('WP_DEBUG') && WP_DEBUG) {
    $tmw_rankmath_hook_audit = get_stylesheet_directory() . '/inc/tmw-rankmath-hook-audit.php';
    if (file_exists($tmw_rankmath_hook_audit)) { require_once $tmw_rankmath_hook_audit; }
    $tmw_model_pagination_audit = get_stylesheet_directory() . '/inc/tmw-model-pagination-audit.php';
    if (file_exists($tmw_model_pagination_audit)) { require_once $tmw_model_pagination_audit; }
}
require_once get_stylesheet_directory() . '/inc/tmw-seo-category-bridge.php';
require_once get_stylesheet_directory() . '/inc/tmw-seo-model-bridge.php';
require_once get_stylesheet_directory() . '/inc/tmw-model-schema.php';
require_once get_stylesheet_directory() . '/inc/tmw-video-schema.php';
require_once get_stylesheet_directory() . '/inc/tmw-video-opengraph.php';
require_once get_stylesheet_directory() . '/inc/tmw-archive-schema.php';
require_once __DIR__ . '/inc/tmw-tml-bridge.php';
require_once TMW_CHILD_PATH . '/inc/frontend/tmw-voting.php';
require_once get_stylesheet_directory() . '/inc/tmw-home-shortcodes.php';
require_once get_stylesheet_directory() . '/inc/tmw-title-and-nav-fixes.php';

// Ensure legacy experiments don't affect the default reset email contents.
remove_all_filters('retrieve_password_message');

// === TMW Reset URL normalizer (email message) ===
require_once __DIR__ . '/inc/tmw-reset-mail-url.php';

// TEMP: disable email activation module
// if (file_exists(get_stylesheet_directory() . '/inc/tmw-email-activation.php')) {
//     require_once get_stylesheet_directory() . '/inc/tmw-email-activation.php';
// }

// Load Codex Reports admin viewer (read-only)
if (is_admin()) {
    $viewer = get_stylesheet_directory() . '/inc/admin/codex-reports-viewer.php';
    if (file_exists($viewer)) { require_once $viewer; }
}

require_once get_stylesheet_directory() . '/inc/tmw-mail-fix.php';

// [TMW-BREADCRUMB-WP] Render breadcrumbs only after the main query is ready.
add_action('wp', function () {
    add_action('tmw_render_breadcrumbs', function () {
        if (!function_exists('wpst_breadcrumbs')) {
            return;
        }

        if (xbox_get_field_value('wpst-options', 'enable-breadcrumbs') != 'on') {
            return;
        }

        wpst_breadcrumbs();
    }, 20);
});

// [TMW-BREADCRUMB] Disable parent breadcrumb rendering on single videos.
add_action('wp', function () {
    if (!is_singular('video')) {
        return;
    }

    remove_all_actions('wpst_breadcrumbs');
}, 20);

add_action('wp_head', function () {
    if (!is_front_page()) {
        return;
    }

    if (!function_exists('tmw_child_front_page_lcp_image')) {
        return;
    }

    $lcp_image = tmw_child_front_page_lcp_image();
    if (empty($lcp_image['url'])) {
        return;
    }
    ?>
    <link rel="preload" as="image" href="<?php echo esc_url($lcp_image['url']); ?>">
    <?php
});

add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
    if (get_option('show_on_front') !== 'page' || !is_front_page()) {
        return $redirect_url;
    }

    $redirect_path = wp_parse_url($redirect_url, PHP_URL_PATH);
    $requested_path = wp_parse_url($requested_url, PHP_URL_PATH);

    if ($redirect_path === '/models/' && ($requested_path === '/' || $requested_path === '')) {
        return false;
    }

    return $redirect_url;
}, 10, 2);

add_action('wp_head', function () {
    if (!is_singular('model')) {
        return;
    }

    if (!function_exists('tmw_resolve_model_banner_url')) {
        return;
    }

    $model_id = get_queried_object_id();
    if (!$model_id) {
        return;
    }

    $banner_url = tmw_resolve_model_banner_url($model_id);
    if (!$banner_url) {
        return;
    }
    ?>
    <link rel="preload" as="image" href="<?php echo esc_url($banner_url); ?>" fetchpriority="high">
    <?php
}, 1);

// Ensure logo images retain explicit dimensions and async decoding without moving markup.
add_filter('get_custom_logo_image_attributes', function ($attrs, $custom_logo_id) {
    if (!$custom_logo_id) {
        return $attrs;
    }

    $meta = wp_get_attachment_metadata($custom_logo_id);
    if (is_array($meta)) {
        if (empty($attrs['width']) && !empty($meta['width'])) {
            $attrs['width'] = (int) $meta['width'];
        }
        if (empty($attrs['height']) && !empty($meta['height'])) {
            $attrs['height'] = (int) $meta['height'];
        }
    }

    if (!isset($attrs['loading'])) {
        $attrs['loading'] = 'lazy';
    }

    if (empty($attrs['decoding'])) {
        $attrs['decoding'] = 'async';
    }

    return $attrs;
}, 10, 2);

// Ensure grayscale logo variants keep explicit dimensions without altering templates.
add_filter('wp_get_attachment_image_attributes', function ($attr, $attachment, $size) {
    $classes = isset($attr['class']) ? (string) $attr['class'] : '';
    if (strpos($classes, 'custom-logo') === false && strpos($classes, 'grayscale') === false) {
        return $attr;
    }

    $attachment_id = is_object($attachment) && isset($attachment->ID) ? (int) $attachment->ID : (int) $attachment;
    if (!$attachment_id) {
        return $attr;
    }

    $meta = wp_get_attachment_metadata($attachment_id);
    if (is_array($meta)) {
        if (empty($attr['width']) && !empty($meta['width'])) {
            $attr['width'] = (int) $meta['width'];
        }

        if (empty($attr['height']) && !empty($meta['height'])) {
            $attr['height'] = (int) $meta['height'];
        }
    }

    if (empty($attr['decoding'])) {
        $attr['decoding'] = 'async';
    }

    return $attr;
}, 10, 3);

// Disable updates for the Retrotube parent theme
add_filter('site_transient_update_themes', function($value) {

    $theme_to_block = 'retrotube'; // parent theme folder name

    if (isset($value->response[$theme_to_block])) {
        unset($value->response[$theme_to_block]);
    }

    return $value;
});

add_action('wp', function () {
    if (!is_singular('video')) {
        return;
    }

    remove_all_actions('wpst_breadcrumbs');
    remove_all_actions('breadcrumb');
    remove_all_actions('breadcrumbs');
    remove_action('wpst_breadcrumbs', 'wpst_breadcrumbs');

    static $logged = false;
    if (!$logged) {
        error_log('[TMW-BREADCRUMB-VIDEO] Parent breadcrumbs disabled for single video (RankMath kept)');
        $logged = true;
    }
}, 9);
