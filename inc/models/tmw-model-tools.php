<?php

/* ======================================================================
 * TMW TOOLS INTEGRATION (front/back overrides + alignment/zoom)
 * ====================================================================== */
if (!function_exists('tmw_tools_settings')) {
  /**
   * Fetch TMW tools settings from options.
   *
   * @return array Settings array.
   */
  function tmw_tools_settings(): array {
    $opt = get_option('tmw_mf_settings', []);
    return is_array($opt) ? $opt : [];
  }
}
if (!function_exists('tmw_get_model_keys')) {
  /**
   * Build a list of normalized model identifiers for overrides.
   *
   * @param int $term_id Model term ID.
   * @return array Normalized model keys.
   */
  function tmw_get_model_keys(int $term_id): array {
    $keys = [];
    $aw = get_term_meta($term_id, 'tmw_aw_nick', true);
    $lj = get_term_meta($term_id, 'tmw_lj_nick', true);
    if ($aw) $keys[] = $aw;
    if ($lj) $keys[] = $lj;
    $t = get_term($term_id, 'models');
    if ($t && !is_wp_error($t)) { $keys[] = $t->name; $keys[] = $t->slug; }
    $norm = [];
    foreach ($keys as $k) {
      $k = trim((string)$k);
      if ($k === '') continue;
      $norm[] = strtolower($k);
      $norm[] = preg_replace('~[ _-]+~', '', strtolower($k));
    }
    $out = [];
    foreach (array_merge($keys, $norm) as $k) if ($k !== '' && !in_array($k, $out, true)) $out[] = $k;
    return $out;
  }
}
if (!function_exists('tmw_tools_pick_from_map')) {
  /**
   * Pick the first matching value from a map using candidate keys.
   *
   * @param array $map   Lookup map.
   * @param array $cands Candidate keys.
   * @return mixed|null Matching value or null.
   */
  function tmw_tools_pick_from_map($map, array $cands) {
    if (!is_array($map) || empty($cands)) return null;
    foreach ($cands as $k) if (isset($map[$k]) && $map[$k] !== '') return $map[$k];
    $lower = []; foreach ($map as $k=>$v) $lower[strtolower((string)$k)] = $v;
    foreach ($cands as $k) { $lk = strtolower((string)$k); if (isset($lower[$lk]) && $lower[$lk] !== '') return $lower[$lk]; }
    $norm = []; foreach ($map as $k=>$v) $norm[preg_replace('~[ _-]+~','', strtolower((string)$k))] = $v;
    foreach ($cands as $k) { $nk = preg_replace('~[ _-]+~','', strtolower((string)$k)); if (isset($norm[$nk]) && $norm[$nk] !== '') return $norm[$nk]; }
    return null;
  }
}
if (!function_exists('tmw_bg_align_css')) {
  /**
   * Generate background alignment CSS for the banner tool overrides.
   *
   * @param float|int $pos_percent Horizontal position percentage.
   * @param float|int $zoom        Zoom factor.
   * @return string CSS string for background alignment.
   */
  function tmw_bg_align_css($pos_percent = 50, $zoom = 1.0): string {
    $pos = max(0, min(100, (float)$pos_percent));
    $z   = max(1.0, min(2.5, (float)$zoom));
    $bgsize = ($z > 1.0) ? sprintf('%.2f%% auto', $z * 100.0) : 'cover';
    return sprintf(
      'background-position: %.2f%% 50%% !important; background-size: %s !important; --tmw-bgpos: %.2f%% 50%%; --tmw-bgsize: %s;',
      $pos, $bgsize, $pos, $bgsize
    );
  }
}
if (!function_exists('tmw_tools_overrides_for_term')) {
  /**
   * Resolve front/back overrides and CSS for a model term.
   *
   * @param int $term_id Model term ID.
   * @return array<string,string> Override URLs and CSS.
   */
  function tmw_tools_overrides_for_term(int $term_id): array {
    $s      = tmw_tools_settings();
    $cands  = tmw_get_model_keys($term_id);

    $front_url = tmw_tools_pick_from_map($s['front_overrides'] ?? [], $cands);
    $back_url  = tmw_tools_pick_from_map($s['back_overrides']  ?? [], $cands);

    $pos_f = tmw_tools_pick_from_map($s['object_pos_front'] ?? [], $cands);
    $pos_b = tmw_tools_pick_from_map($s['object_pos_back']  ?? [], $cands);
    $zoom_f= tmw_tools_pick_from_map($s['zoom_front']       ?? [], $cands);
    $zoom_b= tmw_tools_pick_from_map($s['zoom_back']        ?? [], $cands);

    $css_front = tmw_bg_align_css($pos_f !== null ? $pos_f : 50, $zoom_f !== null ? $zoom_f : 1.0);
    $css_back  = tmw_bg_align_css($pos_b !== null ? $pos_b : 50, $zoom_b !== null ? $zoom_b : 1.0);

    return [
      'front_url' => is_string($front_url) ? $front_url : '',
      'back_url'  => is_string($back_url)  ? $back_url  : '',
      'css_front' => $css_front,
      'css_back'  => $css_back,
    ];
  }
}

