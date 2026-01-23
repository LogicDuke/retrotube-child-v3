<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_rm_excluded_audit_should_log')) {
    function tmw_rm_excluded_audit_should_log(): bool
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return isset($_GET['tmw_rm_excl_audit']) && $_GET['tmw_rm_excl_audit'] === '1';
    }
}

if (!function_exists('tmw_rm_excluded_audit_log_stage')) {
    function tmw_rm_excluded_audit_log_stage(array $types, string $stage, int $priority): void
    {
        if (!tmw_rm_excluded_audit_should_log()) {
            return;
        }

        $has_category_page = in_array('category_page', $types, true) ? 1 : 0;
        $list = implode(',', $types);
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        error_log(sprintf(
            '[TMW-RM-EXCL-AUDIT] stage=%s priority=%d has_category_page=%d list=%s uri=%s',
            $stage,
            $priority,
            $has_category_page,
            $list,
            $uri
        ));
    }
}

add_filter('rank_math/excluded_post_types', function ($types) {
    if (is_array($types)) {
        tmw_rm_excluded_audit_log_stage($types, 'pre_mu', 0);
    }

    return $types;
}, 0);

add_filter('rank_math/excluded_post_types', function ($types) {
    if (is_array($types)) {
        tmw_rm_excluded_audit_log_stage($types, 'post_mu', 2);
    }

    return $types;
}, 2);

add_filter('rank_math/excluded_post_types', function ($types) {
    if (is_array($types)) {
        tmw_rm_excluded_audit_log_stage($types, 'post_theme', 1000);
    }

    return $types;
}, 1000);
