<?php

/**
 * 🧩 TMW Flipbox Force Ajax + Footer Fix (v2.9.3)
 * Ensures ajaxurl exists and wp_footer() output includes our ping script.
 */
/**
 * Unified banner resolver used by both admin preview and the front-end.
 * Priority: post-level ACF/legacy sources -> taxonomy ACF & feed helpers -> featured image fallback.
 */
if (!function_exists('tmw_resolve_model_banner_url')) {
  /**
   * Resolve the banner URL for a model post or term.
   *
   * @param int $post_id Optional model post ID.
   * @param int $term_id Optional model term ID.
   * @return string Resolved banner URL or empty string.
   */
  function tmw_resolve_model_banner_url($post_id = 0, $term_id = 0) {
    $arg_count = func_num_args();
    $original_post_id = $post_id;

    $post_id = (int) $post_id;
    $term_id = (int) $term_id;

    if ($arg_count === 1 && $term_id === 0 && $post_id && !get_post($post_id)) {
      $term_id = $post_id;
      $post_id = 0;
    }

    $banner_url = '';

    if ($post_id) {
      if (function_exists('get_field')) {
        $banner_field = get_field('banner_image', $post_id);
        if (is_array($banner_field) && !empty($banner_field['url'])) {
          $banner_url = (string) $banner_field['url'];
        } elseif (is_string($banner_field) && filter_var($banner_field, FILTER_VALIDATE_URL)) {
          $banner_url = $banner_field;
        }
      }

      if (empty($banner_url)) {
        $legacy = get_post_meta($post_id, 'banner_image', true);
        if (is_array($legacy) && !empty($legacy['url']) && filter_var($legacy['url'], FILTER_VALIDATE_URL)) {
          $banner_url = (string) $legacy['url'];
        } elseif (is_string($legacy) && filter_var($legacy, FILTER_VALIDATE_URL)) {
          $banner_url = $legacy;
        } elseif (is_numeric($legacy)) {
          $maybe = wp_get_attachment_url((int) $legacy);
          if (is_string($maybe) && filter_var($maybe, FILTER_VALIDATE_URL)) {
            $banner_url = $maybe;
          }
        }
      }

      if (empty($banner_url)) {
        $legacy_url = get_post_meta($post_id, 'banner_image_url', true);
        if (is_array($legacy_url) && !empty($legacy_url['url']) && filter_var($legacy_url['url'], FILTER_VALIDATE_URL)) {
          $banner_url = (string) $legacy_url['url'];
        } elseif (is_string($legacy_url) && filter_var($legacy_url, FILTER_VALIDATE_URL)) {
          $banner_url = $legacy_url;
        }
      }
    }

    if ($term_id === 0 && $post_id) {
      $terms = wp_get_post_terms($post_id, 'models');
      if (!is_wp_error($terms) && !empty($terms)) {
        $term_id = (int) $terms[0]->term_id;
      }
    }

    if (empty($banner_url) && $term_id) {
      $acf_id = 'models_' . $term_id;
      $source = function_exists('get_field') ? (get_field('banner_source', $acf_id) ?: 'feed') : 'feed';

      if ($source === 'url' && function_exists('get_field')) {
        $maybe_url = (string) (get_field('banner_image_url', $acf_id) ?: '');
        if ($maybe_url) {
          $banner_url = $maybe_url;
        }
      }

      if (empty($banner_url) && $source === 'upload' && function_exists('get_field')) {
        $img = get_field('banner_image', $acf_id);
        if (is_array($img) && !empty($img['url'])) {
          $banner_url = (string) $img['url'];
        }
      }

      if (empty($banner_url) && function_exists('tmw_aw_find_by_candidates')) {
        $term = get_term($term_id, 'models');
        $candidates = [];
        $nick = get_term_meta($term_id, 'tmw_aw_nick', true);
        if ($nick) {
          $candidates[] = $nick;
        }
        if ($term && !is_wp_error($term)) {
          $candidates[] = $term->name;
          $candidates[] = $term->slug;
        }
        $row = tmw_aw_find_by_candidates(array_unique(array_filter($candidates)));
        if ($row) {
          $maybe = tmw_pick_banner_from_feed_row($row);
          if ($maybe) {
            $banner_url = $maybe;
          }
        }
      }

      if (empty($banner_url) && function_exists('get_field')) {
        $img = get_field('banner_image', $acf_id);
        if (is_array($img) && !empty($img['url'])) {
          $banner_url = (string) $img['url'];
        } elseif (!$banner_url) {
          $maybe_url = get_field('banner_image_url', $acf_id);
          if (is_string($maybe_url) && filter_var($maybe_url, FILTER_VALIDATE_URL)) {
            $banner_url = $maybe_url;
          }
        }
      }

      if (empty($banner_url) && function_exists('tmw_placeholder_image_url')) {
        $banner_url = (string) tmw_placeholder_image_url();
      }
    }

    if (empty($banner_url) && $post_id && has_post_thumbnail($post_id)) {
      $banner_url = wp_get_attachment_image_url(get_post_thumbnail_id($post_id), 'full');
    }

    if (!empty($banner_url)) {
      $banner_url = set_url_scheme($banner_url, 'https');
    }

    return $banner_url ? esc_url_raw($banner_url) : '';
  }
}

