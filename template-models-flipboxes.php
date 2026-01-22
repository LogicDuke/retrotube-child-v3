<?php
/**
 * Template Name: Models Flipboxes (with Sidebar)
 * Description: Displays an Actors flipbox grid with pagination, sidebar, and SEO accordion.
 *
 * @package RetrotubeChild
 * @version 2.3.0
 */

// Disable FEATURED MODELS injection on this page
$GLOBALS['tmw_featured_models_disabled'] = true;

get_header();

// Get the page content for the SEO accordion - MUST be inside the loop or use post ID
$page_content = '';
if (have_posts()) {
    while (have_posts()) {
        the_post();
        $page_content = get_the_content();
        $page_content = apply_filters('the_content', $page_content);
        $page_content = str_replace(']]>', ']]&gt;', $page_content);
    }
    wp_reset_postdata();
}

// Sanitize accordion HTML with a WordPress-native allowlist.
$page_content = trim($page_content);
$page_content = tmw_sanitize_accordion_html($page_content);
?>
<main id="primary" class="site-main">
  <div class="tmw-layout container">
    <section class="tmw-content" data-mobile-guard="true">
      <header class="entry-header">
        <h1 class="page-title">
          <?php echo is_front_page() ? '★ Top Models' : get_the_title(); ?>
        </h1>
      </header>
      
      <?php if (!empty($page_content)) : ?>
        <div class="tmw-accordion">
          <div id="tmw-seo-desc" class="tmw-accordion-content tmw-accordion-collapsed" data-tmw-accordion-lines="1">
            <?php echo $page_content; ?>
          </div>
          <div class="tmw-accordion-toggle-wrap">
            <a id="tmw-seo-toggle" class="tmw-accordion-toggle" href="javascript:void(0);" data-tmw-accordion-toggle>
              <span class="tmw-accordion-text"><?php esc_html_e('Read more', 'retrotube-child'); ?></span>
              <i class="fa fa-chevron-down"></i>
            </a>
          </div>
        </div>
      <?php endif; ?>
      
      <?php
      // Models flipbox grid
      add_filter('tmw_model_flipbox_link', 'tmw_flipbox_link_guard_filter', 10, 2);
      echo tmw_models_flipboxes_cb([
        'per_page'        => 16,
        'cols'            => 4,
        'show_pagination' => true,
      ]);
      remove_filter('tmw_model_flipbox_link', 'tmw_flipbox_link_guard_filter', 10, 2);
      ?>
    </section>
    <aside class="tmw-sidebar">
      <?php get_sidebar(); ?>
    </aside>
  </div>
</main>
<?php get_footer(); ?>