/* ======================================================================
 * AWE FEED HELPERS
 * ====================================================================== */
if (!function_exists('tmw_normalize_nick')) {
  /**
   * Normalize a model nickname for feed matching.
   *
   * @param string $s Input string.
   * @return string Normalized nickname.
   */
  function tmw_normalize_nick($s){
    $s = strtolower($s);
    $s = preg_replace('~[^\pL\d]+~u', '', $s);
    return $s;
  }
}
if (!function_exists('tmw_aw_get_feed')) {
  /**
   * Fetch the AWE feed data with caching.
   *
   * @param int $ttl_minutes Cache duration in minutes.
   * @return array Feed data array.
   */
  function tmw_aw_get_feed($ttl_minutes = 10) {
    $key = 'tmw_aw_feed_v1';
    $cached = get_transient($key);
    if ($cached !== false) return $cached;
    if (!defined('AWEMPIRE_FEED_URL') || !AWEMPIRE_FEED_URL) return [];
    $resp = wp_remote_get(AWEMPIRE_FEED_URL, ['timeout' => 15]);
    if (is_wp_error($resp)) return [];
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if (is_array($data)) {
      if (isset($data['data']['models']) && is_array($data['data']['models'])) $data = $data['data']['models'];
      elseif (isset($data['models']) && is_array($data['models']))             $data = $data['models'];
    }
    if (!is_array($data)) $data = [];
    set_transient($key, $data, $ttl_minutes * MINUTE_IN_SECONDS);
    return $data;
  }
}
if (!function_exists('tmw_aw_find_by_candidates')) {
  /**
   * Locate a feed row matching candidate identifiers.
   *
   * @param array $cands Candidate identifiers.
   * @return array|null Matched feed row or null.
   */
  function tmw_aw_find_by_candidates($cands){
    $feed = tmw_aw_get_feed();
    if (empty($feed) || !is_array($feed)) return null;
    $norms = array_map('tmw_normalize_nick', array_filter(array_unique((array)$cands)));
    foreach ($feed as $row){
      $vals = [];
      foreach (['performerId','displayName','nickname','name','uniqueModelId'] as $k) {
        if (!empty($row[$k])) $vals[] = $row[$k];
      }
      foreach ($vals as $v) {
        if (in_array(tmw_normalize_nick($v), $norms, true)) return $row;
      }
    }
    return null;
  }
}

/* ======================================================================
 * AWE image picker — prefer PORTRAIT. FRONT=safe, BACK=explicit
 * ====================================================================== */
