<?php
/**
 * Search results.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container">

	<header class="page-header">
		<h1 class="page-header__title">
			<?php
			printf(
				/* translators: %s: search query */
				esc_html__( 'Search results for: %s', 'mytheme' ),
				'<span>' . esc_html( get_search_query() ) . '</span>'
			);
			?>
		</h1>
		<?php get_search_form(); ?>
	</header>

	<?php if ( have_posts() ) : ?>

		<div class="post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'search' );
			endwhile;
			?>
		</div>

		<?php
		the_posts_pagination( [
			'mid_size'  => 2,
			'prev_text' => esc_html__( 'Previous', 'mytheme' ),
			'next_text' => esc_html__( 'Next', 'mytheme' ),
		] );
		?>

	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>

</div>

<?php
get_footer();
