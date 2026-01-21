<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detect whether the current request is for a login/registration screen.
 */
function tmw_perf_is_login_page(): bool {
    if (!isset($GLOBALS['pagenow'])) {
        return false;
    }

    return in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php'], true);
}

/**
 * Determine whether frontend performance hooks should run for this request.
 */
function tmw_perf_should_run(): bool {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return false;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }

    if (tmw_perf_is_login_page()) {
        return false;
    }

    return true;
}

/**
 * Decide if third-party deferral should run on this request.
 */
function tmw_perf_should_delay_thirdparty(): bool {
    return tmw_perf_should_run() && is_singular('model');
}

/**
 * Case-insensitive substring matcher for asset source URLs.
 *
 * @param string $src Source URL to check.
 * @param array  $needles Substrings to match.
 */
function tmw_perf_src_matches(string $src, array $needles): bool {
    foreach ($needles as $needle) {
        if (stripos($src, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * AGGRESSIVELY dequeue VideoJS on ALL non-video pages.
 * Priority 9999 to ensure it runs LAST.
 */
add_action('wp_enqueue_scripts', function () {
    if (!tmw_perf_should_run() || is_singular('video')) {
        return;
    }

    $videojs_scripts = [
        'video-js',
        'videojs',
        'videojs-core',
        'videojs-quality',
        'videojs-quality-selector',
        'retrotube-videojs',
        'wpst-videojs',
    ];
    $videojs_patterns = [
        'videojs',
        'video-js',
        'vjs.zencdn.net',
        'videojs.com',
        'videojs.net',
        '/video.js',
        '/video.min.js',
    ];

    global $wp_scripts;
    if ($wp_scripts instanceof WP_Scripts) {
        foreach ($wp_scripts->registered as $handle => $script) {
            $src = $script->src ?? '';
            if (
                in_array($handle, $videojs_scripts, true)
                || tmw_perf_src_matches($src, $videojs_patterns)
            ) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
        }
    }

    global $wp_styles;
    if ($wp_styles instanceof WP_Styles) {
        foreach ($wp_styles->registered as $handle => $style) {
            $src = $style->src ?? '';
            if (tmw_perf_src_matches($src, $videojs_patterns)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }
}, 9999);

/**
 * Replace select third-party script tags with deferred placeholders.
 *
 * @param string $tag Script tag.
 * @param string $handle Script handle.
 * @param string $src Script source URL.
 * @return string
 */
function tmw_perf_defer_thirdparty_script_tag(string $tag, string $handle, string $src): string {
    if (!tmw_perf_should_delay_thirdparty()) {
        return $tag;
    }

    $targets = [
        'googletagmanager.com/gtag/js',
        'pagead2.googlesyndication.com/pagead/js/adsbygoogle.js',
        'connect.facebook.net',
    ];

    if (!tmw_perf_src_matches($src, $targets)) {
        return $tag;
    }

    if (stripos($src, 'connect.facebook.net') !== false && stripos($src, '/sdk.js') === false) {
        return $tag;
    }

    $attrs = '';
    if (preg_match('/\scrossorigin(=(["\"]).*?\2)?/i', $tag, $match)) {
        $attrs .= ' ' . trim($match[0]);
    }
    if (preg_match('/\sreferrerpolicy=(["\"]).*?\1/i', $tag, $match)) {
        $attrs .= ' ' . trim($match[0]);
    }
    if (preg_match('/\snonce=(["\"]).*?\1/i', $tag, $match)) {
        $attrs .= ' ' . trim($match[0]);
    }
    if (preg_match('/\sintegrity=(["\"]).*?\1/i', $tag, $match)) {
        $attrs .= ' ' . trim($match[0]);
    }

    return sprintf(
        '<script type="text/tmw-deferred" data-src="%s"%s></script>',
        esc_url($src),
        $attrs
    );
}

/**
 * Enqueue the deferred third-party loader when needed.
 */
function tmw_perf_enqueue_thirdparty_loader(): void {
    if (!tmw_perf_should_delay_thirdparty()) {
        return;
    }

    $path = get_stylesheet_directory() . '/js/tmw-thirdparty-delay.js';
    if (!file_exists($path)) {
        return;
    }

    $filemtime = filemtime($path);
    $version = $filemtime === false ? null : (string) $filemtime;

    wp_enqueue_script(
        'tmw-thirdparty-delay',
        get_stylesheet_directory_uri() . '/js/tmw-thirdparty-delay.js',
        [],
        $version,
        true
    );
}

add_filter('script_loader_tag', 'tmw_perf_defer_thirdparty_script_tag', 10, 3);
add_action('wp_enqueue_scripts', 'tmw_perf_enqueue_thirdparty_loader', 20);
