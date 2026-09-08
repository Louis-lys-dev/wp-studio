<?php
/**
 * Custom taxonomies.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mytheme_register_taxonomies' );
/**
 * Register the theme's taxonomies.
 */
function mytheme_register_taxonomies(): void {
	// Example. Rename or remove.
	register_taxonomy( 'project_category', [ 'project' ], [
		'labels'            => [
			'name'          => __( 'Project Categories', 'mytheme' ),
			'singular_name' => __( 'Project Category', 'mytheme' ),
		],
		'public'            => true,
		'hierarchical'      => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => [ 'slug' => 'project-category', 'with_front' => false ],
	] );
}
