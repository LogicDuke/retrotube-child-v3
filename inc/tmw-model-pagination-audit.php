<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_model_pagination_audit_log')) {
    function tmw_model_pagination_audit_log(string $message): void {
        error_log('[TMW-MODEL-PAG-AUDIT] ' . $message);
    }
}

if (!function_exists('tmw_model_pagination_audit_run')) {
    function tmw_model_pagination_audit_run(): void {
        if (defined('TMW_MODEL_PAG_AUDIT_RAN')) {
            return;
        }
        define('TMW_MODEL_PAG_AUDIT_RAN', true);

        if (is_admin()) {
            return;
        }

        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $is_models_path = (bool) preg_match('#^/models(?:/|$)#', $request_uri);
        if (!is_post_type_archive('model') && !$is_models_path) {
            return;
        }

        global $wp;
        global $wp_query;
        global $post;

        $current_url = $request_uri ? home_url($request_uri) : home_url('/');
        tmw_model_pagination_audit_log('url=' . $current_url);
        tmw_model_pagination_audit_log('request_uri=' . $request_uri);
        tmw_model_pagination_audit_log(sprintf(
            'is_404=%s is_post_type_archive(model)=%s is_paged=%s',
            is_404() ? 'true' : 'false',
            is_post_type_archive('model') ? 'true' : 'false',
            is_paged() ? 'true' : 'false'
        ));
        tmw_model_pagination_audit_log(sprintf(
            'query_var_paged=%s query_var_page=%s',
            (string) get_query_var('paged'),
            (string) get_query_var('page')
        ));

        if ($wp instanceof WP) {
            tmw_model_pagination_audit_log(sprintf(
                'wp.request=%s wp.matched_rule=%s wp.matched_query=%s',
                $wp->request ?? '',
                $wp->matched_rule ?? '',
                $wp->matched_query ?? ''
            ));
        }

        if ($wp_query instanceof WP_Query) {
            tmw_model_pagination_audit_log(sprintf(
                'wp_query.max_num_pages=%s wp_query.found_posts=%s wp_query.post_count=%s',
                (string) $wp_query->max_num_pages,
                (string) $wp_query->found_posts,
                (string) $wp_query->post_count
            ));
        }

        if ($post instanceof WP_Post) {
            tmw_model_pagination_audit_log(sprintf(
                'post id=%s title="%s"',
                (string) $post->ID,
                $post->post_title
            ));
        }
    }
}

add_action('wp', 'tmw_model_pagination_audit_run', 100);
