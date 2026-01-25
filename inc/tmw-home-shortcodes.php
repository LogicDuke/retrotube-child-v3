<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ---------------------------------------------------------
 * HOME ACCORDION SHORTCODE
 * [tmw_home_accordion title="..."]content[/tmw_home_accordion]
 * ---------------------------------------------------------
 */
if (!function_exists('tmw_home_accordion_shortcode')) {

    function tmw_home_accordion_shortcode($atts, $content = null): string {

        $atts = shortcode_atts(
            [
                'title' => '',
            ],
            $atts,
            'tmw_home_accordion'
        );

        $title = trim((string) $atts['title']);
        if ($title === '') {
            return '';
        }

        $content_html = '';
        if ($content !== null) {
            $content_html = do_shortcode($content);
            $content_html = wpautop($content_html);
        }

        if (function_exists('tmw_sanitize_accordion_html')) {
            $content_html = tmw_sanitize_accordion_html($content_html);
        }

        if (!function_exists('tmw_render_accordion')) {
            return '';
        }

        $accordion_html = tmw_render_accordion([
            'content_html' => $content_html,
            'lines'        => 1,
            'collapsed'    => true,
            'id_base'      => 'tmw-home-accordion-',
        ]);

        if ($accordion_html === '') {
            return '';
        }

        return sprintf(
            '<h2 class="widget-title">%1$s</h2>%2$s',
            esc_html($title),
            $accordion_html
        );
    }
}

add_shortcode('tmw_home_accordion', 'tmw_home_accordion_shortcode');


/**
 * ---------------------------------------------------------
 * FEATURED MODELS SHORTCODE PARAM SUPPORT
 * ---------------------------------------------------------
 */
add_filter('shortcode_atts_featured_models', function ($out, $pairs, $atts) {

    if (isset($atts['limit'])) {
        $limit = (int) $atts['limit'];
        if ($limit > 0) {
            $out['count'] = $limit;
        }
    }

    return $out;
}, 10, 3);


/**
 * ---------------------------------------------------------
 * HOME VIDEOS SHORTCODE
 * [tmw_home_videos limit="8"]
 * ---------------------------------------------------------
 */
if (!function_exists('tmw_home_videos_shortcode')) {

    function tmw_home_videos_shortcode($atts = []): string {

        $atts = shortcode_atts(
            [
                'limit' => 8,
            ],
            $atts,
            'tmw_home_videos'
        );

        $limit = max(1, (int) $atts['limit']);

        $widget_class = class_exists('TMW_WP_Widget_Videos_Block_Fixed')
            ? 'TMW_WP_Widget_Videos_Block_Fixed'
            : 'wpst_WP_Widget_Videos_Block';

        if (!class_exists($widget_class)) {
            return '';
        }

        ob_start();

        the_widget(
            $widget_class,
            [
                'title'          => '',
                'video_type'     => 'random',
                'video_number'   => $limit,
                'video_category' => 0,
            ],
            [
                'before_widget' => '<section class="widget widget_videos_block">',
                'after_widget'  => '</section>',
                'before_title'  => '<h2 class="widget-title">',
                'after_title'   => '</h2>',
            ]
        );

        return (string) ob_get_clean();
    }
}

add_shortcode('tmw_home_videos', 'tmw_home_videos_shortcode');


/**
 * ---------------------------------------------------------
 * CATEGORY IMAGE RESOLVER (AUTHORITATIVE PARENT LOGIC)
 * ---------------------------------------------------------
 */
