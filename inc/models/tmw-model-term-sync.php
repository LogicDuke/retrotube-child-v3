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
    $post_id  = 0;

    if ($existing instanceof WP_Post) {
      $post_id      = (int) $existing->ID;
      $needs_update = false;
      $update_data  = ['ID' => $post_id];

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
    } else {
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

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
      return;
    }

    wp_set_post_terms($post_id, [(int) $term_id], 'models', false);
    update_term_meta((int) $term_id, 'tmw_model_post_id', $post_id);
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


add_action('save_post_model', function ($post_id) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  if (function_exists('tmw_resolve_model_term_for_post')) {
    tmw_resolve_model_term_for_post((int) $post_id);
  }
}, 5);

add_action('init', function () {
  if (get_option('tmw_model_post_terms_relinked_v1')) {
    return;
  }

  $post_ids = get_posts([
    'post_type'      => 'model',
    'fields'         => 'ids',
    'posts_per_page' => -1,
    'no_found_rows'  => true,
  ]);

  if (is_array($post_ids)) {
    foreach ($post_ids as $post_id) {
      if (function_exists('tmw_resolve_model_term_for_post')) {
        tmw_resolve_model_term_for_post((int) $post_id);
      }
    }
  }

  update_option('tmw_model_post_terms_relinked_v1', 1);
}, 30);
