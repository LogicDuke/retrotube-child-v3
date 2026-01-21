<?php
/**
 * Archive template for Models CPT
 * With SEO text accordion - pulls content from the "models" page editor.
 *
 * @package RetrotubeChild
 * @version 2.5.0
 */

// Disable FEATURED MODELS injection on this page
$GLOBALS['tmw_featured_models_disabled'] = true;

get_header();

// === GET SEO TEXT FROM THE WORDPRESS PAGE EDITOR ===
// Get the "models" page content (the page you edit in wp-admin)
$seo_text = '';
$models_page = get_page_by_path('models');

if ($models_page) {
    // Get the raw content from the page
    $seo_text = $models_page->post_content;
    
    // Apply content filters (processes shortcodes, etc.)
    $seo_text = apply_filters('the_content', $seo_text);
    
    // Sanitize accordion HTML with a WordPress-native allowlist.
    $seo_text = trim($seo_text);
    $seo_text = tmw_sanitize_accordion_html($seo_text);
}
?>
<main id="primary" class="site-main">
  <div class="tmw-layout container">
    <section class="tmw-content" data-mobile-guard="true">
      <header class="entry-header tmw-models-archive-header">
        <h1 class="widget-title"><span class="tmw-star">★</span> Top Models</h1>
      </header>
      
      <?php if (!empty($seo_text)) : ?>
        <!-- SEO Text Accordion - Uses global TMW accordion styling -->
        <div class="tmw-accordion" id="tmw-models-accordion">
          <div class="tmw-accordion-content tmw-accordion-collapsed" id="tmw-seo-desc">
            <?php echo $seo_text; ?>
          </div>
          <div class="tmw-accordion-toggle-wrap">
            <a class="tmw-accordion-toggle" href="javascript:void(0);" data-tmw-accordion-toggle>
              <span class="tmw-accordion-text"><?php esc_html_e('Read more', 'retrotube-child'); ?></span>
              <i class="fa fa-chevron-down"></i>
            </a>
          </div>
        </div>
      <?php endif; ?>
      
      <?php
      // Models flipbox grid - NO featured models
      add_filter('tmw_model_flipbox_link', 'tmw_flipbox_link_guard_filter', 10, 2);
      echo do_shortcode('[actors_flipboxes per_page="16" cols="4" show_pagination="true"]');
      remove_filter('tmw_model_flipbox_link', 'tmw_flipbox_link_guard_filter', 10, 2);
      ?>
    </section>
    <aside class="tmw-sidebar">
      <?php get_sidebar(); ?>
    </aside>
  </div>
</main>
<?php get_footer(); ?>
