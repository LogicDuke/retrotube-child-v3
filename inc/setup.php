<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_child_thumbnail_post_types')) {
    /**
     * Post types that should always support featured images.
     *
     * @return string[]
     */
    function tmw_child_thumbnail_post_types() {
        return [
            'post',
            'page',
            'model',
            'video',
            'videos',
            'wpsc-video',
            'wp-script-video',
            'wpws_video',
            // Literal slug used because setup.php loads before TMW_CATEGORY_PAGE_CPT is defined.
            'tmw_category_page',
        ];
    }
}

add_action('after_setup_theme', function () {
    add_theme_support('post-thumbnails', tmw_child_thumbnail_post_types());
    add_image_size('tmw-model-hero-land', 1440, 810, true);
    add_image_size('tmw-model-hero-banner', 1200, 350, true);
    add_image_size('tmw-hero-mobile', 480, 270, true);
    add_image_size('tmw-hero-desktop', 1200, 675, true);
    add_image_size('tmw-front-optimized', 400, 600, true);
    // ... add any existing supports previously in functions.php (moved verbatim)
}, 10);

/**
 * Breadcrumb fix:
 * Remove category breadcrumb ONLY on single video pages
 * Do not affect other pages
 */
add_filter('get_the_category', function ($categories) {

    if (is_singular('video')) {
        return [];
    }

    return $categories;
});


add_action('init', function () {
    foreach (tmw_child_thumbnail_post_types() as $post_type) {
        if (post_type_exists($post_type)) {
            add_post_type_support($post_type, 'thumbnail');
        }
    }
}, 20);
