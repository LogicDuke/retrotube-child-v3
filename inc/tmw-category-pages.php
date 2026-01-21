<?php
/**
 * TMW Category Pages - CPT Mirror System
 * 
 * Creates editable "page" versions of category archives.
 * Each category gets a matching CPT post for full WordPress editing.
 *
 * @package suspended-flavor-flavor
 * @version 1.1.0 - Fixed RankMath Gutenberg sidebar
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ======================================================================
 * CONSTANTS
 * ====================================================================== */
if (!defined('TMW_CAT_PAGE_CPT')) {
    define('TMW_CAT_PAGE_CPT', 'category_page');
}

/* ======================================================================
 * REGISTER CUSTOM POST TYPE
 * Key: publicly_queryable must be true for RankMath to work
 * ====================================================================== */
add_action('init', function () {
    $labels = [
        'name'                  => __('Category Pages', 'retrotube-child'),
        'singular_name'         => __('Category Page', 'retrotube-child'),
        'menu_name'             => __('Category Pages', 'retrotube-child'),
        'add_new'               => __('Add New', 'retrotube-child'),
        'add_new_item'          => __('Add New Category Page', 'retrotube-child'),
        'edit_item'             => __('Edit Category Page', 'retrotube-child'),
        'new_item'              => __('New Category Page', 'retrotube-child'),
        'view_item'             => __('View Category Page', 'retrotube-child'),
        'search_items'          => __('Search Category Pages', 'retrotube-child'),
        'not_found'             => __('No category pages found.', 'retrotube-child'),
        'not_found_in_trash'    => __('No category pages found in Trash.', 'retrotube-child'),
    ];

    $args = [
        'labels'              => $labels,
        'public'              => false,
        'publicly_queryable'  => true,    // REQUIRED for RankMath
        'exclude_from_search' => true,    // Keep out of search
        'show_ui'             => true,
        'show_in_menu'        => false,
        'show_in_rest'        => true,    // REQUIRED for Gutenberg
        'has_archive'         => false,
        'rewrite'             => false,   // No public URLs
        'hierarchical'        => false,
        'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields'],
        'menu_icon'           => 'dashicons-category',
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ];

    register_post_type(TMW_CAT_PAGE_CPT, $args);
}, 5);

/* ======================================================================
 * HELPER: Get category_page CPT post for a category
 * ====================================================================== */
if (!function_exists('tmw_get_category_page_post')) {
    function tmw_get_category_page_post($category) {
        if (is_numeric($category)) {
            $category = get_term($category, 'category');
        }

        if (!$category instanceof WP_Term) {
            return null;
        }

        $linked_post_id = get_term_meta($category->term_id, '_tmw_category_page_id', true);

        if ($linked_post_id) {
            $post = get_post($linked_post_id);
            if ($post && $post->post_type === TMW_CAT_PAGE_CPT && $post->post_status !== 'trash') {
                return $post;
            }
        }

        $posts = get_posts([
            'post_type'      => TMW_CAT_PAGE_CPT,
            'name'           => $category->slug,
            'posts_per_page' => 1,
            'post_status'    => ['publish', 'draft', 'pending'],
        ]);

        if (!empty($posts)) {
            update_term_meta($category->term_id, '_tmw_category_page_id', $posts[0]->ID);
            return $posts[0];
        }

        return null;
    }
}

/* ======================================================================
 * HELPER: Create category_page CPT post for a category
 * ====================================================================== */
if (!function_exists('tmw_create_category_page_post')) {
    function tmw_create_category_page_post($category) {
        if (is_numeric($category)) {
            $category = get_term($category, 'category');
        }

        if (!$category instanceof WP_Term) {
            return new WP_Error('invalid_term', 'Invalid category provided.');
        }

        $existing = tmw_get_category_page_post($category);
        if ($existing) {
            return $existing->ID;
        }

        $post_data = [
            'post_type'    => TMW_CAT_PAGE_CPT,
            'post_title'   => $category->name,
            'post_name'    => $category->slug,
            'post_content' => $category->description ?: '',
            'post_status'  => 'publish',
            'meta_input'   => [
                '_tmw_linked_category_id' => $category->term_id,
            ],
        ];

        $post_id = wp_insert_post($post_data, true);

        if (!is_wp_error($post_id)) {
            update_term_meta($category->term_id, '_tmw_category_page_id', $post_id);
        }

        return $post_id;
    }
}

