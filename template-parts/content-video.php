<?php
// Autoplay.
$autoplay = ( xbox_get_field_value( 'wpst-options', 'autoplay-video-player' ) == 'on' ) ? 'autoplay' : '';

// Thumbnail.
$thumb = get_post_meta( $post->ID, 'thumb', true );
if ( has_post_thumbnail() && wp_get_attachment_url( get_post_thumbnail_id() ) ) {
	$thumb_id  = get_post_thumbnail_id();
	$thumb_url = wp_get_attachment_image_src( $thumb_id, 'wpst_thumb_large', true );
	$poster    = $thumb_url[0];
} else {
	$poster = $thumb;
}
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?> itemprop="video" itemscope itemtype="http://schema.org/VideoObject">
	<header class="entry-header">

		<?php
		ob_start(
			function ( $buffer ) use ( $post ) {
				return apply_filters( 'wps_paywall_media_content', $buffer, $post->ID );
			}
		);
		?>

		<?php get_template_part( 'template-parts/content', 'video-player' ); ?>

		<?php if ( get_post_meta( $post->ID, 'unique_ad_under_player', true ) != '' ) : ?>
			<div class="happy-under-player">
				<?php echo get_post_meta( $post->ID, 'unique_ad_under_player', true ); ?>
			</div>
		<?php elseif ( xbox_get_field_value( 'wpst-options', 'under-player-ad-desktop' ) != '' ) : ?>
			<div class="happy-under-player">
				<?php echo wpst_display_ad_or_error_message( xbox_get_field_value( 'wpst-options', 'under-player-ad-desktop' ) ); ?>
			</div>
		<?php endif; ?>

		<?php if ( xbox_get_field_value( 'wpst-options', 'under-player-ad-mobile' ) != '' ) : ?>
			<div class="happy-under-player-mobile">
				<?php echo wpst_display_ad_or_error_message( xbox_get_field_value( 'wpst-options', 'under-player-ad-mobile' ) ); ?>
			</div>
		<?php endif; ?>

		<?php
		$tracking_url = ( xbox_get_field_value( 'wpst-options', 'tracking-button-link' ) != '' )
			? xbox_get_field_value( 'wpst-options', 'tracking-button-link' )
			: get_post_meta( $post->ID, 'tracking_url', true );

		if ( $tracking_url != '' && xbox_get_field_value( 'wpst-options', 'display-tracking-button' ) == 'on' ) :
		?>
			<a class="button" id="tracking-url" href="<?php echo esc_url( $tracking_url ); ?>" title="<?php the_title(); ?>" target="_blank">
				<i class="fa fa-<?php echo esc_attr( xbox_get_field_value( 'wpst-options', 'tracking-button-icon' ) ); ?>"></i>
				<?php
				if ( xbox_get_field_value( 'wpst-options', 'tracking-button-text' ) == '' ) :
					esc_html_e( 'Download complete video now!', 'wpst' );
				else :
					echo esc_html( xbox_get_field_value( 'wpst-options', 'tracking-button-text' ) );
				endif;
				?>
			</a>
		<?php endif; ?>

		<?php ob_end_flush(); ?>

		<div class="title-block box-shadow">
			<?php the_title( '<h1 class="entry-title" itemprop="name">', '</h1>' ); ?>
			<?php if ( xbox_get_field_value( 'wpst-options', 'enable-rating-system' ) == 'on' ) : ?>
				<?php
				$rating_percent = function_exists( 'tmw_get_post_like_rate' )
					? tmw_get_post_like_rate( get_the_ID() )
					: ( function_exists( 'wpst_get_post_like_rate' ) ? wpst_get_post_like_rate( get_the_ID() ) : false );
				$is_rated_yet   = ( $rating_percent === false ) ? ' not-rated-yet' : '';
				$rating_percent = ( $rating_percent === false ) ? 0 : (float) $rating_percent;
				?>
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

		<!-- 🔹 Meta Info INLINE (Model / From / Date) -->
		<div class="video-meta-inline">
			<?php
			// ✅ Show Model(s) only if terms exist
			$terms = wp_get_post_terms( get_the_ID(), 'models' );
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				$terms = wp_get_post_terms( get_the_ID(), 'actors' ); // fallback
			}
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                                echo '<span class="video-meta-item video-meta-model"><i class="fa fa-star"></i> Model:&nbsp;';
				$links = array();
                                foreach ( $terms as $term ) {
                                        $model_link = function_exists( 'tmw_get_model_link_for_term' ) ? tmw_get_model_link_for_term( $term ) : '';
                                        if ( ! $model_link ) {
                                                $fallback = get_term_link( $term );
                                                $model_link = is_wp_error( $fallback ) ? '' : $fallback;
                                        }
                                        if ( $model_link ) {
                                                $links[] = '<a href="' . esc_url( $model_link ) . '">' . esc_html( $term->name ) . '</a>';
                                        }
                                }
				echo implode( ', ', $links );
				echo '</span>';
			}

			// ✅ Author always links
                        echo '<span class="video-meta-item video-meta-author"><i class="fa fa-user"></i> From:&nbsp;<a href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>';

			// ✅ Date
                        echo '<span class="video-meta-item video-meta-date"><i class="fa fa-calendar"></i> Date:&nbsp;' . esc_html( get_the_date() ) . '</span>';
			?>
		</div>
		<!-- 🔹 End Meta Info -->

		<div class="clear"></div>

	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php
		$views_count    = function_exists( 'tmw_get_post_views_count' )
			? tmw_get_post_views_count( (int) get_the_ID() )
			: ( function_exists( 'wpst_get_post_views' ) ? wpst_get_post_views( get_the_ID() ) : 0 );
		$likes_count    = function_exists( 'tmw_get_post_likes_count' )
			? tmw_get_post_likes_count( (int) get_the_ID() )
			: ( function_exists( 'wpst_get_post_likes' ) ? wpst_get_post_likes( get_the_ID() ) : 0 );
		$dislikes_count = function_exists( 'tmw_get_post_dislikes_count' )
			? tmw_get_post_dislikes_count( (int) get_the_ID() )
			: ( function_exists( 'wpst_get_post_dislikes' ) ? wpst_get_post_dislikes( get_the_ID() ) : 0 );
		$views_count    = is_numeric( $views_count ) ? (int) $views_count : 0;
		$likes_count    = is_numeric( $likes_count ) ? (int) $likes_count : 0;
		$dislikes_count = is_numeric( $dislikes_count ) ? (int) $dislikes_count : 0;
		?>
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
							<div class="tmw-accordion tmw-accordion--video-desc">
								<div id="tmw-video-desc-<?php echo (int) get_the_ID(); ?>" class="tmw-accordion-content tmw-accordion-collapsed more" data-tmw-accordion-lines="1">
									<?php the_content(); ?>
								</div>
								<div class="tmw-accordion-toggle-wrap">
									<a class="tmw-accordion-toggle" href="javascript:void(0);" data-tmw-accordion-toggle aria-controls="tmw-video-desc-<?php echo (int) get_the_ID(); ?>" aria-expanded="false" data-readmore-text="<?php echo esc_attr__( 'Read more', 'retrotube-child' ); ?>" data-close-text="<?php echo esc_attr__( 'Close', 'retrotube-child' ); ?>">
										<span class="tmw-accordion-text"><?php esc_html_e( 'Read more', 'retrotube-child' ); ?></span>
										<i class="fa fa-chevron-down"></i>
									</a>
								</div>
							</div>
						<?php else : ?>
							<?php the_content(); ?>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<?php /* ✅ Removed duplicate "Model / From / Date" block inside accordion */ ?>

			</div>

			<?php if ( xbox_get_field_value( 'wpst-options', 'enable-video-share' ) == 'on' ) : ?>
				<?php get_template_part( 'template-parts/content', 'share-buttons' ); ?>
			<?php endif; ?>
		</div>
	</div><!-- .entry-content -->

	<?php if ( xbox_get_field_value( 'wpst-options', 'display-related-videos' ) == 'on' ) : ?>
		<?php get_template_part( 'template-parts/content', 'related' ); ?>
	<?php endif; ?>

	<?php
	// [TMW-VIDEO-TAGS] v4.5.1 — Unified tag layout 100% identical to model page
	if ( xbox_get_field_value( 'wpst-options', 'show-tags-video-about' ) == 'on' ) :
		$video_tags = get_the_tags( get_the_ID() );
		$video_tags_count = is_array( $video_tags ) ? count( $video_tags ) : 0;

		if ( $video_tags_count > 0 ) :
		?>
		<!-- === TMW-VIDEO-TAGS-UNIFIED === -->
		<div class="post-tags entry-tags tmw-model-tags tmw-video-tags">
			<span class="tag-title">
				<i class="fa fa-tags" aria-hidden="true"></i>
				<?php echo esc_html__( 'Tags:', 'retrotube' ); ?>
			</span>
			<?php foreach ( $video_tags as $tag ) : ?>
				<a href="<?php echo esc_url( get_tag_link( $tag->term_id ) ); ?>"
					class="label"
					title="<?php echo esc_attr( $tag->name ); ?>">
					<i class="fa fa-tag"></i><?php echo esc_html( $tag->name ); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<!-- === END TMW-VIDEO-TAGS-UNIFIED === -->
		<?php endif;
	endif;

	// Categories - SAME styling as tags (red pills)
	if ( xbox_get_field_value( 'wpst-options', 'show-categories-video-about' ) == 'on' ) :
		$video_categories = get_the_category( get_the_ID() );
		if ( ! empty( $video_categories ) && ! is_wp_error( $video_categories ) ) :
		?>
		<!-- === TMW-VIDEO-CATEGORIES-UNIFIED === -->
		<div class="post-tags entry-tags tmw-model-tags tmw-video-categories">
			<span class="tag-title">
				<i class="fa fa-tags" aria-hidden="true"></i>
				<?php echo esc_html__( 'Categories:', 'retrotube' ); ?>
			</span>
			<?php foreach ( $video_categories as $cat ) : ?>
				<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"
					class="label"
					title="<?php echo esc_attr( $cat->name ); ?>">
					<i class="fa fa-tag"></i><?php echo esc_html( $cat->name ); ?>
				</a>
			<?php endforeach; ?>
		</div>
		<!-- === END TMW-VIDEO-CATEGORIES-UNIFIED === -->
		<?php
		endif;
	endif;
	?>

	<?php
	// 🔹 Comments
	if ( xbox_get_field_value( 'wpst-options', 'enable-comments' ) == 'on' ) {
		if ( comments_open() || get_comments_number() ) :
			comments_template();
		endif;
	}

        ?>
</article><!-- #post-## -->
