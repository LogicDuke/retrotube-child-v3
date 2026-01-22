<?php
/**
 * Models taxonomy archive.
 * Displays the flipbox grid, matching the Models Grid page layout.
 */

get_header();
$tmw_video_widget_class = class_exists('TMW_WP_Widget_Videos_Block_Fixed') ? 'TMW_WP_Widget_Videos_Block_Fixed' : 'wpst_WP_Widget_Videos_Block';
?>
<div class="tmw-title">
  <span class="tmw-star">★</span>
  <h1 class="tmw-title-text">Models</h1>
</div>
<div class="tmw-layout">
  <main id="primary" class="site-main" data-mobile-guard="true">
    <?php get_template_part('breadcrumb'); ?>
    <h2 class="widget-title">Videos Featuring <?php echo single_term_title('', false); ?></h2>
    <?php
    the_widget(
        $tmw_video_widget_class,
        array(
            'title'          => sprintf(__('Videos Featuring %s', 'retrotube-child'), single_term_title('', false)),
            'video_type'     => 'model',
            'video_number'   => 12,
            'video_category' => 0,
        ),
        array(
            'before_widget' => '<section class="widget widget_videos_block">',
            'after_widget'  => '</section>',
            'before_title'  => '<h2 class="widget-title">',
            'after_title'   => '</h2>',
        )
    );
    ?>
    <?php
    echo tmw_models_flipboxes_cb([
      'per_page'        => 12,
      'cols'            => 3,
      'show_pagination' => true,
      'page_var'        => 'pg',
    ]);
    ?>
  </main>
  <?php get_sidebar(); ?>
</div>
<?php get_footer(); ?>
