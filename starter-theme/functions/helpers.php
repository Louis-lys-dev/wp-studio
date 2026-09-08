<?php
/**
 * Small utilities used across templates.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Echo an <img> from an ACF image field (array return format), or nothing if
 * the field is empty.
 *
 * @param array<string,mixed>|false|null $image ACF image array.
 * @param string                         $size  Registered image size.
 * @param array<string,string>           $attr  Extra attributes.
 */
function mytheme_image( $image, string $size = 'large', array $attr = [] ): void {
	if ( empty( $image['ID'] ) ) {
		return;
	}

	echo wp_get_attachment_image( (int) $image['ID'], $size, false, $attr );
}

/**
 * Render a link from an ACF link field, or nothing if it is empty.
 *
 * @param array<string,string>|false|null $link  ACF link array.
 * @param string                          $class CSS class for the anchor.
 */
function mytheme_link( $link, string $class = 'btn' ): void {
	if ( empty( $link['url'] ) ) {
		return;
	}

	printf(
		'<a class="%s" href="%s"%s>%s</a>',
		esc_attr( $class ),
		esc_url( $link['url'] ),
		empty( $link['target'] ) ? '' : ' target="_blank" rel="noopener"',
		esc_html( $link['title'] ?: $link['url'] )
	);
}

/**
 * Trim text to a word count without cutting mid-word.
 *
 * @param string $text  Source text.
 * @param int    $words Maximum word count.
 * @return string
 */
function mytheme_excerpt( string $text, int $words = 25 ): string {
	return wp_trim_words( wp_strip_all_tags( $text ), $words, '&hellip;' );
}

/**
 * Build a class attribute string from a filtered list.
 *
 * @param array<int,string|false|null> $classes Class names; falsy entries drop out.
 * @return string
 */
function mytheme_classes( array $classes ): string {
	return esc_attr( implode( ' ', array_filter( array_map( 'trim', $classes ) ) ) );
}
