<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Front-end performance trims scoped primarily to the homepage.
 */

/**
 * Remove jQuery migrate unless explicitly required.
 */
add_action('wp_default_scripts', function ($scripts) {
    if (!($scripts instanceof WP_Scripts)) {
        return;
    }

    if (!empty($scripts->registered['jquery'])) {
        $scripts->registered['jquery']->deps = array_diff(
            (array) $scripts->registered['jquery']->deps,
            ['jquery-migrate']
        );
    }
});

/**
 * Defer non-critical styles (homepage only) while keeping critical CSS render-blocking.
 *
 * This attempts to reduce render-blocking styles by using the "preload + onload" pattern
 * for non-critical styles, falling back to regular stylesheet for others.
 */
add_filter('style_loader_tag', function ($html, $handle, $href, $media) {
    if (is_admin()) {
        return $html;
    }

    if (!is_front_page() && !is_home()) {
        return $html;
    }

    // Safety: ensure we have a proper href to work with.
    if (empty($href) || !is_string($href)) {
        return $html;
    }

    // Critical styles that must remain render-blocking.
    // NOTE: Keep this list conservative.
    $critical_handles = [
        'retrotube-parent',
        'retrotube-child-style',
        'rt-child-flip',
        // Font Awesome must load immediately - icons (including social icons in top bar) break otherwise.
        // Keeping FA render-blocking avoids missing glyphs if optimization reorders styles.
        'font-awesome',
        'fontawesome',
        'fontawesome-all',
        'wpst-font-awesome',
        'retrotube-fontawesome',
        'wpst-fontawesome',
        'wpst-fontawesome-all',
        'fa',
        'fa-css',
    ];

    if (in_array($handle, $critical_handles, true)) {
        return $html;
    }

    // Also check the href URL for Font Awesome references (handles Autoptimize aggregation)
    $href_lower = strtolower($href);
    if (strpos($href_lower, 'font-awesome') !== false || strpos($href_lower, 'fontawesome') !== false) {
        return $html;
    }

    // Only defer for styles that are actually enqueued as stylesheets.
    // If $html doesn't look like a stylesheet tag, bail.
    if (stripos($html, "rel='stylesheet'") === false && stripos($html, 'rel="stylesheet"') === false) {
        return $html;
    }

    // Avoid double-deferring if already modified elsewhere.
    if (stripos($html, "rel='preload'") !== false || stripos($html, 'rel="preload"') !== false) {
        return $html;
    }

    // Keep media attribute if present; default to 'all'.
    $media_attr = '';
    if (!empty($media) && is_string($media) && strtolower($media) !== 'all') {
        $media_attr = ' media="' . esc_attr($media) . '"';
    }

    $preload = '<link rel="preload" as="style" href="' . esc_url($href) . '" onload="this.onload=null;this.rel=\'stylesheet\'' . '" />';
    $noscript = '<noscript><link rel="stylesheet" href="' . esc_url($href) . '"' . $media_attr . ' /></noscript>';

    // If original tag has an id attribute, preserve it on the noscript tag.
    // We do not preserve it on the preload tag to avoid duplicate IDs.
    if (preg_match('/\sid=[\'"]([^\'"]+)[\'"]/', $html, $m)) {
        $noscript = '<noscript><link rel="stylesheet" id="' . esc_attr($m[1]) . '" href="' . esc_url($href) . '"' . $media_attr . ' /></noscript>';
    }

    return $preload . $noscript;
}, 10, 4);

/**
 * Remove emoji scripts/styles on the front-end.
 */
add_action('init', function () {
    if (is_admin()) {
        return;
    }

    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
});

/**
 * Strip query strings from static resources for better cacheability.
 */
add_filter('script_loader_src', function ($src) {
    if (is_admin()) {
        return $src;
    }

    if (strpos($src, '?ver=') !== false) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}, 15);

add_filter('style_loader_src', function ($src) {
    if (is_admin()) {
        return $src;
    }

    if (strpos($src, '?ver=') !== false) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}, 15);

/**
 * Add preconnect hints for common third-party domains.
 */
add_filter('wp_resource_hints', function ($hints, $relation_type) {
    if (is_admin()) {
        return $hints;
    }

    if ($relation_type !== 'preconnect') {
        return $hints;
    }

    $domains = [
        'https://fonts.gstatic.com',
        'https://fonts.googleapis.com',
    ];

    foreach ($domains as $domain) {
        if (!in_array($domain, $hints, true)) {
            $hints[] = $domain;
        }
    }

    return $hints;
}, 10, 2);

/**
 * Lazy-load images (frontend) by adding loading="lazy" where absent.
 * NOTE: WordPress already does this in many cases; keep conservative and non-destructive.
 */
add_filter('the_content', function ($content) {
    if (is_admin()) {
        return $content;
    }

    if (empty($content) || !is_string($content)) {
        return $content;
    }

    // Quick bail if no img tags.
    if (stripos($content, '<img') === false) {
        return $content;
    }

    // Add loading="lazy" only if missing.
    $content = preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
        $tag = $m[0];

        if (stripos($tag, ' loading=') !== false) {
            return $tag;
        }

        // Don't lazy-load above-the-fold likely elements if explicitly marked.
        if (stripos($tag, ' data-no-lazy') !== false) {
            return $tag;
        }

        // Insert loading="lazy" right after <img
        return preg_replace('/^<img\b/i', '<img loading="lazy"', $tag);
    }, $content);

    return $content;
}, 20);

