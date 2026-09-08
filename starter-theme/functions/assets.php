<?php
/**
 * Front-end and admin asset loading.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Version string for an asset, based on its modification time.
 *
 * Guards against a missing file: filemtime() on a nonexistent path emits a
 * warning on every request and returns false, which silently drops the cache
 * buster. Falls back to the theme version.
 *
 * @param string $relative Path relative to the theme root, e.g. 'assets/css/main.css'.
 * @return string
 */
function mytheme_asset_version( string $relative ): string {
	$path = MYTHEME_DIR . '/' . ltrim( $relative, '/' );

	return is_readable( $path ) ? (string) filemtime( $path ) : MYTHEME_VERSION;
}

/**
 * Full URI for a theme asset.
 *
 * @param string $relative Path relative to the theme root.
 * @return string
 */
function mytheme_asset_uri( string $relative ): string {
	return MYTHEME_URI . '/' . ltrim( $relative, '/' );
}

add_action( 'wp_enqueue_scripts', 'mytheme_enqueue_assets' );
/**
 * Enqueue front-end assets.
 */
function mytheme_enqueue_assets(): void {
	wp_enqueue_style(
		'mytheme',
		mytheme_asset_uri( 'assets/css/main.css' ),
		[],
		mytheme_asset_version( 'assets/css/main.css' )
	);

	wp_enqueue_script(
		'mytheme',
		mytheme_asset_uri( 'assets/js/main.js' ),
		[],
		mytheme_asset_version( 'assets/js/main.js' ),
		[
			'strategy'  => 'defer',
			'in_footer' => true,
		]
	);

	// Data the front-end JS needs. Never put secrets here -- this ends up in
	// the page source.
	wp_add_inline_script(
		'mytheme',
		'const ThemeData = ' . wp_json_encode( [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'restUrl'  => esc_url_raw( rest_url( 'mytheme/v1/' ) ),
			'nonce'    => wp_create_nonce( 'mytheme_nonce' ),
			'homeUrl'  => home_url( '/' ),
		] ) . ';',
		'before'
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

add_action( 'admin_enqueue_scripts', 'mytheme_enqueue_admin_assets' );
/**
 * Enqueue admin assets.
 */
function mytheme_enqueue_admin_assets(): void {
	$css = 'assets/css/admin.css';

	if ( is_readable( MYTHEME_DIR . '/' . $css ) ) {
		wp_enqueue_style(
			'mytheme-admin',
			mytheme_asset_uri( $css ),
			[],
			mytheme_asset_version( $css )
		);
	}
}

add_action( 'login_enqueue_scripts', 'mytheme_enqueue_login_assets' );
/**
 * Style the login screen through the normal asset pipeline rather than by
 * echoing a <style> block into login_head.
 */
function mytheme_enqueue_login_assets(): void {
	wp_enqueue_style( 'login' );

	$logo = get_theme_file_uri( 'assets/img/logo.svg' );

	wp_add_inline_style( 'login', sprintf(
		'.login h1 a{background-image:url(%s);background-size:contain;width:auto;height:60px}',
		esc_url( $logo )
	) );
}
