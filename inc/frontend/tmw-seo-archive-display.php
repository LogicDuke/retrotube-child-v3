<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_get_clean_archive_heading')) {
    /**
     * Return a clean archive heading without taxonomy prefixes.
     */
    function tmw_get_clean_archive_heading(): string
    {
        if (is_category() || is_tag() || is_tax()) {
            $term_title = single_term_title('', false);
            if (is_string($term_title) && $term_title !== '') {
                return wp_strip_all_tags($term_title);
            }
        }

        return wp_strip_all_tags(get_the_archive_title());
    }
}

if (!function_exists('tmw_get_archive_counterpart_link')) {
    /**
     * Build a contextual cross-link between mirrored category/tag archives.
     *
     * @return array{url:string,label:string}|null
     */
    function tmw_get_archive_counterpart_link(): ?array
    {
        $term = get_queried_object();
        if (!$term instanceof WP_Term) {
            return null;
        }

        if ($term->taxonomy === 'category') {
            $tag = get_term_by('slug', $term->slug, 'post_tag');
            if (!$tag instanceof WP_Term) {
                return null;
            }

            $url = get_tag_link((int) $tag->term_id);
            if (!is_string($url) || $url === '') {
                return null;
            }

            return [
                'url'   => $url,
                'label' => sprintf(__('Browse related tag: %s', 'retrotube-child'), $tag->name),
            ];
        }

        if ($term->taxonomy === 'post_tag') {
            $category = get_term_by('slug', $term->slug, 'category');
            if (!$category instanceof WP_Term) {
                return null;
            }

            $url = get_category_link((int) $category->term_id);
            if (!is_string($url) || $url === '') {
                return null;
            }

            return [
                'url'   => $url,
                'label' => sprintf(__('Browse related category hub: %s', 'retrotube-child'), $category->name),
            ];
        }

        return null;
    }
}

if (!function_exists('tmw_render_archive_counterpart_link')) {
    /**
     * Render contextual cross-link HTML for category/tag archives.
     */
    function tmw_render_archive_counterpart_link(): string
    {
        $link = tmw_get_archive_counterpart_link();
        if (!is_array($link)) {
            return '';
        }

        return sprintf(
            '<p class="tmw-archive-crosslink"><a href="%1$s">%2$s</a></p>',
            esc_url((string) $link['url']),
            esc_html((string) $link['label'])
        );
    }
}

if (!function_exists('tmw_tag_archive_desc_to_accordion')) {
    /**
     * Keep tag archive descriptions visually consistent with category archives.
     */
    function tmw_tag_archive_desc_to_accordion(string $description): string
    {
        if (is_admin() || !is_tag()) {
            return $description;
        }

        if (trim($description) === '') {
            return $description;
        }

        if (stripos($description, 'tmw-accordion') !== false) {
            return $description;
        }

        $lines = (int) apply_filters('tmw_tag_desc_lines', 1);
        $queried_id = get_queried_object_id();

        return tmw_render_accordion([
            'content_html'    => $description,
            'lines'           => $lines,
            'collapsed'       => true,
            'accordion_class' => 'tmw-accordion--tag-desc',
            'id_base'         => $queried_id ? 'tmw-tag-desc-' . $queried_id : 'tmw-tag-desc-',
        ]);
    }
}

add_filter('get_the_archive_description', 'tmw_tag_archive_desc_to_accordion', 25);
