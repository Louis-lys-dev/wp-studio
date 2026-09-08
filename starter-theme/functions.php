<?php
/**
 * Theme bootstrap.
 *
 * Nothing but wiring lives here. Each concern gets its own file under
 * functions/ so this stays readable as the theme grows.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

define( 'MYTHEME_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'MYTHEME_DIR', get_template_directory() );
define( 'MYTHEME_URI', get_template_directory_uri() );

/**
 * Load a functions/ file, failing loudly in development and quietly in
 * production. A missing include here would otherwise take the whole site down
 * with a fatal error on every request.
 */
foreach ( [
	'helpers',
	'setup',
	'assets',
	'acf',
	'post-types',
	'taxonomies',
	'cleanup',
	'security',
] as $file ) {
	$path = MYTHEME_DIR . "/functions/{$file}.php";

	if ( is_readable( $path ) ) {
		require_once $path;
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		trigger_error(
			esc_html( "Missing theme include: functions/{$file}.php" ),
			E_USER_WARNING
		);
	}
}
