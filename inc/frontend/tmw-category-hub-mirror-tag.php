<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_cat_hub_log_once')) {
  function tmw_cat_hub_log_once(string $key, string $message): void {
    if (!defined('WP_DEBUG') || !WP_DEBUG) { return; }
    static $seen = [];
    if (isset($seen[$key])) { return; }
    $seen[$key] = true;
    error_log('[TMW-CAT-HUB] ' . $message);
  }
}

add_action('pre_get_posts', function ($query) {
  if (is_admin() || wp_doing_ajax() || !($query instanceof WP_Query)) { return; }
  if (!$query->is_main_query() || !$query->is_category()) { return; }

  $cat_term = get_queried_object();
  if (!$cat_term instanceof WP_Term || $cat_term->taxonomy !== 'category') { return; }

  // Match tag by same slug (category hub slug == tag slug).
  $tag_term = get_term_by('slug', $cat_term->slug, 'post_tag');
  if (!$tag_term instanceof WP_Term) {
    tmw_cat_hub_log_once('no_tag_' . $cat_term->term_id, 'No matching tag for category slug="' . $cat_term->slug . '". Default category query kept.');
    return;
  }

  // CRITICAL: remove native category vars so WP doesn't force an implicit AND.
  $query->set('cat', 0);
  $query->set('category_name', '');
  $query->set('category__in', []);
  $query->set('category__and', []);
  $query->set('category__not_in', []);

  // Avoid any parent/theme filters restricting category archives differently than tags.
  $query->set('post_type', 'any');
  $query->set('ignore_sticky_posts', 1);

  $mirror_tax_query = [
    'relation' => 'OR',
    [
      'taxonomy' => 'category',
      'field'    => 'term_id',
      'terms'    => [(int) $cat_term->term_id],
    ],
    [
      'taxonomy' => 'post_tag',
      'field'    => 'term_id',
      'terms'    => [(int) $tag_term->term_id],
    ],
  ];

  $query->set('tax_query', $mirror_tax_query);

  // Preserve the category context for titles/descriptions/breadcrumbs.
  $query->is_category = true;
  $query->is_archive  = true;
  $query->queried_object = $cat_term;
  $query->queried_object_id = (int) $cat_term->term_id;

  tmw_cat_hub_log_once(
    'applied_' . $cat_term->term_id,
    'Applied mirror for /category/' . $cat_term->slug . '/ using tag_term_id=' . (int) $tag_term->term_id
  );
}, 50);
