<?php
/**
 * Front page.
 *
 * Built entirely from ACF Flexible Content modules. If the site is set to show
 * latest posts on the front page, WordPress uses home.php or index.php instead
 * and this file is skipped.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	mytheme_render_modules();
endwhile;

get_footer();
