<?php
if (!defined('ABSPATH')) { exit; }

if (!function_exists('tmw_cat_hub_log_once')) {
  function tmw_cat_hub_log_once(string $key, string $message): void {
    if (!defined('WP_DEBUG') || !WP_DEBUG) { return; }
    static $seen = [];
    if (isset($seen[$key])) { return; }
    $seen[$key] = true;
    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-CAT-HUB] ' . $message); }
  }
}

if (!function_exists('tmw_cat_hub_audit_log')) {
  function tmw_cat_hub_audit_log(string $message): void {
    if (!defined('WP_DEBUG') || !WP_DEBUG) { return; }
    if (defined('WP_DEBUG') && WP_DEBUG) { error_log('[TMW-CAT-HUB-PAG-AUDIT] ' . $message); }
  }
}

if (!function_exists('tmw_cat_hub_audit_value')) {
  function tmw_cat_hub_audit_value($value): string {
    if (is_bool($value)) { return $value ? 'true' : 'false'; }
    if ($value === null) { return 'null'; }
    if (is_array($value)) { return wp_json_encode($value); }
    return (string) $value;
  }
}

add_action('pre_get_posts', function ($query) {
  if (!($query instanceof WP_Query)) { return; }
  if (is_admin() || !$query->is_main_query()) { return; }
  if (!$query->is_category()) { return; }

  $cat_term = get_queried_object();
  if (!$cat_term instanceof WP_Term || $cat_term->taxonomy !== 'category') { return; }

  // Match tag by same slug (category hub slug == tag slug).
  $tag_term = get_term_by('slug', $cat_term->slug, 'post_tag');
  if (!$tag_term instanceof WP_Term) {
    tmw_cat_hub_log_once('no_tag_' . $cat_term->term_id, 'No matching tag for category slug="' . $cat_term->slug . '". Default category query kept.');
    return;
  }

  tmw_cat_hub_audit_log(
    'uri=' . tmw_cat_hub_audit_value($_SERVER['REQUEST_URI'] ?? '')
    . ' paged=' . tmw_cat_hub_audit_value($query->get('paged'))
    . ' qv_paged=' . tmw_cat_hub_audit_value(get_query_var('paged'))
    . ' qv_page=' . tmw_cat_hub_audit_value(get_query_var('page'))
    . ' offset=' . tmw_cat_hub_audit_value($query->get('offset'))
    . ' posts_per_page=' . tmw_cat_hub_audit_value($query->get('posts_per_page'))
    . ' is_paged=' . tmw_cat_hub_audit_value($query->is_paged())
  );

  add_filter('posts_request', function (string $sql, WP_Query $filter_query) use ($query): string {
    if (!defined('WP_DEBUG') || !WP_DEBUG) { return $sql; }
    if ($filter_query !== $query) { return $sql; }
    $limit = 'none';
    if (preg_match('/\\sLIMIT\\s+(.+)$/i', $sql, $matches)) {
      $limit = trim($matches[1]);
    }
    tmw_cat_hub_audit_log('limit=' . $limit);
    return $sql;
  }, 10, 2);

  add_filter('the_posts', function (array $posts, WP_Query $filter_query) use ($query): array {
    if (!defined('WP_DEBUG') || !WP_DEBUG) { return $posts; }
    if ($filter_query !== $query) { return $posts; }
    $ids = array_slice(wp_list_pluck($posts, 'ID'), 0, 10);
    $ids_log = $ids ? implode(',', $ids) : 'none';
    tmw_cat_hub_audit_log('count=' . count($posts) . ' ids=' . $ids_log);
    return $posts;
  }, 10, 2);

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