/* ======================================================================
 * AUTO-CREATE: When a new category is created
 * ====================================================================== */
add_action('created_category', function ($term_id, $tt_id) {
    tmw_create_category_page_post($term_id);
}, 10, 2);

/* ======================================================================
 * AUTO-CREATE: Bulk create for all existing categories (runs once)
 * ====================================================================== */
add_action('admin_init', function () {
    if (get_option('tmw_category_pages_initialized')) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    $categories = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => false,
    ]);

    if (is_wp_error($categories) || empty($categories)) {
        update_option('tmw_category_pages_initialized', 1);
        return;
    }

    $created = 0;
    foreach ($categories as $category) {
        if ($category->slug === 'uncategorized') {
            continue;
        }

        $result = tmw_create_category_page_post($category);
        if (!is_wp_error($result)) {
            $created++;
        }
    }

    update_option('tmw_category_pages_initialized', 1);

    if ($created > 0) {
        add_action('admin_notices', function () use ($created) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>TMW Category Pages:</strong> Created ' . esc_html($created) . ' category page(s).</p>';
            echo '</div>';
        });
    }
});

/* ======================================================================
 * ADMIN: Add "Edit Category Page" link to category list table
 * ====================================================================== */
add_filter('category_row_actions', function ($actions, $term) {
    $page_post = tmw_get_category_page_post($term);

    if ($page_post) {
        $edit_url = get_edit_post_link($page_post->ID);
        $actions['edit_category_page'] = sprintf(
            '<a href="%s" aria-label="%s">%s</a>',
            esc_url($edit_url),
            esc_attr__('Edit Category Page', 'retrotube-child'),
            __('Edit Page Content', 'retrotube-child')
        );
    } else {
        $create_url = wp_nonce_url(
            admin_url('admin-post.php?action=tmw_create_category_page&term_id=' . $term->term_id),
            'tmw_create_category_page_' . $term->term_id
        );
        $actions['create_category_page'] = sprintf(
            '<a href="%s" aria-label="%s">%s</a>',
            esc_url($create_url),
            esc_attr__('Create Category Page', 'retrotube-child'),
            __('Create Page', 'retrotube-child')
        );
    }

    return $actions;
}, 10, 2);

/* ======================================================================
 * ADMIN: Handle manual category page creation
 * ====================================================================== */
add_action('admin_post_tmw_create_category_page', function () {
    $term_id = isset($_GET['term_id']) ? absint($_GET['term_id']) : 0;

    if (!$term_id || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'tmw_create_category_page_' . $term_id)) {
        wp_die(__('Invalid request.', 'retrotube-child'));
    }

    if (!current_user_can('manage_categories')) {
        wp_die(__('You do not have permission to do this.', 'retrotube-child'));
    }

    $result = tmw_create_category_page_post($term_id);

    if (is_wp_error($result)) {
        wp_die($result->get_error_message());
    }

    wp_redirect(get_edit_post_link($result, 'raw'));
    exit;
});

/* ======================================================================
 * ADMIN: Add metabox showing linked category info
 * ====================================================================== */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'tmw_linked_category',
        __('Linked Category', 'retrotube-child'),
        function ($post) {
            $category_id = get_post_meta($post->ID, '_tmw_linked_category_id', true);

            if (!$category_id) {
                echo '<p>' . esc_html__('No linked category found.', 'retrotube-child') . '</p>';
                return;
            }

            $category = get_term($category_id, 'category');

            if (!$category || is_wp_error($category)) {
                echo '<p>' . esc_html__('Linked category not found.', 'retrotube-child') . '</p>';
                return;
            }

            $edit_link = get_edit_term_link($category->term_id, 'category');
            $view_link = get_term_link($category);

            echo '<p><strong>' . esc_html__('Category:', 'retrotube-child') . '</strong> ' . esc_html($category->name) . '</p>';
            echo '<p><strong>' . esc_html__('Slug:', 'retrotube-child') . '</strong> ' . esc_html($category->slug) . '</p>';
            echo '<p><strong>' . esc_html__('Videos:', 'retrotube-child') . '</strong> ' . esc_html($category->count) . '</p>';
            echo '<p>';
            echo '<a href="' . esc_url($view_link) . '" class="button" target="_blank">' . esc_html__('View Category', 'retrotube-child') . '</a> ';
            echo '<a href="' . esc_url($edit_link) . '" class="button">' . esc_html__('Edit Category', 'retrotube-child') . '</a>';
            echo '</p>';
        },
        TMW_CAT_PAGE_CPT,
        'side',
        'default'
    );
});

