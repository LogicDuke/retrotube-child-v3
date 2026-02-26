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

        $model_tags = get_the_tags( $post_id );
        $tag_count  = 0;
        $tag_array  = array();

        if ( ! empty( $model_tags ) && ! is_wp_error( $model_tags ) ) {
          $tag_array = $model_tags;
          $tag_count = count( $model_tags );
        }

        if ( $tag_count > 0 ) {
          usort( $tag_array, static function( $a, $b ) {
            return strcasecmp( $a->name, $b->name );
          } );
        }

        set_query_var( 'tmw_model_tags_data', $tag_array );
        set_query_var( 'tmw_model_tags_count', $tag_count );

        $videos = array();
        if ( function_exists( 'tmw_get_videos_for_model' ) ) {
          $videos = tmw_get_videos_for_model( $model_slug, 24 );
        }
        set_query_var( 'tmw_model_videos', $videos );
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
