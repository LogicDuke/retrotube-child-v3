<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Determine whether the current request should receive perf tweaks.
 *
 * @return bool
 */
function tmw_perf_mobile_is_target_request() {
    return !is_admin() && !is_user_logged_in() && is_singular('model');
}

/**
 * Check if a stylesheet URL should be async-loaded on model pages.
 *
 * @param string $href
 * @return bool
 */
function tmw_perf_mobile_should_async_css($href) {
    if (empty($href)) {
        return false;
    }

    $href = (string) $href;
    $theme_urls = array(
        get_stylesheet_directory_uri(),
        get_template_directory_uri(),
    );

    foreach ($theme_urls as $theme_url) {
        if ($theme_url && strpos($href, $theme_url) !== false) {
            return false;
        }
    }

    if (strpos($href, '/wp-content/cache/autoptimize/css/autoptimize_single_') !== false) {
        return true;
    }

    if (strpos($href, '/wp-content/plugins/') === false) {
        return false;
    }

    $keywords = array(
        'cookie',
        'consent',
        'gdpr',
        'gtranslate',
        'translate',
        'slot',
        'popup',
        'optin',
    );

    foreach ($keywords as $keyword) {
        if (stripos($href, $keyword) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Determine if a script source should be delayed.
 *
 * @param string $src
 * @return bool
 */
function tmw_perf_mobile_should_delay_src($src) {
    if (empty($src)) {
        return false;
    }

    $patterns = array(
        '#googletagmanager\.com/gtag/js#i',
        '#pagead2\.googlesyndication\.com/pagead/js/adsbygoogle\.js#i',
        '#connect\.facebook\.net/.*/sdk\.js#i',
        '#static\.cloudflareinsights\.com/#i',
        '#cdn\.gtranslate\.net/#i',
        '#vjs\.zencdn\.net/#i',
        '#jquery\.bxslider\.min\.js#i',
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $src)) {
            return true;
        }
    }

    return false;
}

/**
 * Parse HTML attributes from a tag string.
 *
 * @param string $html
 * @return array
 */
function tmw_perf_mobile_parse_attributes($html) {
    if (!preg_match('#<\w+\s+([^>]+)>#i', $html, $matches)) {
        return array();
    }

    $raw = $matches[1];
    $parsed = wp_kses_hair($raw, wp_allowed_protocols());
    $attrs = array();

    foreach ($parsed as $name => $data) {
        if (!is_array($data)) {
            continue;
        }
        $attrs[$name] = array_key_exists('value', $data) ? $data['value'] : '';
    }

    return $attrs;
}

/**
 * Build a tag attribute string from an array.
 *
 * @param array $attrs
 * @return string
 */
function tmw_perf_mobile_build_attributes($attrs) {
    $parts = array();

    foreach ($attrs as $name => $value) {
        $name = trim((string) $name);
        if ($name === '') {
            continue;
        }
        if ($value === '') {
            $parts[] = esc_attr($name);
            continue;
        }
        $parts[] = sprintf('%s="%s"', esc_attr($name), esc_attr($value));
    }

    return implode(' ', $parts);
}

add_filter('style_loader_tag', function ($html, $handle, $href, $media) {
    if (!tmw_perf_mobile_is_target_request()) {
        return $html;
    }

    if (!tmw_perf_mobile_should_async_css($href)) {
        return $html;
    }

    static $logged = false;
    if (!$logged) {
        $logged = true;
    }

    $attrs = tmw_perf_mobile_parse_attributes($html);
    $href_attr = esc_url($href);

    $preload_attrs = $attrs;
    $preload_attrs['rel'] = 'preload';
    $preload_attrs['as'] = 'style';
    $preload_attrs['href'] = $href_attr;
    $preload_attrs['data-tmw-async'] = '1';
    unset($preload_attrs['onload']);

    $noscript_attrs = $attrs;
    $noscript_attrs['rel'] = 'stylesheet';
    $noscript_attrs['href'] = $href_attr;
    unset($noscript_attrs['onload'], $noscript_attrs['as'], $noscript_attrs['data-tmw-async']);

    $preload = '<link ' . tmw_perf_mobile_build_attributes($preload_attrs) . '>';
    $noscript = '<noscript><link ' . tmw_perf_mobile_build_attributes($noscript_attrs) . '></noscript>';

    return $preload . $noscript;
}, 10, 4);

add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if (!tmw_perf_mobile_is_target_request()) {
        return $tag;
    }

    if (!tmw_perf_mobile_should_delay_src($src)) {
        return $tag;
    }

    $delayed_src = esc_url($src);
    $tag = preg_replace('#\s+src=(["\"]).*?\1#i', '', $tag);
    $tag = preg_replace('#\s+type=(["\"]).*?\1#i', '', $tag);

    return preg_replace(
        '#^<script\b#i',
        '<script type="text/plain" data-tmw-delay="1" data-src="' . $delayed_src . '"',
        $tag,
        1
    );
}, 10, 3);

/**
 * Rewrite third-party script tags in the output buffer for delayed loading.
 *
 * @param string $buffer
 * @return string
 */
function tmw_perf_mobile_buffer_rewrite_scripts($buffer) {
    $count = 0;

    $buffer = preg_replace_callback(
        '#<script\b([^>]*?)\bsrc=(["\'])([^"\']+)\2([^>]*)>\s*</script>#i',
        function ($matches) use (&$count) {
            $attrs = $matches[1] . ' ' . $matches[4];
            if (stripos($attrs, 'data-tmw-delay') !== false) {
                return $matches[0];
            }
            if (stripos($attrs, 'type="text/plain"') !== false || stripos($attrs, "type='text/plain'") !== false) {
                return $matches[0];
            }

            $src = $matches[3];
            if (!tmw_perf_mobile_should_delay_src($src)) {
                return $matches[0];
            }

            $count++;
            $delayed_src = esc_url($src);
            $attrs_clean = preg_replace('#\s+type=(["\"]).*?\1#i', '', $attrs);
            $attrs_clean = preg_replace('#\s+src=(["\"]).*?\1#i', '', $attrs_clean);

            return '<script type="text/plain" data-tmw-delay="1" data-src="' . $delayed_src . '" ' . trim($attrs_clean) . '></script>';
        },
        $buffer
    );

    if ($count > 0) {
    }

    return $buffer;
}

add_action('template_redirect', function () {
    if (!tmw_perf_mobile_is_target_request()) {
        return;
    }

    $GLOBALS['tmw_perf_mobile_buffer_started'] = true;
    ob_start('tmw_perf_mobile_buffer_rewrite_scripts');
}, PHP_INT_MIN);

add_action('shutdown', function () {
    if (empty($GLOBALS['tmw_perf_mobile_buffer_started'])) {
        return;
    }
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}, PHP_INT_MIN);

add_action('wp_enqueue_scripts', function () {
    if (!tmw_perf_mobile_is_target_request()) {
        return;
    }

    $src = get_stylesheet_directory_uri() . '/js/tmw-delay-loader.js';
    wp_enqueue_script('tmw-delay-loader', $src, array(), TMW_CHILD_VERSION, true);
}, 20);
