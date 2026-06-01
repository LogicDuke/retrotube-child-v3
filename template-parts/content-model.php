<?php
$model_id   = get_the_ID();
$model_name = get_the_title();
$banner_url = tmw_resolve_model_banner_url( $model_id );

$cta_url   = function_exists( 'get_field' ) ? get_field( 'model_link', $model_id ) : '';
$cta_label = function_exists( 'get_field' ) ? get_field( 'model_link_label', $model_id ) : '';
$cta_note  = function_exists( 'get_field' ) ? get_field( 'model_link_note', $model_id ) : '';

if ( empty( $cta_label ) ) {
    $cta_label = __( 'Watch Live', 'retrotube' );
}

// Get counts using WPS-Booster compatible functions
$views_count    = function_exists( 'tmw_get_model_views' )
    ? tmw_get_model_views( (int) $model_id )
    : (int) get_post_meta( $model_id, 'post_views_count', true );
$likes_count    = function_exists( 'tmw_get_model_likes' )
    ? tmw_get_model_likes( (int) $model_id )
    : (int) get_post_meta( $model_id, 'likes_count', true );
$dislikes_count = function_exists( 'tmw_get_model_dislikes' )
    ? tmw_get_model_dislikes( (int) $model_id )
    : (int) get_post_meta( $model_id, 'dislikes_count', true );
$views_count    = is_numeric( $views_count ) ? (int) $views_count : 0;
$likes_count    = is_numeric( $likes_count ) ? (int) $likes_count : 0;
$dislikes_count = is_numeric( $dislikes_count ) ? (int) $dislikes_count : 0;
$rating_percent = ( $likes_count + $dislikes_count ) > 0
    ? round( ( $likes_count / ( $likes_count + $dislikes_count ) ) * 100, 1 )
    : 0;
