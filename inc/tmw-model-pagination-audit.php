<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_DEBUG') || !WP_DEBUG) {
    return;
}

if (!function_exists('tmw_model_pag_audit_log_line')) {
    function tmw_model_pag_audit_log_line(string $message): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        error_log('[TMW-MODEL-PAG-AUDIT] ' . $message);
    }
}

if (!function_exists('tmw_model_pag_audit_is_models_request')) {
    function tmw_model_pag_audit_is_models_request($query = null): bool
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return false;
        }

        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $uri_matches = $uri !== '' && strpos($uri, '/models') === 0;

        if (function_exists('is_post_type_archive') && is_post_type_archive('model')) {
            return true;
        }

        if ($query instanceof WP_Query) {
            $post_type = $query->get('post_type');
            if ($post_type === 'model') {
                return true;
            }
            if (is_array($post_type) && in_array('model', $post_type, true)) {
                return true;
            }
        }

        return $uri_matches;
    }
}

if (!function_exists('tmw_model_pag_audit_callback_label')) {
    function tmw_model_pag_audit_callback_label($callback, &$file = null, &$line = null): string
    {
        $file = null;
        $line = null;

        if (is_string($callback)) {
            if (function_exists($callback)) {
                $reflection = new ReflectionFunction($callback);
                $file = $reflection->getFileName() ?: null;
                $line = $reflection->getStartLine();
            }
            return $callback;
        }

        if ($callback instanceof Closure) {
            $reflection = new ReflectionFunction($callback);
            $file = $reflection->getFileName() ?: null;
            $line = $reflection->getStartLine();
            return 'closure';
        }

        if (is_array($callback) && count($callback) === 2) {
            $target = $callback[0];
            $method = $callback[1];
            $label = is_object($target) ? sprintf('%s::%s', get_class($target), $method) : sprintf('%s::%s', (string) $target, $method);
            if ((is_object($target) || is_string($target)) && method_exists($target, $method)) {
                $reflection = new ReflectionMethod($target, $method);
                $file = $reflection->getFileName() ?: null;
                $line = $reflection->getStartLine();
            }
            return $label;
        }

        if (is_object($callback) && method_exists($callback, '__invoke')) {
            $reflection = new ReflectionMethod($callback, '__invoke');
            $file = $reflection->getFileName() ?: null;
            $line = $reflection->getStartLine();
            return get_class($callback);
        }

        return 'unknown';
    }
}

if (!function_exists('tmw_model_pag_audit_log_hook_callbacks')) {
    function tmw_model_pag_audit_log_hook_callbacks(string $hook): void
    {
        if (!tmw_model_pag_audit_is_models_request()) {
            return;
        }

        $hook_data = $GLOBALS['wp_filter'][$hook] ?? null;
        $callbacks = [];
        if ($hook_data instanceof WP_Hook) {
            $callbacks = $hook_data->callbacks;
        } elseif (is_array($hook_data)) {
            $callbacks = $hook_data;
        }

        $priorities = array_keys($callbacks);
        sort($priorities);
        $priority_label = !empty($priorities) ? implode(',', $priorities) : 'none';
        tmw_model_pag_audit_log_line(sprintf('hook=%s priorities=%s', $hook, $priority_label));

        if (empty($callbacks)) {
            tmw_model_pag_audit_log_line(sprintf('hook=%s callbacks=none', $hook));
            return;
        }

        foreach ($callbacks as $priority => $callbacks_at_priority) {
            foreach ($callbacks_at_priority as $callback_entry) {
                if (!isset($callback_entry['function'])) {
                    continue;
                }
                $file = null;
                $line = null;
                $label = tmw_model_pag_audit_callback_label($callback_entry['function'], $file, $line);
                $location = $file ? sprintf('%s:%d', $file, (int) $line) : 'unknown';
                tmw_model_pag_audit_log_line(sprintf(
                    'hook=%s priority=%s callback=%s location=%s',
                    $hook,
                    $priority,
                    $label,
                    $location
                ));
            }
        }
    }
}

