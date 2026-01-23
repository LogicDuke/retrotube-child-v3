<?php

/* ======================================================================
 * SAFE PLACEHOLDER (never 404s)
 * ====================================================================== */
if (!function_exists('tmw_placeholder_image_url')) {
  /**
   * Provide a safe placeholder image URL for missing assets.
   *
   * @return string Placeholder image URL or SVG data URI.
   */
  function tmw_placeholder_image_url() {
    $path = get_stylesheet_directory() . '/assets/img/placeholders/model-card.jpg';
    if (file_exists($path)) {
      return get_stylesheet_directory_uri() . '/assets/img/placeholders/model-card.jpg';
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1200"><rect fill="#121212" width="100%" height="100%"/><text x="50%" y="50%" fill="#666" font-size="40" font-family="system-ui,Arial" text-anchor="middle">Model</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
  }
}

/* ======================================================================
 * DISTINCT-IMAGE HELPERS (ignore size folders, queries, host)
 * ====================================================================== */
if (!function_exists('tmw_img_fingerprint')) {
  /**
   * Normalize an image URL for comparison purposes.
   *
   * @param string $url Image URL.
   * @return string Normalized fingerprint.
   */
  function tmw_img_fingerprint($url) {
    if (!$url) return '';
    $u = explode('?', $url, 2)[0];
    $u = preg_replace('~/(?:\d{3,4}x\d{3,4})/~', '/', $u);
    $u = preg_replace('~([-_]\d{3,4}x\d{3,4})(?=\.[a-z]+$)~i', '', $u);
    $p = @parse_url($u);
    $path = isset($p['path']) ? $p['path'] : $u;
    return strtolower($path);
  }
}
if (!function_exists('tmw_same_image')) {
  /**
   * Compare two image URLs using normalized fingerprints.
   *
   * @param string $a First URL.
   * @param string $b Second URL.
   * @return bool True if the images match.
   */
  function tmw_same_image($a, $b) {
    if (!$a || !$b) return false;
    return tmw_img_fingerprint($a) === tmw_img_fingerprint($b);
  }
}

/* Preserve data: URLs in inline background-image styles */
if (!function_exists('tmw_bg_style')) {
  /**
   * Build a safe background-image CSS string for inline usage.
   *
   * @param string $url Image URL.
   * @return string CSS background-image declaration.
   */
  function tmw_bg_style($url){
    if (!$url) return '';
    if (strpos($url, 'data:image') === 0) {
      $safe = $url;
    } else {
      $safe = esc_url($url);
      if (preg_match('~/gold-black-(?:bg|hero)\.webp$~i', $safe)) {
        $safe = add_query_arg('v', TMW_BG_CACHE_VERSION, $safe);
      }
    }
    return 'background-image:url('. $safe .');';
  }
}

/* ======================================================================
 * EXPLICIT vs NON-EXPLICIT CLASSIFIER + PORTRAIT HELPERS
 * ====================================================================== */
if (!function_exists('tmw_is_portrait')) {
  /**
   * Determine whether the image URL appears to be portrait-oriented.
   *
   * @param string $url Image URL.
   * @return bool True for portrait, false otherwise.
   */
  function tmw_is_portrait($url) {
    $url = (string)$url;
    if (strpos($url, '600x800') !== false || strpos($url, '504x896') !== false) return true;
    if (strpos($url, '800x600') !== false || strpos($url, '896x504') !== false) return false;
    return false;
  }
}
if (!function_exists('tmw_classify_image')) {
  /**
   * Classify an image URL as explicit, safe, or unknown.
   *
   * @param string $url Image URL.
   * @return string Classification string.
   */
  function tmw_classify_image($url) {
    $explicit_re    = defined('TMW_EXPLICIT_RE')    ? TMW_EXPLICIT_RE    : '~(explicit|nsfw|xxx|nude|naked|topless|boobs|tits|pussy|ass|anal|hard|sex|cum|dildo)~i';
    $nonexplicit_re = defined('TMW_NONEXPLICIT_RE') ? TMW_NONEXPLICIT_RE : '~(cover|poster|teaser|profile|safe|thumb|avatar|portrait)~i';
    $explicit_re    = apply_filters('tmw_explicit_regex',    $explicit_re);
    $nonexplicit_re = apply_filters('tmw_nonexplicit_regex', $nonexplicit_re);
    if (@preg_match($explicit_re, (string)$url))    return 'explicit';
    if (@preg_match($nonexplicit_re, (string)$url)) return 'safe';
    return 'unknown';
  }
}
