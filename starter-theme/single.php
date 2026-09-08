<?php
/**
 * Single post.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>

	<article <?php post_class( 'single' ); ?>>
		<div class="container">

			<header class="single__header">
				<h1 class="single__title"><?php the_title(); ?></h1>
				<div class="single__meta">
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
						<?php echo esc_html( get_the_date() ); ?>
					</time>
					<?php the_category( ', ' ); ?>
				</div>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="single__thumbnail">
					<?php the_post_thumbnail( 'hero' ); ?>
				</figure>
			<?php endif; ?>

			<div class="single__body">
				<?php
				the_content();

				wp_link_pages( [
					'before' => '<nav class="page-links">',
					'after'  => '</nav>',
				] );
				?>
			</div>

			<?php the_tags( '<div class="single__tags">', ' ', '</div>' ); ?>

			<nav class="single__nav">
				<?php previous_post_link( '%link', '&larr; %title' ); ?>
				<?php next_post_link( '%link', '%title &rarr;' ); ?>
			</nav>

			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
			?>

		</div>
	</article>

	<?php
endwhile;

get_footer();
