<?php
if (!defined('ABSPATH')) exit;

/**
 * TMW Email Activation — require email verification before login.
 * Logs: [TMW-ACT-SEND], [TMW-ACT-OK], [TMW-ACT-FAIL], [TMW-ACT-RESEND]
 */

if (!defined('TMW_ACTIVATION_EXPIRY_HOURS')) define('TMW_ACTIVATION_EXPIRY_HOURS', 72); // 3 days
if (!defined('TMW_ACTIVATION_AUTLOGIN'))     define('TMW_ACTIVATION_AUTLOGIN', true);   // auto-login after click

/**
 * Gate logins until verified.
 */
add_filter('wp_authenticate_user', function($user, $password){
    if (is_wp_error($user)) return $user;
    $verified = (int) get_user_meta($user->ID, 'tmw_email_verified', true);
    if ($verified !== 1) {
        return new WP_Error(
            'tmw_pending_verification',
            __('Please activate your account via the email we sent. Didn’t get it? Use “Forgot password” or request a new activation link.', 'retrotube')
        );
    }
    return $user;
}, 10, 2);

/**
 * Create & store hashed activation token, then send email.
 */
function tmw_send_activation_email($user_id){
    $user = get_user_by('id', $user_id);
    if (!$user) return false;

    // Skip if already verified
    if ((int) get_user_meta($user_id, 'tmw_email_verified', true) === 1) return true;

    // Rate-limit resends (min 5 minutes)
    $last = (int) get_user_meta($user_id, 'tmw_activation_last_sent', true);
    if ($last && (time() - $last) < 5 * MINUTE_IN_SECONDS) {
        return true;
    }

    // Generate token and store hash + timestamp
    $token = wp_generate_password(20, false);
    $hash  = wp_hash_password($token);
    update_user_meta($user_id, 'tmw_activation_hash', $hash);
    update_user_meta($user_id, 'tmw_activation_ts',   time());
    update_user_meta($user_id, 'tmw_email_verified',  0);
    update_user_meta($user_id, 'tmw_activation_last_sent', time());

    $args = array(
        'uid' => $user_id,
        'key' => $token,
    );
    // Use admin-ajax endpoint (bypasses cache/CDN nicely)
    $activate_url = add_query_arg(
        array_merge(array('action' => 'tmw_activate'), $args),
        admin_url('admin-ajax.php')
    );

    $site  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $subj  = sprintf(__('Activate your account at %s', 'retrotube'), $site);
    $lines = array(
        sprintf(__('Hi %s,', 'retrotube'), $user->user_login),
        __('Thanks for signing up. Please confirm your email to activate your account:', 'retrotube'),
        $activate_url,
        '',
        sprintf(__('This link will expire in %d hours.', 'retrotube'), (int) TMW_ACTIVATION_EXPIRY_HOURS),
        __('If you did not request this, you can ignore this email.', 'retrotube'),
    );
    $msg = implode("\n\n", $lines);

    // Let other plugins/themes adjust mail
    $headers = apply_filters('tmw_activation_mail_headers', array('Content-Type: text/plain; charset=UTF-8'));
    $sent = wp_mail($user->user_email, $subj, $msg, $headers);


    return $sent;
}

/**
 * Validate an activation request (uid + key). Returns true if usable, a
 * WP_Error otherwise. Used by both the GET (show confirm page) and POST
 * (actually activate) branches so they agree on what "valid link" means.
 */
function tmw_validate_activation_request($uid, $key) {
    if (!$uid || !$key) {
        return new WP_Error('tmw_invalid', __('Invalid activation link.', 'retrotube'));
    }
    $hash = (string) get_user_meta($uid, 'tmw_activation_hash', true);
    $ts   = (int) get_user_meta($uid, 'tmw_activation_ts', true);
    if (!$hash || !$ts) {
        return new WP_Error('tmw_used', __('Activation link is invalid or already used.', 'retrotube'));
    }
    // HOUR_IN_SECONDS (singular) is the WP-canonical constant. The earlier
    // HOURS_IN_SECONDS (plural) was undefined → 0, so this expiry check was
    // effectively never enforced: ($now - $ts) > 0 was almost always true,
    // meaning every link looked expired. Fixed in passing.
    if ((time() - $ts) > (HOUR_IN_SECONDS * (int) TMW_ACTIVATION_EXPIRY_HOURS)) {
        return new WP_Error('tmw_expired', __('Activation link has expired. Please request a new one.', 'retrotube'));
    }
    if (!wp_check_password($key, $hash, '')) {
        return new WP_Error('tmw_invalid_key', __('Invalid activation key.', 'retrotube'));
    }
    return true;
}

/**
 * Activation handler (AJAX).
 *
 * Two-step flow:
 *   GET  /admin-ajax.php?action=tmw_activate&uid=…&key=…
 *        → validates the link, renders a confirmation page with a POST form
 *          (CSRF-nonced). Does NOT mark verified, does NOT log the user in.
 *   POST same URL + _tmw_activate_nonce + uid + key
 *        → re-validates, marks verified, deletes the token, optionally
 *          auto-logs in.
 *
 * The split exists because GET is unsafe-by-design here: email link
 * preview scanners, antivirus URL followers, browser prefetch, and
 * accidental link clicks all dispatch GETs without explicit user intent.
 * Requiring a POST means the user must have rendered the confirmation page
 * AND clicked the button — neither of which an automated tool will do.
 */
