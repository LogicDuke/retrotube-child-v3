<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_a11y_viewport_audit_is_enabled')) {
    function tmw_a11y_viewport_audit_is_enabled() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        return isset($_GET['tmw_a11y']) && $_GET['tmw_a11y'] === '1';
    }
}

if (!function_exists('tmw_a11y_viewport_audit_log')) {
    function tmw_a11y_viewport_audit_log($message) {
        error_log('[TMW-A11Y-VIEWPORT] ' . $message);
    }
}

if (tmw_a11y_viewport_audit_is_enabled()) {
    tmw_a11y_viewport_audit_log('Request URL: ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown'));
    tmw_a11y_viewport_audit_log('Timestamp: ' . current_time('mysql'));

    $theme = wp_get_theme();
    $parent_theme = $theme->parent();

    tmw_a11y_viewport_audit_log('get_stylesheet(): ' . get_stylesheet());
    tmw_a11y_viewport_audit_log('get_template(): ' . get_template());
    tmw_a11y_viewport_audit_log('get_stylesheet_directory(): ' . get_stylesheet_directory());
    tmw_a11y_viewport_audit_log('get_template_directory(): ' . get_template_directory());
    tmw_a11y_viewport_audit_log('Theme Name: ' . $theme->get('Name'));
    tmw_a11y_viewport_audit_log('Theme Version: ' . $theme->get('Version'));
    tmw_a11y_viewport_audit_log('Parent Theme Name: ' . ($parent_theme ? $parent_theme->get('Name') : 'none'));
    tmw_a11y_viewport_audit_log('Parent Theme Version: ' . ($parent_theme ? $parent_theme->get('Version') : 'none'));

    $tmw_a11y_viewport_check_file = function ($label, $path) {
        if (!file_exists($path)) {
            tmw_a11y_viewport_audit_log($label . ' header.php missing at ' . $path);
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            tmw_a11y_viewport_audit_log($label . ' header.php unreadable at ' . $path);
            return;
        }

        $has_viewport_single = stripos($contents, "name='viewport'") !== false;
        $has_viewport_double = stripos($contents, 'name="viewport"') !== false;
        $has_user_scalable = stripos($contents, 'user-scalable=0') !== false;
        $has_maximum_scale = stripos($contents, 'maximum-scale=1') !== false;

        tmw_a11y_viewport_audit_log($label . ' header.php contains name=\'viewport\': ' . ($has_viewport_single ? 'yes' : 'no'));
        tmw_a11y_viewport_audit_log($label . ' header.php contains name="viewport": ' . ($has_viewport_double ? 'yes' : 'no'));
        tmw_a11y_viewport_audit_log($label . ' header.php contains user-scalable=0: ' . ($has_user_scalable ? 'yes' : 'no'));
        tmw_a11y_viewport_audit_log($label . ' header.php contains maximum-scale=1: ' . ($has_maximum_scale ? 'yes' : 'no'));
    };

    $tmw_a11y_viewport_check_file('Child', trailingslashit(get_stylesheet_directory()) . 'header.php');
    $tmw_a11y_viewport_check_file('Parent', trailingslashit(get_template_directory()) . 'header.php');

    add_action('template_redirect', function () {
        if (!tmw_a11y_viewport_audit_is_enabled()) {
            return;
        }

        $GLOBALS['tmw_a11y_viewport_buffer'] = '';

        ob_start(function ($content) {
            $GLOBALS['tmw_a11y_viewport_buffer'] .= $content;
            return $content;
        });
    }, 0);

    add_action('shutdown', function () {
        if (!tmw_a11y_viewport_audit_is_enabled()) {
            return;
        }

        $buffer = isset($GLOBALS['tmw_a11y_viewport_buffer']) ? $GLOBALS['tmw_a11y_viewport_buffer'] : '';
        if ($buffer === '' && ob_get_length()) {
            $buffer = ob_get_contents();
        }

        $matches = [];
        preg_match_all('/<meta\\b[^>]*name\\s*=\\s*["\\\']viewport["\\\'][^>]*>/i', $buffer, $matches);
        $viewport_tags = isset($matches[0]) ? $matches[0] : [];

        tmw_a11y_viewport_audit_log('Viewport tags found: ' . count($viewport_tags));
        foreach ($viewport_tags as $tag) {
            tmw_a11y_viewport_audit_log('Viewport tag: ' . $tag);
        }

        tmw_a11y_viewport_audit_log('Final HTML contains user-scalable=0: ' . (stripos($buffer, 'user-scalable=0') !== false ? 'yes' : 'no'));
    }, 0);
}
