<?php
/**
 * Fix model archive pagination by aligning main query with template display.
 *
 * PROBLEM:
 * - Template displays 16 taxonomy terms per page via [actors_flipboxes per_page="16"]
 * - WordPress main query fetches model CPT posts with posts_per_page from Reading Settings (likely 40)
 * - Result: WP calculates 8 pages (300 posts ÷ 40), shortcode shows 12 pages (192 terms ÷ 16)
 * - Pages 9-12 return 404 because WP thinks they don't exist
 *
 * SOLUTION:
 * - Modify main query to match what the template actually displays
 * - Set posts_per_page to 16 (matching shortcode)
 * - Override found_posts to reflect taxonomy term count (not post count)
 * - WordPress pagination now calculates correctly: 192 ÷ 16 = 12 pages
 *
 * This is the PROPER architectural fix - not a 404 suppression band-aid.
 *
 * @package RetrotubeChild
 * @version 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Align model archive main query with template display logic.
 *
 * The archive-model.php template uses [actors_flipboxes per_page="16"] to display
 * taxonomy terms from the 'models' taxonomy. This hook ensures WordPress's main query
 * matches that behavior so pagination calculations are correct.
 *
 * @param WP_Query $query The WordPress query object.
 * @return void
 */
function tmw_fix_model_archive_query($query) {
    // Only target the main query
    if (!$query->is_main_query()) {
        return;
    }

    // Only target model post type archives
    if (!$query->is_post_type_archive('model')) {
        return;
    }

    // Skip for admin, AJAX, cron, and feeds
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed()) {
        return;
    }

    // The template displays 16 models per page (from shortcode parameter)
    $per_page = 16;

    // Get total number of model terms (matching shortcode behavior: hide_empty=false)
    if (function_exists('tmw_count_terms')) {
        $term_count = tmw_count_terms('models', false);
    } else {
        $term_count = wp_count_terms('models', ['hide_empty' => false]);
    }

    // Override posts_per_page to match template display
    $query->set('posts_per_page', $per_page);

    // Override found_posts to reflect actual term count being displayed
    // This ensures max_num_pages calculation is correct: term_count ÷ per_page
    add_filter('found_posts', function($found_posts, $query_obj) use ($query, $term_count) {
        // Only override for this specific query
        if ($query_obj === $query) {
            return $term_count;
        }
        return $found_posts;
    }, 10, 2);

    // Optional: Debug logging if WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $paged = max(1, (int) $query->get('paged'));
        $max_pages = max(1, (int) ceil($term_count / $per_page));
        
        error_log(sprintf(
            '[TMW-MODEL-QUERY-FIX] Aligned query: terms=%d per_page=%d paged=%d max_pages=%d',
            $term_count,
            $per_page,
            $paged,
            $max_pages
        ));
    }
}

// Priority 50 ensures this runs after most other pre_get_posts hooks
// but before WordPress finalizes the query
add_action('pre_get_posts', 'tmw_fix_model_archive_query', 50);

/**
 * Optional: Log successful page renders for verification.
 *
 * This helps confirm that pages 9-12 now render correctly instead of 404ing.
 * Remove this once the fix is verified in production.
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp', function() {
        if (!is_post_type_archive('model')) {
            return;
        }

        global $wp_query;
        $paged = max(1, (int) get_query_var('paged'));
        $max_num_pages = $wp_query->max_num_pages;
        $is_404 = is_404();

        error_log(sprintf(
            '[TMW-MODEL-QUERY-FIX] Page render: paged=%d max_pages=%d is_404=%s found_posts=%d',
            $paged,
            $max_num_pages,
            $is_404 ? 'YES' : 'NO',
            $wp_query->found_posts
        ));
    }, 999);
}
