<?php

/* ======================================================================
 * UTIL
 * ====================================================================== */
if (!function_exists('tmw_count_terms')) {
  /**
   * Count terms for a taxonomy with a safe fallback.
   *
   * @param string $taxonomy   Taxonomy slug.
   * @param bool   $hide_empty Whether to hide empty terms.
   * @return int Term count.
   */
  function tmw_count_terms($taxonomy, $hide_empty=false){
    if (function_exists('wp_count_terms')) {
      $count = wp_count_terms(['taxonomy'=>$taxonomy,'hide_empty'=>$hide_empty]);
      if (!is_wp_error($count)) return (int)$count;
    }
    $ids = get_terms(['taxonomy'=>$taxonomy, 'fields'=>'ids', 'hide_empty'=>$hide_empty ]);
    return is_wp_error($ids) ? 0 : count($ids);
  }
}


if (!function_exists('tmw_bind_model_post_to_term')) {
  /**
   * Persist the relationship between a model post and its models taxonomy term.
   *
   * @param int         $post_id Model post ID.
   * @param int|WP_Term $term    Model term or term ID.
   * @return ?WP_Term
   */
  function tmw_bind_model_post_to_term(int $post_id, $term): ?WP_Term {
    if ($post_id <= 0) {
      return null;
    }

    if (is_numeric($term)) {
      $term = get_term((int) $term, 'models');
    }

    if (!$term || is_wp_error($term) || !($term instanceof WP_Term)) {
      return null;
    }

    update_term_meta((int) $term->term_id, 'tmw_model_post_id', $post_id);
    wp_set_post_terms($post_id, [(int) $term->term_id], 'models', false);

    return $term;
  }
}

if (!function_exists('tmw_resolve_models_term_for_post')) {
  /**
   * Resolve the model term for a model post, restoring the relationship when possible.
   *
   * @param int $post_id Model post ID.
   * @return ?WP_Term
   */
  function tmw_resolve_models_term_for_post(int $post_id): ?WP_Term {
    if ($post_id <= 0) {
      return null;
    }

    $terms = wp_get_post_terms($post_id, 'models', ['fields' => 'all']);
    if (!is_wp_error($terms) && !empty($terms) && ($terms[0] instanceof WP_Term)) {
      return $terms[0];
    }

    $found = get_terms([
      'taxonomy' => 'models',
      'hide_empty' => false,
      'number' => 1,
      'meta_query' => [[
        'key' => 'tmw_model_post_id',
        'value' => $post_id,
        'compare' => '=',
        'type' => 'NUMERIC',
      ]],
    ]);

    if (!is_wp_error($found) && !empty($found) && ($found[0] instanceof WP_Term)) {
      return tmw_bind_model_post_to_term($post_id, $found[0]);
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
      return null;
    }

    $candidates = array_values(array_unique(array_filter([
      sanitize_title((string) $post->post_name),
      sanitize_title((string) $post->post_title),
      (string) $post->post_title,
    ])));

    foreach ($candidates as $candidate) {
      if ($candidate == '') {
        continue;
      }

      $term = null;
      if ($candidate === sanitize_title($candidate)) {
        $term = get_term_by('slug', $candidate, 'models');
      }
      if (!$term || is_wp_error($term)) {
        $term = get_term_by('name', $candidate, 'models');
      }
      if ($term instanceof WP_Term) {
        return tmw_bind_model_post_to_term($post_id, $term);
      }
    }

    return null;
  }
}
