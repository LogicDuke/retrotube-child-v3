<?php
/**
 * Shared banner frame helpers for the model banner meta box.
 */

if (!defined('ABSPATH')) {
  exit;
}

add_action('admin_init', function () {
  $base = get_stylesheet_directory();
  $uri  = get_stylesheet_directory_uri();

  $banner_path = $base . '/admin/css/admin-banners.css';
  if (file_exists($banner_path)) {
    wp_register_style(
      'tmw-admin-banner-style',
      $uri . '/admin/css/admin-banners.css',
      [],
      filemtime($banner_path) ?: null
    );
  }

  $align_path = $base . '/admin/css/tmw-banner-admin.css';
  if (file_exists($align_path)) {
    wp_register_style(
      'tmw-banner-admin-align',
      $uri . '/admin/css/tmw-banner-admin.css',
      [],
      filemtime($align_path) ?: null
    );
  }
});

add_action('admin_enqueue_scripts', function ($hook) {
  $screen = function_exists('get_current_screen') ? get_current_screen() : null;

  $should_enqueue = false;

  if ($hook === 'term.php' && $screen && $screen->taxonomy === 'models') {
    $should_enqueue = true;
  } elseif (($hook === 'post.php' || $hook === 'post-new.php') && $screen && ($screen->post_type ?? '') === 'model') {
    $should_enqueue = true;
  }

  if (!$should_enqueue) {
    return;
  }

  if (wp_style_is('tmw-admin-banner-style', 'registered')) {
    wp_enqueue_style('tmw-admin-banner-style');
  }

  if (wp_style_is('tmw-banner-admin-align', 'registered')) {
    wp_enqueue_style('tmw-banner-admin-align');
  }

  if ($hook === 'post.php' || $hook === 'post-new.php') {
    $script_path = get_stylesheet_directory() . '/js/tmw-model-banner-metabox.js';
    if (file_exists($script_path)) {
      wp_enqueue_media();
      wp_enqueue_script(
        'tmw-model-banner-metabox',
        get_stylesheet_directory_uri() . '/js/tmw-model-banner-metabox.js',
        ['jquery', 'wp-data'],
        (string) filemtime($script_path),
        true
      );
    }
  }
});
