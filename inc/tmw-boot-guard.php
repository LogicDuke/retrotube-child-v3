<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Determine whether heavy subsystems should boot.
 */
function tmw_should_boot_heavy_logic(): bool {
    // Never during cron.
    if (defined('DOING_CRON') && DOING_CRON) {
        $allowed = false;
        $reason = 'cron';
        error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' reason=' . $reason . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
        return $allowed;
    }

    // Restrict AJAX unless authenticated admins/editors.
    if (wp_doing_ajax()) {
        $allowed = is_admin() && is_user_logged_in() && current_user_can('edit_posts');
        $reason = $allowed ? 'ajax-admin-auth' : 'ajax-public';
        error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' reason=' . $reason . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
        return $allowed;
    }

    // Restrict REST unless authenticated admins/editors.
    if (defined('REST_REQUEST') && REST_REQUEST) {
        $allowed = is_user_logged_in() && current_user_can('edit_posts');
        $reason = $allowed ? 'rest-auth' : 'rest-public';
        error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' reason=' . $reason . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
        return $allowed;
    }

    // Skip during bulk trash.
    if (is_admin()) {
        $action = $_REQUEST['action'] ?? '';
        $action2 = $_REQUEST['action2'] ?? '';

        if ($action === 'trash' || $action2 === 'trash') {
            $allowed = false;
            $reason = 'admin-trash';
            error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' reason=' . $reason . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
            return $allowed;
        }
    }

    $allowed = true;
    $reason = 'default';
    error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' reason=' . $reason . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
    return $allowed;
}
