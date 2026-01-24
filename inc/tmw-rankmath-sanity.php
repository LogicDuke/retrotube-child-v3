<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_rm_sanity_log')) {
    function tmw_rm_sanity_log(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message);
        }
    }
}

if (!function_exists('tmw_rm_get_rankmath_option_names')) {
    function tmw_rm_get_rankmath_option_names(): array
    {
        $transient_key = 'tmw_rm_option_names';
        $cached = get_transient($transient_key);
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $like = $wpdb->esc_like('rank-math-options-') . '%';
        $options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );

        $options = is_array($options) ? array_values(array_filter($options, 'is_string')) : [];
        set_transient($transient_key, $options, 12 * HOUR_IN_SECONDS);

        return $options;
    }
}

if (!function_exists('tmw_rm_build_path')) {
    function tmw_rm_build_path(string $path, $key): string
    {
        $key_label = is_int($key) ? '[' . $key . ']' : (string) $key;
        if ($path === '') {
            return $key_label;
        }

        if (is_int($key)) {
            return $path . $key_label;
        }

        return $path . '.' . $key_label;
    }
}

if (!function_exists('tmw_rm_value_contains_needle')) {
    function tmw_rm_value_contains_needle($value, string $needle): bool
    {
        if (is_string($value)) {
            return strpos($value, $needle) !== false;
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $child) {
            if (tmw_rm_value_contains_needle($child, $needle)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('tmw_rm_find_needle_paths')) {
    function tmw_rm_find_needle_paths($value, string $needle, string $path, array &$paths): void
    {
        if (is_string($value)) {
            if (strpos($value, $needle) !== false) {
                $paths[] = $path !== '' ? $path : 'value';
            }
            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            $child_path = tmw_rm_build_path($path, $key);
            tmw_rm_find_needle_paths($child, $needle, $child_path, $paths);
        }
    }
}

if (!function_exists('tmw_rm_is_post_type_list')) {
    function tmw_rm_is_post_type_list($value): bool
    {
        if (!is_array($value) || $value === []) {
            return false;
        }

        if (array_keys($value) !== range(0, count($value) - 1)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        $valid_count = 0;
        foreach ($value as $item) {
            if (post_type_exists($item)) {
                $valid_count++;
            }
        }

        return $valid_count >= 2;
    }
}

if (!function_exists('tmw_rm_sanitize_option_value')) {
    function tmw_rm_sanitize_option_value($value, string $option_name, string $path, array &$fix_counts)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (tmw_rm_is_post_type_list($value) && in_array('category_page', $value, true)) {
            $before = $value;
            $value = array_values(array_filter(
                $value,
                function ($item) {
                    return $item !== 'category_page';
                }
            ));
            $removed_count = count($before) - count($value);
            if ($removed_count > 0) {
                $fix_counts['removed'] += $removed_count;
                tmw_rm_sanity_log(sprintf(
                    '[TMW-RM-FIX] option=%s removed_invalid_post_types=category_page removed_count=%d context=post_type_list path=%s',
                    $option_name,
                    $removed_count,
                    $path !== '' ? $path : 'root'
                ));
            }
        }

        if (array_keys($value) !== range(0, count($value) - 1)) {
            if (
                isset($value['category_page'])
                && !post_type_exists('category_page')
                && post_type_exists('tmw_category_page')
                && !isset($value['tmw_category_page'])
            ) {
                $value['tmw_category_page'] = $value['category_page'];
                unset($value['category_page']);
                $fix_counts['moved']++;
                tmw_rm_sanity_log(sprintf(
                    '[TMW-RM-FIX] option=%s moved_key=category_page->tmw_category_page path=%s',
                    $option_name,
                    $path !== '' ? $path : 'root'
                ));
            }
        }

        foreach ($value as $key => $child) {
            $child_path = tmw_rm_build_path($path, $key);
            $updated_child = tmw_rm_sanitize_option_value($child, $option_name, $child_path, $fix_counts);
            if ($updated_child !== $child) {
                $value[$key] = $updated_child;
            }
        }

        return $value;
    }
}

if (!function_exists('tmw_rm_log_accessible_post_types')) {
    function tmw_rm_log_accessible_post_types(): void
    {
        $accessible_post_types = null;
        if (class_exists('\\RankMath\\Helper') && method_exists('\\RankMath\\Helper', 'get_accessible_post_types')) {
            $accessible_post_types = \RankMath\Helper::get_accessible_post_types();
        }

        if (empty($accessible_post_types) || !is_array($accessible_post_types)) {
            $all_post_types = get_post_types([], 'names');
            $accessible_post_types = array_values(array_filter($all_post_types, 'is_string'));
        }

        $accessible_post_types = array_values(array_unique(array_filter($accessible_post_types, 'is_string')));
        if ($accessible_post_types !== []) {
            tmw_rm_sanity_log(sprintf(
                '[TMW-RM-AUDIT] accessible_post_types=%s',
                implode(',', $accessible_post_types)
            ));
        }
    }
}

if (!function_exists('tmw_rm_run_sanity_scan')) {
    function tmw_rm_run_sanity_scan(array $option_names): void
    {
        if ($option_names === []) {
            tmw_rm_sanity_log('[TMW-RM-AUDIT] rm_options_found=0');
            return;
        }

        tmw_rm_sanity_log(sprintf('[TMW-RM-AUDIT] rm_options_found=%d', count($option_names)));

        $fix_counts = [
            'removed' => 0,
            'moved' => 0,
            'updated' => 0,
            'scanned' => 0,
            'hits' => 0,
        ];

        foreach ($option_names as $option_name) {
            $option_value = get_option($option_name);
            $fix_counts['scanned']++;

            $paths = [];
            tmw_rm_find_needle_paths($option_value, 'category_page', '', $paths);
            if (!empty($paths)) {
                $fix_counts['hits'] += count($paths);
                tmw_rm_sanity_log(sprintf(
                    '[TMW-RM-AUDIT] needle=category_page option=%s hits=%d paths=%s',
                    $option_name,
                    count($paths),
                    implode(';', $paths)
                ));
            }

            $original_value = $option_value;
            $option_value = tmw_rm_sanitize_option_value($option_value, $option_name, 'root', $fix_counts);
            if ($option_value !== $original_value) {
                update_option($option_name, $option_value);
                $fix_counts['updated']++;
            }
        }

        if ($fix_counts['removed'] > 0) {
            tmw_rm_sanity_log('[TMW-RM-FIX] removed_invalid_post_types=category_page');
        }

        tmw_rm_log_accessible_post_types();

        update_site_option('tmw_rm_sanity_last_run', [
            'timestamp' => time(),
            'options_scanned' => $fix_counts['scanned'],
            'options_updated' => $fix_counts['updated'],
            'removed_count' => $fix_counts['removed'],
            'moved_count' => $fix_counts['moved'],
            'hits' => $fix_counts['hits'],
        ]);
    }
}

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) {
        return;
    }

    $option_names = tmw_rm_get_rankmath_option_names();
    $force_run = isset($_GET['tmw_rm_sanity']) && (string) $_GET['tmw_rm_sanity'] !== '';

    if (!$force_run) {
        $needle_found = false;
        foreach ($option_names as $option_name) {
            $option_value = get_option($option_name);
            if (tmw_rm_value_contains_needle($option_value, 'category_page')) {
                $needle_found = true;
                break;
            }
        }

        if (!$needle_found) {
            return;
        }
    }

    tmw_rm_run_sanity_scan($option_names);
});
