<?php
/**
 * RetroTube custom template-path video-player override.
 *
 * [TMW-VIDEO-LAZY] [TMW-EXT-PLAYER] [TMW-PAGESPEED] [TMW-AFFILIATE-TRACKING]
 * Loads the shared child-theme lazy video-player override from the template
 * path used by this RetroTube installation.
 *
 * @package retrotube-child
 */

if (!defined('ABSPATH')) {
    exit;
}

require get_stylesheet_directory() . '/template-parts/content-video-player.php';