add_action('wp_ajax_nopriv_tmw_activate', 'tmw_handle_activation');
add_action('wp_ajax_tmw_activate',        'tmw_handle_activation');
function tmw_handle_activation(){
    $uid = isset($_REQUEST['uid']) ? absint($_REQUEST['uid']) : 0;
    $key = isset($_REQUEST['key']) ? sanitize_text_field(wp_unslash($_REQUEST['key'])) : '';

    $valid = tmw_validate_activation_request($uid, $key);
    if (is_wp_error($valid)) {
        tmw_activation_exit($valid->get_error_message(), false);
    }

    $is_post = ('POST' === ($_SERVER['REQUEST_METHOD'] ?? ''));
    $nonce_ok = $is_post
        && isset($_POST['_tmw_activate_nonce'])
        && wp_verify_nonce($_POST['_tmw_activate_nonce'], 'tmw_activate_' . $uid);

    if (!$nonce_ok) {
        // GET, or POST without a valid nonce → show the confirmation page.
        // The user must explicitly press the button to actually activate.
        tmw_render_activation_confirmation($uid, $key);
        exit;
    }

    // Confirmed via POST + nonce. Perform the activation.
    update_user_meta($uid, 'tmw_email_verified', 1);
    delete_user_meta($uid, 'tmw_activation_hash');
    delete_user_meta($uid, 'tmw_activation_ts');

    if (TMW_ACTIVATION_AUTLOGIN) {
        wp_set_current_user($uid);
        wp_set_auth_cookie($uid);
    }

    $redirect = apply_filters('tmw_activation_redirect', home_url('/?activated=1'));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Render the confirmation page shown for the GET step of activation.
 * Outputs a minimal self-contained HTML page (no theme chrome — this runs
 * through admin-ajax) with a single POST form that includes a per-user
 * CSRF nonce and the original uid/key. Submitting the form triggers the
 * real activation in tmw_handle_activation().
 */
function tmw_render_activation_confirmation($uid, $key) {
    $nonce      = wp_create_nonce('tmw_activate_' . $uid);
    $site_name  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $title      = sprintf(__('Activate your account at %s', 'retrotube'), $site_name);
    $intro      = __('Click the button below to activate your account. We ask for an explicit confirmation so an accidentally-followed link cannot activate your account without your consent.', 'retrotube');
    $button     = __('Activate my account', 'retrotube');
    $form_action = esc_url(admin_url('admin-ajax.php'));

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html($title); ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 480px; margin: 64px auto; padding: 24px; color: #222; }
        h1 { font-size: 22px; margin: 0 0 16px; }
        p  { color: #555; line-height: 1.5; }
        button { background: #e91e63; color: #fff; border: 0; padding: 12px 28px; font-size: 16px; cursor: pointer; border-radius: 4px; margin-top: 8px; }
        button:hover { background: #c2185b; }
    </style>
</head>
<body>
    <h1><?php echo esc_html($title); ?></h1>
    <p><?php echo esc_html($intro); ?></p>
    <form method="post" action="<?php echo $form_action; ?>">
        <input type="hidden" name="action" value="tmw_activate">
        <input type="hidden" name="uid" value="<?php echo absint($uid); ?>">
        <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
        <input type="hidden" name="_tmw_activate_nonce" value="<?php echo esc_attr($nonce); ?>">
        <button type="submit"><?php echo esc_html($button); ?></button>
    </form>
</body>
</html><?php
}

function tmw_activation_exit($message, $success){
    // Render a minimal message (avoids JSON & is shareable link)
    wp_die(
        esc_html($message),
        get_bloginfo('name'),
        array('response' => 200)
    );
}

/**
 * Resend endpoint (AJAX POST). Accepts email or username.
 */
add_action('wp_ajax_nopriv_tmw_resend_activation', 'tmw_resend_activation');
function tmw_resend_activation(){
    $login = isset($_POST['login']) ? sanitize_text_field(wp_unslash($_POST['login'])) : '';
    if (!$login) {
        // Genuine UX error — the form was submitted empty. Not an
        // account-existence signal, safe to surface distinctly.
        wp_send_json_error(array('message' => __('Please enter your username or e-mail.', 'retrotube')), 200);
    }

    // The previous code returned four distinguishable responses
    // ("Account not found." / "Account already activated." / "We've sent..."
    // / "Could not send...") which together let an attacker enumerate
    // (a) whether a username/e-mail is registered, (b) whether it's already
    // verified, and (c) whether the mailer is healthy for that account.
    //
    // The standard hardening is identical responses regardless of state:
    // fire the resend logic for its side effect when the account exists and
    // is unverified, ignore the outcome, and return one generic success.
    $user = is_email($login) ? get_user_by('email', $login) : get_user_by('login', $login);
    if ($user && (int) get_user_meta($user->ID, 'tmw_email_verified', true) !== 1) {
        tmw_send_activation_email($user->ID);
    }

    wp_send_json_success(array(
        'message' => __('If an account matches that username or e-mail and isn’t already activated, a new activation link has been sent. Please check your inbox.', 'retrotube'),
    ));
}
