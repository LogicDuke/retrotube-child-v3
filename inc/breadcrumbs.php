<?php
// CHILD OVERRIDE: wpst_breadcrumbs()
// Fix single VIDEO breadcrumb: Home > Videos > Title (no category)

if ( ! function_exists( 'wpst_breadcrumbs' ) ) {

	function wpst_breadcrumbs() {

		$delimiter   = '<i class="fa fa-caret-right"></i>';
		$home        = 'Home';
		$showCurrent = 1;
		$before      = '<span class="current">';
		$after       = '</span>';

		global $post;
		$homeLink = get_bloginfo( 'url' );

		// ==============================
		// FIX: SINGLE VIDEO POSTS ONLY
		// ==============================
		if ( is_singular( 'video' ) ) {

			echo '<div class="breadcrumbs-area"><div class="row"><div id="breadcrumbs">';

			echo '<a href="' . esc_url( $homeLink ) . '">' . esc_html( $home ) . '</a>';
			echo '<span class="separator">' . $delimiter . '</span>';

			echo '<a href="' . esc_url( $homeLink . '/videos/' ) . '">Videos</a>';
			echo '<span class="separator">' . $delimiter . '</span>';

			echo $before . esc_html( get_the_title() ) . $after;

			echo '</div></div></div>';

			return; // ⛔ STOP here — do NOT fall into parent logic
		}

		// ==============================
		// FALL BACK TO PARENT LOGIC
		// ==============================
		require_once get_template_directory() . '/inc/breadcrumbs.php';
		wpst_breadcrumbs();
	}
}
