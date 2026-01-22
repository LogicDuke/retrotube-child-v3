<?php
/**
 * Admin single editor enforcement for category pages.
 *
 * @package retrotube-child
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', function () {
    global $pagenow;

    if ($pagenow !== 'term.php') {
        return;
    }

    $taxonomy = $_GET['taxonomy'] ?? '';
    if ($taxonomy !== 'category') {
        return;
    }

    if (!current_user_can('manage_categories')) {
        return;
    }

    if (!empty($_GET['tmw_force_term_edit'])) {
        return;
    }

    $term_id = 0;
    if (isset($_GET['tag_ID'])) {
        $term_id = (int) $_GET['tag_ID'];
    } elseif (isset($_GET['term_id'])) {
        $term_id = (int) $_GET['term_id'];
    }

    if (!$term_id) {
        return;
    }

    $term = get_term($term_id, 'category');
    if (!$term instanceof WP_Term) {
        return;
    }

    $post = tmw_get_category_page_post($term);
    if (!$post instanceof WP_Post) {
        $post_id = tmw_create_category_page_post($term);
        if (is_wp_error($post_id)) {
            return;
        }

        tmw_category_page_log_once(
            '[TMW-CAT-ADMIN-CREATE]',
            'term_id=' . $term->term_id . ' post_id=' . $post_id . ' user_id=' . get_current_user_id()
        );

        $post = get_post($post_id);
    }

    if (!$post instanceof WP_Post) {
        return;
    }

    $redirect_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
    $current_url = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    $user_id = get_current_user_id();

    tmw_category_page_log_once(
        '[TMW-CAT-ADMIN-REDIRECT]',
        'term_id=' . $term->term_id . ' post_id=' . $post->ID . ' redirect=' . $redirect_url . ' url=' . $current_url . ' user_id=' . $user_id
    );

    wp_safe_redirect($redirect_url);
    exit;
});

add_filter('get_edit_term_link', function ($link, $term_id, $taxonomy) {
    if (!is_admin() || $taxonomy !== 'category') {
        return $link;
    }

    $term = get_term($term_id, 'category');
    if (!$term instanceof WP_Term) {
        return $link;
    }

    $admin_link = tmw_category_page_admin_link($term);

    tmw_category_page_log_once(
        '[TMW-CAT-ADMIN-EDITLINK]',
        'term_id=' . $term->term_id . ' link=' . $admin_link . ' user_id=' . get_current_user_id()
    );

    return $admin_link;
}, 10, 3);

add_filter('category_row_actions', function ($actions, $term) {
    if (!current_user_can('manage_categories')) {
        return $actions;
    }

    foreach ($actions as $key => $value) {
        if (strpos($key, 'inline') !== false) {
            unset($actions[$key]);
        }
    }

    return $actions;
}, 20, 2);
