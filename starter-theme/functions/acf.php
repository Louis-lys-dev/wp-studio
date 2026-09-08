<?php
/**
 * ACF integration: JSON sync, options page, and the module renderer.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'acf/settings/save_json', fn() => MYTHEME_DIR . '/acf-json' );
/**
 * Load field groups from the theme as well as the database, so field configs
 * travel with the code instead of being imported by hand on every environment.
 *
 * @param string[] $paths Existing load paths.
 * @return string[]
 */
add_filter( 'acf/settings/load_json', function ( array $paths ): array {
	unset( $paths[0] );
	$paths[] = MYTHEME_DIR . '/acf-json';

	return $paths;
} );

add_action( 'acf/init', 'mytheme_acf_options_page' );
/**
 * Register a site-wide options page for header/footer/contact data.
 */
function mytheme_acf_options_page(): void {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	acf_add_options_page( [
		'page_title' => __( 'Theme Settings', 'mytheme' ),
		'menu_title' => __( 'Theme Settings', 'mytheme' ),
		'menu_slug'  => 'theme-settings',
		'capability' => 'manage_options',
		'position'   => 59,
		'icon_url'   => 'dashicons-admin-generic',
		'redirect'   => false,
	] );
}

/**
 * Render one module template.
 *
 * get_template_part() fails silently when the file is missing -- the page just
 * comes out empty with nothing in the logs. This wrapper leaves a trace.
 *
 * @param string              $slug Module slug, matching modules/{slug}.php.
 * @param array<string,mixed> $args Passed through to the template as $args.
 */
function mytheme_module( string $slug, array $args = [] ): void {
	$slug = sanitize_key( str_replace( '_', '-', $slug ) );
	$file = "modules/{$slug}.php";

	if ( ! locate_template( $file ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			printf( '<!-- missing module: %s -->', esc_html( $file ) );
			error_log( "Missing module template: {$file}" );
		}

		return;
	}

	get_template_part( 'modules/' . $slug, null, $args );
}

/**
 * Render every layout of an ACF Flexible Content field in order.
 *
 * This is the whole page-building mechanism: each layout name maps to one file
 * in modules/, and the editor decides the order.
 *
 * @param string   $field   Flexible Content field name.
 * @param int|string $post_id Post ID, or 'option' for the options page.
 */
function mytheme_render_modules( string $field = 'modules', int|string $post_id = 0 ): void {
	$post_id = $post_id ?: get_the_ID();

	if ( ! function_exists( 'have_rows' ) || ! have_rows( $field, $post_id ) ) {
		return;
	}

	$index = 0;

	while ( have_rows( $field, $post_id ) ) {
		the_row();

		mytheme_module( get_row_layout(), [
			'index'  => $index++,
			'layout' => get_row_layout(),
		] );
	}
}
