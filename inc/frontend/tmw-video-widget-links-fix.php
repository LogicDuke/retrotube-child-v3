<?php
if (!defined('ABSPATH')) {
    exit;
}

function tmw_rewrite_video_filter_hrefs_to_videos_page($html) {
    $count = 0;
    $home_host = wp_parse_url(home_url(), PHP_URL_HOST);

    $updated = preg_replace_callback('#href=(["\'])([^"\']+)\1#i', function ($matches) use ($home_host, &$count) {
        $quote = $matches[1];
        $href = html_entity_decode($matches[2], ENT_QUOTES, 'UTF-8');

        if (strpos($href, '//') === 0) {
            $href = (is_ssl() ? 'https:' : 'http:') . $href;
        }

        $parts = wp_parse_url($href);
        if (!$parts) {
            return $matches[0];
        }

        $host = $parts['host'] ?? '';
        if ($host && $home_host && strtolower($host) !== strtolower($home_host)) {
            return $matches[0];
        }

        $query = $parts['query'] ?? '';
        parse_str($query, $q);
        if (empty($q['filter'])) {
            return $matches[0];
        }

        if (function_exists('tmw_normalize_video_filter')) {
            $normalized_filter = tmw_normalize_video_filter($q['filter']);
            if ($normalized_filter !== '') {
                $q['filter'] = $normalized_filter;
            }
        }

        $path = $parts['path'] ?? '';
        if ($path !== '' && $path !== '/') {
            return $matches[0];
        }

        $new_url = add_query_arg($q, home_url('/videos/'));
        $count++;

        return 'href=' . $quote . esc_url($new_url) . $quote;
    }, $html);

    return $updated;
}

if (!function_exists('tmw_normalize_video_filter')) {
    function tmw_normalize_video_filter($filter) {
        if (!is_string($filter)) {
            return '';
        }

        $normalized = strtolower(trim($filter));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/', '-', $normalized);
        $normalized = str_replace('_', '-', $normalized);
        $normalized = trim($normalized, '-');

        if (preg_match('/^(.+?)-(videos|video)$/', $normalized, $match)) {
            $normalized = $match[1];
        }

        $aliases = [
            'new'          => 'latest',
            'recent'       => 'latest',
            'most-popular' => 'popular',
            'mostviewed'   => 'most-viewed',
            'most-viewed'  => 'most-viewed',
            'viewed'       => 'most-viewed',
            'views'        => 'most-viewed',
        ];

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        return $normalized;
    }
}

add_action('widgets_init', function () {
    if (!class_exists('wpst_WP_Widget_Videos_Block')) {
        return;
    }

    if (!class_exists('TMW_WP_Widget_Videos_Block_Fixed')) {
        class TMW_WP_Widget_Videos_Block_Fixed extends wpst_WP_Widget_Videos_Block {
            public function widget($args, $instance) {
                ob_start();
                parent::widget($args, $instance);
                $html = ob_get_clean();
                echo tmw_rewrite_video_filter_hrefs_to_videos_page($html);
            }
        }
    }

    unregister_widget('wpst_WP_Widget_Videos_Block');
    register_widget('TMW_WP_Widget_Videos_Block_Fixed');
}, 20);