/**
 * Back-compat wrapper for existing calls.
 */
if (!function_exists('tmw_get_model_banner_url')) {
  /**
   * Fetch the banner URL for a model post.
   *
   * @param int $post_id Model post ID.
   * @return string Banner URL or empty string.
   */
  function tmw_get_model_banner_url($post_id) {
    $banner = tmw_resolve_model_banner_url($post_id);

    return $banner;
  }
}

if (!function_exists('tmw_offset_to_focal_percent')) {
  /**
   * Convert a legacy pixel offset into an object-position percentage.
   *
   * @param int $offset_px   Saved pixel offset (negative shows lower image).
   * @param int $base_height Base banner height used for the legacy offset.
   * @return float Percentage (0-100).
   */
  function tmw_offset_to_focal_percent($offset_px, $base_height = 350) {
    $offset_px   = is_numeric($offset_px) ? (int) $offset_px : 0;
    $base_height = is_numeric($base_height) ? (int) $base_height : 350;

    if ($base_height <= 0) {
      $base_height = 350;
    }

    $clamped = max(-$base_height, min($base_height, $offset_px));
    $percent = 50 - ($clamped / $base_height * 50);

    return max(0, min(100, $percent));
  }
}

if (!function_exists('tmw_get_model_banner_focal_y')) {
  /**
   * Resolve the banner focal point percentage for a model.
   *
   * @param int $post_id Model post ID.
   * @param int $base_height Legacy offset base height.
   * @return float Percentage (0-100).
   */
  function tmw_get_model_banner_focal_y($post_id, $base_height = 350) {
    $post_id = (int) $post_id;
    if (!$post_id) {
      return 50;
    }

    $stored = get_post_meta($post_id, '_banner_focal_y', true);
    if ($stored !== '' && $stored !== null) {
      $focal = is_numeric($stored) ? (float) $stored : 50;
      return max(0, min(100, $focal));
    }

    $legacy = get_post_meta($post_id, '_banner_position_y', true);
    if ($legacy !== '' && $legacy !== null) {
      $focal = tmw_offset_to_focal_percent((int) $legacy, $base_height);
      update_post_meta($post_id, '_banner_focal_y', $focal);
      return $focal;
    }

    return 50;
  }
}

/**
 * Render the unified model banner markup for both the front-end and admin preview.
 *
 * @param int    $model_id Model post ID (falls back to current post when omitted).
 * @param string $context  Rendering context identifier (e.g. 'frontend' or 'backend').
 *
 * @return bool Whether the banner markup was rendered.
 */
