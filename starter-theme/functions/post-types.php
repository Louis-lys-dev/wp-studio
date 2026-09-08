<?php
/**
 * Custom post types.
 *
 * Delete this file's registrations if the project has no CPTs, or move them to
 * a plugin if the content must survive a theme switch. Content registered by a
 * theme becomes unreachable the moment the theme is deactivated.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mytheme_register_post_types' );
/**
 * Register the theme's post types.
 */
function mytheme_register_post_types(): void {
	// Example. Rename or remove.
	register_post_type( 'project', [
		'labels'        => [
			'name'          => __( 'Projects', 'mytheme' ),
			'singular_name' => __( 'Project', 'mytheme' ),
			'add_new_item'  => __( 'Add New Project', 'mytheme' ),
			'edit_item'     => __( 'Edit Project', 'mytheme' ),
			'not_found'     => __( 'No projects found', 'mytheme' ),
		],
		'public'        => true,
		'has_archive'   => true,
		'rewrite'       => [ 'slug' => 'projects', 'with_front' => false ],
		'menu_icon'     => 'dashicons-portfolio',
		'menu_position' => 20,
		'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes' ],
		'show_in_rest'  => true,
	] );
}
