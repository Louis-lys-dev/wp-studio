<?php
/**
 * 404 template.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container">
	<section class="error-404">
		<h1 class="error-404__title"><?php esc_html_e( 'Page not found', 'mytheme' ); ?></h1>
		<p class="error-404__text">
			<?php esc_html_e( 'The page you are looking for does not exist or has been moved.', 'mytheme' ); ?>
		</p>
		<?php get_search_form(); ?>
		<a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Back to home', 'mytheme' ); ?>
		</a>
	</section>
</div>

<?php
get_footer();