if (!function_exists('tmw_render_model_banner')) {
  function tmw_render_model_banner($model_id = 0, $context = 'frontend') {
    if (!$model_id) {
      $model_id = get_the_ID();
    }

    $model_id = (int) $model_id;
    $context  = is_string($context) ? $context : 'frontend';
    $context  = $context ? sanitize_html_class($context) : 'frontend';

    if (!$model_id) {
      return false;
    }

    $url     = tmw_resolve_model_banner_url($model_id);
    $focal_y = tmw_get_model_banner_focal_y($model_id);

    if ($url) {
      $classes = array_filter(['tmw-banner-frame', $context]);

      $attachment_id = tmw_get_attachment_id_cached($url);
      $image_size    = apply_filters('tmw/model_banner/image_size', 'large');
      $alt           = $attachment_id ? (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true) : '';
      if ($alt === '') {
        $alt = get_the_title($model_id) . ' – live webcam model profile image';
      }
      $dimensions    = function_exists('tmw_child_image_dimensions')
        ? tmw_child_image_dimensions($url, 1035, 350)
        : ['width' => 1035, 'height' => 350];
      $use_picture   = $context === 'frontend' && is_singular('model');

      if ($use_picture) {
        $desktop_data = $attachment_id ? wp_get_attachment_image_src($attachment_id, 'tmw-hero-desktop') : false;
        if (!$desktop_data && $attachment_id) {
          $desktop_data = wp_get_attachment_image_src($attachment_id, 'large');
        }
        if (!$desktop_data && $attachment_id) {
          $desktop_data = wp_get_attachment_image_src($attachment_id, 'full');
        }

        $desktop_url    = is_array($desktop_data) && !empty($desktop_data[0]) ? $desktop_data[0] : $url;
        $desktop_width  = is_array($desktop_data) && !empty($desktop_data[1]) ? (int) $desktop_data[1] : (int) $dimensions['width'];
        $desktop_height = is_array($desktop_data) && !empty($desktop_data[2]) ? (int) $desktop_data[2] : (int) $dimensions['height'];

        $mobile_data = $attachment_id ? wp_get_attachment_image_src($attachment_id, 'tmw-hero-mobile') : false;
        if (!$mobile_data && $attachment_id) {
          $mobile_data = wp_get_attachment_image_src($attachment_id, 'medium');
        }
        if (!$mobile_data) {
          $mobile_data = $desktop_data;
        }

        $mobile_url = is_array($mobile_data) && !empty($mobile_data[0]) ? $mobile_data[0] : $desktop_url;

        $attrs = [
          'src'           => esc_url($desktop_url),
          'alt'           => $alt,
          'decoding'      => 'async',
          'loading'       => 'eager',
          'fetchpriority' => 'high',
          'width'         => $desktop_width,
          'height'        => $desktop_height,
          'style'         => sprintf('object-position: 50%% %s%%;', $focal_y),
        ];

        $attr_html = '';
        foreach ($attrs as $key => $value) {
          if ($value === '' || $value === null) {
            continue;
          }
          $attr_html .= ' ' . $key . '="' . esc_attr($value) . '"';
        }

        echo '<div class="tmw-banner-container">';
        echo '  <div class="' . esc_attr(implode(' ', $classes)) . '">';
        echo '    <picture>';
        if ($mobile_url) {
          echo '      <source media="(max-width: 768px)" srcset="' . esc_url($mobile_url) . '" type="image/webp" />';
        }
        echo '      <img' . $attr_html . ' />';
        echo '    </picture>';
        echo '  </div>';
        echo '</div>';
      } else {
        $attrs = [
          'src'           => esc_url($url),
          'alt'           => $alt,
          'decoding'      => 'async',
          'width'         => (int) $dimensions['width'],
          'height'        => (int) $dimensions['height'],
          'style'         => sprintf('object-position: 50%% %s%%;', $focal_y),
        ];

        $attrs['loading'] = 'lazy';

        if ($attachment_id) {
          $src_data = wp_get_attachment_image_src($attachment_id, $image_size);
          if (is_array($src_data) && !empty($src_data[0])) {
            $attrs['src']    = esc_url($src_data[0]);
            $attrs['width']  = !empty($src_data[1]) ? (int) $src_data[1] : $attrs['width'];
            $attrs['height'] = !empty($src_data[2]) ? (int) $src_data[2] : $attrs['height'];
          }

          $meta = wp_get_attachment_metadata($attachment_id);
          if (is_array($meta) && isset($meta['sizes'][$image_size])) {
            $size_meta = $meta['sizes'][$image_size];

            if (!empty($size_meta['width'])) {
              $attrs['width'] = (int) $size_meta['width'];
            }

            if (!empty($size_meta['height'])) {
              $attrs['height'] = (int) $size_meta['height'];
            }
          }

          $srcset = wp_get_attachment_image_srcset($attachment_id, $image_size);
          if ($srcset) {
            $attrs['srcset'] = $srcset;
          }

          $sizes = wp_get_attachment_image_sizes($attachment_id, $image_size);
          if ($sizes) {
            $attrs['sizes'] = $sizes;
          }
        }

        $attr_html = '';
        foreach ($attrs as $key => $value) {
          if ($value === '' || $value === null) {
            continue;
          }
          $attr_html .= ' ' . $key . '="' . esc_attr($value) . '"';
        }

        echo '<div class="tmw-banner-container">';
        echo '  <div class="' . esc_attr(implode(' ', $classes)) . '">';
        echo '    <img' . $attr_html . ' />';
        echo '  </div>';
        echo '</div>';
      }

      return true;
    }

    return false;
  }
}

