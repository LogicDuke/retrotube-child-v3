<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Determine whether heavy subsystems should boot.
 */
function tmw_should_boot_heavy_logic(): bool {
    // Never during AJAX.
    if (wp_doing_ajax()) {
        $allowed = false;
        error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' uri=' . ($_SERVER['REQUEST_URI'] ?? '')); 
        return $allowed;
    }

    // Never during cron.
    if (defined('DOING_CRON') && DOING_CRON) {
        $allowed = false;
        error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
        return $allowed;
    }

    // Never during REST.
    if (defined('REST_REQUEST') && REST_REQUEST) {
        $allowed = false;
        error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
        return $allowed;
    }

    // Skip during bulk trash.
    if (is_admin()) {
        $action = $_REQUEST['action'] ?? '';
        $action2 = $_REQUEST['action2'] ?? '';

        if ($action === 'trash' || $action2 === 'trash') {
            $allowed = false;
            error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
            return $allowed;
        }
    }

    $allowed = true;
    error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' uri=' . ($_SERVER['REQUEST_URI'] ?? ''));
    return $allowed;
}

