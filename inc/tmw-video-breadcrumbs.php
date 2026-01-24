<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * [TMW-BREADCRUMB] Canonical single video breadcrumbs.
 */
function tmw_render_video_breadcrumbs() {
    if ( ! is_singular( 'video' ) ) {
        return '';
    }

    $post_id = get_queried_object_id();
    if ( ! $post_id ) {
        return '';
    }

    $home_label   = __( 'Home', 'wpst' );
    $videos_label = __( 'Videos', 'wpst' );

    ob_start();
    ?>
    <div class="breadcrumbs-area">
        <div class="row">
            <div id="breadcrumbs">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $home_label ); ?></a>
                <span class="separator"><i class="fa fa-caret-right"></i></span>
                <a href="<?php echo esc_url( home_url( '/videos/' ) ); ?>"><?php echo esc_html( $videos_label ); ?></a>
                <span class="separator"><i class="fa fa-caret-right"></i></span>
                <span class="current"><?php echo esc_html( get_the_title( $post_id ) ); ?></span>
            </div>
        </div>
    </div>
    <?php
    $output = ob_get_clean();

    echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    error_log( sprintf( '[TMW-BREADCRUMB] Video breadcrumb rendered for ID %d', (int) $post_id ) );

    return $output;
}
