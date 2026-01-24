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

    ob_start();
    ?>
    <div class="breadcrumbs-area">
        <div class="row">
            <div id="breadcrumbs">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
                <span class="separator"><i class="fa fa-caret-right"></i></span>
                <a href="<?php echo esc_url( home_url( '/videos/' ) ); ?>">Videos</a>
                <span class="separator"><i class="fa fa-caret-right"></i></span>
                <span class="current"><?php the_title(); ?></span>
            </div>
        </div>
    </div>
    <?php
    $output = ob_get_clean();

    echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    error_log( sprintf( '[TMW-BREADCRUMB] Video breadcrumb rendered for ID %d', (int) $post_id ) );

    return $output;
}
