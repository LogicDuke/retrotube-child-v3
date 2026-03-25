<?php

if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('tmw_video_thumbnail_compat_post_types')) {
  /**
   * Video-like post types that should keep featured images and thumb meta aligned.
   *
   * @return string[]
   */
  function tmw_video_thumbnail_compat_post_types(): array {
    $post_types = ['video', 'videos', 'wpsc-video', 'wp-script-video', 'wpws_video'];

    return array_values(array_unique(array_filter(apply_filters('tmw_video_thumbnail_compat_post_types', $post_types), 'is_string')));
  }
}

if (!function_exists('tmw_video_thumbnail_compat_applies')) {
  /**
   * Determine whether thumbnail compatibility should run for the given post type.
   *
   * @param string $post_type Post type slug.
   * @return bool
   */
  function tmw_video_thumbnail_compat_applies(string $post_type): bool {
    return in_array($post_type, tmw_video_thumbnail_compat_post_types(), true);
  }
}

if (!function_exists('tmw_video_thumbnail_meta_auth_callback')) {
  /**
   * Auth callback for thumb meta exposure.
   *
   * @param bool   $allowed Existing permission state.
   * @param string $meta_key Meta key.
   * @param int    $post_id Post ID.
   * @return bool
   */
  function tmw_video_thumbnail_meta_auth_callback(bool $allowed, string $meta_key, int $post_id = 0): bool {
    if ($post_id > 0) {
      return current_user_can('edit_post', $post_id);
    }

    return current_user_can('edit_posts');
  }
}

if (!function_exists('tmw_video_thumbnail_normalize_value')) {
  /**
   * Normalize a posted thumbnail value into a URL when possible.
   *
   * @param mixed $value Raw thumb field value.
   * @return string
   */
  function tmw_video_thumbnail_normalize_value($value): string {
    if (is_array($value)) {
      if (!empty($value['id'])) {
        $value = (int) $value['id'];
      } elseif (!empty($value['url'])) {
        $value = (string) $value['url'];
      }
    }

    if (is_numeric($value)) {
      $attachment_url = wp_get_attachment_url(absint($value));
      return is_string($attachment_url) ? esc_url_raw($attachment_url) : '';
    }

    $value = trim((string) $value);
    if ($value === '') {
      return '';
    }

    if (filter_var($value, FILTER_VALIDATE_URL)) {
      return esc_url_raw($value);
    }

    return '';
  }
}

if (!function_exists('tmw_video_thumbnail_featured_url')) {
  /**
   * Get the current featured image URL for a post.
   *
   * @param int $post_id Post ID.
   * @return string
   */
  function tmw_video_thumbnail_featured_url(int $post_id): string {
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if ($thumbnail_id <= 0) {
      return '';
    }

    $url = wp_get_attachment_url($thumbnail_id);
    return is_string($url) ? esc_url_raw($url) : '';
  }
}

if (!function_exists('tmw_video_thumbnail_sync_fields')) {
  /**
   * Keep the legacy thumb meta and WordPress featured image aligned.
   *
   * @param int $post_id Post ID.
   * @return void
   */
  function tmw_video_thumbnail_sync_fields(int $post_id): void {
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !tmw_video_thumbnail_compat_applies((string) $post->post_type)) {
      return;
    }

    $thumb_url = tmw_video_thumbnail_normalize_value(get_post_meta($post_id, 'thumb', true));
    $featured_url = tmw_video_thumbnail_featured_url($post_id);

    if ($featured_url !== '') {
      if ($thumb_url !== $featured_url) {
        update_post_meta($post_id, 'thumb', $featured_url);
      }
      return;
    }

    if ($thumb_url === '') {
      return;
    }

    $attachment_id = attachment_url_to_postid($thumb_url);
    if ($attachment_id > 0 && !has_post_thumbnail($post_id)) {
      set_post_thumbnail($post_id, $attachment_id);
    }
  }
}

add_action('init', function (): void {
  foreach (tmw_video_thumbnail_compat_post_types() as $post_type) {
    if (!post_type_exists($post_type)) {
      continue;
    }

    if (!post_type_supports($post_type, 'thumbnail')) {
      add_post_type_support($post_type, 'thumbnail');
    }

    register_post_meta($post_type, 'thumb', [
      'type' => 'string',
      'single' => true,
      'show_in_rest' => true,
      'default' => '',
      'sanitize_callback' => 'esc_url_raw',
      'auth_callback' => 'tmw_video_thumbnail_meta_auth_callback',
    ]);
  }
}, 99);

add_action('save_post', function ($post_id, $post, $update): void {
  if (!$post instanceof WP_Post || !tmw_video_thumbnail_compat_applies((string) $post->post_type)) {
    return;
  }

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  if (isset($_POST['thumb'])) {
    $posted_thumb = tmw_video_thumbnail_normalize_value(wp_unslash($_POST['thumb']));
    if ($posted_thumb !== '') {
      update_post_meta($post_id, 'thumb', $posted_thumb);
    } else {
      delete_post_meta($post_id, 'thumb');
    }
  }

  tmw_video_thumbnail_sync_fields((int) $post_id);
}, 25, 3);


foreach (tmw_video_thumbnail_compat_post_types() as $tmw_video_thumbnail_post_type) {
  add_action('rest_after_insert_' . $tmw_video_thumbnail_post_type, function (WP_Post $post, WP_REST_Request $request, bool $creating): void {
    tmw_video_thumbnail_sync_fields((int) $post->ID);
  }, 10, 3);
}
