<?php
/**
 * Search form.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

$mytheme_search_id = wp_unique_id( 'search-field-' );
?>
<form class="search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $mytheme_search_id ); ?>">
		<?php esc_html_e( 'Search for:', 'mytheme' ); ?>
	</label>
	<input
		id="<?php echo esc_attr( $mytheme_search_id ); ?>"
		class="search-form__input"
		type="search"
		name="s"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Search&hellip;', 'mytheme' ); ?>"
		required
	>
	<button class="search-form__submit" type="submit">
		<?php esc_html_e( 'Search', 'mytheme' ); ?>
	</button>
</form>