/**
 * Resolve vertical offset (in px). Prefers saved slider meta, then ACF taxonomy `banner_offset_y` if present.
 */
if (!function_exists('tmw_get_model_banner_offset_y')) {
  /**
   * Get banner vertical offset for a model post.
   *
   * @param int $post_id Model post ID.
   * @return int Offset in pixels.
   */
  function tmw_get_model_banner_offset_y($post_id) {
    $raw_y   = get_post_meta($post_id, '_banner_position_y', true);
    $has_meta = $raw_y !== '' && $raw_y !== null;
    $y       = $has_meta ? (int)$raw_y : 0;

    if (!$has_meta) {
      // Try taxonomy ACF offset
      $terms = wp_get_post_terms($post_id, 'models');
      if (!is_wp_error($terms) && !empty($terms) && function_exists('get_field')) {
        $term_id = (int)$terms[0]->term_id;
        $acf_y   = get_field('banner_offset_y', 'models_' . $term_id);
        if (is_numeric($acf_y)) {
          $y = (int)$acf_y;
        }
      }
    }

    // Clamp to sensible range
    if ($y < -1000) {
      $y = -1000;
    }
    if ($y > 1000) {
      $y = 1000;
    }

    return $y;
  }
}

if (!function_exists('tmw_get_model_banner_height')) {
  /**
   * Get banner height for a model post.
   *
   * @param int $post_id Model post ID.
   * @return int Banner height in pixels.
   */
  function tmw_get_model_banner_height($post_id) {
    $height = 350;

    $terms = wp_get_post_terms($post_id, 'models');
    if (!is_wp_error($terms) && !empty($terms) && function_exists('get_field')) {
      $term_id = (int)$terms[0]->term_id;
      $pick    = get_field('banner_height', 'models_' . $term_id);

      if (is_array($pick) && isset($pick['value'])) {
        $pick = $pick['value'];
      }

      if (is_numeric($pick) || is_string($pick)) {
        $pick = (string)$pick;
        if ($pick === '350') {
          $height = 350;
        }
      }
    }

    return $height;
  }
}

if (!function_exists('tmw_get_banner_style')) {
  /**
   * Build banner inline style properties.
   *
   * @param int   $offset_y Vertical offset in pixels.
   * @param int   $height   Banner height in pixels.
   * @param array $context  Optional context overrides.
   * @return string Inline object-position style string.
   */
  function tmw_get_banner_style($offset_y = 0, $height = 350, $context = []): string {
    $offset_y = is_numeric($offset_y) ? (int) $offset_y : 0;
    $height   = is_numeric($height) ? (int) $height : 350;
    if ($height <= 0) {
      $height = 350;
    }

    if (!is_array($context)) {
      if (is_numeric($context)) {
        $context = ['post_id' => (int) $context];
      } else {
        $context = [];
      }
    }

    $pos_x = 50;
    if (isset($context['pos_x']) && is_numeric($context['pos_x'])) {
      $pos_x = max(0, min(100, (float) $context['pos_x']));
    }

    $focal_y = function_exists('tmw_offset_to_focal_percent')
      ? tmw_offset_to_focal_percent($offset_y, $height)
      : 50;

    return sprintf('object-position: %s%% %s%%;', $pos_x, $focal_y);
  }
}
