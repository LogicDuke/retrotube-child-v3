<?php
/**
 * Child-theme video-player override.
 *
 * [TMW-VIDEO-LAZY] [TMW-EXT-PLAYER] [TMW-PAGESPEED] [TMW-AFFILIATE-TRACKING]
 * Lazy-loads third-party AWE/LiveJasmin player markup after explicit user
 * interaction while keeping the parent RetroTube player wrapper intact.
 *
 * @package retrotube-child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('tmw_video_lazy_get_poster_url')) {
    /**
     * Resolve the same local poster/thumbnail URL used before player render.
     *
     * @param int $post_id Post ID.
     * @return string
     */
    function tmw_video_lazy_get_poster_url(int $post_id): string {
        if (has_post_thumbnail($post_id) && wp_get_attachment_url(get_post_thumbnail_id($post_id))) {
            $thumb_url = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'wpst_thumb_large', true);
            if (is_array($thumb_url) && !empty($thumb_url[0])) {
                return (string) $thumb_url[0];
            }
        }

        $thumb = get_post_meta($post_id, 'thumb', true);
        return is_string($thumb) ? $thumb : '';
    }
}

if (!function_exists('tmw_video_lazy_extract_player_src')) {
    /**
     * Extract the exact already-resolved player URL from the parent markup.
     *
     * [TMW-AFFILIATE-TRACKING] This intentionally does not rebuild URLs, so
     * psid/accessKey/contentHash/psprogram/pstool/siteId/color/c parameters
     * remain byte-for-byte as the parent player produced them.
     *
     * @param string $markup Parent player markup.
     * @return string
     */
    function tmw_video_lazy_extract_player_src(string $markup): string {
        if ($markup === '') {
            return '';
        }

        $preferred_patterns = [
            // [TMW-EXT-PLAYER] AWE tbplyr may be script-based, not iframe-based.
            '/<script\b[^>]*\bsrc=\s*(["\']?)([^"\'\s>]+)\1/ix',
            '/<(?:iframe|embed)\b[^>]*\bsrc=\s*(["\']?)([^"\'\s>]+)\1/ix',
            '/<source\b[^>]*\bsrc=\s*(["\']?)([^"\'\s>]+)\1/ix',
            '/<video\b[^>]*\bsrc=\s*(["\']?)([^"\'\s>]+)\1/ix',
        ];

        foreach ($preferred_patterns as $pattern) {
            if (preg_match_all($pattern, $markup, $matches) && !empty($matches[2])) {
                foreach ($matches[2] as $src) {
                    $src = html_entity_decode((string) $src, ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
                    if (preg_match('~(?:atwmcd\.com|/embed/tbplyr|/tube-player/|vcmdiawe\.com|galleryn\d*\.vcmdiawe\.com)~i', $src)) {
                        return $src;
                    }
                }

                return html_entity_decode((string) $matches[2][0], ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
            }
        }

        return '';
    }
}


if (!function_exists('tmw_video_lazy_is_external_player')) {
    /**
     * Determine whether markup contains the third-party AWE/LiveJasmin player.
     *
     * @param string $markup Parent player markup.
     * @param string $src Extracted player URL.
     * @return bool
     */
    function tmw_video_lazy_is_external_player(string $markup, string $src): bool {
        $haystack = $src . ' ' . $markup;

        return (bool) preg_match('~(?:atwmcd\.com|/embed/tbplyr|/tube-player/|vcmdiawe\.com|galleryn\d*\.vcmdiawe\.com)~i', $haystack);
    }
}

$post_id = get_the_ID();
$parent_player_markup = '';

if (class_exists('WPST_Content_Video_Player')) {
    $content_video_player = new WPST_Content_Video_Player($post_id);
    $parent_player_markup = (string) $content_video_player->get_content_video_player();
}

$parent_player_output = '<div class="video-player">' . $parent_player_markup . '</div>';

/*
 * [TMW-VIDEO-LAZY] [TMW-PAGESPEED]
 * [TMW-AFFILIATE-TRACKING] Preserve wps_paywall_media_content behavior before
 * deciding whether to expose any deferred player payload. If a paywall/media
 * filter removes or replaces the player, render that safe filtered response and
 * never place the original unfiltered HTML in data-player-markup.
 */
$filtered_player_output = apply_filters('wps_paywall_media_content', $parent_player_output, $post_id);
if (!is_string($filtered_player_output)) {
    $filtered_player_output = '';
}

if ($filtered_player_output !== $parent_player_output) {
    echo $filtered_player_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Paywall/media filter owns the safe replacement markup.
    return;
}

$player_src = tmw_video_lazy_extract_player_src($parent_player_markup);
$is_external_player = tmw_video_lazy_is_external_player($parent_player_markup, $player_src);

if (!$is_external_player || $player_src === '' || $parent_player_markup === '') :
    echo $filtered_player_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing trusted parent theme player markup after paywall filtering.
    return;
endif;

$poster_url = tmw_video_lazy_get_poster_url($post_id);
$player_markup_payload = base64_encode($parent_player_markup);
$placeholder_id = 'tmw-video-lazy-player-' . (int) $post_id;
?>
<!-- [TMW-VIDEO-LAZY] [TMW-EXT-PLAYER] [TMW-PAGESPEED] [TMW-AFFILIATE-TRACKING] External player deferred until viewer intent. -->
<div class="video-player">
    <div
        id="<?php echo esc_attr($placeholder_id); ?>"
        class="tmw-video-lazy-player"
        data-player-src="<?php echo esc_url($player_src); ?>"
        data-player-markup="<?php echo esc_attr($player_markup_payload); ?>"
        data-tmw-video-lazy="1"
    >
        <button
            type="button"
            class="tmw-video-lazy-button"
            aria-label="<?php echo esc_attr__('Play video', 'retrotube-child'); ?>"
            data-tmw-video-lazy-trigger
        >
            <?php if ($poster_url !== '') : ?>
                <img class="tmw-video-lazy-poster" src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>" loading="eager" decoding="async">
            <?php endif; ?>
            <span class="tmw-video-lazy-overlay" aria-hidden="true">
                <span class="tmw-video-lazy-play-icon"></span>
            </span>
        </button>
    </div>
</div>
