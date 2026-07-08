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

        return $aliases[$normalized] ?? $normalized;
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
                $html = tmw_rewrite_video_filter_hrefs_to_videos_page($html);
                $html = tmw_a11y_fix_more_videos_links($html);

                // [TMW-SEO v1.0.2] Keep Latest/Random Videos visible on single
                // model pages, but neutralize unrelated model names in visible
                // sidebar text/title/alt only. href/src URLs are never changed.
                if (is_singular('model') && function_exists('tmw_sanitize_sidebar_video_names')) {
                    $html = tmw_sanitize_sidebar_video_names($html);
                }

                echo $html;
            }
        }
    }

    unregister_widget('wpst_WP_Widget_Videos_Block');
    register_widget('TMW_WP_Widget_Videos_Block_Fixed');
}, 20);

if (!function_exists('tmw_get_unrelated_model_names')) {
    /**
     * Build unrelated model-name replacement lists from the models taxonomy.
     * Full names are always eligible; single parts are guarded by length,
     * stoplist, current-model protection, and capitalization at replacement.
     *
     * @return array{full:array<string>,parts:array<string>}
     */
    function tmw_get_unrelated_model_names(): array {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = ['full' => [], 'parts' => []];
        $terms = get_terms(['taxonomy' => 'models', 'hide_empty' => false, 'fields' => 'names']);
        if (is_wp_error($terms) || empty($terms)) {
            return $cache;
        }

        $protected = [];
        $post_id = (int) get_the_ID();
        if ($post_id > 0) {
            $protected[] = strtolower(trim((string) get_the_title($post_id)));
            $own_terms = get_the_terms($post_id, 'models');
            if (is_array($own_terms)) {
                foreach ($own_terms as $term) {
                    if ($term instanceof WP_Term) {
                        $protected[] = strtolower(trim($term->name));
                    }
                }
            }
        }

        $part_stoplist = [
            'angel', 'baby', 'bella', 'berry', 'candy', 'cherry', 'coco',
            'crystal', 'daisy', 'dawn', 'destiny', 'diamond', 'dream',
            'ginger', 'grace', 'honey', 'hunter', 'jade', 'jasmine', 'jewel',
            'kitty', 'lily', 'love', 'lucky', 'melody', 'pearl', 'penny',
            'princess', 'rain', 'rose', 'ruby', 'sage', 'star', 'stone',
            'storm', 'summer', 'sunny', 'sweet', 'violet', 'winter',
        ];

        foreach ($terms as $name) {
            $name = trim((string) $name);
            if ($name === '' || in_array(strtolower($name), $protected, true)) {
                continue;
            }

            $cache['full'][] = $name;

            foreach (preg_split('/\s+/', $name) as $part) {
                $part = trim($part);
                if (mb_strlen($part) < 4) {
                    continue;
                }

                $part_l = strtolower($part);
                if (in_array($part_l, $part_stoplist, true)) {
                    continue;
                }

                $in_protected = false;
                foreach ($protected as $protected_name) {
                    if (strpos($protected_name, $part_l) !== false) {
                        $in_protected = true;
                        break;
                    }
                }

                if (!$in_protected && !in_array($part, $cache['parts'], true)) {
                    $cache['parts'][] = $part;
                }
            }
        }

        usort($cache['full'], static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        usort($cache['parts'], static fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return $cache;
    }
}

if (!function_exists('tmw_neutralize_model_names_in_text')) {
    function tmw_neutralize_model_names_in_text(string $text): string {
        if (trim($text) === '') {
            return $text;
        }

        $names = tmw_get_unrelated_model_names();
        $sep = '(?:\s|&nbsp;|&#0*160;|&#x0*a0;)+';

        foreach ($names['full'] as $full) {
            $parts = preg_split('/\s+/', trim($full));
            if (!$parts) {
                continue;
            }
            $pattern = '/(?<![\p{L}\p{N}_])' . implode($sep, array_map(static fn($p) => preg_quote($p, '/'), $parts)) . '(?![\p{L}\p{N}_])/iu';
            $result = preg_replace($pattern, 'Featured Model', $text);
            $text = $result ?? $text;
        }

        foreach ($names['parts'] as $part) {
            $pattern = '/\b' . preg_quote($part, '/') . '\b/iu';
            $result = preg_replace_callback(
                $pattern,
                static function ($match) {
                    $first = function_exists('mb_substr') ? mb_substr($match[0], 0, 1) : substr($match[0], 0, 1);
                    $upper = function_exists('mb_strtoupper') ? mb_strtoupper($first) : strtoupper($first);
                    return ($first === $upper) ? 'Model' : $match[0];
                },
                $text
            );
            $text = $result ?? $text;
        }

        $result = preg_replace('/\b(?:Featured\s+)?Model(?:\s+Model)+\b/u', 'Featured Model', $text);
        return $result ?? $text;
    }
}

if (!function_exists('tmw_sanitize_sidebar_video_names')) {
    function tmw_sanitize_sidebar_video_names(string $html): string {
        if ($html === '') {
            return $html;
        }

        $names = tmw_get_unrelated_model_names();
        if (empty($names['full']) && empty($names['parts'])) {
            return $html;
        }

        // Sanitize title="..." tooltips.
        $result = preg_replace_callback(
            '/\btitle=(["\'])(.*?)\1/is',
            static function ($match) {
                return 'title=' . $match[1] . tmw_neutralize_model_names_in_text($match[2]) . $match[1];
            },
            $html
        );
        $html = $result ?? $html;

        // Sanitize alt="..." thumbnail text.
        $result = preg_replace_callback(
            '/\balt=(["\'])(.*?)\1/is',
            static function ($match) {
                return 'alt=' . $match[1] . tmw_neutralize_model_names_in_text($match[2]) . $match[1];
            },
            $html
        );
        $html = $result ?? $html;

        // Sanitize visible text nodes only. href/src attribute values live
        // inside tags and are never touched by this pattern.
        $result = preg_replace_callback(
            '/>([^<>]+)</s',
            static function ($match) {
                return '>' . tmw_neutralize_model_names_in_text($match[1]) . '<';
            },
            $html
        );
        return $result ?? $html;
    }
}
