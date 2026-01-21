<?php
/**
 * TMW Category Pages - CPT Mirror System
 * 
 * Creates editable "page" versions of category archives.
 * Each category gets a matching CPT post for full WordPress editing.
 *
 * @package suspended-flavor-flavor
 * @version 1.0.0
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
 * Hidden from public, admin-only for editing category content
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
    'publicly_queryable'  => true,   // ← CHANGED from false to true
    'exclude_from_search' => true,   // ← ADDED - keeps it out of search
    'show_ui'             => true,
    'show_in_menu'        => false,
    'show_in_rest'        => true,
    'has_archive'         => false,
    'rewrite'             => false,  // No public URLs because this is false
    'hierarchical'        => false,
    'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
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
    /**
     * Get the category_page CPT post linked to a category.
     *
     * @param int|WP_Term $category Category term ID or object.
     * @return WP_Post|null The linked CPT post or null.
     */
    function tmw_get_category_page_post($category) {
        if (is_numeric($category)) {
            $category = get_term($category, 'category');
        }

        if (!$category instanceof WP_Term) {
            return null;
        }

        // Check if we have a linked CPT post ID stored
        $linked_post_id = get_term_meta($category->term_id, '_tmw_category_page_id', true);

        if ($linked_post_id) {
            $post = get_post($linked_post_id);
            if ($post && $post->post_type === TMW_CAT_PAGE_CPT && $post->post_status !== 'trash') {
                return $post;
            }
        }

        // Fallback: find by slug match
        $posts = get_posts([
            'post_type'      => TMW_CAT_PAGE_CPT,
            'name'           => $category->slug,
            'posts_per_page' => 1,
            'post_status'    => ['publish', 'draft', 'pending'],
        ]);

        if (!empty($posts)) {
            // Store the link for future lookups
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
    /**
     * Create a category_page CPT post for a category.
     *
     * @param int|WP_Term $category Category term ID or object.
     * @return int|WP_Error Post ID on success, WP_Error on failure.
     */
    function tmw_create_category_page_post($category) {
        if (is_numeric($category)) {
            $category = get_term($category, 'category');
        }

        if (!$category instanceof WP_Term) {
            return new WP_Error('invalid_term', 'Invalid category provided.');
        }

        // Check if already exists
        $existing = tmw_get_category_page_post($category);
        if ($existing) {
            return $existing->ID;
        }

        // Create new CPT post
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
            // Store the link on the term
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
    // Only run once
    if (get_option('tmw_category_pages_initialized')) {
        return;
    }

    // Only for admins
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
        // Skip "Uncategorized" default category
        if ($category->slug === 'uncategorized') {
            continue;
        }

        $result = tmw_create_category_page_post($category);
        if (!is_wp_error($result)) {
            $created++;
        }
    }

    update_option('tmw_category_pages_initialized', 1);

    // Admin notice
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
        // Create link if doesn't exist
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
        wp_die(__('Permission denied.', 'retrotube-child'));
    }

    $result = tmw_create_category_page_post($term_id);

    if (is_wp_error($result)) {
        wp_die($result->get_error_message());
    }

    // Redirect to edit the new post
    wp_redirect(get_edit_post_link($result, 'raw'));
    exit;
});

/* ======================================================================
 * ADMIN: Add info metabox to category_page edit screen
 * ====================================================================== */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'tmw_category_page_info',
        __('Linked Category', 'retrotube-child'),
        'tmw_category_page_info_metabox',
        TMW_CAT_PAGE_CPT,
        'side',
        'high'
    );
});