if (!function_exists('tmw_try_portrait_variant')) {
  /**
   * Try to derive a portrait URL variant from a landscape URL.
   *
   * @param string $url Image URL.
   * @return string|null Portrait variant or null.
   */
  function tmw_try_portrait_variant($url) {
    $try = preg_replace_callback('~/(800x600|896x504)/~', function($m){
      return '/'.($m[1]==='800x600' ? '600x800' : '504x896').'/';
    }, $url, 1);
    if ($try && $try !== $url) return $try;
    $try2 = preg_replace_callback('~([-_])(800x600|896x504)(?=\.[a-z]+$)~i', function($m){
      return $m[1].($m[2]==='800x600' ? '600x800' : '504x896');
    }, $url, 1);
    return ($try2 && $try2 !== $url) ? $try2 : null;
  }
}
if (!function_exists('tmw_aw_pick_images_from_row')) {
  /**
   * Pick front/back images from a feed row.
   *
   * @param array $row Feed row data.
   * @return array{0:?string,1:?string} Front/back image URLs.
   */
  function tmw_aw_pick_images_from_row($row) {
    $all = [];
    $walk = function($v) use (&$walk, &$all) {
      if (is_string($v) && preg_match('~https?://[^\s"]+\.(?:jpe?g|png|webp)(?:\?[^\s"]*)?$~i', $v)) {
        $all[] = $v;
      } elseif (is_array($v)) {
        foreach ($v as $vv) $walk($vv);
      }
    };
    $walk($row);
    $all = array_values(array_unique($all));
    if (!$all) return [null, null];

    $by_pic = [];
    foreach ($all as $u) {
      $fp = function_exists('tmw_img_fingerprint') ? tmw_img_fingerprint($u) : $u;
      if (!isset($by_pic[$fp])) $by_pic[$fp] = [];
      $by_pic[$fp][] = $u;
    }

    $pick_best_url = function($urls){
      usort($urls, function($a,$b){
        $sa = (tmw_is_portrait($a) ? 2 : 0) + (strpos($a,'600x800')!==false ? 1 : 0) + (strpos($a,'504x896')!==false ? 1 : 0);
        $sb = (tmw_is_portrait($b) ? 2 : 0) + (strpos($b,'600x800')!==false ? 1 : 0) + (strpos($b,'504x896')!==false ? 1 : 0);
        return $sb <=> $sa;
      });
      return $urls[0];
    };

    $shots = [];
    foreach ($by_pic as $urls) $shots[] = $pick_best_url($urls);

    $portrait_safe = []; $portrait_exp = []; $portrait_unk = []; $land_any = [];
    foreach ($shots as $u) {
      $cls = tmw_classify_image($u);
      if (tmw_is_portrait($u)) {
        if     ($cls === 'safe')     $portrait_safe[] = $u;
        elseif ($cls === 'explicit') $portrait_exp[]  = $u;
        else                         $portrait_unk[]  = $u;
      } else {
        $land_any[] = $u;
      }
    }

    $front = $portrait_safe[0] ?? ($portrait_unk[0] ?? null);
    $back  = null;
    foreach ($portrait_exp as $u) { if (!tmw_same_image($u, $front)) { $back = $u; break; } }
    if (!$back) { foreach ($portrait_unk as $u) { if (!tmw_same_image($u, $front)) { $back = $u; break; } } }

    if (!$front && !empty($land_any)) {
      $front_try = tmw_try_portrait_variant($land_any[0]);
      $front = $front_try ? $front_try : $land_any[0];
    }
    if (!$back && !empty($land_any)) {
      foreach ($land_any as $u) {
        if (!tmw_same_image($u, $front)) {
          $back_try = tmw_try_portrait_variant($u);
          $back = $back_try ? $back_try : $u;
          break;
        }
      }
    }

    if (!$front && !$back && !empty($shots)) $front = $shots[0];
    if (!$back) $back = $front;

    return [$front, $back];
  }
}
if (!function_exists('tmw_aw_build_link')) {
  /**
   * Build a tracking URL with optional sub-affiliate ID.
   *
   * @param string $base Base tracking URL.
   * @param string $sub  Sub-affiliate ID.
   * @return string Tracking URL.
   */
  function tmw_aw_build_link($base, $sub = '') {
    if (!$base) return '#';
    if ($sub) {
      if (strpos($base, '{SUBAFFID}') !== false) return str_replace('{SUBAFFID}', rawurlencode($sub), $base);
      $sep = (strpos($base, '?') !== false) ? '&' : '?';
      return $base . $sep . 'subAffId=' . rawurlencode($sub);
    }
    return str_replace('{SUBAFFID}', '', $base);
  }
}

