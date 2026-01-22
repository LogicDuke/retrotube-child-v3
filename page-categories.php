<?php
/**
 * Categories page template override.
 */

get_header();

tmw_render_sidebar_layout('page-categories', function () {
    while (have_posts()) :
        the_post();

        echo tmw_render_title_bar(get_the_title(), 1);

        $intro_html = '';
        $remaining_html = '';

        if (has_excerpt()) {
            $intro_html = apply_filters('the_excerpt', get_the_excerpt());
        } else {
            $content = (string) get_post_field('post_content', get_the_ID());
            if (strpos($content, '<!--more-->') !== false) {
                $extended = get_extended($content);
                $intro_raw = $extended['main'] ?? '';
                $remaining_raw = $extended['extended'] ?? '';

                if (trim($intro_raw) !== '') {
                    $intro_html = apply_filters('the_content', $intro_raw);
                }

                if (trim($remaining_raw) !== '') {
                    $remaining_html = apply_filters('the_content', $remaining_raw);
                }
            }
        }

        if ($intro_html !== '') {
            echo tmw_render_accordion([
                'content_html'    => $intro_html,
                'lines'           => 1,
                'collapsed'       => true,
                'accordion_class' => 'tmw-accordion--categories-intro',
                'id_base'         => 'tmw-categories-intro-',
            ]);
        }

        if ($remaining_html !== '') {
            echo $remaining_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            the_content();
        }
    endwhile;
});

get_footer();
