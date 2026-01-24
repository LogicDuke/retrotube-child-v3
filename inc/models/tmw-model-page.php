<?php

/* ======================================================================
 * MODEL PAGE – VIRTUAL TEMPLATE (NO HERO)
 * ====================================================================== */
add_action('widgets_init', function () {
  register_sidebar([
    'name'          => __('Model Sidebar', 'retrotube-child'),
    'id'            => 'model-sidebar',
    'description'   => __('Widgets on single model pages', 'retrotube-child'),
    'before_widget' => '<section class="widget %2$s">',
    'after_widget'  => '</section>',
    'before_title'  => '<h2 class="widget-title">',
    'after_title'   => '</h2>',
  ]);
});

add_action('template_redirect', function(){
  if (!is_tax('models')) return;

  $term    = get_queried_object();
  $term_id = isset($term->term_id) ? (int)$term->term_id : 0;
  $acf_id  = 'models_' . $term_id;

  // Data
  $bio         = function_exists('get_field') ? (get_field('bio', $acf_id) ?: '') : '';
  $read_lines  = function_exists('get_field') ? (int) (get_field('readmore_lines', $acf_id) ?: 20) : 20;
  $featured_sc = function_exists('get_field') ? (get_field('featured_models_shortcode', $acf_id) ?: '[tmw_featured_models count="4"]') : '[tmw_featured_models count="4"]';

  $banner_src  = function_exists('tmw_resolve_model_banner_url') ? tmw_resolve_model_banner_url(0, $term_id) : '';
  $bx          = function_exists('get_field') ? (float)(get_field('banner_offset_x', $acf_id) ?: 0) : 0;
  $by          = function_exists('get_field') ? (float)(get_field('banner_offset_y', $acf_id) ?: 0) : 0;
  $banner_h    = 350;

  $pos_x = max(0, min(100, 50 + $bx));
  $offset_y = (int) round($by);
  if ($offset_y < -1000) {
    $offset_y = -1000;
  }
  if ($offset_y > 1000) {
    $offset_y = 1000;
  }
  get_header();
  ?>
  <div class="tmw-model-page">
    <div class="container tmw-model-grid">
      <main class="tmw-model-main">
        <?php if ($banner_src): ?>
          <?php
          $focal_y = function_exists('tmw_offset_to_focal_percent')
            ? tmw_offset_to_focal_percent($offset_y, $banner_h)
            : 50;
          $object_style = function_exists('tmw_get_banner_style')
            ? tmw_get_banner_style($offset_y, $banner_h, ['pos_x' => $pos_x])
            : sprintf('object-position: %s%% %s%%;', $pos_x, $focal_y);
          ?>
          <div class="tmw-model-banner">
            <div class="tmw-model-banner-frame tmw-banner-frame">
              <img src="<?php echo esc_url($banner_src); ?>" alt="<?php echo esc_attr($term->name); ?>" style="<?php echo esc_attr($object_style); ?>" />
            </div>
          </div>
        <?php endif; ?>

        <h1 class="tmw-model-title"><?php echo esc_html($term->name); ?></h1>

        <div class="tmw-accordion tmw-bio-wrap">
          <div id="tmw-bio" class="tmw-accordion-content tmw-accordion-collapsed" data-tmw-accordion-lines="<?php echo (int) $read_lines; ?>">
            <?php
              if ($bio) echo wpautop($bio);
              else echo '<p>'.esc_html__('No biography provided yet.','retrotube-child').'</p>';
            ?>
          </div>
          <?php if ($bio): ?>
            <div class="tmw-accordion-toggle-wrap">
              <button class="tmw-accordion-toggle" type="button" data-tmw-accordion-toggle aria-controls="tmw-bio">
                <span class="tmw-accordion-text"><?php esc_html_e('Read more','retrotube-child'); ?></span>
                <i class="fa fa-chevron-down"></i>
              </button>
            </div>
          <?php endif; ?>
        </div>

        <section class="tmw-featured-flipboxes">
          <?php echo do_shortcode($featured_sc); ?>
        </section>
      </main>

      <aside class="tmw-model-sidebar">
        <?php
          if (is_active_sidebar('model-sidebar')) { dynamic_sidebar('model-sidebar'); }
          else { get_sidebar(); }
        ?>
      </aside>
    </div>
  </div>
  <?php
  get_footer();
  exit;
});
