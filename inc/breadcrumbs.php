<?php
/**
 * CHILD override of wpst_breadcrumbs()
 * Scope:
 * - Videos archive
 * - Single video posts
 * - Leave all other cases to parent fallback
 */

if ( ! function_exists( 'wpst_breadcrumbs' ) ) {

	function wpst_breadcrumbs() {

		$delimiter = '<i class="fa fa-caret-right"></i>';
		$home      = 'Home';
		$homeLink = get_bloginfo( 'url' );

		// ===============================
		// VIDEOS ARCHIVE: Home > Videos (NOT clickable)
		// ===============================
		if ( is_post_type_archive( 'video' ) ) {

			echo '<div class="breadcrumbs-area"><div class="row"><div id="breadcrumbs">';
			echo '<a href="' . esc_url( $homeLink ) . '">' . esc_html( $home ) . '</a>';
			echo '<span class="separator">' . $delimiter . '</span>';
			echo '<span class="current">Videos</span>';
			echo '</div></div></div>';

			return;
		}

		// ===============================
		// SINGLE VIDEO: Home > Videos > Title
		// ===============================
		if ( is_singular( 'video' ) ) {

			echo '<div class="breadcrumbs-area"><div class="row"><div id="breadcrumbs">';
			echo '<a href="' . esc_url( $homeLink ) . '">' . esc_html( $home ) . '</a>';
			echo '<span class="separator">' . $delimiter . '</span>';
			echo '<a href="' . esc_url( $homeLink . '/videos/' ) . '">Videos</a>';
			echo '<span class="separator">' . $delimiter . '</span>';
			echo '<span class="current">' . esc_html( get_the_title() ) . '</span>';
			echo '</div></div></div>';

			return;
		}

		// ===============================
		// FALLBACK → Parent behavior
		// ===============================
		if ( function_exists( 'wpst_parent_breadcrumbs' ) ) {
			wpst_parent_breadcrumbs();
		}
	}
}
