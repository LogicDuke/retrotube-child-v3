<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

/* === TMW Theme Prune Kit loader (v3.7.0) === */
add_action('after_setup_theme', function () {
    if (!is_user_logged_in() || !current_user_can('manage_options')) { return; }
    $tool = __DIR__ . '/inc/tools/tmw-prune-kit.php';
    if (file_exists($tool)) { require_once $tool; }
}, 99);

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

/**
 * RetroTube Child (Flipbox Edition) v2 — Bootstrap
 * v4.2.0: move logic into /inc without behavior change.
 */
define('TMW_CHILD_VERSION', '4.2.0');
define('TMW_CHILD_PATH', get_stylesheet_directory());
define('TMW_CHILD_URL',  get_stylesheet_directory_uri());

// Single include: all logic is now in /inc/bootstrap.php
require_once TMW_CHILD_PATH . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/tmw-tml-bridge.php';
require_once TMW_CHILD_PATH . '/inc/frontend/tmw-voting.php';

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
