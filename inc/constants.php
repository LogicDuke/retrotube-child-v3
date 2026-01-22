<?php
if (!defined('ABSPATH')) { exit; }

if (!defined('TMW_CHILD_NS')) {
    define('TMW_CHILD_NS', 'TMW\\Child');
}

// Reserved for future constants.

define('TMW_CHILD_ASSETS', TMW_CHILD_URL . '/assets');

// Cache-bust version for background images.
if (!defined('TMW_BG_CACHE_VERSION')) {
    define('TMW_BG_CACHE_VERSION', '20260110');
}