if (!function_exists('tmw_category_page_info_metabox')) {
    /**
     * Render the category info metabox.
     *
     * @param WP_Post $post Current post object.
     */
    function tmw_category_page_info_metabox($post) {
        $term_id = get_post_meta($post->ID, '_tmw_linked_category_id', true);

        if (!$term_id) {
            echo '<p>' . esc_html__('No linked category found.', 'retrotube-child') . '</p>';
            return;
        }

        $term = get_term($term_id, 'category');

        if (!$term || is_wp_error($term)) {
            echo '<p>' . esc_html__('Linked category no longer exists.', 'retrotube-child') . '</p>';
            return;
        }

        $category_url = get_term_link($term);
        $edit_url = get_edit_term_link($term->term_id, 'category');

        echo '<p><strong>' . esc_html__('Category:', 'retrotube-child') . '</strong> ' . esc_html($term->name) . '</p>';
        echo '<p><strong>' . esc_html__('Slug:', 'retrotube-child') . '</strong> <code>' . esc_html($term->slug) . '</code></p>';
        echo '<p><strong>' . esc_html__('Videos:', 'retrotube-child') . '</strong> ' . esc_html($term->count) . '</p>';
        echo '<p>';
        echo '<a href="' . esc_url($category_url) . '" class="button" target="_blank">' . esc_html__('View Category', 'retrotube-child') . '</a> ';
        echo '<a href="' . esc_url($edit_url) . '" class="button">' . esc_html__('Edit Category', 'retrotube-child') . '</a>';
        echo '</p>';
    }
}

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

    // Only sync title/slug if they match (avoid overwriting custom titles)
    $updates = [];

    // Always keep slug in sync
    if ($page_post->post_name !== $term->slug) {
        $updates['post_name'] = $term->slug;
    }

    // Only sync title if it was previously matching
    if ($page_post->post_title === $term->name || empty($page_post->post_content)) {
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
    /**
     * Get the content from the category_page CPT for a category.
     *
     * @param int|WP_Term|null $category Category (defaults to current queried object).
     * @return array{title: string, content: string, excerpt: string, has_content: bool}
     */
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

        // Apply content filters for shortcodes, blocks, etc.
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
 * RANK MATH: Register CPT for SEO (Gutenberg sidebar + REST support)
 * ====================================================================== */
if (!function_exists('tmw_rank_math_add_category_page_post_type')) {
    /**
     * Ensure Rank Math includes the category_page CPT even when public is false.
     *
     * @param array $post_types Post types list.
     * @return array
     */
    function tmw_rank_math_add_category_page_post_type($post_types) {
        if (!is_array($post_types)) {
            $post_types = [];
        }

        if (!in_array(TMW_CAT_PAGE_CPT, $post_types, true)) {
            $post_types[] = TMW_CAT_PAGE_CPT;
        }

        return $post_types;
    }
}

add_filter('rank_math/post_types', 'tmw_rank_math_add_category_page_post_type', 20);
add_filter('rank_math/metabox/post_types', 'tmw_rank_math_add_category_page_post_type', 20);
add_filter('rank_math/gutenberg/post_types', 'tmw_rank_math_add_category_page_post_type', 20);
add_filter('rank_math/rest_post_types', 'tmw_rank_math_add_category_page_post_type', 20);

// Allow Rank Math to treat this non-public CPT as accessible for Gutenberg.
add_filter('rank_math/is_post_type_accessible', function ($is_accessible, $post_type) {
    if ($post_type === TMW_CAT_PAGE_CPT) {
        return true;
    }

    return $is_accessible;
}, 20, 2);

// Bypass WordPress viewability checks for Rank Math in wp-admin.
add_filter('is_post_type_viewable', function ($is_viewable, $post_type) {
    if (is_admin() && $post_type === TMW_CAT_PAGE_CPT) {
        return true;
    }

    return $is_viewable;
}, 20, 2);

// Ensure Rank Math scripts load on category_page edit screen in Gutenberg.
add_filter('rank_math/admin/editor_scripts', function ($load) {
    global $post_type;

    if ($post_type === TMW_CAT_PAGE_CPT) {
        return true;
    }

    return $load;
}, 20);

// Enable Rank Math for this post type in settings.
add_filter('rank_math/settings/general', function ($settings) {
    if (!isset($settings['pt_' . TMW_CAT_PAGE_CPT . '_add_meta_box'])) {
        $settings['pt_' . TMW_CAT_PAGE_CPT . '_add_meta_box'] = 'on';
    }

    return $settings;
}, 20);

// Force the option so Rank Math sidebar loads for hidden CPTs.
add_filter('option_rank-math-options-titles', function ($options) {
    if (!is_array($options)) {
        $options = [];
    }

    $options['pt_' . TMW_CAT_PAGE_CPT . '_add_meta_box'] = 'on';

    return $options;
}, 20);

// Persist the Rank Math meta box toggle for category_page so Gutenberg sidebar loads.
add_action('admin_init', function () {
    $options = get_option('rank-math-options-titles');
    if (!is_array($options)) {
        $options = [];
    }

    if (($options['pt_' . TMW_CAT_PAGE_CPT . '_add_meta_box'] ?? '') !== 'on') {
        $options['pt_' . TMW_CAT_PAGE_CPT . '_add_meta_box'] = 'on';
        update_option('rank-math-options-titles', $options);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[TMW-SEO] Enabled Rank Math meta box for category_page CPT.');
        }
    }
}, 20);

/* ======================================================================
 * ADMIN: Add submenu page for managing all category pages
 * ====================================================================== */
add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php',                                    // Parent: Posts menu
        __('Category Pages', 'retrotube-child'),       // Page title
        __('Category Pages', 'retrotube-child'),       // Menu title
        'manage_categories',                           // Capability
        'edit.php?post_type=' . TMW_CAT_PAGE_CPT       // URL
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

    // If CPT has content, use it; otherwise fall back to original description
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

    // Only override if the CPT title differs from the category name
    // This allows custom titles while keeping auto-sync working
    if ($page_post->post_title !== $category->name) {
        return esc_html($page_post->post_title);
    }

    return $title;
}, 15);
