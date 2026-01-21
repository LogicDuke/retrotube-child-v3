<?php
/**
 * Template Name: Videos Page
 */
get_header();

$filter_raw = isset( $_GET['filter'] ) ? wp_unslash( $_GET['filter'] ) : '';
$filter_raw = is_string( $filter_raw ) ? sanitize_text_field( $filter_raw ) : '';
$filter     = '';
$cat      = isset( $_GET['cat'] ) ? absint( $_GET['cat'] ) : 0;
$instance = array();

if ( $filter_raw && is_numeric( $filter_raw ) ) {
    $cat    = absint( $filter_raw );
    $filter = 'latest';
} else {
    $filter = function_exists( 'tmw_normalize_video_filter' ) ? tmw_normalize_video_filter( $filter_raw ) : strtolower( $filter_raw );
}

$tmw_video_widget_class = class_exists( 'TMW_WP_Widget_Videos_Block_Fixed' ) ? 'TMW_WP_Widget_Videos_Block_Fixed' : 'wpst_WP_Widget_Videos_Block';

if ( $filter ) {
    $filter_map = array(
        'latest'      => array(
            'title'      => __( 'Latest videos', 'retrotube-child' ),
            'video_type' => 'latest',
        ),
        'random'      => array(
            'title'      => __( 'Random videos', 'retrotube-child' ),
            'video_type' => 'random',
        ),
        'related'     => array(
            'title'      => __( 'Related videos', 'retrotube-child' ),
            'video_type' => 'random',
        ),
        'longest'     => array(
            'title'      => __( 'Longest videos', 'retrotube-child' ),
            'video_type' => 'longest',
        ),
        'popular'     => array(
            'title'      => __( 'Most popular videos', 'retrotube-child' ),
            'video_type' => 'popular',
        ),
        'most-viewed' => array(
            'title'      => __( 'Most viewed videos', 'retrotube-child' ),
            'video_type' => 'most-viewed',
        ),
    );

    if ( isset( $filter_map[ $filter ] ) ) {
        $instance = array(
            'title'          => $filter_map[ $filter ]['title'],
            'video_type'     => $filter_map[ $filter ]['video_type'],
            'video_number'   => 12,
            'video_category' => $cat,
        );
    }
}

tmw_render_sidebar_layout('', function () use ( $filter, $instance, $tmw_video_widget_class ) {
    $videos_page_id = get_queried_object_id();
    $raw_content    = $videos_page_id ? (string) get_post_field( 'post_content', $videos_page_id ) : '';
    $intro_content  = $raw_content;
    $rest_content   = '';

    if ( strpos( $raw_content, '<!--more-->' ) !== false ) {
        list( $intro_content, $rest_content ) = explode( '<!--more-->', $raw_content, 2 );
    }

    $intro_content = trim( apply_filters( 'the_content', $intro_content ) );
    $rest_content  = trim( apply_filters( 'the_content', $rest_content ) );

    if ( function_exists( 'tmw_sanitize_accordion_html' ) ) {
        $intro_content = tmw_sanitize_accordion_html( $intro_content );
    }

    $intro_is_accordion = $intro_content && stripos( $intro_content, 'tmw-accordion' ) !== false;
    ?>
      <header class="entry-header">
        <h1 class="entry-title"><i class="fa fa-video-camera"></i> Videos</h1>
        <?php if ( $intro_content ) : ?>
          <?php if ( $intro_is_accordion || ! function_exists( 'tmw_render_accordion' ) ) : ?>
            <?php echo $intro_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
          <?php else : ?>
            <?php
            echo tmw_render_accordion(
                array(
                    'content_html'    => $intro_content,
                    'accordion_class' => 'tmw-accordion--videos-archive',
                    'collapsed'       => true,
                    'lines'           => 1,
                    'id_base'         => 'tmw-videos-archive-intro-',
                )
            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
          <?php endif; ?>
        <?php endif; ?>
      </header>

      <?php if ( $rest_content ) : ?>
        <?php echo $rest_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
      <?php endif; ?>
    <?php
      if ( $filter ) :
        ?>

        <?php if ( ! empty( $instance ) ) : ?>
          <?php
          the_widget(
              $tmw_video_widget_class,
              $instance,
              array(
                  'before_widget' => '<section class="widget widget_videos_block">',
                  'after_widget'  => '</section>',
                  'before_title'  => '<h2 class="widget-title">',
                  'after_title'   => '</h2>',
              )
          );
          ?>
        <?php else : ?>
          <p><?php esc_html_e( 'No videos found for this filter.', 'retrotube-child' ); ?></p>
        <?php endif; ?>

      <?php elseif ( is_page( 'videos' ) ) : ?>

        <?php
        the_widget(
            $tmw_video_widget_class,
            array(
                'title'          => 'Videos being watched',
                'video_type'     => 'random',
                'video_number'   => 8,
                'video_category' => 0,
            ),
            array(
                'before_widget' => '<section class="widget widget_videos_block">',
                'after_widget'  => '</section>',
                'before_title'  => '<h2 class="widget-title">',
                'after_title'   => '</h2>',
            )
        );

        the_widget(
            $tmw_video_widget_class,
            array(
                'title'          => 'Latest videos',
                'video_type'     => 'latest',
                'video_number'   => 6,
                'video_category' => 0,
            ),
            array(
                'before_widget' => '<section class="widget widget_videos_block">',
                'after_widget'  => '</section>',
                'before_title'  => '<h2 class="widget-title">',
                'after_title'   => '</h2>',
            )
        );

        the_widget(
            $tmw_video_widget_class,
            array(
                'title'          => 'Longest videos',
                'video_type'     => 'longest',
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

      <?php endif; ?>
    <?php
});

get_footer();
