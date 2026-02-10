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

      // === Video data ===
      $vid_id       = (int) $video_post->ID;
      $vid_link     = get_permalink($vid_id);
      $vid_title    = get_the_title($vid_id);

      // Thumbnail: featured image → meta 'thumb' → empty.
      $vid_thumb = '';
      if (has_post_thumbnail($vid_id)) {
          $thumb_arr = wp_get_attachment_image_src(get_post_thumbnail_id($vid_id), 'wpst_thumb_large');
          if ($thumb_arr) {
              $vid_thumb = $thumb_arr[0];
          }
      }
      if (!$vid_thumb) {
          $vid_thumb = get_post_meta($vid_id, 'thumb', true);
      }

      // Rating.
      $vid_likes    = function_exists('tmw_get_post_likes_count')
          ? tmw_get_post_likes_count($vid_id)
          : (int) get_post_meta($vid_id, 'likes_count', true);
      $vid_dislikes = function_exists('tmw_get_post_dislikes_count')
          ? tmw_get_post_dislikes_count($vid_id)
          : (int) get_post_meta($vid_id, 'dislikes_count', true);
      $vid_likes    = is_numeric($vid_likes) ? (int) $vid_likes : 0;
      $vid_dislikes = is_numeric($vid_dislikes) ? (int) $vid_dislikes : 0;
      $vid_total    = $vid_likes + $vid_dislikes;
      $vid_percent  = ($vid_total > 0) ? round(($vid_likes / $vid_total) * 100, 0) : 0;
      ?>
      <article class="thumb-block tmw-model-video-item">
        <a href="<?php echo esc_url($vid_link); ?>" title="<?php echo esc_attr($vid_title); ?>">
          <?php if ($vid_thumb) : ?>
            <img src="<?php echo esc_url($vid_thumb); ?>" alt="<?php echo esc_attr($vid_title); ?>" loading="lazy" decoding="async" />
          <?php endif; ?>
        </a>
        <div class="tmw-vid-rating">
          <div class="rating-bar"><div class="rating-bar-meter" style="width: <?php echo esc_attr($vid_percent); ?>%;"></div></div>
          <div class="rating-result"><div class="percentage"><i class="fa fa-thumbs-up" aria-hidden="true"></i> <?php echo esc_html($vid_percent); ?>%</div></div>
        </div>
        <header class="entry-header">
          <h2 class="entry-title"><a href="<?php echo esc_url($vid_link); ?>"><?php echo esc_html($vid_title); ?></a></h2>
        </header>
      </article>
      <?php
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