/**
 * Add decoding="async" to images where absent (non-destructive).
 */
add_filter('the_content', function ($content) {
    if (is_admin()) {
        return $content;
    }

    if (empty($content) || !is_string($content)) {
        return $content;
    }

    if (stripos($content, '<img') === false) {
        return $content;
    }

    $content = preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
        $tag = $m[0];

        if (stripos($tag, ' decoding=') !== false) {
            return $tag;
        }

        return preg_replace('/^<img\b/i', '<img decoding="async"', $tag);
    }, $content);

    return $content;
}, 21);

/**
 * Add fetchpriority hints to likely LCP images on home if possible (non-destructive).
 * Only applies if the theme or content uses a hero image with a known class.
 */
add_filter('the_content', function ($content) {
    if (is_admin()) {
        return $content;
    }

    if (!is_front_page() && !is_home()) {
        return $content;
    }

    if (empty($content) || !is_string($content)) {
        return $content;
    }

    if (stripos($content, '<img') === false) {
        return $content;
    }

    // Add fetchpriority="high" to the first image that looks like a hero or banner.
    $did = false;

    $content = preg_replace_callback('/<img\b[^>]*>/i', function ($m) use (&$did) {
        $tag = $m[0];

        if ($did) {
            return $tag;
        }

        // Already has fetchpriority
        if (stripos($tag, ' fetchpriority=') !== false) {
            $did = true;
            return $tag;
        }

        // Heuristic: hero/banner class names
        $lower = strtolower($tag);
        if (
            strpos($lower, 'hero') !== false ||
            strpos($lower, 'banner') !== false ||
            strpos($lower, 'featured') !== false ||
            strpos($lower, 'tmw-hero') !== false ||
            strpos($lower, 'tmw-banner') !== false
        ) {
            $did = true;
            return preg_replace('/^<img\b/i', '<img fetchpriority="high"', $tag);
        }

        return $tag;
    }, $content);

    return $content;
}, 22);

/**
 * Normalize HTML output for images with missing width/height attributes by attempting to infer.
 * This can reduce CLS when images render without explicit dimensions.
 *
 * Non-destructive: only adds width/height if missing and if src contains known size patterns.
 */
add_filter('the_content', function ($content) {
    if (is_admin()) {
        return $content;
    }

    if (empty($content) || !is_string($content)) {
        return $content;
    }

    if (stripos($content, '<img') === false) {
        return $content;
    }

    // Use DOMDocument for safer parsing.
    $libxml_prev = libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $html = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $content . '</body></html>';

    if (!$doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        libxml_clear_errors();
        libxml_use_internal_errors($libxml_prev);
        return $content;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($libxml_prev);

    $imgs = $doc->getElementsByTagName('img');

    if (!$imgs || $imgs->length === 0) {
        return $content;
    }

    foreach ($imgs as $img) {
        if (!($img instanceof DOMElement)) {
            continue;
        }

        $width_attr = $img->getAttribute('width');
        $height_attr = $img->getAttribute('height');
        if ($width_attr !== '' && $height_attr !== '') {
            continue;
        }

        $src = $img->getAttribute('src');
        if (empty($src)) {
            continue;
        }

        // Try to parse "-{w}x{h}." pattern from filenames (WordPress thumbnails).
        if (preg_match('/-([0-9]{2,5})x([0-9]{2,5})\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $src, $m)) {
            $w = (int) $m[1];
            $h = (int) $m[2];
            if ($width_attr === '' && $w > 0) {
                $img->setAttribute('width', (string) $w);
            }
            if ($height_attr === '' && $h > 0) {
                $img->setAttribute('height', (string) $h);
            }
        }
    }

    // Extract body inner HTML.
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) {
        return $content;
    }

    $output = '';
    foreach ($body->childNodes as $child) {
        $output .= $doc->saveHTML($child);
    }

    return $output !== '' ? $output : $content;
}, 30);

/**
 * Replace wp_get_attachment_image() HTML to ensure decoding/async and loading/lazy are present.
 * This covers images rendered outside the_content.
 */
add_filter('wp_get_attachment_image_attributes', function ($attr) {
    if (is_admin()) {
        return $attr;
    }

    if (!isset($attr['decoding'])) {
        $attr['decoding'] = 'async';
    }

    if (!isset($attr['loading'])) {
        $attr['loading'] = 'lazy';
    }

    return $attr;
}, 20);

/**
 * Prevent WordPress from printing 'wp-block-library' CSS if Gutenberg is unused.
 * Keep conservative: only on front-end and only if the theme isn't using blocks.
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }

    // If you are using blocks, remove these lines.
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('wc-block-style');
}, 100);

/**
 * Reduce heartbeat frequency on the front-end.
 */
add_filter('heartbeat_settings', function ($settings) {
    if (is_admin()) {
        return $settings;
    }

    $settings['interval'] = 60; // seconds
    return $settings;
});

/**
 * Disable oEmbed discovery and related front-end requests.
 */
add_action('init', function () {
    if (is_admin()) {
        return;
    }

    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
});

/**
 * Optional: strip "type='text/javascript'" attributes (legacy).
 */
add_filter('script_loader_tag', function ($tag) {
    if (is_admin()) {
        return $tag;
    }

    // Remove type attribute if present.
    $tag = preg_replace('/\s+type=(["\'])text\/javascript\1/i', '', $tag);
    return $tag;
}, 20);

/**
 * Optional: strip "type='text/css'" attributes (legacy).
 */
add_filter('style_loader_tag', function ($tag) {
    if (is_admin()) {
        return $tag;
    }

    $tag = preg_replace('/\s+type=(["\'])text\/css\1/i', '', $tag);
    return $tag;
}, 20);

/**
 * Add rel=preload for the theme's main stylesheet on the homepage (optional).
 */
add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }

    if (!is_front_page() && !is_home()) {
        return;
    }

    $stylesheet_uri = get_stylesheet_uri();
    if (!empty($stylesheet_uri)) {
        echo '<link rel="preload" as="style" href="' . esc_url($stylesheet_uri) . '">' . "\n";
    }
}, 1);

