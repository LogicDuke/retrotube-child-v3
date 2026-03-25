<?php
/**
 * Template Part: Model Videos Section — Hybrid Scan Query
 * Card markup matches parent theme sidebar widget (loop-video thumb-block)
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
    $videos = function_exists('tmw_get_videos_for_model') ? tmw_get_videos_for_model($model_slug) : [];
}

$video_count = is_array($videos) ? count($videos) : 0;

if ($video_count === 0) {
    $fallback_query = new WP_Query([
        'post_type'      => ['post', 'video'],
        'posts_per_page' => 12,
        's'              => $model_name,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ]);

    if ($fallback_query->have_posts()) {
        $videos = $fallback_query->posts;
        $video_count = count($videos);
    }

    wp_reset_postdata();
}

if ($video_count === 0) {
    return;
}

// Store resolved videos globally so tag section can access them.
global $tmw_resolved_model_videos;
$tmw_resolved_model_videos = $videos;

$original_post = $post;

/**
 * Format raw seconds (e.g. "341") into MM:SS or HH:MM:SS.
 */
if (!function_exists('tmw_format_duration_display')) {
    function tmw_format_duration_display($raw) {
        $raw = trim($raw);
        if (strpos($raw, ':') !== false) {
            return $raw;
        }
        $secs = (int) $raw;
        if ($secs <= 0) {
            return $raw;
        }
        $h = floor($secs / 3600);
        $m = floor(($secs % 3600) / 60);
        $s = $secs % 60;
        if ($h > 0) {
            return sprintf('%d:%02d:%02d', $h, $m, $s);
        }
        return sprintf('%02d:%02d', $m, $s);
    }
}
?>
<section class="tmw-model-videos videos-featuring widget widget_videos_block">
  <h3 class="tmw-section-header widget-title"><?php esc_html_e('Videos Featuring', 'retrotube'); ?> <?php echo esc_html($model_name); ?></h3>
  <div class="videos-list">
    <?php
    $tmw_vid_uid = 100;
    foreach ($videos as $video_post) :
      if (!$video_post instanceof WP_Post) {
        continue;
      }

      $post = $video_post;
      setup_postdata($post);

      $permalink = get_permalink($post);
      $title     = get_the_title($post);
      $title     = is_string($title) ? trim($title) : '';
      if ($title === '' || preg_match('/^\d+$/', $title)) {
        $title = sprintf(__('Video featuring %s', 'retrotube'), $model_name);
      }
      $video_id = get_the_ID();

      // Thumbnail URL.
      $thumb_url = get_the_post_thumbnail_url($post, 'wpst_thumb_large');
      if (empty($thumb_url)) {
        $thumb_url = get_post_meta($post->ID, 'thumb', true);
      }

      // Duration.
      $duration_raw = get_post_meta($post->ID, 'duration', true);
      $duration_raw = is_string($duration_raw) ? trim($duration_raw) : '';
      $duration_display = tmw_format_duration_display($duration_raw);

      // Views.
      $views = function_exists('tmw_get_post_views_count')
          ? tmw_get_post_views_count($video_id)
          : (int) get_post_meta($video_id, 'post_views_count', true);
      $views = is_numeric($views) ? (int) $views : 0;

      // Rating.
      $vid_likes    = function_exists('tmw_get_post_likes_count')
          ? tmw_get_post_likes_count($video_id)
          : (int) get_post_meta($video_id, 'likes_count', true);
      $vid_dislikes = function_exists('tmw_get_post_dislikes_count')
          ? tmw_get_post_dislikes_count($video_id)
          : (int) get_post_meta($video_id, 'dislikes_count', true);
      $vid_likes    = is_numeric($vid_likes) ? (int) $vid_likes : 0;
      $vid_dislikes = is_numeric($vid_dislikes) ? (int) $vid_dislikes : 0;
      $vid_total    = $vid_likes + $vid_dislikes;
      $vid_pct      = ($vid_total > 0) ? round(($vid_likes / $vid_total) * 100, 0) : 0;

      // Thumbnail rotation data.
      $thumbs_data = get_post_meta($post->ID, 'thumbs', true);
      $thumbs_data = is_string($thumbs_data) ? trim($thumbs_data) : '';

      $tmw_vid_uid++;
    ?>
<article data-video-uid="<?php echo esc_attr($tmw_vid_uid); ?>" data-post-id="<?php echo esc_attr($video_id); ?>" <?php post_class('loop-video thumb-block'); ?>>
	<a href="<?php echo esc_url($permalink); ?>" title="<?php echo esc_attr($title); ?>">
		<div class="post-thumbnail">
			<div class="post-thumbnail-container<?php echo $thumbs_data !== '' ? ' video-with-thumbs thumbs-rotation' : ''; ?>"<?php echo $thumbs_data !== '' ? ' data-thumbs="' . esc_attr($thumbs_data) . '"' : ''; ?>><img width="300" height="168.75" <?php echo $thumbs_data !== '' ? 'data-src' : 'src'; ?>="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($title); ?>"></div>
			<?php if ($views > 0) : ?><span class="views"><i class="fa fa-eye"></i> <?php echo esc_html(number_format_i18n($views)); ?></span><?php endif; ?>
			<?php if ($duration_display !== '') : ?><span class="duration"><i class="fa fa-clock-o"></i><?php echo esc_html($duration_display); ?></span><?php endif; ?>
		</div>
		<div class="rating-bar"><div class="rating-bar-meter" style="width:<?php echo esc_attr($vid_pct); ?>%"></div><i class="fa fa-thumbs-up" aria-hidden="true"></i><span><?php echo esc_html($vid_pct); ?>%</span></div>
		<header class="entry-header">
			<span><?php echo esc_html($title); ?></span>
		</header>
	</a>
</article>
    <?php endforeach; ?>
  </div>
</section>
<?php
wp_reset_postdata();

if ($original_post instanceof WP_Post) {
    $post = $original_post;
}
?>
