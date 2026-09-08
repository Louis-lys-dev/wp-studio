<?php
/**
 * Archive template: categories, tags, dates, custom post types.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container">

	<header class="page-header">
		<h1 class="page-header__title"><?php the_archive_title(); ?></h1>
		<?php the_archive_description( '<div class="page-header__description">', '</div>' ); ?>
	</header>

	<?php if ( have_posts() ) : ?>

		<div class="post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', get_post_type() );
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
