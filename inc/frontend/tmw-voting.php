<?php
if (!defined('ABSPATH')) { exit; }

/**
 * TMW Voting (WPS-Booster compatible)
 *
 * - Always renders like/dislike buttons (no session/cookie checks).
 * - Uses post meta keys: post_views_count, likes_count, dislikes_count.
 * - Allows unlimited voting per page load.
 */

if (!function_exists('tmw_get_post_meta_count')) {
    /**
     * Read a numeric meta count safely.
     */
    function tmw_get_post_meta_count(int $post_id, string $meta_key): int {
        $value = get_post_meta($post_id, $meta_key, true);
        return is_numeric($value) ? (int) $value : 0;
    }
}

if (!function_exists('tmw_get_post_views_count')) {
    function tmw_get_post_views_count(int $post_id): int {
        return tmw_get_post_meta_count($post_id, 'post_views_count');
    }
}

if (!function_exists('tmw_get_post_likes_count')) {
    function tmw_get_post_likes_count(int $post_id): int {
        return tmw_get_post_meta_count($post_id, 'likes_count');
    }
}

if (!function_exists('tmw_get_post_dislikes_count')) {
    function tmw_get_post_dislikes_count(int $post_id): int {
        return tmw_get_post_meta_count($post_id, 'dislikes_count');
    }
}

if (!function_exists('tmw_get_post_like_rate')) {
    /**
     * Return a percentage based on likes/dislikes, or false when unrated.
     *
     * @return float|false
     */
    function tmw_get_post_like_rate(int $post_id) {
        $likes    = tmw_get_post_likes_count($post_id);
        $dislikes = tmw_get_post_dislikes_count($post_id);
        $total    = $likes + $dislikes;

        if ($total === 0) {
            return false;
        }

        return round(($likes / $total) * 100, 0);
    }
}

if (!function_exists('tmw_get_post_like_link')) {
    /**
     * Render always-visible voting buttons.
     */
    function tmw_get_post_like_link(int $post_id): string {
        ob_start();
        ?>
        <div class="tmw-vote-buttons" data-post-id="<?php echo esc_attr($post_id); ?>">
            <button type="button" class="tmw-vote-button tmw-vote-like" data-vote-type="like" aria-label="<?php esc_attr_e('Like', 'retrotube'); ?>">
                <i class="fa fa-thumbs-up" aria-hidden="true"></i>
            </button>
            <button type="button" class="tmw-vote-button tmw-vote-dislike" data-vote-type="dislike" aria-label="<?php esc_attr_e('Dislike', 'retrotube'); ?>">
                <i class="fa fa-thumbs-down fa-flip-horizontal" aria-hidden="true"></i>
            </button>
        </div>
        <?php
        return trim((string) ob_get_clean());
    }
}

/**
 * AJAX voting handler.
 */
function tmw_handle_vote_ajax(): void {
    check_ajax_referer('tmw_vote', 'nonce');

    $post_id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $vote_type = isset($_POST['vote_type']) ? sanitize_key($_POST['vote_type']) : '';

    if (!$post_id || !in_array($vote_type, ['like', 'dislike'], true)) {
        wp_send_json_error(['message' => 'Invalid vote request.'], 400);
    }

    $likes    = tmw_get_post_likes_count($post_id);
    $dislikes = tmw_get_post_dislikes_count($post_id);

    if ($vote_type === 'like') {
        $likes++;
        update_post_meta($post_id, 'likes_count', $likes);
    } else {
        $dislikes++;
        update_post_meta($post_id, 'dislikes_count', $dislikes);
    }

    $total   = $likes + $dislikes;
    $percent = $total > 0 ? round(($likes / $total) * 100, 0) : 0;

    wp_send_json_success([
        'likes'    => $likes,
        'dislikes' => $dislikes,
        'percent'  => $percent,
    ]);
}

add_action('wp_ajax_tmw_vote', 'tmw_handle_vote_ajax');
add_action('wp_ajax_nopriv_tmw_vote', 'tmw_handle_vote_ajax');
