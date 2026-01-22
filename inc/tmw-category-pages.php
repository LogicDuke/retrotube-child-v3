<?php
/**
 * TMW Category Pages - CPT-backed SEO surface for category archives.
 *
 * @package retrotube-child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('TMW_CATEGORY_PAGE_CPT')) {
    define('TMW_CATEGORY_PAGE_CPT', 'tmw_category_page');
}

if (!function_exists('tmw_category_page_log_once')) {
    function tmw_category_page_log_once(string $tag, string $message): void {
        static $logged = [];

        if (!empty($logged[$tag])) {
            return;
        }

        $logged[$tag] = true;
        error_log($tag . ' ' . $message);
    }
}

if (!function_exists('tmw_category_page_audit_log_once')) {
    function tmw_category_page_audit_log_once(string $key, string $message): void {
        static $logged = [];

        if (!empty($logged[$key])) {
            return;
        }

        $logged[$key] = true;
        error_log('[TMW-CAT-ADMIN-AUDIT] ' . $message);
    }
}

if (!function_exists('tmw_category_page_get_linked_term_url')) {
    function tmw_category_page_get_linked_term_url(int $post_id): ?string {
        static $cached_links = [];

        if (!$post_id) {
            return null;
        }

        if (array_key_exists($post_id, $cached_links)) {
            return $cached_links[$post_id];
        }

        if (get_post_type($post_id) !== TMW_CATEGORY_PAGE_CPT) {
            $cached_links[$post_id] = null;
            return null;
        }

        $term_id = (int) get_post_meta($post_id, '_tmw_linked_term_id', true);

        if (!$term_id) {
            $cached_links[$post_id] = null;
            return null;
        }

        $term = get_term($term_id, 'category');
        if (!$term instanceof WP_Term || $term->taxonomy !== 'category') {
            $cached_links[$post_id] = null;
            return null;
        }

        $term_link = get_term_link($term);
        if (is_wp_error($term_link)) {
            $cached_links[$post_id] = null;
            return null;
        }

        $cached_links[$post_id] = $term_link;
        return $term_link;
    }
}

add_action('init', function () {
    $labels = [
        'name'          => __('Category Pages', 'retrotube-child'),
        'singular_name' => __('Category Page', 'retrotube-child'),
    ];

    $args = [
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => true,
        'supports'            => ['title', 'editor', 'excerpt', 'revisions'],
        'exclude_from_search' => false,
        'rewrite'             => false,
    ];

    register_post_type(TMW_CATEGORY_PAGE_CPT, $args);
    error_log('[TMW-CAT-CPT-VISIBILITY] Registered Category Page CPT.');
}, 5);

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || is_feed()) {
        return;
    }

    if (!is_singular(TMW_CATEGORY_PAGE_CPT)) {
        return;
    }

    $post_id = get_queried_object_id();
    $term_id = get_post_meta($post_id, '_tmw_linked_term_id', true);
    $taxonomy = get_post_meta($post_id, '_tmw_linked_taxonomy', true);

    if (!$term_id || $taxonomy !== 'category') {
        return;
    }

    $term = get_term((int) $term_id, 'category');
    if (!$term instanceof WP_Term) {
        return;
    }

    $term_link = get_term_link($term);
    if (is_wp_error($term_link)) {
        return;
    }

    error_log('[TMW-CAT-REDIRECT] Redirecting category page post ' . $post_id . ' to term ' . $term->term_id . '.');
    wp_safe_redirect($term_link);
    exit;
});

add_filter('rank_math/frontend/canonical', function ($canonical) {
    $post_id = get_queried_object_id();

    if (!$post_id || get_post_type($post_id) !== TMW_CATEGORY_PAGE_CPT) {
        return $canonical;
    }

    $term_link = tmw_category_page_get_linked_term_url($post_id);
    if ($term_link) {
        tmw_category_page_log_once(
            '[TMW-CAT-RM-CANONICAL]',
            'Using category URL ' . $term_link . ' for post ' . $post_id . '.'
        );
        return $term_link;
    }

    tmw_category_page_log_once(
        '[TMW-CAT-RM-CANONICAL]',
        'Falling back to original canonical for post ' . $post_id . '.'
    );
    return $canonical;
}, 20);

add_filter('rank_math/opengraph/url', function ($url) {
    $post_id = get_queried_object_id();

    if (!$post_id || get_post_type($post_id) !== TMW_CATEGORY_PAGE_CPT) {
        return $url;
    }

    $term_link = tmw_category_page_get_linked_term_url($post_id);
    if ($term_link) {
        tmw_category_page_log_once(
            '[TMW-CAT-RM-OG]',
            'Using category URL ' . $term_link . ' for post ' . $post_id . '.'
        );
        return $term_link;
    }

    tmw_category_page_log_once(
        '[TMW-CAT-RM-OG]',
        'Falling back to original opengraph URL for post ' . $post_id . '.'
    );
    return $url;
}, 20);

add_filter('rank_math/twitter/url', function ($url) {
    $post_id = get_queried_object_id();

    if (!$post_id || get_post_type($post_id) !== TMW_CATEGORY_PAGE_CPT) {
        return $url;
    }

    $term_link = tmw_category_page_get_linked_term_url($post_id);
    if ($term_link) {
        tmw_category_page_log_once(
            '[TMW-CAT-RM-TWITTER]',
            'Using category URL ' . $term_link . ' for post ' . $post_id . '.'
        );
        return $term_link;
    }

    tmw_category_page_log_once(
        '[TMW-CAT-RM-TWITTER]',
        'Falling back to original twitter URL for post ' . $post_id . '.'
    );
    return $url;
}, 20);

add_filter('rank_math/pre_get_preview_url', function ($url, $post) {
    $post_id = $post instanceof WP_Post ? $post->ID : (int) $post;

    if (!$post_id || get_post_type($post_id) !== TMW_CATEGORY_PAGE_CPT) {
        return $url;
    }

    $term_link = tmw_category_page_get_linked_term_url($post_id);
    if ($term_link) {
        tmw_category_page_log_once(
            '[TMW-CAT-RM-PREVIEW]',
            'Using category URL ' . $term_link . ' for post ' . $post_id . '.'
        );
        return $term_link;
    }

    tmw_category_page_log_once(
        '[TMW-CAT-RM-PREVIEW]',
        'Falling back to original preview URL for post ' . $post_id . '.'
    );
    return $url;
}, 20, 2);

if (!function_exists('tmw_get_category_page_post')) {
    function tmw_get_category_page_post($category) {
        if (is_numeric($category)) {
            $category = get_term((int) $category, 'category');
        }

        if (!$category instanceof WP_Term) {
            return null;
        }

        $posts = get_posts([
            'post_type'      => TMW_CATEGORY_PAGE_CPT,
            'posts_per_page' => 2,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'meta_query'     => [
                [
                    'key'   => '_tmw_linked_term_id',
                    'value' => $category->term_id,
                ],
                [
                    'key'   => '_tmw_linked_taxonomy',
                    'value' => 'category',
                ],
            ],
        ]);

        if (count($posts) > 1) {
            $ids = wp_list_pluck($posts, 'ID');
            error_log('[TMW-CAT-GUARD] Multiple category page posts detected for term ' . $category->term_id . ': ' . implode(',', $ids));
        }

        if (!empty($posts)) {
            return $posts[0];
        }

        $slug_match = get_posts([
            'post_type'      => TMW_CATEGORY_PAGE_CPT,
            'posts_per_page' => 2,
            'name'           => $category->slug,
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
        ]);

        if (count($slug_match) > 1) {
            $ids = wp_list_pluck($slug_match, 'ID');
            error_log('[TMW-CAT-GUARD] Multiple slug matches for category term ' . $category->term_id . ': ' . implode(',', $ids));
        }

        if (!empty($slug_match)) {
            update_post_meta($slug_match[0]->ID, '_tmw_linked_term_id', $category->term_id);
            update_post_meta($slug_match[0]->ID, '_tmw_linked_taxonomy', 'category');
            error_log('[TMW-CAT-LINK] Linked existing category page post ' . $slug_match[0]->ID . ' to term ' . $category->term_id . '.');
            return $slug_match[0];
        }

        return null;
    }
}

if (!function_exists('tmw_category_page_audit_resolve_post')) {
    function tmw_category_page_audit_resolve_post($category): ?WP_Post {
        if (is_numeric($category)) {
            $category = get_term((int) $category, 'category');
        }

        if (!$category instanceof WP_Term) {
            return null;
        }

        $post = tmw_get_category_page_post($category);
        $post_id = $post instanceof WP_Post ? $post->ID : 0;
        $post_type = $post instanceof WP_Post ? $post->post_type : 'none';
        $post_status = $post instanceof WP_Post ? $post->post_status : 'none';

        tmw_category_page_audit_log_once(
            'resolve_category_page_' . $category->term_id,
            'resolve_category_page term_id=' . $category->term_id . ' found_post=' . $post_id . ' post_type=' . $post_type . ' status=' . $post_status
        );

        return $post instanceof WP_Post ? $post : null;
    }
}

add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!is_category() || !is_user_logged_in() || !current_user_can('manage_categories')) {
        return;
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return;
    }

    $node = $wp_admin_bar->get_node('edit');
    $href = $node ? (string) $node->href : 'none';
    $title = $node ? (string) $node->title : 'none';
    $post = tmw_category_page_audit_resolve_post($term);
    $post_id = $post instanceof WP_Post ? $post->ID : 0;
    $current_url = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');

    tmw_category_page_audit_log_once(
        'admin_bar_edit_node',
        'admin_bar_edit_node href=' . $href . ' title=' . $title . ' term_id=' . $term->term_id . ' term_slug=' . $term->slug . ' post_id=' . $post_id . ' url=' . $current_url
    );
}, 999);

add_filter('get_edit_term_link', function ($link, $term_id, $taxonomy, $object_type) {
    if ($taxonomy !== 'category') {
        return $link;
    }

    $is_category = is_category();
    $is_frontend = !is_admin();

    tmw_category_page_audit_log_once(
        'get_edit_term_link_' . (int) $term_id,
        'get_edit_term_link term_id=' . (int) $term_id . ' taxonomy=' . $taxonomy . ' link=' . $link . ' is_category=' . ($is_category ? 'yes' : 'no') . ' is_frontend=' . ($is_frontend ? 'yes' : 'no')
    );

    return $link;
}, 10, 4);

if (!function_exists('tmw_create_category_page_post')) {
    function tmw_create_category_page_post($category) {
        if (is_numeric($category)) {
            $category = get_term((int) $category, 'category');
        }

        if (!$category instanceof WP_Term) {
            return new WP_Error('tmw_invalid_category', __('Invalid category.', 'retrotube-child'));
        }

        $existing = tmw_get_category_page_post($category);
        if ($existing instanceof WP_Post) {
            return $existing->ID;
        }

        $post_id = wp_insert_post([
            'post_type'    => TMW_CATEGORY_PAGE_CPT,
            'post_title'   => $category->name,
            'post_name'    => $category->slug,
            'post_status'  => 'publish',
            'post_content' => '',
            'meta_input'   => [
                '_tmw_linked_term_id'   => $category->term_id,
                '_tmw_linked_taxonomy'  => 'category',
            ],
        ], true);

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        error_log('[TMW-CAT-LINK] Created category page post ' . $post_id . ' for term ' . $category->term_id . '.');
        return $post_id;
    }
}

add_action('created_category', function ($term_id) {
    tmw_create_category_page_post($term_id);
}, 10, 1);

add_action('edited_category', function ($term_id) {
    $term = get_term($term_id, 'category');
    if (!$term instanceof WP_Term) {
        return;
    }

    $post = tmw_get_category_page_post($term);
    if (!$post instanceof WP_Post) {
        return;
    }

    $updates = [];
    if ($post->post_name !== $term->slug) {
        $updates['post_name'] = $term->slug;
    }

    if (!empty($updates)) {
        $updates['ID'] = $post->ID;
        wp_update_post($updates);
    }
}, 10, 1);

if (!function_exists('tmw_category_page_admin_link')) {
    function tmw_category_page_admin_link(WP_Term $term): string {
        $url = add_query_arg([
            'action'  => 'tmw_category_page_edit',
            'term_id' => $term->term_id,
        ], admin_url('admin-post.php'));

        return wp_nonce_url($url, 'tmw_category_page_edit_' . $term->term_id);
    }
}

add_filter('category_row_actions', function ($actions, $term) {
    if (!current_user_can('manage_categories')) {
        return $actions;
    }

    $actions['tmw_edit_category_page'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url(tmw_category_page_admin_link($term)),
        esc_html__('Edit Category Page', 'retrotube-child')
    );

    return $actions;
}, 10, 2);

add_action('category_edit_form_fields', function ($term) {
    if (!current_user_can('manage_categories')) {
        return;
    }

    $link = tmw_category_page_admin_link($term);
    ?>
    <tr class="form-field tmw-category-page-edit">
        <th scope="row"><?php esc_html_e('Category Page', 'retrotube-child'); ?></th>
        <td>
            <a class="button" href="<?php echo esc_url($link); ?>">
                <?php esc_html_e('Edit Category Page', 'retrotube-child'); ?>
            </a>
        </td>
    </tr>
    <?php
});

add_action('admin_init', function () {
    global $pagenow;

    if ($pagenow !== 'term.php') {
        return;
    }

    $taxonomy = $_GET['taxonomy'] ?? '';
    if ($taxonomy !== 'category') {
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

    $post = tmw_category_page_audit_resolve_post($term);
    $post_id = $post instanceof WP_Post ? $post->ID : 0;
    $current_url = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    $would_redirect_to = $post_id ? admin_url('post.php?post=' . $post_id . '&action=edit') : 'none';

    tmw_category_page_audit_log_once(
        'term_php_access_' . $term->term_id,
        'term_php_access term_id=' . $term->term_id . ' post_id=' . $post_id . ' url=' . $current_url . ' would_redirect_to=' . $would_redirect_to
    );
});

add_action('admin_post_tmw_category_page_edit', function () {
    if (!current_user_can('manage_categories')) {
        wp_die(__('You do not have permission to do this.', 'retrotube-child'));
    }

    $term_id = isset($_GET['term_id']) ? (int) $_GET['term_id'] : 0;
    if (!$term_id || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'tmw_category_page_edit_' . $term_id)) {
        wp_die(__('Invalid request.', 'retrotube-child'));
    }

    $term = get_term($term_id, 'category');
    if (!$term instanceof WP_Term) {
        wp_die(__('Invalid category.', 'retrotube-child'));
    }

    $post = tmw_get_category_page_post($term);
    if (!$post instanceof WP_Post) {
        $post_id = tmw_create_category_page_post($term);
        if (is_wp_error($post_id)) {
            wp_die($post_id->get_error_message());
        }
        $post = get_post($post_id);
    }

    if (!$post instanceof WP_Post) {
        wp_die(__('Unable to resolve category page.', 'retrotube-child'));
    }

    $redirect_url = admin_url('post.php?post=' . $post->ID . '&action=edit');
    $current_url = (is_ssl() ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    $user_id = get_current_user_id();

    tmw_category_page_log_once(
        '[TMW-CAT-ADMIN-AUDIT]',
        'term_id=' . $term->term_id . ' post_id=' . $post->ID . ' redirect=' . $redirect_url . ' url=' . $current_url . ' user_id=' . $user_id
    );

    error_log('[TMW-CAT-ADMIN] Opening category page edit for term ' . $term->term_id . ' (post ' . $post->ID . ').');

    wp_safe_redirect($redirect_url);
    exit;
});

if (is_admin()) {
    require_once __DIR__ . '/admin/tmw-category-pages-admin-single-editor.php';
}

if (!function_exists('tmw_category_page_extract_sections')) {
    function tmw_category_page_extract_sections(WP_Post $post): array {
        $intro_html = '';
        $body_html = '';
        $faq_html = '';

        if ($post->post_excerpt !== '') {
            $intro_html = apply_filters('the_excerpt', $post->post_excerpt);
        }

        $raw_content = (string) $post->post_content;
        $body_raw = $raw_content;

        if ($intro_html === '' && strpos($raw_content, '<!--more-->') !== false) {
            $extended = get_extended($raw_content);
            $intro_raw = $extended['main'] ?? '';
            $body_raw = $extended['extended'] ?? '';
            if (trim($intro_raw) !== '') {
                $intro_html = apply_filters('the_content', $intro_raw);
            }
        }

        if (trim($body_raw) === '') {
            return [
                'intro' => $intro_html,
                'body'  => '',
                'faq'   => '',
            ];
        }

        $faq_blocks = [];
        $content_blocks = [];
        $faq_block_names = [
            'rank-math/faq-block',
            'yoast/faq-block',
            'core/faq',
        ];

        if (has_blocks($body_raw)) {
            $blocks = parse_blocks($body_raw);
            foreach ($blocks as $block) {
                if (isset($block['blockName']) && in_array($block['blockName'], $faq_block_names, true)) {
                    $faq_blocks[] = $block;
                    continue;
                }
                $content_blocks[] = $block;
            }

            foreach ($content_blocks as $block) {
                $body_html .= render_block($block);
            }

            foreach ($faq_blocks as $block) {
                $faq_html .= render_block($block);
            }
        } else {
            $body_html = apply_filters('the_content', $body_raw);
        }

        return [
            'intro' => $intro_html,
            'body'  => $body_html,
            'faq'   => $faq_html,
        ];
    }
}

add_action('loop_start', function ($query) {
    if (is_admin() || wp_doing_ajax() || is_feed()) {
        return;
    }

    if (!$query->is_main_query() || !is_category()) {
        return;
    }

    if (!empty($GLOBALS['tmw_category_page_rendered'])) {
        return;
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return;
    }

    $post = tmw_get_category_page_post($term);
    if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
        error_log('[TMW-CAT-FALLBACK] Category page fallback for term ' . $term->term_id . '.');
        return;
    }

    $sections = tmw_category_page_extract_sections($post);
    $intro_html = trim($sections['intro']);
    $body_html = trim($sections['body']);
    $faq_html = trim($sections['faq']);

    tmw_category_page_log_once(
        '[TMW-CAT-ACC-AUDIT]',
        'source=loop_start_injection term_id=' . $term->term_id . ' post_id=' . $post->ID
        . ' intro_len=' . strlen($intro_html) . ' body_len=' . strlen($body_html) . ' faq_len=' . strlen($faq_html)
    );

    if ($intro_html === '' && $body_html === '' && $faq_html === '') {
        error_log('[TMW-CAT-FALLBACK] Category page empty content for term ' . $term->term_id . '.');
        return;
    }

    $GLOBALS['tmw_category_page_rendered'] = true;

    error_log('[TMW-CAT-RENDER] Injecting category page content for term ' . $term->term_id . ' using post ' . $post->ID . '.');

    echo '<div class="tmw-category-page-content">';
    if ($intro_html !== '') {
        echo '<div class="tmw-category-page-intro">' . $intro_html . '</div>';
    }
    if ($body_html !== '') {
        echo '<div class="tmw-category-page-body">' . $body_html . '</div>';
    }
    if ($faq_html !== '') {
        echo '<div class="tmw-category-page-faq">' . $faq_html . '</div>';
    }
    echo '</div>';
}, 10, 1);
