<?php
/**
 * Front-end hardening.
 *
 * Server-level rules (blocking xmlrpc.php, stopping PHP execution in the
 * uploads directory, rate-limiting wp-login.php) belong in the Nginx config --
 * see docs/bt-lnmp.md sections 7 and 19. This file covers only what has to
 * happen inside WordPress.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'xmlrpc_enabled', '__return_false' );

add_action( 'template_redirect', 'mytheme_block_author_enumeration' );
/**
 * Redirect author archives away.
 *
 * /?author=1 leaks the first administrator's login name, which is half of a
 * brute-force attempt. Drop this if the site genuinely uses author archives.
 */
function mytheme_block_author_enumeration(): void {
	if ( is_author() && ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}

add_filter( 'rest_endpoints', 'mytheme_restrict_rest_users' );
/**
 * Hide the REST users endpoint from anonymous requests.
 *
 * @param array<string,mixed> $endpoints Registered endpoints.
 * @return array<string,mixed>
 */
function mytheme_restrict_rest_users( array $endpoints ): array {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}

	return $endpoints;
}

add_filter( 'login_errors', fn() => __( 'The username or password is incorrect.', 'mytheme' ) );

add_filter( 'wp_headers', 'mytheme_security_headers' );
/**
 * Send security headers from PHP.
 *
 * Prefer setting these in Nginx. This is the fallback for shared hosting where
 * the server config is not editable.
 *
 * @param array<string,string> $headers Existing headers.
 * @return array<string,string>
 */
function mytheme_security_headers( array $headers ): array {
	$headers['X-Content-Type-Options'] = 'nosniff';
	$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';

	return $headers;
}
