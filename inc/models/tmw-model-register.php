<?php

/* ======================================================================
 * MODELS CUSTOM POST TYPE
 * ====================================================================== */
add_action('init', function () {
  $labels = [
    'name'                  => esc_html__('Models', 'retrotube-child'),
    'singular_name'         => esc_html__('Model', 'retrotube-child'),
    'menu_name'             => esc_html__('Models', 'retrotube-child'),
    'name_admin_bar'        => __('Model', 'retrotube-child'),
    'add_new'               => __('Add New', 'retrotube-child'),
    'add_new_item'          => __('Add New Model', 'retrotube-child'),
    'new_item'              => __('New Model', 'retrotube-child'),
    'edit_item'             => __('Edit Model', 'retrotube-child'),
    'view_item'             => __('View Model', 'retrotube-child'),
    'all_items'             => __('All Models', 'retrotube-child'),
    'search_items'          => __('Search Models', 'retrotube-child'),
    'parent_item_colon'     => __('Parent Models:', 'retrotube-child'),
    'not_found'             => __('No models found.', 'retrotube-child'),
    'not_found_in_trash'    => __('No models found in Trash.', 'retrotube-child'),
    'items_list'            => __('Models list', 'retrotube-child'),
    'items_list_navigation' => __('Models list navigation', 'retrotube-child'),
  ];

  $args = [
    'labels'             => $labels,
    'public'             => true,
    'publicly_queryable' => true,
    'show_ui'            => true,
    'show_in_menu'       => true,
    'show_in_rest'       => true,
    'has_archive'        => 'models',
    'rewrite'            => ['slug' => 'model', 'with_front' => false],
    'hierarchical'       => false,
    'supports'           => ['title', 'editor', 'thumbnail', 'comments'],
    'taxonomies'         => ['category', 'post_tag'],
    'menu_icon'          => 'dashicons-groups',
    'capability_type'    => 'post',
    'map_meta_cap'       => true,
  ];

  register_post_type('model', $args);
}, 5);

add_filter('rank_math/post_types', function ($post_types) {
  if (!is_array($post_types)) return $post_types;
  if (!in_array('model', $post_types, true)) {
    $post_types[] = 'model';
  }
  return $post_types;
});

/* ======================================================================
 * BREADCRUMBS (Rank Math)
 * ====================================================================== */
add_filter('rank_math/frontend/breadcrumb/items', function ($crumbs) {
  if (!function_exists('rank_math_the_breadcrumbs')) {
    return $crumbs;
  }

  $models_url = home_url('/models/');

  if (is_post_type_archive('model') || is_post_type_archive('model_bio')) {
    $crumbs = [
      ['label' => 'Home', 'url' => home_url('/')],
      ['label' => 'Models', 'url' => $models_url],
    ];
  } elseif (is_singular('model') || is_singular('model_bio')) {
    $crumbs = [
      ['label' => 'Home', 'url' => home_url('/')],
      ['label' => 'Models', 'url' => $models_url],
      ['label' => get_the_title(), 'url' => ''],
    ];
  }

  foreach ($crumbs as $key => $crumb) {
    if (!is_array($crumb) || !isset($crumb['label'])) {
      continue;
    }

    $label = strtolower(trim((string) $crumb['label']));
    if ($label === 'model' || $label === 'model bio') {
      $crumbs[$key]['label'] = 'Models';
      $crumbs[$key]['title'] = 'Models';
      $crumbs[$key]['url']   = $models_url;

      return $crumbs;
    }
  }

  return $crumbs;
});

/* ======================================================================
 * MODELS TAXONOMY (new internal slug) + redirects from old /actor/*
 * - Public URLs: /model/{term}/
 * - Keeps old /actor/* and /actors/* working with 301 to the new URL.
 * - Flushes permalinks once.
 * ====================================================================== */
if (!defined('TMW_TAX_SLUG')) define('TMW_TAX_SLUG', 'models');   // taxonomy key
if (!defined('TMW_URL_SLUG')) define('TMW_URL_SLUG', 'model-tag'); // public URL base (no CPT collision)

