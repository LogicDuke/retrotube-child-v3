<?php

/* ======================================================================
 * CONTENT CLEANUPS (remove video players from post content area)
 * ====================================================================== */
if (!function_exists('tmw_strip_video_in_content_active')) {
  /**
   * Determine whether video embeds should be stripped from content.
   *
   * @return bool True when stripping is active.
   */
  function tmw_strip_video_in_content_active() {
    if (is_admin()) return false;
    if (!is_singular()) return false;
    $pt = get_post_type();
    $video_types = ['post','video','videos','wpsc-video','wp-script-video','wpws_video'];
    return in_array($pt, $video_types, true);
  }
}
add_filter('pre_do_shortcode_tag', function($return, $tag){
  if (!tmw_strip_video_in_content_active()) return $return;
  $video_tags = ['video','playlist','audio','embed','wpvideo','wp_playlist','youtube','vimeo','dailymotion','jwplayer','videojs','fvplayer','plyr','wpsc_video','wps_video','wpws_video','flowplayer','jetpack_video'];
  return in_array(strtolower($tag), $video_tags, true) ? '' : $return;
}, 10, 2);
add_filter('embed_oembed_html', function($html){ return tmw_strip_video_in_content_active() ? '' : $html; }, 10);
add_filter('oembed_dataparse',  function($r){    return tmw_strip_video_in_content_active() ? '' : $r;    }, 10);
add_filter('render_block', function($block_content, $block){
  if (!tmw_strip_video_in_content_active()) return $block_content;
  $name = isset($block['blockName']) ? $block['blockName'] : '';
  if ($name === 'core/video' || $name === 'core/embed' || strpos($name, 'core-embed/') === 0) return '';
  return $block_content;
}, 9, 2);
add_filter('the_content', function ($content) {
  if (!tmw_strip_video_in_content_active()) return $content;
  $patterns = [
    '#<iframe\\b[^>]*>.*?</iframe>#is',
    '#<video\\b[^>]*>.*?</video>#is',
    '#<audio\\b[^>]*>.*?</audio>#is',
    '#<object\\b[^>]*>.*?</object>#is',
    '#<embed\\b[^>]*>.*?</embed>#is',
    '#<figure[^>]*class="[^"]*(wp-block-embed|wp-block-video)[^"]*"[^>]*>.*?</figure>#is',
    '#<div[^>]*class="[^"]*(wp-block-embed|video-js|jwplayer|plyr|flowplayer|responsive-embed|embed-container)[^"]*"[^>]*>.*?</div>#is',
  ];
  foreach ($patterns as $rx) $content = preg_replace($rx, '', $content);
  $content = preg_replace('/\[[^\]]*?video[^\]]*\](?:.*?\[\/[^^\]]*?video[^\]]*\])?/is', '', $content);
  $content = preg_replace('/<p>\s*<\/p>/i', '', $content);
  return $content;
}, 99);
add_filter('the_content', function ($content) {
  if (!function_exists('tmw_strip_video_in_content_active') || !tmw_strip_video_in_content_active()) return $content;
  $content = preg_replace('#<div[^>]*class=(["\']).*?\\bplayer\\b.*?\1[^>]*>[\s\S]*?</div>#i', '', $content);
  $content = preg_replace('#<div[^>]*style=(["\']).*?aspect-ratio.*?\1[^>]*>\s*(?:<!--.*?-->\s*)*</div>#is', '', $content);
  return $content;
}, 98);
add_filter('body_class', function ($classes) {
  if (!function_exists('tmw_strip_video_in_content_active') || !tmw_strip_video_in_content_active()) return $classes;
  $post = get_queried_object();
  if ($post && is_object($post)) {
    if (stripos((string)$post->post_content, 'class="player"') !== false) $classes[] = 'tmw-no-embed';
  }
  return $classes;
}, 11);
