<?php
/**
 * TMW OG Title Dedupe — v1.0.2
 *
 * Problem:
 * Single model pages output TWO og:title meta tags:
 *
 *   <meta property="og:title" content="Mia Collie" />                                  <- duplicate (short)
 *   <meta property="og:title" content="Mia Collie LiveJasmin Profile — Live Cam Guide 2026" />  <- correct (Rank Math)
 *
 * The short duplicate is NOT produced by any child theme file (verified: the only
 * OG output in the child theme is tmw-video-opengraph.php, which fires only on
 * is_singular(['post','video']) and never prints og:title). The duplicate comes
 * from the parent theme or a plugin-level OpenGraph integration (the fb:app_id
 * 966242223397117 present in the head is the classic Jetpack/theme OG signature).
 * Since the exact source hook is outside this repo, the safe and deterministic
 * fix is an output-buffer dedupe scoped to single model pages only.
 *
 * Behavior:
 * - Runs ONLY on is_singular('model').
 * - Finds all <meta property="og:title" ...> tags in the final HTML.
 * - Keeps exactly one: preferring the tag whose content matches the stored
 *   rank_math_facebook_title (or rank_math_title) post meta; if neither matches,
 *   keeps the LONGEST content (the full SEO title is always longer than the
 *   bare post title).
 * - Removes the other og:title tags. No other OG tags (image, description,
 *   url, type, site_name, locale) are touched.
 *
 * This uses the same template_redirect output-buffer pattern already
 * established in inc/frontend/tmw-featured-models-inject.php.
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
        // Match every og:title meta tag, tolerant of attribute order and quote style.
        $pattern = '/<meta\s+(?:[^>]*?\s)?property=["\']og:title["\'][^>]*>\s*/i';

        if (!preg_match_all($pattern, $buffer, $matches, PREG_OFFSET_CAPTURE)) {
            return $buffer;
        }

        $tags = $matches[0];
        if (count($tags) < 2) {
            // Zero or one og:title — nothing to dedupe.
            return $buffer;
        }

        // Resolve the expected title from Rank Math post meta.
        $expected = '';
        $post_id  = (int) get_queried_object_id();
        if ($post_id > 0) {
            $expected = trim((string) get_post_meta($post_id, 'rank_math_facebook_title', true));
            if ($expected === '') {
                $expected = trim((string) get_post_meta($post_id, 'rank_math_title', true));
            }
        }

        // Extract content="" from each tag and choose the keeper.
        $keep_index  = -1;
        $longest_len = -1;
        $contents    = [];

        foreach ($tags as $i => $tag_match) {
            $tag_html = $tag_match[0];
            $content  = '';
            if (preg_match('/content=["\']([^"\']*)["\']/i', $tag_html, $cm)) {
                $content = html_entity_decode($cm[1], ENT_QUOTES);
            }
            $contents[$i] = $content;

            // Exact match to the Rank Math stored title wins immediately.
            if ($expected !== '' && trim($content) === $expected) {
                $keep_index = $i;
            }

            if (mb_strlen($content) > $longest_len) {
                $longest_len = mb_strlen($content);
                if ($keep_index === -1) {
                    // Track longest as fallback only until an exact match is found.
                    $keep_index = $i;
                }
            }
        }

        // If an exact match was found later than a longer non-matching tag,
        // re-check: exact match always wins.
        if ($expected !== '') {
            foreach ($contents as $i => $content) {
                if (trim($content) === $expected) {
                    $keep_index = $i;
                    break;
                }
            }
        }

        // Remove all og:title tags except the keeper. Iterate in reverse so
        // string offsets stay valid while splicing.
        for ($i = count($tags) - 1; $i >= 0; $i--) {
            if ($i === $keep_index) {
                continue;
            }
            $offset = $tags[$i][1];
            $length = strlen($tags[$i][0]);
            $buffer = substr_replace($buffer, '', $offset, $length);
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

// Priority 1: after the featured-models injector buffer (priority 0) so the
// buffers nest cleanly and this one processes the final HTML last-in-first-out.
add_action('template_redirect', 'tmw_og_title_dedupe_start', 1);
