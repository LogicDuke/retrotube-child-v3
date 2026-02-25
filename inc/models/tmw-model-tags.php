<?php

// === TMW v3.1.5 — Label alias for model tags ===
add_filter('the_content', function ($content) {
    if (!is_singular('model')) {
        return $content;
    }

    return preg_replace_callback(
        '/<a\b[^>]*\bclass="[^"]*\btag-link\b[^"]*"[^>]*>/i',
        function ($matches) {
            $tag = $matches[0];

            if (!preg_match('/class="([^"]*)"/i', $tag, $class_match)) {
                return $tag;
            }

            $classes = $class_match[1];

            if (preg_match('/\blabel\b/i', $classes)) {
                return $tag;
            }

            $normalized = trim(preg_replace('/\s+/', ' ', $classes));
            $updated = 'label' . ($normalized !== '' ? ' ' . $normalized : '');

            return str_replace($class_match[0], 'class="' . $updated . '"', $tag);
        },
        $content
    );
}, 12);


/**
 * Register post tags on the model post type.
 */
function tmw_bind_post_tag_to_model(): void {
    if (!taxonomy_exists('post_tag') || !post_type_exists('model')) {
        return;
    }

    if (!is_object_in_taxonomy('model', 'post_tag')) {
        register_taxonomy_for_object_type('post_tag', 'model');
    }
}

add_action('init', 'tmw_bind_post_tag_to_model', 20);
add_action('registered_post_type', function ($post_type) {
    if ('model' === $post_type) {
        tmw_bind_post_tag_to_model();
    }
}, 20);

/**
 * Attach the models taxonomy to relevant post types.
 */
function tmw_bind_models_taxonomy(): void {
    if (!taxonomy_exists('models')) {
        return;
    }

    $targets = ['post'];
    $detected = tmw_detect_livejasmin_post_type();
    if ($detected && 'video' !== $detected) {
        $targets[] = $detected;
    }

    foreach (array_unique($targets) as $post_type) {
        if (!post_type_exists($post_type)) {
            continue;
        }

        if (!is_object_in_taxonomy($post_type, 'models')) {
            register_taxonomy_for_object_type('models', $post_type);
        }
    }
}

add_action('init', 'tmw_bind_models_taxonomy', 30);
add_action('registered_post_type', function ($post_type) {
    if ($post_type === 'video') {
        return;
    }

    tmw_bind_models_taxonomy();
}, 30, 1);
add_action('registered_taxonomy', function ($taxonomy) {
    if ('models' === $taxonomy) {
        tmw_bind_models_taxonomy();
    }
}, 30, 1);

if (!function_exists('tmw_detect_livejasmin_post_type')) {
    /**
     * Determine the LiveJasmin post type slug if available.
     *
     * @return string Post type slug.
     */
    function tmw_detect_livejasmin_post_type() {
        static $detected = null;

        if ($detected !== null) {
            return $detected;
        }

        $detected = post_type_exists('video') ? 'video' : 'post';

        global $wpdb;
        $meta_keys = ['wpslj_video_id', 'wpslj_model', 'wpslj_stream'];
        $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
        $sql = "
            SELECT p.post_type
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
            WHERE m.meta_key IN ($placeholders)
            LIMIT 1
        ";

        $prepared = $wpdb->prepare($sql, $meta_keys);
        if ($prepared) {
            $found = $wpdb->get_var($prepared);
            if (!empty($found)) {
                $detected = $found;
            }
        }

        if ($detected && taxonomy_exists('models') && !is_object_in_taxonomy($detected, 'models')) {
            if ('video' === $detected) {
                if (function_exists('tmw_bind_models_to_video_once')) {
                    tmw_bind_models_to_video_once();
                }
            } else {
                register_taxonomy_for_object_type('models', $detected);
            }
        }

        return $detected;
    }
}

// === [TMW-MODEL-QUERY-FIX v2.6.8] Ensure model pages display related videos ===
if (!function_exists('tmw_get_videos_for_model')) {
    /**
     * Query videos related to a model slug.
     *
     * @param string $model_slug Model slug.
     * @param int    $limit      Max number of results.
     * @return WP_Post[] Array of posts (empty when none).
     */
    function tmw_get_videos_for_model($model_slug, $limit = 24) {
        if (empty($model_slug)) {
            return [];
        }

        $taxonomy = 'models';
        $post_type = tmw_detect_livejasmin_post_type();

        if ('video' !== $post_type && taxonomy_exists($taxonomy) && !is_object_in_taxonomy($post_type, $taxonomy)) {
            register_taxonomy_for_object_type($taxonomy, $post_type);
        }

        $args = [
            'post_type'      => $post_type,
            'posts_per_page' => $limit,
            'tax_query'      => [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'slug',
                    'terms'    => $model_slug,
                ],
            ],
            'post_status'    => 'publish',
            'no_found_rows'  => true,
        ];

        $q = new WP_Query($args);

        if ($q->have_posts()) {
            return $q->posts;
        }

        $model_post = get_page_by_path($model_slug, OBJECT, ['model', 'model_bio']);
        if (!$model_post instanceof WP_Post) {
            return [];
        }

        $related_ids = get_post_meta($model_post->ID, 'rt_model_videos', true);
        if (!is_array($related_ids)) {
            $related_ids = is_scalar($related_ids) ? preg_split('/[\s,]+/', (string) $related_ids) : [];
        }

        $related_ids = array_values(array_unique(array_filter(array_map('absint', $related_ids))));
        if (empty($related_ids)) {
            return [];
        }

        if (is_int($limit) && $limit > 0) {
            $related_ids = array_slice($related_ids, 0, $limit);
        }

        $fallback = new WP_Query([
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'post__in'       => $related_ids,
            'orderby'        => 'post__in',
            'posts_per_page' => $limit,
            'no_found_rows'  => true,
        ]);

        if (!$fallback->have_posts()) {
            return [];
        }

        return array_values(array_unique($fallback->posts, SORT_REGULAR));
    }
}

if (!function_exists('tmw_register_hybrid_scan_cli')) {
  /**
   * Register the hybrid scan WP-CLI command if available.
   */
  function tmw_register_hybrid_scan_cli() {
    if (!defined('WP_CLI') || !WP_CLI) {
      return;
    }

    if (class_exists('WP_CLI')) {
      WP_CLI::add_command('tmw scan-model-videos', 'tmw_cli_scan_model_videos');
    }
  }
}

add_action('init', 'tmw_register_hybrid_scan_cli');
