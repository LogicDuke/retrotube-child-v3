<?php
/**
 * Reusable Featured Models block partial (wrapped for reliable centering).
 */
if (!defined('ABSPATH')) exit;

$excluded_pages = [
    'models',
    '18-u-s-c-2257',
    'dmca',
    'privacy-policy-top-models-webcam',
    'terms-of-use-of-top-models-webcam-directory',
];

if (!empty($GLOBALS['tmw_featured_models_disabled']) || is_page($excluded_pages)) {
    return;
}

$models_page = get_page_by_path('models');
if ($models_page instanceof WP_Post && (int) get_queried_object_id() === (int) $models_page->ID) {
    return;
}

$shortcode_to_use = get_query_var('tmw_featured_shortcode', '[tmw_featured_models]');
if (!is_string($shortcode_to_use)) return;
$shortcode_to_use = trim($shortcode_to_use);
if ($shortcode_to_use === '') return;

$output = do_shortcode($shortcode_to_use);
if (!is_string($output) || $output === '') { echo $output; return; }

/* Ensure the shuffle icon appears once in the H3 */
$output = preg_replace(
    '~(<h3\\b[^>]*class=["\']([^"\']*\\s)?tmwfm-heading([^"\']*\\s)?[^"\']*["\'][^>]*>\\s*)(?!<i[^>]*\\bfa-random\\b[^>]*>)(FEATURED MODELS)~',
    '$1<i class="fa fa-random"></i> $4',
    $output,
    1
);

/* Wrap the entire block so CSS can center the heading no matter what the shortcode outputs */
echo '<!-- TMW-FEATURED-MODELS -->';
echo '<div class="tmwfm-slot" data-tmwfm="wrap">', $output, '</div>';
echo '<!-- /TMW-FEATURED-MODELS -->';
