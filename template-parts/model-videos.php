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

      // Calculate rating data for this video.
      $vid_id         = (int) $video_post->ID;
      $vid_likes      = function_exists('tmw_get_post_likes_count')
          ? tmw_get_post_likes_count($vid_id)
          : (int) get_post_meta($vid_id, 'likes_count', true);
      $vid_dislikes   = function_exists('tmw_get_post_dislikes_count')
          ? tmw_get_post_dislikes_count($vid_id)
          : (int) get_post_meta($vid_id, 'dislikes_count', true);
      $vid_likes      = is_numeric($vid_likes) ? (int) $vid_likes : 0;
      $vid_dislikes   = is_numeric($vid_dislikes) ? (int) $vid_dislikes : 0;
      $vid_total      = $vid_likes + $vid_dislikes;
      $vid_percent    = ($vid_total > 0) ? round(($vid_likes / $vid_total) * 100, 0) : 0;

      // Wrap in .tmw-model-video-item so child-theme CSS can target it.
      echo '<div class="tmw-model-video-item">';

      // Capture parent loop-video output so we can inject the rating bar inside .thumb-block.
      ob_start();
      get_template_part('template-parts/loop', 'video');
      $card_html = ob_get_clean();

      // Inject rating-col HTML just before the closing </article> of the thumb-block card.
      $rating_html  = '<div class="rating-col">';
      $rating_html .= '<div class="rating-bar"><div class="rating-bar-meter" style="width: ' . esc_attr($vid_percent) . '%;"></div></div>';
      $rating_html .= '<div class="rating-result">';
      $rating_html .= '<div class="percentage">' . esc_html($vid_percent) . '%</div>';
      $rating_html .= '</div>';
      $rating_html .= '</div>';

      // Try to insert inside .thumb-block (before closing </article>), fall back to appending.
      $insertion_pos = strrpos($card_html, '</article>');
      if ($insertion_pos !== false) {
          $card_html = substr_replace($card_html, $rating_html, $insertion_pos, 0);
      } else {
          $card_html .= $rating_html;
      }

      echo $card_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
      echo '</div>';
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
