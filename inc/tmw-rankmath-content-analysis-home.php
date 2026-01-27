<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('enqueue_block_editor_assets', function (): void {
    if (!is_admin()) {
        return;
    }

    if (!defined('RANK_MATH_VERSION') && !class_exists('\RankMath\\Helper')) {
        return;
    }

    $front_page_id = (int) get_option('page_on_front');
    if ($front_page_id <= 0) {
        return;
    }

    $current_post_id = 0;
    if (isset($_GET['post'])) {
        $current_post_id = (int) $_GET['post'];
    } elseif (isset($_POST['post_ID'])) {
        $current_post_id = (int) $_POST['post_ID'];
    } else {
        $post = get_post();
        if ($post) {
            $current_post_id = (int) $post->ID;
        }
    }

    if ($current_post_id !== $front_page_id) {
        return;
    }

    wp_enqueue_script(
        'tmw-rankmath-home-accordion',
        get_stylesheet_directory_uri() . '/js/tmw-rankmath-home-accordion.js',
        ['wp-hooks', 'wp-data', 'rank-math-analyzer'],
        defined('TMW_CHILD_VERSION') ? TMW_CHILD_VERSION : null,
        true
    );

    wp_localize_script(
        'tmw-rankmath-home-accordion',
        'tmwRankMathHome',
        [
            'frontPageId'   => $front_page_id,
            'currentPostId' => $current_post_id,
        ]
    );
});
