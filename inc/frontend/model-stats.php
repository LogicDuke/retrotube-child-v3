<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'tmw_model_stats_increment_views' ) ) {
    function tmw_model_stats_increment_views( int $post_id ): void {
        if ( $post_id <= 0 ) {
            return;
        }

        $wpst_used = '';
        foreach ( [
            'wpst_set_post_views',
            'wpst_update_post_views',
            'wpst_increment_post_views',
            'wpst_set_post_views_count',
        ] as $fn ) {
            if ( function_exists( $fn ) ) {
                try {
                    $fn( $post_id );
                    $wpst_used = $fn;
                } catch ( \Throwable $e ) {
                    // Ignore and fall back to our own meta count.
                }
                break;
            }
        }

        if ( $wpst_used === '' ) {
            $key = '_tmw_model_views';
            $cur = get_post_meta( $post_id, $key, true );
            $cur = is_numeric( $cur ) ? (int) $cur : 0;
            update_post_meta( $post_id, $key, $cur + 1 );

        }
    }
}

/**
 * Get views count for model - WPS-Booster compatible.
 * Reads from 'post_views_count' meta key (same as WPS-Booster uses).
 */
if ( ! function_exists( 'tmw_get_model_views' ) ) {
    function tmw_get_model_views( int $post_id ): int {
        $count = get_post_meta( $post_id, 'post_views_count', true );
        if ( is_numeric( $count ) && (int) $count > 0 ) {
            return (int) $count;
        }

        if ( function_exists( 'wpst_get_post_views' ) ) {
            $wpst = wpst_get_post_views( $post_id );
            if ( is_numeric( $wpst ) && (int) $wpst > 0 ) {
                return (int) $wpst;
            }
        }

        $fallback = get_post_meta( $post_id, '_tmw_model_views', true );
        return is_numeric( $fallback ) ? (int) $fallback : 0;
    }
}

/**
 * Get likes count for model - WPS-Booster compatible.
 * Reads from 'likes_count' meta key (same as WPS-Booster uses).
 */
if ( ! function_exists( 'tmw_get_model_likes' ) ) {
    function tmw_get_model_likes( int $post_id ): int {
        $count = get_post_meta( $post_id, 'likes_count', true );
        if ( is_numeric( $count ) ) {
            return (int) $count;
        }

        if ( function_exists( 'wpst_get_post_likes' ) ) {
            $wpst = wpst_get_post_likes( $post_id );
            if ( is_numeric( $wpst ) ) {
                return (int) $wpst;
            }
        }

        return 0;
    }
}

/**
 * Get dislikes count for model - WPS-Booster compatible.
 * Reads from 'dislikes_count' meta key (same as WPS-Booster uses).
 */
if ( ! function_exists( 'tmw_get_model_dislikes' ) ) {
    function tmw_get_model_dislikes( int $post_id ): int {
        $count = get_post_meta( $post_id, 'dislikes_count', true );
        if ( is_numeric( $count ) ) {
            return (int) $count;
        }

        if ( function_exists( 'wpst_get_post_dislikes' ) ) {
            $wpst = wpst_get_post_dislikes( $post_id );
            if ( is_numeric( $wpst ) ) {
                return (int) $wpst;
            }
        }

        return 0;
    }
}

/**
 * Get rating percentage for model.
 */
if ( ! function_exists( 'tmw_get_model_rating_percent' ) ) {
    function tmw_get_model_rating_percent( int $post_id ): float {
        $likes    = tmw_get_model_likes( $post_id );
        $dislikes = tmw_get_model_dislikes( $post_id );
        $total    = $likes + $dislikes;

        if ( 0 === $total ) {
            return 0.0;
        }

        return round( ( $likes / $total ) * 100, 1 );
    }
}

// Keep the old function name for backward compatibility.
if ( ! function_exists( 'tmw_get_display_model_views' ) ) {
    function tmw_get_display_model_views( int $post_id ): int {
        return tmw_get_model_views( $post_id );
    }
}

// Count EVERY visit/page load: +1 per load (no dedup).
add_action( 'wp', function () {
    if ( ! is_singular( 'model' ) ) {
        return;
    }

    // Avoid counting admin screens / AJAX / REST (not real front-end visits).
    if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    $post_id = (int) get_queried_object_id();
    if ( $post_id <= 0 ) {
        return;
    }

    tmw_model_stats_increment_views( $post_id );
}, 20 );
