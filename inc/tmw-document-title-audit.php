<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_title_audit_log_line')) {
    function tmw_title_audit_log_line($message) {
        error_log('[TMW-TITLE-AUDIT] ' . $message);
    }
}

if (!function_exists('tmw_title_audit_callback_label')) {
    function tmw_title_audit_callback_label($callback, &$file = null, &$line = null) {
        $file = null;
        $line = null;

        try {
            if (is_array($callback) && isset($callback[0], $callback[1])) {
                $class = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
                $method = $callback[1];
                $label = $class . '::' . $method;
                if (is_object($callback[0]) || class_exists($class)) {
                    $reflector = new ReflectionMethod($callback[0], $method);
                    $file = $reflector->getFileName();
                    $line = $reflector->getStartLine();
                }
                return $label;
            }

            if ($callback instanceof Closure) {
                $reflector = new ReflectionFunction($callback);
                $file = $reflector->getFileName();
                $line = $reflector->getStartLine();
                return 'Closure';
            }

            if (is_string($callback)) {
                if (function_exists($callback)) {
                    $reflector = new ReflectionFunction($callback);
                    $file = $reflector->getFileName();
                    $line = $reflector->getStartLine();
                }
                return $callback;
            }

            if (is_object($callback) && method_exists($callback, '__invoke')) {
                $reflector = new ReflectionMethod($callback, '__invoke');
                $file = $reflector->getFileName();
                $line = $reflector->getStartLine();
                return get_class($callback) . '::__invoke';
            }
        } catch (Exception $exception) {
            return 'Unknown';
        }

        return 'Unknown';
    }
}

if (!function_exists('tmw_title_audit_log_hooks')) {
    function tmw_title_audit_log_hooks($hook) {
        global $wp_filter;
        if (empty($wp_filter[$hook])) {
            return;
        }

        $callbacks = $wp_filter[$hook];
        if ($callbacks instanceof WP_Hook) {
            $callbacks = $callbacks->callbacks;
        }

        if (!is_array($callbacks)) {
            return;
        }

        foreach ($callbacks as $handlers) {
            foreach ($handlers as $handler) {
                $file = null;
                $line = null;
                $label = tmw_title_audit_callback_label($handler['function'], $file, $line);
                $details = 'hook=' . $hook . ' cb=' . $label;
                if ($file) {
                    $details .= ' file=' . $file;
                }
                if ($line) {
                    $details .= ' line=' . $line;
                }
                tmw_title_audit_log_line($details);
            }
        }
    }
}

if (!function_exists('tmw_title_audit_run')) {
    function tmw_title_audit_run() {
        if (defined('TMW_TITLE_AUDIT_RAN')) {
            return;
        }
        define('TMW_TITLE_AUDIT_RAN', true);

        if (is_admin()) {
            return;
        }

        if (!is_post_type_archive('model')) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $current_url = $request_uri ? home_url($request_uri) : home_url('/');
        tmw_title_audit_log_line('url=' . $current_url);
        tmw_title_audit_log_line('wp_get_document_title="' . wp_get_document_title() . '"');

        $queried_object = get_queried_object();
        if (is_object($queried_object)) {
            $label = get_class($queried_object);
            $id = null;
            if (isset($queried_object->ID)) {
                $id = $queried_object->ID;
            } elseif (isset($queried_object->term_id)) {
                $id = $queried_object->term_id;
            }
            $title = null;
            if (isset($queried_object->post_title)) {
                $title = $queried_object->post_title;
            } elseif (isset($queried_object->name)) {
                $title = $queried_object->name;
            }
            $details = 'queried_object=' . $label;
            if ($id !== null) {
                $details .= ' id=' . $id;
            }
            if ($title !== null) {
                $details .= ' title="' . $title . '"';
            }
            tmw_title_audit_log_line($details);
        }

        global $post;
        if ($post instanceof WP_Post) {
            tmw_title_audit_log_line('post id=' . $post->ID . ' title="' . $post->post_title . '"');
        }

        $flags = array(
            'is_post_type_archive(model)' => is_post_type_archive('model') ? 'true' : 'false',
            'is_singular' => is_singular() ? 'true' : 'false',
            'is_page' => is_page() ? 'true' : 'false',
            'is_single' => is_single() ? 'true' : 'false',
        );
        tmw_title_audit_log_line('flags=' . implode(' ', array_map(
            function ($key) use ($flags) {
                return $key . '=' . $flags[$key];
            },
            array_keys($flags)
        )));

        $hooks = array(
            'pre_get_document_title',
            'document_title_parts',
            'rank_math/frontend/title',
            'wpseo_title',
        );

        foreach ($hooks as $hook) {
            tmw_title_audit_log_hooks($hook);
        }
    }
}

add_action('wp', 'tmw_title_audit_run', 100);
