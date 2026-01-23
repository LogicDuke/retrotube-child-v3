<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_rankmath_hook_audit_describe_callback')) {
    function tmw_rankmath_hook_audit_describe_callback($callback)
    {
        $description = [
            'type' => 'unknown',
            'name' => 'unknown',
            'location' => 'unknown',
        ];

        if (is_string($callback)) {
            $description['type'] = 'function';
            $description['name'] = $callback;
            if (function_exists($callback)) {
                $reflection = new ReflectionFunction($callback);
                $file = $reflection->getFileName();
                $line = $reflection->getStartLine();
                if ($file) {
                    $description['location'] = sprintf('%s:%d', $file, $line);
                }
            }
            return $description;
        }

        if ($callback instanceof Closure) {
            $description['type'] = 'closure';
            $description['name'] = 'closure';
            $reflection = new ReflectionFunction($callback);
            $file = $reflection->getFileName();
            $line = $reflection->getStartLine();
            if ($file) {
                $description['location'] = sprintf('%s:%d', $file, $line);
            }
            return $description;
        }

        if (is_array($callback) && count($callback) === 2) {
            $target = $callback[0];
            $method = $callback[1];
            if (is_object($target)) {
                $description['type'] = 'method';
                $description['name'] = sprintf('%s::%s', get_class($target), $method);
                $reflection = new ReflectionMethod($target, $method);
            } else {
                $description['type'] = 'static_method';
                $description['name'] = sprintf('%s::%s', $target, $method);
                $reflection = new ReflectionMethod($target, $method);
            }

            $file = $reflection->getFileName();
            $line = $reflection->getStartLine();
            if ($file) {
                $description['location'] = sprintf('%s:%d', $file, $line);
            }
            return $description;
        }

        if (is_object($callback) && method_exists($callback, '__invoke')) {
            $description['type'] = 'invokable';
            $description['name'] = get_class($callback);
            $reflection = new ReflectionMethod($callback, '__invoke');
            $file = $reflection->getFileName();
            $line = $reflection->getStartLine();
            if ($file) {
                $description['location'] = sprintf('%s:%d', $file, $line);
            }
        }

        return $description;
    }
}

add_action('template_redirect', function () {
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    if (!is_category()) {
        return;
    }

    static $did_log = false;
    if ($did_log) {
        return;
    }
    $did_log = true;

    $hooks = [
        'rank_math/frontend/title',
        'rank_math/frontend/description',
        'rank_math/frontend/canonical',
        'rank_math/frontend/robots',
        'rank_math/json_ld',
        'rank_math/opengraph/url',
        'rank_math/twitter/url',
        'rank_math/excluded_post_types',
    ];

    foreach ($hooks as $hook) {
        $hook_data = isset($GLOBALS['wp_filter'][$hook]) ? $GLOBALS['wp_filter'][$hook] : null;
        $callbacks = [];
        if ($hook_data instanceof WP_Hook) {
            $callbacks = $hook_data->callbacks;
        } elseif (is_array($hook_data)) {
            $callbacks = $hook_data;
        }

        $priorities = array_keys($callbacks);
        sort($priorities);
        $priority_label = !empty($priorities) ? implode(',', $priorities) : 'none';
        error_log(sprintf('[TMW-RM-HOOK-AUDIT] hook=%s priorities=%s', $hook, $priority_label));

        if (empty($callbacks)) {
            error_log(sprintf('[TMW-RM-HOOK-AUDIT] hook=%s callbacks=none', $hook));
            continue;
        }

        foreach ($callbacks as $priority => $callbacks_at_priority) {
            foreach ($callbacks_at_priority as $callback_entry) {
                if (!isset($callback_entry['function'])) {
                    continue;
                }
                $description = tmw_rankmath_hook_audit_describe_callback($callback_entry['function']);
                error_log(sprintf(
                    '[TMW-RM-HOOK-AUDIT] hook=%s priority=%s type=%s callback=%s location=%s',
                    $hook,
                    $priority,
                    $description['type'],
                    $description['name'],
                    $description['location']
                ));
            }
        }
    }
}, 99);
