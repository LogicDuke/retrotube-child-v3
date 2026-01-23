<?php

/* ======================================================================
 * MODEL TERM → CPT SYNC
 * ====================================================================== */
if (!function_exists('tmw_sync_model_term_to_post')) {
  /**
   * Sync a model taxonomy term to a model post.
   *
   * @param int $term_id Term ID.
   * @param int $tt_id   Term taxonomy ID.
   */
  function tmw_sync_model_term_to_post($term_id, $tt_id) {
    $term = get_term($term_id, 'models');
    if (is_wp_error($term) || !$term) return;

    $slug     = sanitize_title(isset($term->slug) ? $term->slug : $term->name);
    $title    = sanitize_text_field($term->name);
    $desc     = term_description($term_id, 'models');
    $content  = wp_strip_all_tags($desc);
    $existing = get_page_by_path($slug, OBJECT, 'model');

    if ($existing instanceof WP_Post) {
      $needs_update = false;
      $update_data  = ['ID' => $existing->ID];

      if ($title && $existing->post_title !== $title) {
        $update_data['post_title'] = $title;
        $needs_update              = true;
      }

      if ($content && $existing->post_content !== $content) {
        $update_data['post_content'] = $content;
        $needs_update                = true;
      }

      if ($needs_update) {
        $result = wp_update_post($update_data, true);
        if (is_wp_error($result)) {
          return;
        }
      }

      return;
    }

    $post_id = wp_insert_post([
      'post_title'   => $title,
      'post_name'    => $slug,
      'post_content' => $content,
      'post_type'    => 'model',
      'post_status'  => 'publish',
    ]);

    if (is_wp_error($post_id)) {
      return;
    }
  }
}

add_action('created_models', 'tmw_sync_model_term_to_post', 10, 2);
add_action('edited_models',  'tmw_sync_model_term_to_post', 10, 2);

add_action('init', function () {
  if (get_option('tmw_models_synced')) return;

  $terms = get_terms([
    'taxonomy'   => 'models',
    'hide_empty' => false,
  ]);

  if (is_wp_error($terms)) {
    return;
  }

  foreach ($terms as $term) {
    tmw_sync_model_term_to_post($term->term_id, $term->term_taxonomy_id);
  }

  update_option('tmw_models_synced', true);
}, 20);