if (!function_exists('tmw_home_get_category_image_url')) {

    function tmw_home_get_category_image_url(WP_Term $term): string {

        // ✅ Parent theme authoritative source
        $image_id = (int) get_term_meta($term->term_id, 'category-image-id', true);
        if ($image_id > 0) {
            $image_url = wp_get_attachment_image_url($image_id, 'medium');
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        // Legacy / fallback resolvers
        if (function_exists('wpst_get_term_image')) {
            $image_url = wpst_get_term_image($term);
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        if (function_exists('wpst_get_category_image')) {
            $image_url = wpst_get_category_image($term);
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        if (function_exists('z_taxonomy_image_url')) {
            $image_url = z_taxonomy_image_url($term->term_id);
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        $thumbnail_id = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
        if ($thumbnail_id > 0) {
            $image = wp_get_attachment_image_src($thumbnail_id, 'medium');
            if (is_array($image) && !empty($image[0])) {
                return (string) $image[0];
            }
        }

        $image_url = get_term_meta($term->term_id, 'image', true);
        if (is_string($image_url) && $image_url !== '') {
            return $image_url;
        }

        if (function_exists('tmw_placeholder_image_url')) {
            $image_url = tmw_placeholder_image_url();
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        return '';
    }
}

/**
 * ---------------------------------------------------------
 * HOME CATEGORY REPRESENTATIVE POST (HOME ONLY)
 * ---------------------------------------------------------
 */
if (!function_exists('tmw_home_get_category_representative_post')) {

    function tmw_home_get_category_representative_post(WP_Term $term): ?WP_Post {

        $query = new WP_Query([
            'post_type'           => 'post',
            'posts_per_page'      => 1,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'post_status'         => 'publish',
            'orderby'             => 'date',
            'order'               => 'DESC',
            'tax_query'           => [
                [
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ],
            ],
        ]);

        if (!empty($query->posts[0]) && $query->posts[0] instanceof WP_Post) {
            return $query->posts[0];
        }

        return null;
    }
}

/**
 * ---------------------------------------------------------
 * HOME CATEGORY IMAGE RESOLVER (POST CONTEXT)
 * ---------------------------------------------------------
 */
if (!function_exists('tmw_home_get_category_image_from_post')) {

    function tmw_home_get_category_image_from_post(WP_Post $post): string {

        if (has_post_thumbnail($post)) {
            $image_url = get_the_post_thumbnail_url($post, 'medium');
            if (is_string($image_url) && $image_url !== '') {
                return $image_url;
            }
        }

        $thumb = get_post_meta($post->ID, 'thumb', true);
        if (is_string($thumb) && $thumb !== '') {
            return $thumb;
        }

        if (function_exists('tmw_placeholder_image_url')) {
            return tmw_placeholder_image_url();
        }

        return '';
    }
}


/**
 * ---------------------------------------------------------
 * HOME CATEGORIES SHORTCODE
 * [tmw_home_categories limit="6"]
 * ---------------------------------------------------------
 */
if (!function_exists('tmw_home_categories_shortcode')) {

    function tmw_home_categories_shortcode($atts = []): string {

        $atts = shortcode_atts(
            [
                'limit' => 6,
            ],
            $atts,
            'tmw_home_categories'
        );

        $limit    = max(1, (int) $atts['limit']);
        $taxonomy = apply_filters('tmw_home_categories_taxonomy', 'category');

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => $limit,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        ob_start();
        ?>
        <section class="widget widget_categories">
            <div class="video-grid tmw-home-category-grid">
                <?php foreach ($terms as $term) : ?>
                    <?php
                    if (!$term instanceof WP_Term) {
                        continue;
                    }

                    $term_link = get_term_link($term);
                    if (is_wp_error($term_link)) {
                        continue;
                    }

                    $post = tmw_home_get_category_representative_post($term);
                    $image_url = '';

                    if ($post) {
                        $image_url = tmw_home_get_category_image_from_post($post);
                    }
                    ?>
                    <article class="video-item tmw-home-category-card">
                        <a class="video-thumb"
                           href="<?php echo esc_url($term_link); ?>"
                           aria-label="<?php echo esc_attr($term->name); ?>">
                            <?php if ($image_url !== '') : ?>
                                <img class="video-thumb-img"
                                     src="<?php echo esc_url($image_url); ?>"
                                     alt="<?php echo esc_attr($term->name); ?>"
                                     loading="lazy"
                                     decoding="async"
                                     fetchpriority="low" />
                            <?php endif; ?>
                        </a>
                        <h4 class="video-title">
                            <a href="<?php echo esc_url($term_link); ?>">
                                <?php echo esc_html($term->name); ?>
                            </a>
                        </h4>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }
}

add_shortcode('tmw_home_categories', 'tmw_home_categories_shortcode');
