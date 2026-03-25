<?php

if (!defined('ABSPATH')) {
  exit;
}

if (!function_exists('tmw_model_flipbox_metabox_clamp_int')) {
  function tmw_model_flipbox_metabox_clamp_int($value, int $min, int $max): int {
    return max($min, min($max, (int) $value));
  }
}

if (!function_exists('tmw_model_flipbox_metabox_clamp_float')) {
  function tmw_model_flipbox_metabox_clamp_float($value, float $min, float $max): float {
    return max($min, min($max, (float) $value));
  }
}

if (!function_exists('tmw_model_flipbox_meta_auth_callback')) {
  function tmw_model_flipbox_meta_auth_callback(bool $allowed, string $meta_key, int $post_id = 0): bool {
    if ($post_id > 0) {
      return current_user_can('edit_post', $post_id);
    }

    return current_user_can('edit_posts');
  }
}

if (!function_exists('tmw_model_flipbox_sanitize_absint')) {
  function tmw_model_flipbox_sanitize_absint($value): int {
    return max(0, absint($value));
  }
}

if (!function_exists('tmw_model_flipbox_sanitize_pos')) {
  function tmw_model_flipbox_sanitize_pos($value): int {
    return tmw_model_flipbox_metabox_clamp_int($value, 0, 100);
  }
}

if (!function_exists('tmw_model_flipbox_sanitize_zoom')) {
  function tmw_model_flipbox_sanitize_zoom($value): float {
    return tmw_model_flipbox_metabox_clamp_float($value, 1.0, 2.5);
  }
}

add_action('init', function (): void {
  register_post_meta('model', 'tmw_flip_front_id', [
    'type' => 'integer',
    'single' => true,
    'show_in_rest' => true,
    'default' => 0,
    'sanitize_callback' => 'tmw_model_flipbox_sanitize_absint',
    'auth_callback' => 'tmw_model_flipbox_meta_auth_callback',
  ]);

  register_post_meta('model', 'tmw_flip_back_id', [
    'type' => 'integer',
    'single' => true,
    'show_in_rest' => true,
    'default' => 0,
    'sanitize_callback' => 'tmw_model_flipbox_sanitize_absint',
    'auth_callback' => 'tmw_model_flipbox_meta_auth_callback',
  ]);

  register_post_meta('model', 'tmw_flip_pos_front', [
    'type' => 'integer',
    'single' => true,
    'show_in_rest' => true,
    'default' => 50,
    'sanitize_callback' => 'tmw_model_flipbox_sanitize_pos',
    'auth_callback' => 'tmw_model_flipbox_meta_auth_callback',
  ]);

  register_post_meta('model', 'tmw_flip_pos_back', [
    'type' => 'integer',
    'single' => true,
    'show_in_rest' => true,
    'default' => 50,
    'sanitize_callback' => 'tmw_model_flipbox_sanitize_pos',
    'auth_callback' => 'tmw_model_flipbox_meta_auth_callback',
  ]);

  register_post_meta('model', 'tmw_flip_zoom_front', [
    'type' => 'number',
    'single' => true,
    'show_in_rest' => true,
    'default' => 1.0,
    'sanitize_callback' => 'tmw_model_flipbox_sanitize_zoom',
    'auth_callback' => 'tmw_model_flipbox_meta_auth_callback',
  ]);

  register_post_meta('model', 'tmw_flip_zoom_back', [
    'type' => 'number',
    'single' => true,
    'show_in_rest' => true,
    'default' => 1.0,
    'sanitize_callback' => 'tmw_model_flipbox_sanitize_zoom',
    'auth_callback' => 'tmw_model_flipbox_meta_auth_callback',
  ]);
});
