<?php
// CHILD OVERRIDE OF wpst_breadcrumbs()
// Fix: Single VIDEO breadcrumb = Home > Videos > Title (no category)

if ( ! function_exists( 'wpst_breadcrumbs' ) ) {

	function wpst_breadcrumbs() {

		$delimiter   = '<i class="fa fa-caret-right"></i>';
		$home        = 'Home';
		$showCurrent = 1;
		$before      = '<span class="current">';
		$after       = '</span>';

		global $post;
		$homeLink = get_bloginfo( 'url' );

		/* =====================================================
		 * FIX: SINGLE VIDEO POSTS ONLY
		 * ===================================================== */
		if ( is_singular( 'video' ) ) {

			echo '<div class="breadcrumbs-area"><div class="row"><div id="breadcrumbs">';
			echo '<a href="' . esc_url( $homeLink ) . '">' . esc_html( $home ) . '</a>';
			echo '<span class="separator">' . $delimiter . '</span>';
			echo '<a href="' . esc_url( $homeLink . '/videos/' ) . '">Videos</a>';
			echo '<span class="separator">' . $delimiter . '</span>';
			echo $before . esc_html( get_the_title() ) . $after;
			echo '</div></div></div>';

			return; // ⛔ STOP — do not run generic logic
		}

		/* =====================================================
		 * DEFAULT / PARENT BEHAVIOR (copied safely)
		 * ===================================================== */

		if ( is_home() || is_front_page() ) {
			return;
		}

		echo '<div class="breadcrumbs-area"><div class="row"><div id="breadcrumbs">';
		echo '<a href="' . esc_url( $homeLink ) . '">' . esc_html( $home ) . '</a>';

		if ( is_single() && ! is_attachment() ) {

			echo '<span class="separator">' . $delimiter . '</span>';

			if ( get_post_type() !== 'post' ) {
				$post_type = get_post_type_object( get_post_type() );
				echo '<a href="' . esc_url( $homeLink . '/' . $post_type->rewrite['slug'] . '/' ) . '">';
				echo esc_html( $post_type->labels->singular_name );
				echo '</a>';

				if ( $showCurrent ) {
					echo '<span class="separator">' . $delimiter . '</span>';
					echo $before . esc_html( get_the_title() ) . $after;
				}
			}

		} elseif ( is_page() ) {

			echo '<span class="separator">' . $delimiter . '</span>';
			echo $before . esc_html( get_the_title() ) . $after;

		}

		echo '</div></div></div>';
	}
}
