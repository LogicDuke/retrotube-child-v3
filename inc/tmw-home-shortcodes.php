<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_page_has_h1')) {
    function tmw_page_has_h1(): bool {
        static $has_h1 = null;

        if ($has_h1 !== null) {
            return $has_h1;
        }

        ob_start();
        do_action('wp_head');
        $head = ob_get_clean();

        $has_h1 = (bool) preg_match('/<h1\b/i', (string) $head);
        return $has_h1;
    }
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

        $has_heading = (bool) preg_match('/<h[1-6][^>]*>/i', $content_html);
        if (!$has_heading) {
            $auto_level = 'h2';

            $auto_heading_text = $title . ' Webcam Directory';
            $auto_heading_html = sprintf(
                '<%1$s class="tmw-accordion-auto-h1 tmw-accordion-auto-h2">%2$s</%1$s>',
                esc_attr($auto_level),
                esc_html($auto_heading_text)
            );

            $paragraph_close = stripos($content_html, '</p>');
            if ($paragraph_close !== false) {
                $insert_at = $paragraph_close + strlen('</p>');
                $content_html = substr($content_html, 0, $insert_at)
                    . $auto_heading_html
                    . substr($content_html, $insert_at);
            } else {
                $content_html = $auto_heading_html . $content_html;
            }
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

        static $home_accordion_count = 0;
        $is_home_context = (is_front_page() || (is_home() && get_option('show_on_front') === 'posts'));
        if ($is_home_context) {
            $home_accordion_count++;
            $heading_level = ($home_accordion_count === 1) ? 'h1' : 'h2';
        } else {
            $heading_level = 'h2';
        }

        return sprintf(
            '<%1$s class="widget-title">%2$s</%1$s>%3$s',
            $heading_level,
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
 * HOME CATEGORIES SHORTCODE
 * [tmw_home_categories limit="6"]
 * ---------------------------------------------------------
 */
if (!function_exists('tmw_home_categories_shortcode')) {

    function tmw_home_categories_shortcode($atts = []): string {

        $atts = shortcode_atts(
            [
                'limit' => 8,
            ],
            $atts,
            'tmw_home_categories'
        );

        $limit    = max(1, absint($atts['limit']));
        $taxonomy = apply_filters('tmw_home_categories_taxonomy', 'category');

        $catThumbQuality = 'medium';
        if (function_exists('xbox_get_field_value')) {
            $value = xbox_get_field_value('wpst-options', 'categories-thumbnail-quality');
            if (is_string($value) && $value !== '') {
                $catThumbQuality = $value;
            }
        }

        $categoriesPerRow = 3;
        if (function_exists('xbox_get_field_value')) {
            $value = xbox_get_field_value('wpst-options', 'categories-per-row');
            if (is_numeric($value)) {
                $value = (int) $value;
                if ($value > 0) {
                    $categoriesPerRow = $value;
                }
            }
        }

        switch ($categoriesPerRow) {
            case 2:
                $thumbBlockWidth = 50;
                break;
            case 3:
                $thumbBlockWidth = 33.33;
                break;
            case 4:
                $thumbBlockWidth = 25;
                break;
            case 5:
                $thumbBlockWidth = 20;
                break;
            case 6:
                $thumbBlockWidth = 16.66;
                break;
            default:
                $thumbBlockWidth = 20;
        }

        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
            'number'     => $limit,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        ob_start();
        ?>
        <div class="tmw-home-categories-list categories-list">
            <style>
                @media only screen and (min-width:64.001em) and (max-width:84em) {
                    .tmw-home-categories-list .thumb-block { width: <?php echo esc_html((string) $thumbBlockWidth); ?>% !important; }
                }
                @media only screen and (min-width:84.001em) {
                    .tmw-home-categories-list .thumb-block { width: <?php echo esc_html((string) $thumbBlockWidth); ?>% !important; }
                }
            </style>
            <div class="videos-list">
                <?php foreach ($terms as $term) : ?>
                    <?php
                    if (!$term instanceof WP_Term) {
                        continue;
                    }

                    $term_link = get_term_link($term);
                    if (is_wp_error($term_link)) {
                        continue;
                    }

                    $query = new WP_Query([
                        'post_type'           => 'post',
                        'posts_per_page'      => 1,
                        'post_status'         => 'publish',
                        'orderby'             => 'rand',
                        'tax_query'           => [
                            [
                                'taxonomy' => 'category',
                                'field'    => 'slug',
                                'terms'    => $term->slug,
                            ],
                        ],
                    ]);

                    $thumbnail_html = '';
                    $image_id = (int) get_term_meta($term->term_id, 'category-image-id', true);
                    if ($image_id > 0) {
                        $thumbnail_html = wp_get_attachment_image($image_id, $catThumbQuality);
                    }

                    $post = null;
                    if ($thumbnail_html === '' && $query->have_posts()) {
                        $post = $query->posts[0];
                        if ($post instanceof WP_Post) {
                            $thumbnail_html = get_the_post_thumbnail($post, $catThumbQuality);
                            if ($thumbnail_html === '') {
                                $thumb = get_post_meta($post->ID, 'thumb', true);
                                if (is_string($thumb) && $thumb !== '') {
                                    $thumbnail_html = sprintf(
                                        '<img src="%1$s" alt="%2$s" />',
                                        esc_url($thumb),
                                        esc_attr($term->name)
                                    );
                                }
                            }
                        }
                    }

                    if ($thumbnail_html === '') {
                        $thumbnail_html = '<div class="no-thumb"><span><i class="fa fa-image"></i> No image</span></div>';
                    }
                    ?>
                    <article class="thumb-block tmw-home-category">
                        <a href="<?php echo esc_url($term_link); ?>" title="<?php echo esc_attr($term->name); ?>">
                            <div class="post-thumbnail">
                                <?php echo $thumbnail_html; ?>
                            </div>
                            <header class="entry-header">
                                <span class="cat-title"><?php echo esc_html($term->name); ?></span>
                            </header>
                        </a>
                    </article>
                    <?php wp_reset_postdata(); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}

add_shortcode('tmw_home_categories', 'tmw_home_categories_shortcode');
