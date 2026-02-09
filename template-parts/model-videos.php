<?php
/**
 * Template Part: Model Videos Section — Hybrid Scan Query
 */

if (!defined('ABSPATH')) {
    exit;
}

global $post;

if (!$post instanceof WP_Post) {
    return;
}

$model_name = get_the_title($post->ID);
$model_slug = get_post_field('post_name', $post->ID);

if (empty($model_slug)) {
    return;
}

$videos = get_query_var('tmw_model_videos', null);

if (!is_array($videos)) {
    $videos = tmw_get_videos_for_model($model_slug);
}

$video_count = is_array($videos) ? count($videos) : 0;

if ($video_count === 0) {
    return;
}

$original_post = $post;
?>
<section class="tmw-model-videos videos-featuring widget widget_videos_block">
  <h3 class="tmw-section-header widget-title"><?php esc_html_e('Videos Featuring', 'retrotube'); ?> <?php echo esc_html($model_name); ?></h3>
  <div class="videos-list">
    <?php
    foreach ($videos as $video_post) :
      if (!$video_post instanceof WP_Post) {
        continue;
      }

      $post = $video_post;
      setup_postdata($post);
      get_template_part('template-parts/loop', 'video');
    endforeach;
    ?>
  </div>
</section>
<?php
wp_reset_postdata();

if ($original_post instanceof WP_Post) {
    $post = $original_post;
}
?>
