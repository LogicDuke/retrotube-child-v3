<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Log a boot-guard decision.
 *
 * Logs the request PATH only — never the query string. REQUEST_URI carries
 * password-reset keys (?key=...), activation tokens (?action=tmw_activate&...),
 * and one-time nonces; writing those to the PHP error log on every request
 * means anyone with log read access (shared-hosting staff, ops, SIEM taps,
 * backup tarballs) can harvest tokens after the fact, and anyone with live
 * `tail -F` can harvest them as users click the links.
 *
 * Centralised so a future log site can't copy-paste the old REQUEST_URI
 * pattern back in by accident.
 */
function tmw_guard_log(bool $allowed, string $reason): void {
    // Quiet by default. The previous version wrote a line on every PHP
    // request, which on a busy site buries real errors under harmless guard
    // chatter. Enabled by either:
    //   - WP_DEBUG  (dev environments, matches existing project convention)
    //   - TMW_GUARD_LOG  (targeted prod debugging without flipping all of
    //     WP_DEBUG; add `define('TMW_GUARD_LOG', true);` to wp-config.php)
    $debug = (defined('TMW_GUARD_LOG') && TMW_GUARD_LOG)
        || (defined('WP_DEBUG') && WP_DEBUG);
    if (!$debug) {
        return;
    }
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($path)) {
        $path = '';
    }
    error_log('[TMW-GUARD] Heavy boot allowed=' . ($allowed ? 'YES' : 'NO') . ' reason=' . $reason . ' path=' . $path);
}

/**
 * Determine whether heavy subsystems should boot.
 */
function tmw_should_boot_heavy_logic(): bool {
    // Never during cron.
    if (defined('DOING_CRON') && DOING_CRON) {
        tmw_guard_log(false, 'cron');
        return false;
    }

    // Restrict AJAX unless authenticated admins/editors.
    if (wp_doing_ajax()) {
        $allowed = is_admin() && is_user_logged_in() && current_user_can('edit_posts');
        tmw_guard_log($allowed, $allowed ? 'ajax-admin-auth' : 'ajax-public');
        return $allowed;
    }

    // Restrict REST unless authenticated admins/editors.
    if (defined('REST_REQUEST') && REST_REQUEST) {
        $allowed = is_user_logged_in() && current_user_can('edit_posts');
        tmw_guard_log($allowed, $allowed ? 'rest-auth' : 'rest-public');
        return $allowed;
    }

    // Skip during bulk trash.
    if (is_admin()) {
        $action = $_REQUEST['action'] ?? '';
        $action2 = $_REQUEST['action2'] ?? '';

        if ($action === 'trash' || $action2 === 'trash') {
            tmw_guard_log(false, 'admin-trash');
            return false;
        }
    }

    tmw_guard_log(true, 'default');
    return true;
}
