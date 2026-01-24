<?php

/* ======================================================================
 * GRID/FLIP CSS (base 2:3 + rotate)
 * ====================================================================== */

/**
 * Override parent query mods for /videos/?filter=longest to prevent 404.
 *
 * @param WP_Query $query Main query instance.
 */
function tmw_videos_page_override( $query ) {
  if ( ! is_admin() && $query->is_main_query() && is_page( 'videos' ) ) {
    // Neutralize parent meta query that causes empty results
    $query->set( 'meta_key', '' );
    $query->set( 'orderby', 'none' );
    $query->is_page = true;
    $query->is_home = false;
    $query->is_404  = false;
  }
}
add_action( 'pre_get_posts', 'tmw_videos_page_override', 20 );


add_filter('wp_resource_hints', function($urls, $relation_type){
  $should_hint = is_singular('video') || is_page('videos');
  if ($should_hint && 'preconnect' === $relation_type) {
    $urls[] = 'https://galleryn3.vcmdawe.com';
  }
  if ($should_hint && 'dns-prefetch' === $relation_type) {
    $urls[] = '//galleryn3.vcmdawe.com';
  }
  return $urls;
}, 10, 2);

add_action('after_setup_theme', function () {
  // Keep for completeness – not used directly by the banner
  add_image_size('tmw-model-hero-land', 1440, 810, true);   // 16:9
  add_image_size('tmw-model-hero-banner', 1200, 350, true); // ~3.43:1
  add_image_size('tmw-hero-mobile', 480, 270, true);        // 16:9
  add_image_size('tmw-hero-desktop', 1200, 675, true);      // 16:9
});