$is_rated_yet = ( $likes_count + $dislikes_count === 0 ) ? ' not-rated-yet' : '';
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?> itemprop="performer" itemscope itemtype="http://schema.org/Person">
    <header class="entry-header">

        <div class="video-player box-shadow model-banner">
            <?php if ( ! tmw_render_model_banner( $model_id, 'frontend' ) ) : ?>
                <div class="tmw-banner-container">
                    <div class="tmw-banner-frame frontend">
                        <div class="no-banner-placeholder">
                            <p><?php esc_html_e( 'No banner image uploaded yet.', 'retrotube' ); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( $cta_url ) : ?>
                <a class="button model-cta" id="model-cta" href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="nofollow noopener">
                    <i class="fa fa-video-camera"></i>
                    <?php echo esc_html( $cta_label ); ?>
                </a>
            <?php endif; ?>

            <?php if ( $cta_note ) : ?>
                <p class="model-cta-note"><?php echo wp_kses_post( $cta_note ); ?></p>
            <?php endif; ?>
        </div>

        <div class="title-block box-shadow">
            <?php the_title( '<h1 class="entry-title model-name" itemprop="name">', '</h1>' ); ?>
            <?php if ( xbox_get_field_value( 'wpst-options', 'enable-rating-system' ) == 'on' ) : ?>
                <div id="rating" class="<?php echo esc_attr( trim( $is_rated_yet ) ); ?>">
                    <span id="video-rate">
                        <?php
                        echo function_exists( 'tmw_get_post_like_link' )
                            ? tmw_get_post_like_link( get_the_ID() )
                            : ( function_exists( 'wpst_get_post_like_link' ) ? wpst_get_post_like_link( get_the_ID() ) : '' );
                        ?>
                    </span>
                </div>
            <?php endif; ?>
            <div id="video-tabs" class="tabs">
                <button class="tab-link active about" data-tab-id="video-about">
                    <i class="fa fa-info-circle"></i> <?php esc_html_e( 'About', 'wpst' ); ?>
                </button>
                <?php if ( xbox_get_field_value( 'wpst-options', 'enable-video-share' ) == 'on' ) : ?>
                    <button class="tab-link share" data-tab-id="video-share">
                        <i class="fa fa-share"></i> <?php esc_html_e( 'Share', 'wpst' ); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="video-meta-inline">
            <?php
            echo '<span class="video-meta-item video-meta-model"><i class="fa fa-star"></i> Model:&nbsp;' . esc_html( $model_name ) . '</span>';
            echo '<span class="video-meta-item video-meta-author"><i class="fa fa-user"></i> From:&nbsp;<a href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>';
            echo '<span class="video-meta-item video-meta-date"><i class="fa fa-calendar"></i> Date:&nbsp;' . esc_html( get_the_date() ) . '</span>';
            ?>
        </div>

        <div class="clear"></div>

    </header><!-- .entry-header -->

    <div class="entry-content">
        <?php if ( xbox_get_field_value( 'wpst-options', 'enable-views-system' ) == 'on' || xbox_get_field_value( 'wpst-options', 'enable-rating-system' ) == 'on' ) : ?>
            <div id="rating-col">
                <?php if ( xbox_get_field_value( 'wpst-options', 'enable-views-system' ) == 'on' ) : ?>
                    <div id="video-views"><span><?php echo esc_html( $views_count ); ?></span> <?php esc_html_e( 'views', 'wpst' ); ?></div>
                <?php endif; ?>
                <?php if ( xbox_get_field_value( 'wpst-options', 'enable-rating-system' ) == 'on' ) : ?>
                    <div class="rating-bar"><div class="rating-bar-meter" style="width: <?php echo esc_attr( $rating_percent ); ?>%;"></div></div>
                    <div class="rating-result">
                        <div class="percentage"><?php echo esc_html( $rating_percent ); ?>%</div>
                        <div class="likes">
                            <i class="fa fa-thumbs-up"></i> <span class="likes_count"><?php echo esc_html( $likes_count ); ?></span>
                            <i class="fa fa-thumbs-down fa-flip-horizontal"></i> <span class="dislikes_count"><?php echo esc_html( $dislikes_count ); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="tab-content">
            <?php $width = ( xbox_get_field_value( 'wpst-options', 'enable-views-system' ) == 'off' && xbox_get_field_value( 'wpst-options', 'enable-rating-system' ) == 'off' ) ? '100' : '70'; ?>
            <div id="video-about" class="width<?php echo $width; ?>">
                <div class="video-description">
                    <?php if ( xbox_get_field_value( 'wpst-options', 'show-description-video-about' ) == 'on' ) : ?>
                        <?php if ( xbox_get_field_value( 'wpst-options', 'truncate-description' ) == 'on' ) : ?>
                            <!-- CUSTOM TMW ACCORDION -->
                            <div class="tmw-accordion tmw-accordion--video-desc">
                                <div id="tmw-model-desc-<?php echo (int) get_the_ID(); ?>" class="tmw-accordion-content tmw-accordion-collapsed" data-tmw-accordion-lines="1">
                                    <?php the_content(); ?>
                                </div>
                                <div class="tmw-accordion-toggle-wrap">
                                    <button class="tmw-accordion-toggle" type="button" data-tmw-accordion-toggle aria-controls="tmw-model-desc-<?php echo (int) get_the_ID(); ?>" aria-expanded="false" data-readmore-text="<?php echo esc_attr__( 'Read more', 'retrotube-child' ); ?>" data-close-text="<?php echo esc_attr__( 'Close', 'retrotube-child' ); ?>">
                                        <span class="tmw-accordion-text"><?php esc_html_e( 'Read more', 'retrotube-child' ); ?></span>
                                        <i class="fa fa-chevron-down"></i>
                                    </button>
                                </div>
                            </div>
                        <?php else : ?>
                            <?php the_content(); ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ( xbox_get_field_value( 'wpst-options', 'show-categories-video-about' ) == 'on' || xbox_get_field_value( 'wpst-options', 'show-tags-video-about' ) == 'on' ) : ?>
                    <div class="tags"><?php wpst_entry_footer(); ?></div>
                <?php endif; ?>
            </div>
        </div><!-- END .tab-content -->

        <?php
        // === TMW SLOT BANNER ZONE ===
        if ( function_exists( 'tmw_render_model_slot_banner_zone' ) ) :
            $tmw_slot_html = tmw_render_model_slot_banner_zone( (int) get_the_ID() );
            if ( $tmw_slot_html !== '' ) :
                echo $tmw_slot_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            endif;
        endif;
        // === END TMW SLOT BANNER ZONE ===
        ?>

        <?php
        // === [TMW-FIX] TAGS + CATEGORIES SECTION - Collect from associated videos ===
        $tmw_model_tags       = array();
        $tmw_model_tags_count = 0;
        $tmw_model_categories = array();
        $tmw_model_cats_count = 0;

        // Step 1: Get model info.
        $tmw_m_id   = get_the_ID();
        $tmw_m_slug = get_post_field( 'post_name', $tmw_m_id );
        $tmw_m_name = get_the_title( $tmw_m_id );

        // Step 2: Get videos — try taxonomy query first, then fallback search.
        $tmw_tag_videos = array();
        if ( function_exists( 'tmw_get_videos_for_model' ) && $tmw_m_slug ) {
            $tmw_tag_videos = tmw_get_videos_for_model( $tmw_m_slug, 24 );
        }

        // Fallback: same search query model-videos.php uses.
        if ( empty( $tmw_tag_videos ) && $tmw_m_name ) {
            $tmw_fb_q = new WP_Query( array(
                'post_type'      => array( 'post', 'video' ),
                'posts_per_page' => 12,
                's'              => $tmw_m_name,
                'post_status'    => 'publish',
                'no_found_rows'  => true,
            ) );
            if ( $tmw_fb_q->have_posts() ) {
                $tmw_tag_videos = $tmw_fb_q->posts;
            }
            wp_reset_postdata();
        }

        // Step 3: Collect tags/categories from each video.
        if ( ! empty( $tmw_tag_videos ) && is_array( $tmw_tag_videos ) ) {
            $collected_tags       = array();
            $collected_categories = array();
            $default_category_id  = (int) get_option( 'default_category', 1 );
            foreach ( $tmw_tag_videos as $tv ) {
                if ( ! $tv instanceof WP_Post ) { continue; }

                $tv_tags = wp_get_post_terms( $tv->ID, 'post_tag' );
                if ( ! is_wp_error( $tv_tags ) && ! empty( $tv_tags ) ) {
                    foreach ( $tv_tags as $vt ) {
                        $collected_tags[ $vt->term_id ] = $vt;
                    }
                }

                $tv_categories = get_the_category( $tv->ID );
                if ( ! is_wp_error( $tv_categories ) && ! empty( $tv_categories ) ) {
                    foreach ( $tv_categories as $cat ) {
                        if ( ! $cat instanceof WP_Term ) {
                            continue;
                        }

                        if ( $cat->term_id === $default_category_id || $cat->slug === 'uncategorized' || $cat->count < 1 ) {
                            continue;
                        }

                        $collected_categories[ $cat->term_id ] = $cat;
                    }
                }
            }

            if ( ! empty( $collected_tags ) ) {
                usort( $collected_tags, static function( $a, $b ) {
                    return strcasecmp( $a->name, $b->name );
                } );
                $tmw_original_tags_count = count( $collected_tags );
                $tmw_model_tags = function_exists( 'tmw_filter_visible_model_profile_tags' )
                    ? tmw_filter_visible_model_profile_tags( array_values( $collected_tags ), 12 )
                    : array_slice( array_values( $collected_tags ), 0, 12 );

                $tmw_model_tags_count = count( $tmw_model_tags );

                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf(
                        '[TMW-MODEL-TAGS-FILTER] model_post_id=%d original_tag_count=%d visible_tag_count=%d hidden_tag_count=%d',
                        (int) $tmw_m_id,
                        (int) $tmw_original_tags_count,
                        (int) $tmw_model_tags_count,
                        max( 0, (int) $tmw_original_tags_count - (int) $tmw_model_tags_count )
                    ) );
                }
            }

            if ( ! empty( $collected_categories ) ) {
                usort( $collected_categories, static function( $a, $b ) {
                    return strcasecmp( $a->name, $b->name );
                } );

                $tmw_model_categories = array_slice( array_values( $collected_categories ), 0, 20 );
                $tmw_model_cats_count = count( $tmw_model_categories );
            }
        }
        ?>
        <!-- === TMW-TAGS === -->
        <div class="post-tags entry-tags tmw-model-tags<?php echo $tmw_model_tags_count === 0 ? ' no-tags' : ''; ?>">
            <span class="tag-title">
                <i class="fa fa-tags" aria-hidden="true"></i>
                <?php
                echo $tmw_model_tags_count === 0
                    ? esc_html__( '(No tags linked)', 'retrotube' )
                    : esc_html__( 'Tags:', 'retrotube' );
                ?>
            </span>
            <?php if ( $tmw_model_tags_count > 0 && is_array( $tmw_model_tags ) ) : ?>
                <?php foreach ( $tmw_model_tags as $tag ) : ?>
                    <a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>"
                       class="label"
                       title="<?php echo esc_attr( $tag->name ); ?>">
                        <i class="fa fa-tag"></i><?php echo esc_html( $tag->name ); ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- === END TMW-TAGS === -->

        <?php if ( $tmw_model_cats_count > 0 && is_array( $tmw_model_categories ) ) : ?>
            <!-- === TMW-MODEL-CATEGORIES === -->
            <div class="post-tags entry-tags tmw-model-tags tmw-video-categories">
                <span class="tag-title">
                    <i class="fa fa-tags" aria-hidden="true"></i>
                    <?php echo esc_html__( 'Categories:', 'retrotube' ); ?>
                </span>
                <?php foreach ( $tmw_model_categories as $category ) : ?>
                    <?php
                    $category_link = get_term_link( $category );
                    if ( is_wp_error( $category_link ) ) {
                        continue;
                    }
                    ?>
                    <a href="<?php echo esc_url( $category_link ); ?>"
                       class="label"
                       title="<?php echo esc_attr( $category->name ); ?>">
                        <i class="fa fa-tag"></i><?php echo esc_html( $category->name ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <!-- === END TMW-MODEL-CATEGORIES === -->
        <?php endif; ?>

        <?php
        // === VIDEOS FEATURING MODEL ===
        get_template_part( 'template-parts/model-videos' );
        ?>

        <?php if ( xbox_get_field_value( 'wpst-options', 'enable-video-share' ) == 'on' ) : ?>
            <?php get_template_part( 'template-parts/content', 'share-buttons' ); ?>
        <?php endif; ?>

    </div><!-- .entry-content -->

    <?php if ( xbox_get_field_value( 'wpst-options', 'display-related-videos' ) == 'on' ) : ?>
        <?php get_template_part( 'template-parts/content', 'related' ); ?>
    <?php endif; ?>

    <?php
    if ( xbox_get_field_value( 'wpst-options', 'enable-comments' ) == 'on' ) {
        if ( comments_open() || get_comments_number() ) :
            comments_template();
        endif;
    }
    ?>
</article><!-- #post-## -->
