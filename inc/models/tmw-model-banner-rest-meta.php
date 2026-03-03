<?php

if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('tmw_model_banner_meta_auth_callback')) {
  function tmw_model_banner_meta_auth_callback(bool $allowed, string $meta_key, int $post_id = 0): bool {
    if ($post_id > 0) {
      return current_user_can('edit_post', $post_id);
    }

    return current_user_can('edit_posts');
  }
}

add_action('init', function (): void {
  register_post_meta('model', 'tmw_banner_image_id', [
    'type' => 'integer',
    'single' => true,
    'show_in_rest' => true,
    'default' => 0,
    'sanitize_callback' => 'absint',
    'auth_callback' => 'tmw_model_banner_meta_auth_callback',
  ]);

  register_post_meta('model', 'banner_image', [
    'type' => 'integer',
    'single' => true,
    'show_in_rest' => true,
    'default' => 0,
    'sanitize_callback' => 'absint',
    'auth_callback' => 'tmw_model_banner_meta_auth_callback',
  ]);
});
