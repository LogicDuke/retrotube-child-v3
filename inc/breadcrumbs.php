<?php
die('CHILD BREADCRUMB ACTIVE');
// CHILD OVERRIDE — BREADCRUMBS
// Fix single VIDEO breadcrumb: Home › Videos › Title (no category)

if ( ! function_exists( 'wpst_breadcrumbs' ) ) {

	function wpst_breadcrumbs() {

		$showOnHome  = 0;
		$delimiter   = '<i class="fa fa-caret-right"></i>';
		$home        = 'Home';
		$showCurrent = 1;
		$before      = '<span class="current">';
		$after       = '</span>';

		global $post;
		$homeLink = get_bloginfo( 'url' );

		// ===============================
		// ✅ FIX: SINGLE VIDEO POSTS ONLY
		// ===============================
		if ( is_singular( 'video' ) ) {

			echo '<div class="breadcrumbs-area"><div class="row"><div id="breadcrumbs">';
			echo '<a href="' . esc_url( $homeLink ) . '">' . esc_html( $home ) . '</a>';
			echo '<span class="separator">' . $delimiter . '</span>';
			echo '<a href="' . esc_url( $homeLink . '/videos/' ) . '">Videos</a>';

			if ( $showCurrent ) {
				echo '<span class="separator">' . $delimiter . '</span>';
				echo $before . get_the_title() . $after;
			}

			echo '</div></div></div>';
			return;
		}

		// ===============================
		// ORIGINAL LOGIC (UNCHANGED)
		// ===============================

		if ( is_home() || is_front_page() ) {

			if ( $showOnHome == 1 ) {
				echo '<div class="breadcrumbs-area"><div id="breadcrumbs"><a href="' . $homeLink . '">' . $home . '</a></div></div>';
			}

		} else {

			echo '<div class="breadcrumbs-area"><div class="row"><div id="breadcrumbs">';
			echo '<a href="' . $homeLink . '">' . $home . '</a>';
			echo '<span class="separator">' . $delimiter . '</span>';

			if ( is_category() ) {

				$thisCat = get_category( get_query_var( 'cat' ), false );
				if ( $thisCat && $thisCat->parent != 0 ) {
					echo get_category_parents( $thisCat->parent, true, '<span class="separator">' . $delimiter . '</span>' );
				}
				echo $before . single_cat_title( '', false ) . $after;

			} elseif ( is_search() ) {

				echo $before . 'Search results for "' . get_search_query() . '"' . $after;

			} elseif ( is_day() ) {

				echo '<a href="' . get_year_link( get_the_time( 'Y' ) ) . '">' . get_the_time( 'Y' ) . '</a>';
				echo '<span class="separator">' . $delimiter . '</span>';
				echo '<a href="' . get_month_link( get_the_time( 'Y' ), get_the_time( 'm' ) ) . '">' . get_the_time( 'F' ) . '</a>';
				echo '<span class="separator">' . $delimiter . '</span>';
				echo $before . get_the_time( 'd' ) . $after;

			} elseif ( is_month() ) {

				echo '<a href="' . get_year_link( get_the_time( 'Y' ) ) . '">' . get_the_time( 'Y' ) . '</a>';
				echo '<span class="separator">' . $delimiter . '</span>';
				echo $before . get_the_time( 'F' ) . $after;

			} elseif ( is_year() ) {

				echo $before . get_the_time( 'Y' ) . $after;

			} elseif ( is_single() && ! is_attachment() ) {

				if ( get_post_type() != 'post' ) {

					$post_type = get_post_type_object( get_post_type() );
					if ( $post_type && $post_type->rewrite ) {
						echo '<a href="' . esc_url( $homeLink . '/' . $post_type->rewrite['slug'] . '/' ) . '">' . esc_html( $post_type->labels->singular_name ) . '</a>';
					}

					if ( $showCurrent ) {
						echo '<span class="separator">' . $delimiter . '</span>';
						echo $before . get_the_title() . $after;
					}

				} else {

					$cat = get_the_category();
					if ( isset( $cat[0] ) ) {
						echo get_category_parents( $cat[0], true, '<span class="separator">' . $delimiter . '</span>' );
					}
					if ( $showCurrent ) {
						echo $before . get_the_title() . $after;
					}
				}

			} elseif ( is_page() && ! $post->post_parent ) {

				if ( $showCurrent ) {
					echo $before . get_the_title() . $after;
				}

			} elseif ( is_page() && $post->post_parent ) {

				$parent_id   = $post->post_parent;
				$breadcrumbs = array();

				while ( $parent_id ) {
					$page = get_page( $parent_id );
					$breadcrumbs[] = '<a href="' . get_permalink( $page->ID ) . '">' . get_the_title( $page->ID ) . '</a>';
					$parent_id = $page->post_parent;
				}

				$breadcrumbs = array_reverse( $breadcrumbs );
				echo implode( '<span class="separator">' . $delimiter . '</span>', $breadcrumbs );

				if ( $showCurrent ) {
					echo '<span class="separator">' . $delimiter . '</span>';
					echo $before . get_the_title() . $after;
				}

			} elseif ( is_tag() ) {

				echo $before . single_tag_title( '', false ) . $after;

			} elseif ( is_author() ) {

				$userdata = get_userdata( get_query_var( 'author' ) );
				echo $before . esc_html( $userdata->display_name ) . $after;

			} elseif ( is_404() ) {

				echo $before . 'Error 404' . $after;
			}

			echo '</div></div></div>';
		}
	}
}
