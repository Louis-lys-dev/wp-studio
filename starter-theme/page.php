<?php
/**
 * Default page template.
 *
 * Renders the ACF module stack when the page has one, and falls back to the
 * editor content when it does not -- so a page still works before its modules
 * are filled in.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	if ( function_exists( 'have_rows' ) && have_rows( 'modules' ) ) {
		mytheme_render_modules();
	} else {
		?>
		<article <?php post_class( 'page-content' ); ?>>
			<div class="container">
				<h1 class="page-content__title"><?php the_title(); ?></h1>
				<div class="page-content__body">
					<?php
					the_content();

					wp_link_pages( [
						'before' => '<nav class="page-links">',
						'after'  => '</nav>',
					] );
					?>
				</div>
			</div>
		</article>
		<?php
	}

endwhile;

get_footer();
