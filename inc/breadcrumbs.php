<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * [TMW-BREADCRUMB-WPST] Child override of wpst_breadcrumbs() with category hierarchy fix.
 */
function wpst_breadcrumbs() {
    global $post;

    $delimiter = '<i class="fa fa-caret-right"></i>';
    $home = __('Home', 'wpst');
    $show_current = 1;
    $before = '<span class="current">';
    $after = '</span>';

    $home_link = home_url('/');

    if (!is_home() && !is_front_page()) {
        echo '<div class="breadcrumbs-area">';
        echo '<div class="row">';
        echo '<div id="breadcrumbs">';
        echo '<a href="' . esc_url($home_link) . '">' . esc_html($home) . '</a>';

        if (is_category() || (is_tax() && get_queried_object() && get_queried_object()->taxonomy === 'category')) {
            $term = get_queried_object();
            $category_link = $term ? get_term_link($term) : '';
            $categories_page = get_page_by_path('categories');
            $categories_link = $categories_page ? get_permalink($categories_page) : '';
            $filter = isset($_GET['filter']) ? sanitize_key(wp_unslash($_GET['filter'])) : '';

            // [TMW-BREADCRUMB-FIX] Link Categories to real hub page only.
            echo '<span class="separator">' . $delimiter . '</span>';
            if ($categories_link) {
                echo '<a href="' . esc_url($categories_link) . '">' . esc_html__('Categories', 'wpst') . '</a>';
            } else {
                echo esc_html__('Categories', 'wpst');
            }

            if ($term && !is_wp_error($category_link)) {
                echo '<span class="separator">' . $delimiter . '</span>';
                if ($filter) {
                    echo '<a href="' . esc_url($category_link) . '">' . esc_html($term->name) . '</a>';
                } else {
                    echo $before . esc_html($term->name) . $after;
                }
            }

            if ($filter) {
                $filter_labels = array(
                    'latest' => __('Latest', 'wpst'),
                    'random' => __('Random', 'wpst'),
                    'popular' => __('Popular', 'wpst'),
                    'longest' => __('Longest', 'wpst'),
                    'most-viewed' => __('Most Viewed', 'wpst'),
                );
                $filter_label = isset($filter_labels[$filter])
                    ? $filter_labels[$filter]
                    : ucwords(str_replace('-', ' ', $filter));
                echo '<span class="separator">' . $delimiter . '</span>';
                echo $before . esc_html($filter_label) . $after;
            }
        } elseif (is_search()) {
            echo '<span class="separator">' . $delimiter . '</span>';
            echo $before . sprintf(__('Search Results for "%s"', 'wpst'), get_search_query()) . $after;
        } elseif (is_day()) {
            echo '<span class="separator">' . $delimiter . '</span>';
            echo '<a href="' . esc_url(get_year_link(get_the_time('Y'))) . '">' . esc_html(get_the_time('Y')) . '</a>';
            echo '<span class="separator">' . $delimiter . '</span>';
            echo '<a href="' . esc_url(get_month_link(get_the_time('Y'), get_the_time('m'))) . '">' . esc_html(get_the_time('F')) . '</a>';
            echo '<span class="separator">' . $delimiter . '</span>';
            echo $before . esc_html(get_the_time('d')) . $after;
        } elseif (is_month()) {
            echo '<span class="separator">' . $delimiter . '</span>';
            echo '<a href="' . esc_url(get_year_link(get_the_time('Y'))) . '">' . esc_html(get_the_time('Y')) . '</a>';
            echo '<span class="separator">' . $delimiter . '</span>';
            echo $before . esc_html(get_the_time('F')) . $after;
        } elseif (is_year()) {
            echo '<span class="separator">' . $delimiter . '</span>';
            echo $before . esc_html(get_the_time('Y')) . $after;
        } elseif (is_single() && !is_attachment()) {
            $post_type = get_post_type();
            if ($post_type === 'video') {
                $video_label = __('Videos', 'wpst');

                // [TMW-BREADCRUMB-VIDEO] Enforce Home > Videos > Title trail for single videos.
                echo '<span class="separator">' . $delimiter . '</span>';
                echo '<a href="' . esc_url(home_url('/videos/')) . '">' . esc_html($video_label) . '</a>';

                if ($show_current) {
                    echo '<span class="separator">' . $delimiter . '</span>';
                    echo $before . get_the_title() . $after;
                }
            } elseif ($post_type === 'model') {
                $models_page = get_page_by_path('models');
                $models_link = $models_page ? get_permalink($models_page) : '';
                $models_label = __('Models', 'wpst');

                echo '<span class="separator">' . $delimiter . '</span>';
                // [TMW-BREADCRUMB-MODELS] Always link Models crumb, fallback to /models/.
                $models_href = $models_link ? $models_link : home_url('/models/');
                echo '<a href="' . esc_url($models_href) . '">' . esc_html($models_label) . '</a>';

                if ($show_current) {
                    echo '<span class="separator">' . $delimiter . '</span>';
                    echo $before . get_the_title() . $after;
                }
            } elseif ($post_type != 'post') {
                $post_type_object = get_post_type_object($post_type);
                if ($post_type_object) {
                    $slug = $post_type_object->rewrite;
                    if (is_array($slug) && isset($slug['slug'])) {
                        echo '<span class="separator">' . $delimiter . '</span>';
                        echo '<a href="' . esc_url($home_link . '/' . $slug['slug'] . '/') . '">' . esc_html($post_type_object->labels->singular_name) . '</a>';
                    }
                }
                if ($show_current) {
                    echo '<span class="separator">' . $delimiter . '</span>';
                    echo $before . get_the_title() . $after;
                }
            } else {
                $cat = get_the_category();
                if (!empty($cat)) {
                    $cat = $cat[0];
                    $cats = get_category_parents($cat, true, '<span class="separator">' . $delimiter . '</span>');
                    if (!$show_current) {
                        $cats = preg_replace('#^(.+)\s' . preg_quote('<span class="separator">' . $delimiter . '</span>', '#') . '\s$#', '$1', $cats);
                    }
                    echo '<span class="separator">' . $delimiter . '</span>';
                    echo $cats;
                }
                if ($show_current) {
                    echo $before . get_the_title() . $after;
                }
            }
        } elseif (!is_single() && !is_page() && get_post_type() != 'post' && !is_404()) {
            $post_type_object = get_post_type_object(get_post_type());
            if ($post_type_object) {
                echo '<span class="separator">' . $delimiter . '</span>';
                if (is_post_type_archive('video')) {
                    // [TMW-BREADCRUMB-VIDEO-LINK] Ensure Videos archive crumb is clickable.
                    $video_label = !empty($post_type_object->labels->name)
                        ? $post_type_object->labels->name
                        : $post_type_object->labels->singular_name;
                    echo '<a href="' . esc_url(home_url('/videos/')) . '">' . esc_html($video_label) . '</a>';
                } else {
                    echo $before . esc_html($post_type_object->labels->singular_name) . $after;
                }
            }

            $filter = '';
            if (isset($_GET['filter'])) {
                $filter = sanitize_key(wp_unslash($_GET['filter']));
            }

            if ($filter && is_post_type_archive('video')) {
                // [TMW-BREADCRUMB-FILTER] Append filter label for video archives.
                $filter_labels = array(
                    'random' => __('Random', 'wpst'),
                    'latest' => __('Latest', 'wpst'),
                    'top' => __('Top', 'wpst'),
                );
                if (isset($filter_labels[$filter])) {
                    echo '<span class="separator">' . $delimiter . '</span>';
                    echo $before . esc_html($filter_labels[$filter]) . $after;
                }
            }
        } elseif (is_page() && !is_front_page()) {
            if ($show_current) {
                $filter = isset($_GET['filter']) ? sanitize_key(wp_unslash($_GET['filter'])) : '';

                echo '<span class="separator">' . $delimiter . '</span>';
                if (is_page('videos')) {
                    if ($filter) {
                        // [TMW-BREADCRUMB-VIDEOS] Keep Videos crumb clickable on filtered page variants.
                        echo '<a href="' . esc_url(home_url('/videos/')) . '">' . esc_html(get_the_title()) . '</a>';
                    } else {
                        // [TMW-BREADCRUMB-VIDEOS] Treat Videos as current page when unfiltered.
                        echo $before . get_the_title() . $after;
                    }
                } else {
                    echo $before . get_the_title() . $after;
                }
            }

            // [TMW-BREADCRUMB-VIDEO-FILTER] Append filter label on Videos page.
            if (get_post_field('post_name', get_queried_object_id()) === 'videos' && isset($_GET['filter'])) {
                $filter = sanitize_key(wp_unslash($_GET['filter']));

                $filter_labels = array(
                    'latest' => __('Latest', 'wpst'),
                    'random' => __('Random', 'wpst'),
                    'top' => __('Top', 'wpst'),
                );

                if (isset($filter_labels[$filter])) {
                    echo '<span class="separator">' . $delimiter . '</span>';
                    echo $before . esc_html($filter_labels[$filter]) . $after;
                }
            }
        } elseif (is_attachment()) {
            $parent = get_post($post->post_parent);
            if ($parent) {
                $cat = get_the_category($parent->ID);
                if (!empty($cat)) {
                    $cat = $cat[0];
                    echo '<span class="separator">' . $delimiter . '</span>';
                    echo get_category_parents($cat, true, '<span class="separator">' . $delimiter . '</span>');
                }
                echo '<span class="separator">' . $delimiter . '</span>';
                echo '<a href="' . esc_url(get_permalink($parent)) . '">' . esc_html(get_the_title($parent)) . '</a>';
            }
            if ($show_current) {
                echo '<span class="separator">' . $delimiter . '</span>';
                echo $before . get_the_title() . $after;
            }
        } elseif (is_tag()) {
            echo '<span class="separator">' . $delimiter . '</span>';
            echo $before . sprintf(__('Posts tagged "%s"', 'wpst'), single_tag_title('', false)) . $after;
        } elseif (is_author()) {
            $userdata = get_userdata(get_query_var('author'));
            if ($userdata) {
                echo '<span class="separator">' . $delimiter . '</span>';
                echo $before . sprintf(__('Articles posted by %s', 'wpst'), esc_html($userdata->display_name)) . $after;
            }
        } elseif (is_404()) {
            echo '<span class="separator">' . $delimiter . '</span>';
            echo $before . __('Error 404', 'wpst') . $after;
        }

        if (get_query_var('paged')) {
            echo '<span class="separator">' . $delimiter . '</span>';
            echo $before . __('Page', 'wpst') . ' ' . intval(get_query_var('paged')) . $after;
        }

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
}
