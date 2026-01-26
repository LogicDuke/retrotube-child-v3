<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

$tmw_seo_linktext_audit_enabled = function () {
    if (!is_user_logged_in()) {
        return false;
    }

    if (!current_user_can('manage_options')) {
        return false;
    }

    if (!isset($_GET['tmw_seo']) || $_GET['tmw_seo'] !== '1') {
        return false;
    }

    return true;
};

$tmw_seo_linktext_audit_log = function ($message) {
    error_log('[TMW-SEO-LINKTEXT] ' . $message);
};

$tmw_seo_linktext_audit_buffer = '';

add_action('template_redirect', function () use ($tmw_seo_linktext_audit_enabled, &$tmw_seo_linktext_audit_buffer) {
    if (!$tmw_seo_linktext_audit_enabled()) {
        return;
    }

    ob_start(function ($buffer) use (&$tmw_seo_linktext_audit_buffer) {
        $tmw_seo_linktext_audit_buffer .= $buffer;
        return $buffer;
    });
}, 0);

add_action('shutdown', function () use ($tmw_seo_linktext_audit_enabled, &$tmw_seo_linktext_audit_buffer, $tmw_seo_linktext_audit_log) {
    if (!$tmw_seo_linktext_audit_enabled()) {
        return;
    }

    $html = $tmw_seo_linktext_audit_buffer;
    if ($html === '' && ob_get_level() > 0) {
        $html = (string) ob_get_contents();
    }

    if ($html === '') {
        $tmw_seo_linktext_audit_log('No HTML captured for audit.');
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

        if ($found_injectors) {
            $tmw_seo_linktext_audit_log('Injector tokens found: ' . implode(', ', $found_injectors));
        } else {
            $tmw_seo_linktext_audit_log('No injector tokens found.');
        }

        $links = array();
        $previous_setting = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous_setting);

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            if ($href && stripos($href, 'technologies/cookies') !== false) {
                $links[] = $anchor;
            }
        }

        if (!$links) {
            $tmw_seo_linktext_audit_log('No <a> elements matched technologies/cookies.');
        }

        foreach ($links as $anchor) {
            $markup = trim($dom->saveHTML($anchor));
            $text = trim($anchor->textContent);
            $tmw_seo_linktext_audit_log('Anchor markup: ' . $markup);
            $tmw_seo_linktext_audit_log('Anchor text: ' . ($text !== '' ? $text : '(empty)'));
        }
    }

    if (ob_get_level() > 0) {
        @ob_end_flush();
    }
}, 0);
