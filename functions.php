<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

// Global frontend runtime guard: prevent expensive frontend boot on admin/AJAX/cron flows.
if (is_admin() || wp_doing_ajax() || defined('DOING_CRON')) {
    return;
}

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
DELETE_BLOCK_END
*/
$__tmw_filter_links = __DIR__ . '/inc/tmw-filter-links.php';
if (file_exists($__tmw_filter_links)) { require_once $__tmw_filter_links; }
$__tmw_filter_canonical = __DIR__ . '/inc/tmw-filter-canonical.php';
if (file_exists($__tmw_filter_canonical)) { require_once $__tmw_filter_canonical; }

/**
 * RetroTube Child (Flipbox Edition) v3 — Bootstrap
 * v4.2.2: production hardening (no behavior change).
 */
define('TMW_CHILD_VERSION', '4.2.4');
define('TMW_CHILD_PATH', get_stylesheet_directory());
define('TMW_CHILD_URL',  get_stylesheet_directory_uri());

require_once get_stylesheet_directory() . '/inc/breadcrumbs.php';
require_once get_stylesheet_directory() . '/inc/tmw-video-breadcrumbs.php';
// Dev-only audits: keep out of production for performance + cleanliness.
if (defined('WP_DEBUG') && WP_DEBUG) {
    require_once get_stylesheet_directory() . '/inc/tmw-a11y-viewport-audit.php';
}
require_once get_stylesheet_directory() . '/inc/tmw-a11y-link-names.php';
if (defined('WP_DEBUG') && WP_DEBUG) {
    require_once get_stylesheet_directory() . '/inc/tmw-seo-linktext-audit.php';
    require_once get_stylesheet_directory() . '/inc/tmw-seo-linktext-audit-js.php';
}
require_once get_stylesheet_directory() . '/inc/tmw-seo-linktext-fix.php';

// Single include: all logic is now in /inc/bootstrap.php
require_once TMW_CHILD_PATH . '/inc/bootstrap.php';
require_once get_stylesheet_directory() . '/inc/tmw-rankmath-category-pages.php';
require_once get_stylesheet_directory() . '/inc/tmw-rankmath-excluded-sanitizer.php';
require_once get_stylesheet_directory() . '/inc/tmw-rankmath-sanity.php';
require_once get_stylesheet_directory() . '/inc/tmw-rankmath-content-analysis-home.php';
require_once get_stylesheet_directory() . '/inc/tmw-rankmath-tag-archives.php';
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
require_once get_stylesheet_directory() . '/inc/blocks/tmw-home-accordion-block.php';
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

/**
 * Trash debugging instrumentation.
 *
 * Enable with WP_DEBUG=true and optional TMW_TRASH_DEBUG=true in wp-config.php.
 * Logs run into wp-content/debug.log when WP_DEBUG_LOG is enabled.
 */
if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options') && (!defined('TMW_TRASH_DEBUG') || TMW_TRASH_DEBUG)) {
    /**
     * Normalize callback labels for logging.
     *
     * @param mixed $callback Hook callback.
     */
    $tmw_format_hook_callback = static function ($callback): string {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback) && isset($callback[0], $callback[1])) {
            $target = is_object($callback[0]) ? get_class($callback[0]) : (string) $callback[0];

            return $target . '::' . $callback[1];
        }

        if ($callback instanceof Closure) {
            return 'Closure';
        }

        if (is_object($callback) && method_exists($callback, '__invoke')) {
            return get_class($callback) . '::__invoke';
        }

        return 'Unknown callback';
    };

    /**
     * Log every registered callback attached to a hook.
     */
    $tmw_log_hook_callbacks = static function (string $hook_name) use ($tmw_format_hook_callback): void {
        global $wp_filter;

        if (!isset($wp_filter[$hook_name])) {
            error_log('[TRASH DEBUG] No callbacks registered for hook: ' . $hook_name);

            return;
        }

        $hook = $wp_filter[$hook_name];
        if (!($hook instanceof WP_Hook)) {
            error_log('[TRASH DEBUG] Hook registry is not a WP_Hook for: ' . $hook_name);

            return;
        }

        foreach ($hook->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $entry) {
                $accepted_args = isset($entry['accepted_args']) ? (int) $entry['accepted_args'] : 1;
                $label = isset($entry['function']) ? $tmw_format_hook_callback($entry['function']) : 'Unknown callback';

                error_log(sprintf('[TRASH DEBUG] Hook %s priority=%d accepted_args=%d callback=%s', $hook_name, (int) $priority, $accepted_args, $label));
            }
        }
    };

    $tmw_trash_trace_started_at = microtime(true);
    add_action('current_screen', static function ($screen) use ($tmw_log_hook_callbacks): void {
        if (!($screen instanceof WP_Screen)) {
            return;
        }

        $is_trash_request = isset($_GET['action']) && $_GET['action'] === 'trash';
        if (!$is_trash_request) {
            return;
        }

        error_log('[TRASH DEBUG] Entering trash flow on screen=' . $screen->id);
        $tmw_log_hook_callbacks('pre_trash_post');
        $tmw_log_hook_callbacks('trashed_post');
        $tmw_log_hook_callbacks('before_delete_post');
    });

    add_filter('pre_trash_post', static function ($override, $post) {
        $post_id = is_object($post) && isset($post->ID) ? (int) $post->ID : 0;
        error_log('[TRASH DEBUG] pre_trash_post post_id=' . $post_id . ' override=' . var_export($override, true));

        return $override;
    }, 10, 2);

    add_action('trashed_post', static function ($post_id) {
        error_log('[TRASH DEBUG] trashed_post post_id=' . (int) $post_id);
    });

    add_action('before_delete_post', static function ($post_id, $post) {
        $post_type = is_object($post) && isset($post->post_type) ? $post->post_type : 'unknown';
        error_log('[TRASH DEBUG] before_delete_post post_id=' . (int) $post_id . ' post_type=' . $post_type);
    }, 10, 2);

    add_action('shutdown', static function () use ($tmw_trash_trace_started_at): void {
        $is_trash_request = isset($_GET['action']) && $_GET['action'] === 'trash';
        if (!$is_trash_request) {
            return;
        }

        $elapsed = microtime(true) - $tmw_trash_trace_started_at;
        $peak_mb = memory_get_peak_usage(true) / 1048576;
        error_log(sprintf('[TRASH DEBUG] shutdown elapsed=%.3fs peak_memory=%.2fMB', $elapsed, $peak_mb));
    });
}

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
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-BREADCRUMB-VIDEO] Parent breadcrumbs disabled for single video (RankMath kept)'); }
        $logged = true;
    }
}, 9);
