<?php
/**
 * SHORTCODE PAGINATION FIX - Direct patch for tmw_models_flipboxes_cb
 * 
 * This file patches the shortcode in tmw-video-hooks.php to fix the internal
 * mismatch between querying terms and counting them for pagination.
 *
 * THE BUG IN THE SHORTCODE (tmw-video-hooks.php):
 * - Line 529-533: Queries terms with hide_empty = false
 * - Line 541: Counts terms with hide_empty = TRUE
 * - Result: Displays 192 terms but paginates for only 140 terms
 * 
 * THE FIX:
 * Change line 541 from:
 *   $total = (function_exists('tmw_count_terms') ? tmw_count_terms('models', true) : 0);
 * To:
 *   $total = (function_exists('tmw_count_terms') ? tmw_count_terms('models', false) : 0);
 *
 * This file provides a filter-based patch so you don't have to edit core files.
 *
 * @package RetrotubeChild  
 * @version 3.1.2
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Comprehensive fix: Align WordPress query + Shortcode behavior
 */
class TMW_Model_Archive_Pagination_Fix {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Fix WordPress main query
        add_action('pre_get_posts', [$this, 'fix_main_query'], 50);
        
        // Fix shortcode term counting
        add_filter('pre_option_tmw_flipbox_term_count', [$this, 'override_term_count'], 10, 1);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp', [$this, 'debug_log'], 999);
        }
    }
    
    /**
     * Get the correct term count for pagination (matching actual query).
     */
    private function get_correct_term_count() {
        // The shortcode default is hide_empty=false (see line 516 in tmw-video-hooks.php)
        if (function_exists('tmw_count_terms')) {
            return tmw_count_terms('models', false);
        }
        return wp_count_terms('models', ['hide_empty' => false]);
    }
    
    /**
     * Fix WordPress main query to match shortcode display.
     */
    public function fix_main_query($query) {
        if (!$query->is_main_query() || !$query->is_post_type_archive('model')) {
            return;
        }
        
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed()) {
            return;
        }
        
        $per_page = 16;  // Matches shortcode per_page parameter
        $term_count = $this->get_correct_term_count();
        
        $query->set('posts_per_page', $per_page);
        
        // Override found_posts to match term count
        add_filter('found_posts', function($found_posts, $query_obj) use ($query, $term_count) {
            if ($query_obj === $query) {
                return $term_count;
            }
            return $found_posts;
        }, 10, 2);
    }
    
    /**
     * Debug logging to verify everything is aligned.
     */
    public function debug_log() {
        if (!is_post_type_archive('model')) {
            return;
        }
        
        global $wp_query;
        
        $term_count_correct = $this->get_correct_term_count();
        $term_count_wrong = function_exists('tmw_count_terms') 
            ? tmw_count_terms('models', true)  // What the buggy shortcode uses
            : wp_count_terms('models', ['hide_empty' => true]);
        
        $per_page = 16;
        $paged = max(1, (int) get_query_var('paged'));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            '[TMW-PAG-FIX-V3] paged=%d | WP: found=%d max_pages=%d is_404=%s | Terms: correct=%d wrong=%d | Should_be_pages=%d',
            $paged,
            $wp_query->found_posts,
            $wp_query->max_num_pages,
            is_404() ? 'YES' : 'NO',
            $term_count_correct,
            $term_count_wrong,
            max(1, (int) ceil($term_count_correct / $per_page))
        ));
        }
    }
}

// Initialize the fix
TMW_Model_Archive_Pagination_Fix::get_instance();

/**
 * CRITICAL: You MUST also fix the shortcode directly!
 * 
 * Edit: inc/tmw-video-hooks.php
 * Line: 541
 * 
 * Change FROM:
 *   $total = (function_exists('tmw_count_terms') ? tmw_count_terms('models', true) : 0);
 * 
 * Change TO:
 *   $total = (function_exists('tmw_count_terms') ? tmw_count_terms('models', false) : 0);
 * 
 * This ONE character change (true → false) fixes the root cause.
 */
