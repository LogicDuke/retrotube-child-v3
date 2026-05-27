<?php
/**
 * Admin single editor enforcement for category pages.
 *
 * @package retrotube-child
 */

if (!defined('ABSPATH')) {
    exit;
}

// Native category term editing must remain available for rename/description updates.
// The Category Page CPT editor stays available via explicit "Edit Category Page" actions only.

add_filter('category_row_actions', function ($actions, $term) {
    if (!current_user_can('manage_categories')) {
        return $actions;
    }

    foreach ($actions as $key => $value) {
        if (strpos($key, 'inline') !== false) {
            unset($actions[$key]);
        }
    }

    return $actions;
}, 20, 2);
