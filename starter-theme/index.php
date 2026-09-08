<?php
/**
 * Fallback template.
 *
 * The last resort in the template hierarchy: any request that matches no more
 * specific template lands here. It must render something meaningful -- an empty
 * index.php turns every unmatched URL into a blank 200 page.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container">

	<?php if ( have_posts() ) : ?>

		<?php if ( ! is_front_page() ) : ?>
			<header class="page-header">
				<h1 class="page-header__title"><?php echo esc_html( wp_get_document_title() ); ?></h1>
			</header>
		<?php endif; ?>

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