/* ======================================================================
 * SYNC: Update CPT when category is edited
 * ====================================================================== */
add_action('edited_category', function ($term_id, $tt_id) {
    $term = get_term($term_id, 'category');
    if (!$term || is_wp_error($term)) {
        return;
    }

    $page_post = tmw_get_category_page_post($term);
    if (!$page_post) {
        return;
    }

    $updates = [];

    if ($page_post->post_name !== $term->slug) {
        $updates['post_name'] = $term->slug;
    }

    if ($page_post->post_title === $term->name || empty($page_post->post_title)) {
        // Title was synced before, keep syncing
    }

    if (!empty($updates)) {
        $updates['ID'] = $page_post->ID;
        wp_update_post($updates);
    }
}, 10, 2);

/* ======================================================================
 * CLEANUP: Trash CPT when category is deleted
 * ====================================================================== */
add_action('pre_delete_term', function ($term_id, $taxonomy) {
    if ($taxonomy !== 'category') {
        return;
    }

    $term = get_term($term_id, 'category');
    if (!$term || is_wp_error($term)) {
        return;
    }

    $page_post = tmw_get_category_page_post($term);
    if ($page_post) {
        wp_trash_post($page_post->ID);
    }
}, 10, 2);

/* ======================================================================
 * FRONTEND: Get category page content for display
 * ====================================================================== */
if (!function_exists('tmw_get_category_page_content')) {
    function tmw_get_category_page_content($category = null) {
        if ($category === null) {
            $category = get_queried_object();
        }

        $default = [
            'title'       => '',
            'content'     => '',
            'excerpt'     => '',
            'has_content' => false,
            'post_id'     => 0,
        ];

        if (!$category instanceof WP_Term) {
            return $default;
        }

        $page_post = tmw_get_category_page_post($category);

        if (!$page_post || $page_post->post_status !== 'publish') {
            return $default;
        }

        $content = $page_post->post_content;

        if (!empty($content)) {
            $content = apply_filters('the_content', $content);
        }

        return [
            'title'       => $page_post->post_title,
            'content'     => $content,
            'excerpt'     => $page_post->post_excerpt,
            'has_content' => !empty(trim($page_post->post_content)),
            'post_id'     => $page_post->ID,
        ];
    }
}

/* ======================================================================
 * ADMIN: Add submenu page for managing all category pages
 * ====================================================================== */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php',
        __('Category Pages', 'retrotube-child'),
        __('Category Pages', 'retrotube-child'),
        'manage_categories',
        'edit.php?post_type=' . TMW_CAT_PAGE_CPT
    );
});

/* ======================================================================
 * ADMIN: Highlight correct menu when editing category_page
 * ====================================================================== */
add_filter('parent_file', function ($parent_file) {
    global $current_screen;

    if ($current_screen && $current_screen->post_type === TMW_CAT_PAGE_CPT) {
        return 'edit.php';
    }

    return $parent_file;
});

add_filter('submenu_file', function ($submenu_file) {
    global $current_screen;

    if ($current_screen && $current_screen->post_type === TMW_CAT_PAGE_CPT) {
        return 'edit.php?post_type=' . TMW_CAT_PAGE_CPT;
    }

    return $submenu_file;
});

/* ======================================================================
 * FRONTEND: Override archive description with CPT content
 * ====================================================================== */
add_filter('get_the_archive_description', function ($description) {
    if (!is_category()) {
        return $description;
    }

    $category = get_queried_object();
    if (!$category instanceof WP_Term) {
        return $description;
    }

    $page_data = tmw_get_category_page_content($category);

    if ($page_data['has_content']) {
        return $page_data['content'];
    }

    return $description;
}, 15);

/* ======================================================================
 * FRONTEND: Override archive title with CPT title (if customized)
 * ====================================================================== */
add_filter('get_the_archive_title', function ($title) {
    if (!is_category()) {
        return $title;
    }

    $category = get_queried_object();
    if (!$category instanceof WP_Term) {
        return $title;
    }

    $page_post = tmw_get_category_page_post($category);

    if (!$page_post || $page_post->post_status !== 'publish') {
        return $title;
    }

    if ($page_post->post_title !== $category->name) {
        return esc_html($page_post->post_title);
    }

    return $title;
}, 15);
