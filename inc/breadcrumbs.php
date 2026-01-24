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

        if (is_category()) {
            $category_link = get_category_link(get_queried_object_id());
            $category_base = untrailingslashit(dirname($category_link));
            echo '<span class="separator">' . $delimiter . '</span>';
            echo '<a href="' . esc_url($category_base) . '">Categories</a>';
            echo '<span class="separator">' . $delimiter . '</span>';
            echo $before . esc_html(single_cat_title('', false)) . $after;
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
            if ($post_type != 'post') {
                $post_type_object = get_post_type_object($post_type);
                if ($post_type_object) {
                    $slug = $post_type_object->rewrite;
                    if (is_array($slug) && isset($slug['slug'])) {
                        // [TMW-BREADCRUMB-FIX] Use plural label for model breadcrumbs on single pages.
                        $label = (get_post_type() === 'model' && !empty($post_type_object->labels->name))
                            ? $post_type_object->labels->name
                            : $post_type_object->labels->singular_name;
                        echo '<span class="separator">' . $delimiter . '</span>';
                        echo '<a href="' . esc_url($home_link . '/' . $slug['slug'] . '/') . '">' . esc_html($label) . '</a>';
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
        } elseif (is_page() && !is_front_page()) {
            if ($show_current) {
                echo '<span class="separator">' . $delimiter . '</span>';
                echo $before . get_the_title() . $after;
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