add_action('init', function () {
  $labels = [
    'name'                       => 'Models',
    'singular_name'              => 'Model',
    'menu_name'                  => 'Models',
    'all_items'                  => 'All Models',
    'edit_item'                  => 'Edit Model',
    'view_item'                  => 'View Model',
    'update_item'                => 'Update Model',
    'add_new_item'               => 'Add New Model',
    'new_item_name'              => 'New Model Name',
    'parent_item'                => 'Parent Model',
    'search_items'               => 'Search Models',
    'popular_items'              => 'Popular Models',
    'separate_items_with_commas' => 'Separate models with commas',
    'add_or_remove_items'        => 'Add or remove models',
    'choose_from_most_used'      => 'Choose from the most used models',
    'not_found'                  => 'No models found',
    'back_to_items'              => '← Back to Models',
  ];

  register_taxonomy(TMW_TAX_SLUG, ['model'], [
    'labels'            => $labels,
    'public'            => true,
    'show_ui'           => true,
    'show_admin_column' => true,
    'hierarchical'      => false,
    'query_var'         => TMW_TAX_SLUG,
    'rewrite'           => ['slug' => TMW_URL_SLUG, 'with_front' => false, 'hierarchical' => false],
    'show_in_rest'      => true,
    'rest_base'         => TMW_TAX_SLUG,
  ]);
}, 5);

add_action('init', function () {
  add_rewrite_rule('^actor/([^/]+)/?$',  'index.php?' . TMW_TAX_SLUG . '=$matches[1]', 'top');
  add_rewrite_rule('^actors/([^/]+)/?$', 'index.php?' . TMW_TAX_SLUG . '=$matches[1]', 'top');

  if (!get_option('tmw_models_flush_v3')) {
    flush_rewrite_rules(false);
    update_option('tmw_models_flush_v3', 1);
  }
}, 20);

// --- Force all single model pages to use single-model.php ---
add_filter('template_include', function($template) {
  if (is_singular('model')) {
    $child_template = get_stylesheet_directory() . '/single-model.php';
    if (file_exists($child_template)) {
      return $child_template;
    }
  }
  return $template;
}, 999);

add_action('template_redirect', function () {
  if (is_tax(TMW_TAX_SLUG)) {
    $term = get_queried_object();
    if (!is_wp_error($term) && !empty($term->slug)) {
      $maybe = get_page_by_path($term->slug, OBJECT, 'model');
      if ($maybe) {
        $to = get_permalink($maybe);
        wp_redirect($to, 301);
        exit;
      }
    }
  }
});

add_action('after_switch_theme', function () {
  flush_rewrite_rules();
});

// 1) Always show a Model/Models line on single video pages.
// Prefers your 'models' taxonomy; falls back to legacy 'actors'.
add_filter('the_content', function ($content) {
  if ( ! is_singular(['post','video']) || ! in_the_loop() || ! is_main_query() ) return $content;

  // If the theme already prints its own block, do nothing.
  if (strpos($content, 'id="video-actors"') !== false ||
      strpos($content, 'id="video-models"') !== false) {
    return $content;
  }

  $post_id = get_the_ID();
  $terms = get_the_terms($post_id, 'models');
  if (empty($terms) || is_wp_error($terms)) {
    $terms = get_the_terms($post_id, 'actors');
  }
  if (empty($terms) || is_wp_error($terms)) return $content;

  $links = [];
  foreach ($terms as $t) {
    $model_link = tmw_get_model_link_for_term($t);
    if (!$model_link) {
      $fallback = get_term_link($t);
      $model_link = is_wp_error($fallback) ? '' : $fallback;
    }
    if ($model_link) {
      $links[] = sprintf('<a href="%s">%s</a>', esc_url($model_link), esc_html($t->name));
    }
  }

  // singular when one model, plural otherwise
  $label = (count($terms) === 1) ? 'Model' : 'Models';

  $block = '<div id="video-models"><i class="fa fa-star"></i> ' .
           $label . ': ' . implode(', ', $links) . '</div>';

  // Place it right under the date/meta if present; otherwise prepend.
  if (preg_match('~(<div[^>]+id="video-date"[^>]*>.*?</div>)~is', $content)) {
    $content = preg_replace('~(<div[^>]+id="video-date"[^>]*>.*?</div>)~is', '$1' . $block, $content, 1);
  } else {
    $content = $block . $content;
  }
  return $content;
}, 45);

// 2) Keep old ‘actors’ and new ‘models’ in sync on save.
add_action('save_post', function ($post_id) {
  if (wp_is_post_revision($post_id)) return;
  $pt = get_post_type($post_id);
  if ($pt !== 'post' && $pt !== 'video') return;

  if (!taxonomy_exists('models') || !is_object_in_taxonomy($pt, 'models')) {
    return;
  }

  $actors = wp_get_post_terms($post_id, 'actors', ['fields' => 'ids']);
  $models = wp_get_post_terms($post_id, 'models', ['fields' => 'ids']);

  // If plugin set actors, mirror to models
  if (!empty($actors) && !is_wp_error($actors)) {
    wp_set_post_terms($post_id, $actors, 'models', false);
  }
  // If you ever tag only in models, mirror to actors too
  if (!empty($models) && !is_wp_error($models)) {
    wp_set_post_terms($post_id, $models, 'actors', false);
  }
}, 20);
