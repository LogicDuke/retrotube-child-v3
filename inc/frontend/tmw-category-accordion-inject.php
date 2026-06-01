<?php
/**
 * Category archive accordion injector.
 *
 * Safely injects the category SEO/read-more accordion into the existing
 * parent-rendered category archive output without taking over category.php or
 * the archive template hierarchy.
 *
 * @package retrotube-child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_category_accordion_inject_log')) {
    function tmw_category_accordion_inject_log(string $message): void {
        static $logged = [];

        if (isset($logged[$message])) {
            return;
        }

        $logged[$message] = true;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TMW-CAT-ACCORDION-INJECT] ' . $message);
        }
    }
}

if (!function_exists('tmw_category_accordion_inject_should_run')) {
    function tmw_category_accordion_inject_should_run(): bool {
        if (is_admin()) {
            tmw_category_accordion_inject_log('skipped reason=admin');
            return false;
        }

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            tmw_category_accordion_inject_log('skipped reason=ajax');
            return false;
        }

        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            tmw_category_accordion_inject_log('skipped reason=cron');
            return false;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            tmw_category_accordion_inject_log('skipped reason=rest');
            return false;
        }

        if (function_exists('wp_is_json_request') && wp_is_json_request()) {
            tmw_category_accordion_inject_log('skipped reason=json');
            return false;
        }

        if (is_feed()) {
            tmw_category_accordion_inject_log('skipped reason=feed');
            return false;
        }

        if (function_exists('is_embed') && is_embed()) {
            tmw_category_accordion_inject_log('skipped reason=embed');
            return false;
        }

        if (!is_category()) {
            tmw_category_accordion_inject_log('skipped reason=not_category');
            return false;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            tmw_category_accordion_inject_log('skipped reason=non_html_method method=' . $method);
            return false;
        }

        return true;
    }
}

if (!function_exists('tmw_category_accordion_inject_get_native_description')) {
    function tmw_category_accordion_inject_get_native_description(WP_Term $term): string {
        $removed_term_filter = false;

        if (function_exists('tmw_category_append_cpt_to_term_description') && has_filter('term_description', 'tmw_category_append_cpt_to_term_description') !== false) {
            remove_filter('term_description', 'tmw_category_append_cpt_to_term_description', 19);
            $removed_term_filter = true;
        }

        $description = term_description($term->term_id, 'category');

        if ($removed_term_filter) {
            add_filter('term_description', 'tmw_category_append_cpt_to_term_description', 19, 3);
        }

        return is_string($description) ? trim($description) : '';
    }
}

if (!function_exists('tmw_category_accordion_inject_get_cpt_content')) {
    function tmw_category_accordion_inject_get_cpt_content(WP_Term $term): string {
        if (!function_exists('tmw_get_category_page_post') || !function_exists('tmw_category_page_extract_sections')) {
            return '';
        }

        $post = tmw_get_category_page_post($term);
        if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
            return '';
        }

        $sections = tmw_category_page_extract_sections($post);
        $intro_html = isset($sections['intro']) ? trim((string) $sections['intro']) : '';
        $body_html = isset($sections['body']) ? trim((string) $sections['body']) : '';
        $faq_html = isset($sections['faq']) ? trim((string) $sections['faq']) : '';

        if ($intro_html === '' && $body_html === '' && $faq_html === '') {
            return '';
        }

        $content = '<div class="tmw-category-page-content">';
        if ($intro_html !== '') {
            $content .= '<div class="tmw-category-page-intro">' . $intro_html . '</div>';
        }
        if ($body_html !== '') {
            $content .= '<div class="tmw-category-page-body">' . $body_html . '</div>';
        }
        if ($faq_html !== '') {
            $content .= '<div class="tmw-category-page-faq">' . $faq_html . '</div>';
        }
        $content .= '</div>';

        return $content;
    }
}

if (!function_exists('tmw_category_accordion_inject_build_content')) {
    function tmw_category_accordion_inject_build_content(WP_Term $term): string {
        $parts = [];

        $native_description = tmw_category_accordion_inject_get_native_description($term);
        if ($native_description !== '') {
            $parts[] = $native_description;
        }

        $cpt_content = tmw_category_accordion_inject_get_cpt_content($term);
        if ($cpt_content !== '') {
            $parts[] = $cpt_content;
        }

        $content = trim(implode("\n", $parts));
        if ($content === '' || trim(wp_strip_all_tags($content)) === '') {
            return '';
        }

        return $content;
    }
}

if (!function_exists('tmw_category_accordion_inject_render_markup')) {
    function tmw_category_accordion_inject_render_markup(WP_Term $term): string {
        if (!function_exists('tmw_render_accordion')) {
            return '';
        }

        $content = tmw_category_accordion_inject_build_content($term);
        if ($content === '') {
            tmw_category_accordion_inject_log('skipped reason=content_empty term_id=' . (int) $term->term_id);
            return '';
        }

        $accordion = tmw_render_accordion([
            'content_html'    => $content,
            'lines'           => 1,
            'collapsed'       => true,
            'accordion_class' => 'tmw-accordion--category-desc',
            'id_base'         => 'tmw-category-desc-',
        ]);

        if ($accordion === '') {
            tmw_category_accordion_inject_log('skipped reason=accordion_empty term_id=' . (int) $term->term_id);
            return '';
        }

        $length = strlen(wp_strip_all_tags($content));
        $GLOBALS['tmw_category_accordion_inject_content_length'] = $length;

        return "\n" . '<div class="tmw-category-accordion-injected" data-tmw-category-accordion="1">' . $accordion . '</div>' . "\n";
    }
}

if (!function_exists('tmw_category_accordion_inject_has_duplicate_marker')) {
    function tmw_category_accordion_inject_has_duplicate_marker(string $buffer): bool {
        $needles = [
            'data-tmw-category-accordion="1"',
            'tmw-accordion--category-desc',
            'tmw-category-page-content',
        ];

        foreach ($needles as $needle) {
            if (strpos($buffer, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('tmw_category_accordion_inject_find_anchor')) {
    function tmw_category_accordion_inject_find_anchor(string $buffer) {
        $grid_pos = false;
        if (preg_match('~<article\b[^>]*(?:\bloop-video\b|\bthumb-block\b|\bpost-)~i', $buffer, $grid_match, PREG_OFFSET_CAPTURE)) {
            $grid_pos = $grid_match[0][1];
        }

        if (!preg_match_all('~<div\b(?=[^>]*\btmw-title\b)[^>]*>.*?</div>~is', $buffer, $title_matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        foreach ($title_matches[0] as $title_match) {
            $title_start = $title_match[1];
            $title_end = $title_start + strlen($title_match[0]);

            if ($grid_pos !== false && $title_end > $grid_pos) {
                continue;
            }

            if (stripos($title_match[0], 'tmw-title-text') === false && stripos($title_match[0], '<h1') === false) {
                continue;
            }

            $GLOBALS['tmw_category_accordion_inject_anchor'] = 'tmw-title-after';
            return $title_end;
        }

        return false;
    }
}

if (!function_exists('tmw_category_accordion_inject_into_buffer')) {
    function tmw_category_accordion_inject_into_buffer(string $buffer): string {
        if ($buffer === '') {
            return $buffer;
        }

        if (tmw_category_accordion_inject_has_duplicate_marker($buffer)) {
            tmw_category_accordion_inject_log('skipped reason=duplicate_marker');
            return $buffer;
        }

        $markup = isset($GLOBALS['tmw_category_accordion_inject_markup']) ? (string) $GLOBALS['tmw_category_accordion_inject_markup'] : '';
        if ($markup === '') {
            return $buffer;
        }

        $insert_pos = tmw_category_accordion_inject_find_anchor($buffer);
        if ($insert_pos === false) {
            tmw_category_accordion_inject_log('skipped reason=anchor_not_found');
            return $buffer;
        }

        $term_id = isset($GLOBALS['tmw_category_accordion_inject_term_id']) ? (int) $GLOBALS['tmw_category_accordion_inject_term_id'] : 0;
        $content_length = isset($GLOBALS['tmw_category_accordion_inject_content_length']) ? (int) $GLOBALS['tmw_category_accordion_inject_content_length'] : strlen(wp_strip_all_tags($markup));
        $anchor = isset($GLOBALS['tmw_category_accordion_inject_anchor']) ? (string) $GLOBALS['tmw_category_accordion_inject_anchor'] : 'unknown';

        tmw_category_accordion_inject_log('success term_id=' . $term_id . ' content_length=' . $content_length . ' anchor=' . $anchor);

        return substr_replace($buffer, $markup, $insert_pos, 0);
    }
}

if (!function_exists('tmw_category_accordion_inject_shutdown')) {
    function tmw_category_accordion_inject_shutdown(): void {
        if (!empty($GLOBALS['tmw_category_accordion_inject_shutdown_ran'])) {
            return;
        }

        $GLOBALS['tmw_category_accordion_inject_shutdown_ran'] = true;

        if (!isset($GLOBALS['tmw_category_accordion_inject_ob_level'])) {
            return;
        }

        $target_level = (int) $GLOBALS['tmw_category_accordion_inject_ob_level'];
        while (ob_get_level() > $target_level) {
            ob_end_flush();
        }

        if (ob_get_level() !== $target_level) {
            return;
        }

        $content = ob_get_clean();
        if (!is_string($content)) {
            return;
        }

        echo tmw_category_accordion_inject_into_buffer($content); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
}

if (!function_exists('tmw_category_accordion_inject_start')) {
    function tmw_category_accordion_inject_start(): void {
        if (!tmw_category_accordion_inject_should_run()) {
            return;
        }

        if (!empty($GLOBALS['tmw_category_accordion_inject_started'])) {
            return;
        }

        $term = get_queried_object();
        if (!$term instanceof WP_Term || $term->taxonomy !== 'category') {
            tmw_category_accordion_inject_log('skipped reason=invalid_term');
            return;
        }

        $markup = tmw_category_accordion_inject_render_markup($term);
        if ($markup === '') {
            return;
        }

        $GLOBALS['tmw_category_accordion_inject_started'] = true;
        $GLOBALS['tmw_category_accordion_inject_markup'] = $markup;
        $GLOBALS['tmw_category_accordion_inject_term_id'] = (int) $term->term_id;

        ob_start();
        $GLOBALS['tmw_category_accordion_inject_ob_level'] = ob_get_level();
        add_action('shutdown', 'tmw_category_accordion_inject_shutdown', -10);
    }
}

add_action('template_redirect', 'tmw_category_accordion_inject_start', 1);
