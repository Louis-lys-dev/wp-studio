<?php
/**
 * Theme supports, menus and sidebars.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', 'mytheme_setup' );
/**
 * Declare what the theme supports.
 */
function mytheme_setup(): void {
	load_theme_textdomain( 'mytheme', MYTHEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );

	add_theme_support( 'html5', [
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	] );

	register_nav_menus( [
		'primary' => __( 'Primary Menu', 'mytheme' ),
		'footer'  => __( 'Footer Menu', 'mytheme' ),
	] );

	// Image sizes. Add only what the design actually uses -- every size here
	// multiplies the disk space and processing cost of each upload.
	add_image_size( 'card', 640, 480, true );
	add_image_size( 'hero', 1920, 900, true );
}

add_action( 'widgets_init', 'mytheme_widgets_init' );
/**
 * Register sidebars. Remove this entirely if the design has no widget areas.
 */
function mytheme_widgets_init(): void {
	register_sidebar( [
		'name'          => __( 'Footer Widgets', 'mytheme' ),
		'id'            => 'footer-widgets',
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget__title">',
		'after_title'   => '</h4>',
	] );
}

add_filter( 'excerpt_length', fn() => 25 );
add_filter( 'excerpt_more', fn() => '&hellip;' );

add_filter( 'body_class', 'mytheme_body_class' );
/**
 * Add a class naming the current template, useful as a styling hook.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function mytheme_body_class( array $classes ): array {
	if ( is_page() ) {
		$classes[] = 'page--' . sanitize_html_class( get_post_field( 'post_name' ) );
	}

	return $classes;
}
