<?php
/**
 * Trim WordPress output the theme does not use.
 *
 * Every removal here is a decision. Re-check the list against the current
 * WordPress version before copying it into another project -- several of the
 * hooks people habitually remove no longer exist.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mytheme_cleanup_head' );
/**
 * Remove unused wp_head output.
 */
function mytheme_cleanup_head(): void {
	remove_action( 'wp_head', 'wp_generator' );                        // Version number.
	remove_action( 'wp_head', 'rsd_link' );                            // Really Simple Discovery.
	remove_action( 'wp_head', 'wlwmanifest_link' );                    // Windows Live Writer.
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
}

add_filter( 'the_generator', '__return_empty_string' );

add_action( 'wp_enqueue_scripts', 'mytheme_dequeue_block_styles', 100 );
/**
 * Drop the block editor's front-end stylesheet.
 *
 * Only do this when the theme does not use core blocks on the front end --
 * an ACF-driven modular theme usually does not.
 */
function mytheme_dequeue_block_styles(): void {
	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
}

add_filter( 'emoji_svg_url', '__return_false' );
add_action( 'init', 'mytheme_disable_emojis' );
/**
 * Remove the emoji detection script and its DNS prefetch.
 */
function mytheme_disable_emojis(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}

add_filter( 'nav_menu_css_class', 'mytheme_nav_menu_css_class', 10, 1 );
/**
 * Add a shorter alias for the current-item class.
 *
 * Done with the filter rather than a str_replace() over the rendered menu,
 * which would also hit the string wherever it appears in titles or URLs.
 *
 * @param string[] $classes Menu item classes.
 * @return string[]
 */
function mytheme_nav_menu_css_class( array $classes ): array {
	if ( array_intersect( $classes, [ 'current-menu-item', 'current-menu-ancestor', 'current_page_parent' ] ) ) {
		$classes[] = 'is-active';
	}

	return $classes;
}
