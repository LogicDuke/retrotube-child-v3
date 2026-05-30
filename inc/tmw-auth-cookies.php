<?php
/**
 * TMW Auth Cookies — explicit SameSite on WordPress auth cookies.
 *
 * Why this file exists:
 *   WordPress did not set the SameSite attribute on its auth / logged-in /
 *   session-token cookies until WP 6.4 (Nov 2023). Before then, the SameSite
 *   behaviour fell back to whatever the browser defaulted to — modern
 *   browsers default to Lax, but older browsers and embedded webviews don't,
 *   leaving a CSRF-shaped gap. WP 6.4+ defaults to Lax via the new
 *   `wp_set_auth_cookie_options` filter; this file forces Lax explicitly so
 *   the behaviour is the same whether the live site runs current WP or an
 *   older release that happens to expose the filter.
 *
 * Why Lax and not Strict:
 *   Strict means a user clicking a link from another site (e.g. an outbound
 *   affiliate link, a search-engine result, a social share) lands on
 *   top-models.webcam in a logged-OUT state even when their auth cookie is
 *   valid, because the browser refuses to send it on cross-origin top-level
 *   navigation. Lax sends the cookie on top-level GETs but withholds it
 *   from cross-origin subresource requests (the actual CSRF surface). For
 *   an auth cookie this is the right balance and matches WP core's own
 *   default since 6.4.
 *
 * Notes:
 *   - On WP < 6.4, this filter does not fire. No way to backport explicit
 *     SameSite to old wp_set_auth_cookie() without monkey-patching core;
 *     pre-6.4 deployments fall back to browser-default Lax, which is the
 *     same effective behaviour for the browsers that matter.
 *   - The filter receives a $scheme argument identifying which cookie
 *     (auth / secure_auth / logged_in). We force Lax for all three; if a
 *     future need calls for per-scheme variation (e.g. Strict on logged_in
 *     but Lax on auth), the $scheme switch is the place to add it.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter(
    'wp_set_auth_cookie_options',
    static function (array $options): array {
        $options['samesite'] = 'Lax';
        return $options;
    },
    10,
    1
);
