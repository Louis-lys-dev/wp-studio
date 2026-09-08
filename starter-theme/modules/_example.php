<?php
/**
 * Example module -- copy this file to create a new one.
 *
 * The filename must match the ACF Flexible Content layout name, with
 * underscores converted to hyphens: layout `hero_banner` -> `modules/hero-banner.php`.
 *
 * Inside a module, the current row is already active, so sub-fields are read
 * with get_sub_field() rather than get_field().
 *
 * $args comes from mytheme_module() and carries:
 *   - index  int    zero-based position of this module on the page
 *   - layout string the ACF layout name
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

$heading = get_sub_field( 'heading' );
$text    = get_sub_field( 'text' );
$image   = get_sub_field( 'image' );
$link    = get_sub_field( 'link' );

// Bail on an empty module rather than rendering an empty section.
if ( ! $heading && ! $text && ! $image ) {
	return;
}

$index = $args['index'] ?? 0;
?>

<section class="<?php echo mytheme_classes( [
	'module',
	'module--example',
	0 === $index ? 'module--first' : '',
] ); ?>">
	<div class="container">

		<?php if ( $heading ) : ?>
			<?php // First module on the page carries the h1. ?>
			<<?php echo 0 === $index ? 'h1' : 'h2'; ?> class="module__heading">
				<?php echo esc_html( $heading ); ?>
			</<?php echo 0 === $index ? 'h1' : 'h2'; ?>>
		<?php endif; ?>

		<?php if ( $text ) : ?>
			<div class="module__text">
				<?php echo wp_kses_post( $text ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $image ) : ?>
			<figure class="module__media">
				<?php mytheme_image( $image, 'hero', [ 'loading' => 'lazy' ] ); ?>
			</figure>
		<?php endif; ?>

		<?php mytheme_link( $link, 'btn module__cta' ); ?>

	</div>
</section>
