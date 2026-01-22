<?php
/**
 * TMW link normalization helpers.
 *
 * @package retrotube-child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_link_normalize_log_once')) {
    function tmw_link_normalize_log_once(string $message): void {
        static $logged = [];

        if (!empty($logged[$message])) {
            return;
        }

        $logged[$message] = true;
        error_log('[TMW-LINK-NORMALIZE] ' . $message);
    }
}

if (!function_exists('tmw_filter_links_get_model_post')) {
    function tmw_filter_links_get_model_post(WP_Term $term): ?WP_Post {
        if (function_exists('tmw_get_model_post_for_term')) {
            $post = tmw_get_model_post_for_term($term);
            if ($post instanceof WP_Post) {
                return $post;
            }
        }

        if (!post_type_exists('model')) {
            return null;
        }

        $post = get_page_by_path($term->slug, OBJECT, 'model');
        if (!$post) {
            $post = get_page_by_title($term->name, OBJECT, 'model');
        }

        if ($post instanceof WP_Post && $post->post_status !== 'trash') {
            return $post;
        }

        return null;
    }
}

add_filter('term_link', function ($url, $term, $taxonomy) {
    $term = $term instanceof WP_Term ? $term : null;

    if (!$term || $taxonomy !== 'models') {
        return $url;
    }

    $post = tmw_filter_links_get_model_post($term);
    if (!$post) {
        return $url;
    }

    $post_link = get_permalink($post);
    if (!$post_link) {
        return $url;
    }

    tmw_link_normalize_log_once('Models term ' . $term->term_id . ' linked to post ' . $post->ID . '.');
    return $post_link;
}, 20, 3);

add_filter('post_type_link', function ($post_link, $post, $leavename, $sample) {
    if (!$post instanceof WP_Post) {
        return $post_link;
    }

    $category_cpt = defined('TMW_CATEGORY_PAGE_CPT') ? TMW_CATEGORY_PAGE_CPT : 'tmw_category_page';
    if ($post->post_type !== $category_cpt) {
        return $post_link;
    }

    $term_link = null;
    if (function_exists('tmw_category_page_get_linked_term_url')) {
        $term_link = tmw_category_page_get_linked_term_url($post->ID);
    }

    if (!$term_link) {
        $term_id = (int) get_post_meta($post->ID, '_tmw_linked_term_id', true);
        $taxonomy = (string) get_post_meta($post->ID, '_tmw_linked_taxonomy', true);

        if ($term_id && $taxonomy === 'category') {
            $term = get_term($term_id, 'category');
            if ($term instanceof WP_Term) {
                $term_link = get_term_link($term);
                if (is_wp_error($term_link)) {
                    $term_link = null;
                }
            }
        }
    }

    if (!$term_link) {
        return $post_link;
    }

    tmw_link_normalize_log_once('Category page post ' . $post->ID . ' linked to term URL.');
    return $term_link;
}, 20, 4);

add_filter('preview_post_link', function ($link, $post) {
    if (!$post instanceof WP_Post) {
        return $link;
    }

    $category_cpt = defined('TMW_CATEGORY_PAGE_CPT') ? TMW_CATEGORY_PAGE_CPT : 'tmw_category_page';
    if ($post->post_type !== $category_cpt) {
        return $link;
    }

    $term_link = null;
    if (function_exists('tmw_category_page_get_linked_term_url')) {
        $term_link = tmw_category_page_get_linked_term_url($post->ID);
    }

    if (!$term_link) {
        return $link;
    }

    tmw_link_normalize_log_once('Preview link normalized for category page post ' . $post->ID . '.');
    return $term_link;
}, 20, 2);
