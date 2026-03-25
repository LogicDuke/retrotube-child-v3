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