add_action('pre_get_posts', function ($query) {
    if (!$query instanceof WP_Query) {
        return;
    }
    if (!$query->is_main_query()) {
        return;
    }
    if (!tmw_model_pag_audit_is_models_request($query)) {
        return;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $query_post_type = $query->get('post_type');
    $post_type_label = is_array($query_post_type) ? wp_json_encode($query_post_type) : (string) $query_post_type;

    tmw_model_pag_audit_log_line(sprintf(
        'PRE_GET_POSTS BEFORE uri=%s paged_var=%s post_type=%s',
        $uri,
        (string) get_query_var('paged'),
        $post_type_label
    ));
    tmw_model_pag_audit_log_line(sprintf(
        'PRE_GET_POSTS BEFORE posts_per_page=%s posts_per_archive_page=%s offset=%s',
        (string) $query->get('posts_per_page'),
        (string) $query->get('posts_per_archive_page'),
        (string) $query->get('offset')
    ));
    tmw_model_pag_audit_log_line(sprintf(
        'PRE_GET_POSTS BEFORE option_posts_per_page=%s option_posts_per_archive_page=%s',
        (string) get_option('posts_per_page'),
        (string) get_option('posts_per_archive_page')
    ));
}, 1);

add_action('pre_get_posts', function ($query) {
    if (!$query instanceof WP_Query) {
        return;
    }
    if (!$query->is_main_query()) {
        return;
    }
    if (!tmw_model_pag_audit_is_models_request($query)) {
        return;
    }

    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $query_post_type = $query->get('post_type');
    $post_type_label = is_array($query_post_type) ? wp_json_encode($query_post_type) : (string) $query_post_type;

    tmw_model_pag_audit_log_line(sprintf(
        'PRE_GET_POSTS AFTER uri=%s paged_var=%s post_type=%s',
        $uri,
        (string) get_query_var('paged'),
        $post_type_label
    ));
    tmw_model_pag_audit_log_line(sprintf(
        'PRE_GET_POSTS AFTER posts_per_page=%s posts_per_archive_page=%s offset=%s',
        (string) $query->get('posts_per_page'),
        (string) $query->get('posts_per_archive_page'),
        (string) $query->get('offset')
    ));
    tmw_model_pag_audit_log_line(sprintf(
        'PRE_GET_POSTS AFTER option_posts_per_page=%s option_posts_per_archive_page=%s',
        (string) get_option('posts_per_page'),
        (string) get_option('posts_per_archive_page')
    ));
}, 999);

add_action('wp', function () {
    if (!tmw_model_pag_audit_is_models_request()) {
        return;
    }

    $query = $GLOBALS['wp_query'] ?? null;
    if (!$query instanceof WP_Query) {
        return;
    }

    $query_vars = [
        'posts_per_page' => $query->get('posts_per_page'),
        'posts_per_archive_page' => $query->get('posts_per_archive_page'),
        'offset' => $query->get('offset'),
        'nopaging' => $query->get('nopaging'),
        'paged' => $query->get('paged'),
        'post_type' => $query->get('post_type'),
        'orderby' => $query->get('orderby'),
        'order' => $query->get('order'),
        'suppress_filters' => $query->get('suppress_filters'),
    ];

    tmw_model_pag_audit_log_line(sprintf(
        'WP_MAIN_QUERY vars=%s',
        wp_json_encode($query_vars)
    ));
    tmw_model_pag_audit_log_line(sprintf(
        'WP_MAIN_QUERY found_posts=%s max_num_pages=%s',
        (string) $query->found_posts,
        (string) $query->max_num_pages
    ));

    static $did_log_hooks = false;
    if ($did_log_hooks) {
        return;
    }
    $did_log_hooks = true;

    $hooks = [
        'pre_get_posts',
        'parse_query',
        'posts_per_page',
        'option_posts_per_page',
        'option_posts_per_archive_page',
    ];

    foreach ($hooks as $hook) {
        tmw_model_pag_audit_log_hook_callbacks($hook);
    }
}, 99);