/* ======================================================================
 * CARD DATA
 * ====================================================================== */
if (!function_exists('tmw_aw_card_data')) {
  /**
   * Build front/back card data for a model term.
   *
   * @param int $term_id Model term ID.
   * @return array<string,string> Card data array.
   */
  function tmw_aw_card_data($term_id) {
    $placeholder = tmw_placeholder_image_url();
    $front = $back = ''; $link  = '';

    // 1) ACF overrides (taxonomy: models)
    if (function_exists('get_field')) {
      $acf_front = get_field('actor_card_front', 'models_' . $term_id); // keeping field names for data continuity
      $acf_back  = get_field('actor_card_back',  'models_' . $term_id);
      if (is_array($acf_front) && !empty($acf_front['url'])) $front = $acf_front['url'];
      if (is_array($acf_back)  && !empty($acf_back['url']))  $back  = $acf_back['url'];
    }

    // 2) Candidate keys
    $term = get_term($term_id, 'models');
    $cands = [];
    $explicit = get_term_meta($term_id, 'tmw_aw_nick', true);
    if (!$explicit) $explicit = get_term_meta($term_id, 'tmw_lj_nick', true);
    if ($explicit) $cands[] = $explicit;
    if ($term && !is_wp_error($term)) {
      $cands[] = $term->slug;
      $cands[] = $term->name;
      $cands[] = str_replace(['-','_',' '], '', $term->slug);
      $cands[] = str_replace(['-','_',' '], '', $term->name);
    }

    // 3) Find feed row
    $row = tmw_aw_find_by_candidates(array_unique(array_filter($cands)));
    $sub = get_term_meta($term_id, 'tmw_aw_subaff', true);
    if (!$sub && $term && !is_wp_error($term)) $sub = $term->slug;

    if ($row) {
      if (!$front || !$back) {
        list($f, $b) = tmw_aw_pick_images_from_row($row);
        if (!$front) $front = $f;
        if (!$back)  $back  = $b;
      }
      $link = tmw_aw_build_link(($row['tracking_url'] ?? ($row['url'] ?? '')), $sub ?: ($explicit ?: ($term ? $term->slug : '')));
    }

    // 4) Fallback
    if (!$front || !$back) {
      $feed = tmw_aw_get_feed();
      if (is_array($feed) && !empty($feed)) {
        $ix = crc32((string)$term_id) % count($feed);
        $alt = $feed[$ix];
        list($f2, $b2) = tmw_aw_pick_images_from_row($alt);
        if (!$front) $front = $f2 ?: $placeholder;
        if (!$back)  $back  = $b2 ?: $front;
        if (!$link)  $link  = tmw_aw_build_link(($alt['tracking_url'] ?? ($alt['url'] ?? '')), $sub ?: ($term ? $term->slug : ''));
      }
    }

    // 5) Enforce portrait when possible
    if ($front && !tmw_is_portrait($front)) { $try = tmw_try_portrait_variant($front); if ($try) $front = $try; }
    if ($back  && !tmw_is_portrait($back))  { $try = tmw_try_portrait_variant($back);  if ($try) $back  = $try; }

    if (!$front) $front = $placeholder;
    if (!$back)  $back  = $front;

    return ['front' => $front, 'back' => $back, 'link' => $link];
  }
}

/* Admin bar button to purge AWE cache */
add_action('admin_bar_menu', function($bar){
  if (!current_user_can('manage_options')) return;
  $bar->add_node([
    'id'    => 'tmw_aw_clear_cache',
    'title' => 'Purge AWEmpire Cache',
    'href'  => wp_nonce_url(admin_url('?tmw_aw_clear_cache=1'), 'tmw_aw_clear_cache'),
  ]);
}, 100);
add_action('admin_init', function(){
  if ( current_user_can('manage_options') && isset($_GET['tmw_aw_clear_cache']) && wp_verify_nonce($_GET['_wpnonce'] ?? '', 'tmw_aw_clear_cache') ) {
    delete_transient('tmw_aw_feed_v1');
    wp_safe_redirect(remove_query_arg(['tmw_aw_clear_cache','_wpnonce']));
    exit;
  }
});
