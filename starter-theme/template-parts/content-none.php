<?php
/**
 * Shown when a loop returns no posts.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;
?>

<section class="no-results">
	<h2 class="no-results__title"><?php esc_html_e( 'Nothing found', 'mytheme' ); ?></h2>

	<?php if ( is_search() ) : ?>
		<p><?php esc_html_e( 'No results matched your search. Try different keywords.', 'mytheme' ); ?></p>
		<?php get_search_form(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'There is nothing to show here yet.', 'mytheme' ); ?></p>
	<?php endif; ?>
</section>
