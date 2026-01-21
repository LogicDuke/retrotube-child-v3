<?php
/**
 * Featured Models Archive Lock
 *
 * Ensures Featured Models block is rendered after pagination/navigation
 * on category/tag archives (main query only).
 */

if (!defined('ABSPATH')) {
    exit;
}

// Disabled: featured models placement is handled by output-buffer injection to guarantee left-column lock.
return;

if (!function_exists('tmw_featured_models_archive_lock_is_target')) {
    function tmw_featured_models_archive_lock_is_target(): bool {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }

        if (is_feed() || is_embed()) {
            return false;
        }

        return is_category() || is_tag();
    }
}

if (!function_exists('tmw_featured_models_archive_lock_is_main_query')) {
    function tmw_featured_models_archive_lock_is_main_query(): bool {
        if (!isset($GLOBALS['wp_query']) || !$GLOBALS['wp_query'] instanceof WP_Query) {
            return false;
        }

        return $GLOBALS['wp_query']->is_main_query();
    }
}

if (!function_exists('tmw_featured_models_archive_lock_get_context')) {
    function tmw_featured_models_archive_lock_get_context(): array {
        $context = 'other';
        if (is_category()) {
            $context = 'category';
        } elseif (is_tag()) {
            $context = 'tag';
        }

        return [
            'context' => $context,
            'term_id' => (int) get_queried_object_id(),
        ];
    }
}

if (!function_exists('tmw_featured_models_archive_lock_has_printed')) {
    function tmw_featured_models_archive_lock_has_printed(): bool {
        return !empty($GLOBALS['tmw_featured_models_archive_lock_printed']);
    }
}

if (!function_exists('tmw_featured_models_archive_lock_mark_printed')) {
    function tmw_featured_models_archive_lock_mark_printed(): void {
        $GLOBALS['tmw_featured_models_archive_lock_printed'] = true;
    }
}

if (!function_exists('tmw_featured_models_archive_lock_get_markup')) {
    function tmw_featured_models_archive_lock_get_markup(): string {
        $shortcode = '[tmw_featured_models limit="4"]';
        set_query_var('tmw_featured_shortcode', $shortcode);

        ob_start();
        $template = locate_template('partials/featured-models-block.php', false, false);
        if ($template) {
            include $template;
        } else {
            get_template_part('partials/featured-models-block');
        }
        $markup = ob_get_clean();
        set_query_var('tmw_featured_shortcode', '');

        if (!is_string($markup)) {
            return '';
        }

        $markup = trim($markup);
        if ($markup === '') {
            return '';
        }

        $wrapped = '<!-- TMW-FEATURED-LOCK:START -->' . "\n"
            . '<div class="tmw-featured-slot tmw-featured-slot--locked" data-tmw-featured-lock="1">' . "\n"
            . $markup . "\n"
            . '</div>' . "\n"
            . '<!-- TMW-FEATURED-LOCK:END -->';

        return trim($wrapped);
    }
}

if (!function_exists('tmw_featured_models_archive_lock_append_nav')) {
    function tmw_featured_models_archive_lock_append_nav(string $navigation): string {
        if (!tmw_featured_models_archive_lock_is_target()) {
            return $navigation;
        }

        if (!tmw_featured_models_archive_lock_is_main_query()) {
            return $navigation;
        }

        if (tmw_featured_models_archive_lock_has_printed()) {
            return $navigation;
        }

        if (trim($navigation) === '') {
            return $navigation;
        }

        $markup = tmw_featured_models_archive_lock_get_markup();
        if ($markup === '') {
            return $navigation;
        }

        tmw_featured_models_archive_lock_mark_printed();
        return $navigation . "\n" . $markup;
    }
}

if (!function_exists('tmw_featured_models_archive_lock_loop_end')) {
    function tmw_featured_models_archive_lock_loop_end($query): void {
        if (!($query instanceof WP_Query)) {
            return;
        }

        if (!tmw_featured_models_archive_lock_is_target()) {
            return;
        }

        if (!$query->is_main_query()) {
            return;
        }

        if (tmw_featured_models_archive_lock_has_printed()) {
            return;
        }

        if ($query->max_num_pages > 1) {
            return;
        }

        $markup = tmw_featured_models_archive_lock_get_markup();
        if ($markup === '') {
            return;
        }

        tmw_featured_models_archive_lock_mark_printed();
        echo $markup;
    }
}

add_filter('the_posts_navigation', 'tmw_featured_models_archive_lock_append_nav', 10, 2);
add_filter('the_posts_pagination', 'tmw_featured_models_archive_lock_append_nav', 10, 2);
add_action('loop_end', 'tmw_featured_models_archive_lock_loop_end', 10, 1);
