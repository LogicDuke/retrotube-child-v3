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


if (!function_exists('tmw_resolve_model_term_for_post')) {
  /**
   * Resolve the matching models taxonomy term for a model post.
   *
   * Prefers an already assigned term. When missing, falls back to an explicit
   * tmw_model_post_id link, then slug, then exact title. When a match is found
   * it re-links the post and term so banner/flipbox reads stay in sync.
   *
   * @param int  $post_id Model post ID.
   * @param bool $assign  Whether to restore the post/term relationship when found.
   * @return WP_Term|null Matched models term or null.
   */
  function tmw_resolve_model_term_for_post($post_id, $assign = true): ?WP_Term {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !taxonomy_exists('models')) {
      return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== 'model') {
      return null;
    }

    $terms = wp_get_post_terms($post_id, 'models', ['fields' => 'all']);
    if (!is_wp_error($terms) && !empty($terms) && ($terms[0] instanceof WP_Term)) {
      update_term_meta((int) $terms[0]->term_id, 'tmw_model_post_id', $post_id);
      return $terms[0];
    }

    $term = null;

    $linked_terms = get_terms([
      'taxonomy'   => 'models',
      'hide_empty' => false,
      'number'     => 1,
      'meta_query' => [
        [
          'key'     => 'tmw_model_post_id',
          'value'   => (string) $post_id,
          'compare' => '=',
        ],
      ],
    ]);

    if (!is_wp_error($linked_terms) && !empty($linked_terms) && ($linked_terms[0] instanceof WP_Term)) {
      $term = $linked_terms[0];
    }

    if (!$term instanceof WP_Term) {
      $slug = get_post_field('post_name', $post_id);
      if (is_string($slug) && $slug !== '') {
        $maybe = get_term_by('slug', $slug, 'models');
        if ($maybe instanceof WP_Term) {
          $term = $maybe;
        }
      }
    }

    if (!$term instanceof WP_Term) {
      $title = get_the_title($post_id);
      if (is_string($title) && $title !== '') {
        $maybe = get_term_by('name', $title, 'models');
        if ($maybe instanceof WP_Term) {
          $term = $maybe;
        }
      }
    }

    if (!$term instanceof WP_Term) {
      return null;
    }

    if ($assign) {
      wp_set_post_terms($post_id, [(int) $term->term_id], 'models', false);
    }

    update_term_meta((int) $term->term_id, 'tmw_model_post_id', $post_id);

    return $term;
  }
}