/**
 * Avoid printing unnecessary adjacent posts links in <head>.
 */
add_action('init', function () {
    if (is_admin()) {
        return;
    }

    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
});

/**
 * Harden: remove WP generator tag.
 */
remove_action('wp_head', 'wp_generator');

/**
 * Basic: disable shortlink in <head>.
 */
remove_action('wp_head', 'wp_shortlink_wp_head', 10);

/**
 * Disable RSS feed links in head if not used.
 */
add_action('init', function () {
    if (is_admin()) {
        return;
    }

    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'feed_links_extra', 3);
});

/**
 * Ensure async/defer attributes for certain scripts on home (non-destructive).
 */
add_filter('script_loader_tag', function ($tag, $handle, $src) {
    if (is_admin()) {
        return $tag;
    }

    if (!is_front_page() && !is_home()) {
        return $tag;
    }

    if (empty($src) || !is_string($src)) {
        return $tag;
    }

    // Do not touch already async/defer scripts.
    if (stripos($tag, ' async') !== false || stripos($tag, ' defer') !== false) {
        return $tag;
    }

    // Conservative allowlist: add defer to known non-critical scripts.
    $defer_handles = [
        'comment-reply',
        'wp-embed',
    ];

    if (in_array($handle, $defer_handles, true)) {
        $tag = str_replace('></script>', ' defer></script>', $tag);
    }

    return $tag;
}, 10, 3);

/**
 * If HTML is fed in from a buffer rewrite pass, ensure it still outputs valid attributes.
 * This helper can be used by other modules.
 */
function tmw_perf_strip_duplicate_attr($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    // Remove duplicated 'loading' attributes.
    $html = preg_replace('/\sloading=(["\'])lazy\1\sloading=(["\'])lazy\2/i', ' loading="lazy"', $html);
    $html = preg_replace('/\sdecoding=(["\'])async\1\sdecoding=(["\'])async\2/i', ' decoding="async"', $html);

    return $html;
}

/**
 * Ensure images in arbitrary HTML fragments have sensible attributes (non-destructive).
 */
function tmw_perf_img_attrs_normalize_html($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }

    if (stripos($html, '<img') === false) {
        return $html;
    }

    $libxml_prev = libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $wrap = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';
    if (!$doc->loadHTML($wrap, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
        libxml_clear_errors();
        libxml_use_internal_errors($libxml_prev);
        return $html;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($libxml_prev);

    $imgs = $doc->getElementsByTagName('img');
    if (!$imgs || $imgs->length === 0) {
        return $html;
    }

    foreach ($imgs as $img) {
        if (!($img instanceof DOMElement)) {
            continue;
        }

        if ($img->getAttribute('loading') === '') {
            $img->setAttribute('loading', 'lazy');
        }
        if ($img->getAttribute('decoding') === '') {
            $img->setAttribute('decoding', 'async');
        }

        // width/height inference
        $width_attr = $img->getAttribute('width');
        $height_attr = $img->getAttribute('height');

        if ($width_attr !== '' && $height_attr !== '') {
            continue;
        }

        $src = $img->getAttribute('src');
        if (empty($src)) {
            continue;
        }

        if (preg_match('/-([0-9]{2,5})x([0-9]{2,5})\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $src, $m)) {
            $w = (int) $m[1];
            $h = (int) $m[2];
            if ($width_attr === '' && $w > 0) {
                $img->setAttribute('width', (string) $w);
            }
            if ($height_attr === '' && $h > 0) {
                $img->setAttribute('height', (string) $h);
            }
        }
    }

    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) {
        return $html;
    }

    $output = '';
    foreach ($body->childNodes as $child) {
        $output .= $doc->saveHTML($child);
    }

    return $output !== '' ? $output : $html;
}
