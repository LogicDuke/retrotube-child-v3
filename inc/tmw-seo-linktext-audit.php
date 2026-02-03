<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_seo_linktext_audit_enabled')) {
    function tmw_seo_linktext_audit_enabled(): bool {
        if (!is_user_logged_in()) { return false; }
        if (!current_user_can('manage_options')) { return false; }
        if (!isset($_GET['tmw_seo']) || $_GET['tmw_seo'] !== '1') { return false; }
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) { return false; }
        if (function_exists('is_feed') && is_feed()) { return false; }
        return true;
    }
}

if (!function_exists('tmw_seo_linktext_audit_log')) {
    function tmw_seo_linktext_audit_log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-SEO-LINKTEXT] ' . $message); }
    }
}

// Globals (single include; safe for this audit helper).
$GLOBALS['tmw_seo_linktext_audit_buffer']   = '';
$GLOBALS['tmw_seo_linktext_audit_started']  = false;
$GLOBALS['tmw_seo_linktext_audit_ob_level'] = 0;
$GLOBALS['tmw_seo_linktext_audit_truncated'] = false;

add_action('template_redirect', function () {
    if (!tmw_seo_linktext_audit_enabled()) {
        return;
    }

    $GLOBALS['tmw_seo_linktext_audit_started']  = true;
    $GLOBALS['tmw_seo_linktext_audit_ob_level'] = ob_get_level();

    ob_start(function ($buffer) {
        // Cap capture to avoid memory spikes (2MB is plenty for link detection).
        $max = 2 * 1024 * 1024;

        if (!$GLOBALS['tmw_seo_linktext_audit_truncated']) {
            $current = strlen($GLOBALS['tmw_seo_linktext_audit_buffer']);
            $remaining = $max - $current;

            if ($remaining > 0) {
                $GLOBALS['tmw_seo_linktext_audit_buffer'] .= substr($buffer, 0, $remaining);
            }

            if (strlen($GLOBALS['tmw_seo_linktext_audit_buffer']) >= $max) {
                $GLOBALS['tmw_seo_linktext_audit_truncated'] = true;
                tmw_seo_linktext_audit_log('HTML capture truncated at 2MB (still sufficient for link audit).');
            }
        }

        return $buffer; // Never modify output.
    });
}, 0);

add_action('shutdown', function () {
    if (!tmw_seo_linktext_audit_enabled()) {
        return;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri  = $_SERVER['REQUEST_URI'] ?? '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $url = $host ? ($scheme . $host . $uri) : $uri;

    tmw_seo_linktext_audit_log('Audit URL: ' . $url);
    tmw_seo_linktext_audit_log('Theme: stylesheet=' . get_stylesheet() . ' template=' . get_template());

    $html = (string) ($GLOBALS['tmw_seo_linktext_audit_buffer'] ?? '');
    if ($html === '') {
        tmw_seo_linktext_audit_log('No HTML captured for audit.');
    } else {
        $injector_tokens = array(
            'fundingchoices',
            'googlefc',
            'consent.google',
            '__tcfapi',
            'cookie',
            'cmp',
        );

        $found_injectors = array();
        foreach ($injector_tokens as $token) {
            if (stripos($html, $token) !== false) {
                $found_injectors[] = $token;
            }
        }

        tmw_seo_linktext_audit_log(
            $found_injectors
                ? ('Injector tokens found: ' . implode(', ', $found_injectors))
                : 'No injector tokens found.'
        );

        $found = array();

        // Prefer DOM parsing; fallback to regex if DOM fails.
        if (class_exists('DOMDocument')) {
            $previous_setting = libxml_use_internal_errors(true);

            $dom = new DOMDocument();
            // Help DOM treat content as UTF-8 (prevents weird textContent).
            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);

            libxml_clear_errors();
            libxml_use_internal_errors($previous_setting);

            if ($loaded) {
                foreach ($dom->getElementsByTagName('a') as $anchor) {
                    $href = $anchor->getAttribute('href');
                    if ($href && stripos($href, 'technologies/cookies') !== false) {
                        $markup = trim($dom->saveHTML($anchor));
                        $text   = trim($anchor->textContent);
                        $found[] = array('markup' => $markup, 'text' => ($text !== '' ? $text : '(empty)'));
                    }
                }
            } else {
                tmw_seo_linktext_audit_log('DOMDocument loadHTML failed; falling back to regex scan.');
            }
        }

        if (!$found) {
            // Regex fallback: capture simple <a ...href*="technologies/cookies"...>...</a>
            if (preg_match_all('/<a\b[^>]*href=["\"][^"\"]*technologies\/cookies[^"\"]*["\"][^>]*>.*?<\/a>/is', $html, $m)) {
                foreach ($m[0] as $raw) {
                    $text = trim(strip_tags($raw));
                    $found[] = array('markup' => trim($raw), 'text' => ($text !== '' ? $text : '(empty)'));
                }
            }
        }

        if (!$found) {
            tmw_seo_linktext_audit_log('No <a> elements matched technologies/cookies.');
        } else {
            tmw_seo_linktext_audit_log('Matched technologies/cookies anchors: ' . count($found));
            foreach ($found as $row) {
                tmw_seo_linktext_audit_log('Anchor markup: ' . $row['markup']);
                tmw_seo_linktext_audit_log('Anchor text: ' . $row['text']);
            }
        }
    }

    // IMPORTANT: only flush buffers started AFTER our recorded level (avoid breaking other buffers).
    if (!empty($GLOBALS['tmw_seo_linktext_audit_started'])) {
        $target_level = (int) ($GLOBALS['tmw_seo_linktext_audit_ob_level'] ?? 0);
        while (ob_get_level() > $target_level) {
            @ob_end_flush();
        }
    }
}, 0);
