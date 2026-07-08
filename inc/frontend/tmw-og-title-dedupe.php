<?php
/**
 * TMW OG Title Dedupe — v1.0.2
 *
 * Keeps one og:title tag on single model pages. Prefer the Rank Math title
 * stored on the post, and otherwise keep the longest rendered og:title tag.
 *
 * @package retrotube-child-v3
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_og_title_dedupe_should_run')) {
    function tmw_og_title_dedupe_should_run(): bool {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }
        return is_singular('model');
    }
}

if (!function_exists('tmw_og_title_dedupe_buffer')) {
    /**
     * Output buffer callback: keep exactly one og:title tag.
     *
     * @param string $buffer Full page HTML.
     * @return string Filtered HTML with a single og:title.
     */
    function tmw_og_title_dedupe_buffer(string $buffer): string {
        $pattern = '/<meta\s+(?:[^>]*?\s)?property=["\']og:title["\'][^>]*>\s*/i';

        if (!preg_match_all($pattern, $buffer, $matches, PREG_OFFSET_CAPTURE)) {
            return $buffer;
        }

        $tags = $matches[0];
        if (count($tags) < 2) {
            return $buffer;
        }

        $expected = '';
        $post_id  = (int) get_queried_object_id();
        if ($post_id > 0) {
            $expected = trim((string) get_post_meta($post_id, 'rank_math_facebook_title', true));
            if ($expected === '') {
                $expected = trim((string) get_post_meta($post_id, 'rank_math_title', true));
            }
        }

        $exact_index   = -1;
        $longest_index = -1;
        $longest_len   = -1;

        foreach ($tags as $i => $tag_match) {
            $tag_html = $tag_match[0];
            $content  = '';
            if (preg_match('/content=["\']([^"\']*)["\']/i', $tag_html, $cm)) {
                $content = html_entity_decode($cm[1], ENT_QUOTES);
            }

            if ($expected !== '' && trim($content) === $expected) {
                $exact_index = $i;
            }

            $content_len = mb_strlen($content);
            if ($content_len > $longest_len) {
                $longest_len   = $content_len;
                $longest_index = $i;
            }
        }

        $keep_index = $exact_index !== -1 ? $exact_index : $longest_index;
        if ($keep_index === -1) {
            return $buffer;
        }

        for ($i = count($tags) - 1; $i >= 0; $i--) {
            if ($i === $keep_index) {
                continue;
            }
            $buffer = substr_replace($buffer, '', $tags[$i][1], strlen($tags[$i][0]));
        }

        return $buffer;
    }
}

if (!function_exists('tmw_og_title_dedupe_start')) {
    function tmw_og_title_dedupe_start(): void {
        if (!tmw_og_title_dedupe_should_run()) {
            return;
        }
        ob_start('tmw_og_title_dedupe_buffer');
    }
}

add_action('template_redirect', 'tmw_og_title_dedupe_start', 1);
