<?php

/* ======================================================================
 * ONE-TIME MIGRATIONS
 * ====================================================================== */
/**
 * Move all legacy model_bio posts into the model CPT.
 */
if (!function_exists('tmw_should_skip_heavy_runtime')) {
  function tmw_should_skip_heavy_runtime(): bool
  {
    if ((defined('DOING_CRON') && DOING_CRON) || wp_doing_ajax()) {
      return true;
    }

    if (is_admin()) {
      return true;
    }

    return false;
  }
}

if (!function_exists('tmw_maybe_run_db_migration')) {
  function tmw_maybe_run_db_migration(): void
  {
    if (tmw_should_skip_heavy_runtime()) {
      return;
    }

    if (get_option('tmw_db_version') === TMW_DB_VERSION) {
      return;
    }

    global $wpdb;

    $wpdb->query("UPDATE {$wpdb->posts} SET post_type = 'model' WHERE post_type = 'model_bio'");

    update_option('tmw_migrated_model_bio', 1);
    flush_rewrite_rules();
    update_option('tmw_db_version', TMW_DB_VERSION);
  }
}

add_action('init', 'tmw_maybe_run_db_migration', 5);

/* ======================================================================
 * LEGACY CPT CLEANUP
 * ====================================================================== */
add_action('init', function(){
  if (tmw_should_skip_heavy_runtime()) return;

  global $wp_post_types;
  if (isset($wp_post_types['model_bio'])) {
    unset($wp_post_types['model_bio']);
  }
}, 20);

/* ======================================================================
 * MODEL CPT NORMALIZATION
 * ====================================================================== */
/**
 * Normalize 'model' CPT so breadcrumbs are correct.
 * Works even if CPT is registered by parent theme or plugin.
 */
add_filter('register_post_type_args', function ($args, $post_type) {
  if ($post_type !== 'model') return $args;

  // Labels used by theme breadcrumbs
  $args['labels']                 = isset($args['labels']) ? $args['labels'] : [];
  $args['labels']['name']         = 'Models';
  $args['labels']['menu_name']    = 'Models';
  $args['labels']['singular_name'] = isset($args['labels']['singular_name']) ? $args['labels']['singular_name'] : 'Model';
  $args['labels']['archives']     = 'Models';

  // Archive should be /models/
  // Singles should remain /model/%postname%/
  $args['has_archive'] = 'models';
  $args['rewrite'] = [
    'slug'       => 'model',
    'with_front' => false,
  ];

  // Ensure public so archive link is generated
  $args['public'] = true;

  return $args;
}, 10, 2);

/**
 * One-time flush so the new /models/ archive starts working immediately.
 */
add_action('init', function () {
  if (tmw_should_skip_heavy_runtime()) return;
  if (get_option('tmw_flushed_cpt_rewrites_models')) return;
  flush_rewrite_rules();
  update_option('tmw_flushed_cpt_rewrites_models', 1);
});

/**
 * Redirect legacy /model/ archive to /models/
 */
add_action('template_redirect', function () {
  $req = isset($_SERVER['REQUEST_URI']) ? trailingslashit(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) : '';
  if ($req === '/model/' && !is_singular('model')) {
    wp_redirect(home_url('/models/'), 301);
    exit;
  }
});

/**
 * Add "Edit Models Page" link to admin bar when viewing /models/.
 */
add_action('admin_bar_menu', function ($admin_bar) {
  if (!is_post_type_archive('model') || !current_user_can('edit_pages')) return;

  $models_page = get_page_by_path('models');
  if (!$models_page instanceof WP_Post) return;

  $admin_bar->add_menu([
    'id'    => 'edit-models-page',
    'title' => 'Edit Models Page',
    'href'  => get_edit_post_link($models_page->ID),
  ]);
}, 100);
