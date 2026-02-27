<?php
/**
 * Template Name: Single Model
 * Description: Displays single model banner and related videos.
 */

get_header(); ?>

<div id="primary" class="content-area with-sidebar-right">
  <main id="main" class="site-main with-sidebar-right" role="main">
    <?php
    if ( have_posts() ) :
      while ( have_posts() ) :
        the_post();

        // === [TMW-FIX] Model tags + model videos resolution ===
        $post_id    = get_the_ID();
        $model_slug = get_post_field( 'post_name', $post_id );

        // Fetch videos for this model.
        $videos = array();
        if ( function_exists( 'tmw_get_videos_for_model' ) ) {
          $videos = tmw_get_videos_for_model( $model_slug, 24 );
        }
        set_query_var( 'tmw_model_videos', $videos );

        // Collect tags from the associated videos (models don't have tags themselves).
        $video_tags = array();
        if ( ! empty( $videos ) ) {
          foreach ( $videos as $v_post ) {
            $tags_for_video = wp_get_post_terms( $v_post->ID, 'post_tag' );
            if ( ! is_wp_error( $tags_for_video ) && ! empty( $tags_for_video ) ) {
              foreach ( $tags_for_video as $tag_term ) {
                $video_tags[ $tag_term->term_id ] = $tag_term;
              }
            }
          }
        }

        $tag_count = count( $video_tags );

        if ( $tag_count > 0 ) {
          usort( $video_tags, static function( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
          } );
        }

        set_query_var( 'tmw_model_tags_data', array_values( $video_tags ) );
        set_query_var( 'tmw_model_tags_count', $tag_count );
        // Render the model content template.
        get_template_part( 'template-parts/content', 'model' );

        // Cleanup query vars.
        set_query_var( 'tmw_model_tags_data', array() );
        set_query_var( 'tmw_model_tags_count', null );
        set_query_var( 'tmw_model_videos', null );

      endwhile;
    endif;
    ?>
    <?php get_template_part('partials/featured-models-block'); ?>
  </main>
</div>

<?php get_sidebar(); ?>

<?php
// Removed side-wide injected comments block to prevent duplicate forms.
// The normal comment form (if any) should be rendered by content-model.php / theme.
get_footer();
