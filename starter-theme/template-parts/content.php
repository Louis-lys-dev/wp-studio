<?php
/**
 * One entry in a post list.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;
?>

<article <?php post_class( 'card' ); ?>>

	<?php if ( has_post_thumbnail() ) : ?>
		<a class="card__media" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php the_post_thumbnail( 'card', [ 'loading' => 'lazy' ] ); ?>
		</a>
	<?php endif; ?>

	<div class="card__body">
		<h2 class="card__title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>

		<div class="card__meta">
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
				<?php echo esc_html( get_the_date() ); ?>
			</time>
		</div>

		<div class="card__excerpt">
			<?php the_excerpt(); ?>
		</div>
	</div>

</article>
