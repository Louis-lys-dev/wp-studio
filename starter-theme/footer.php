<?php
/**
 * Site footer and closing markup.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;
?>
</main><!-- .site-main -->

<footer class="site-footer" role="contentinfo">
	<div class="site-footer__inner">

		<?php if ( is_active_sidebar( 'footer-widgets' ) ) : ?>
			<div class="site-footer__widgets">
				<?php dynamic_sidebar( 'footer-widgets' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav class="site-footer__nav" aria-label="<?php esc_attr_e( 'Footer', 'mytheme' ); ?>">
				<?php
				wp_nav_menu( [
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'site-footer__list',
					'depth'          => 1,
					'fallback_cb'    => false,
				] );
				?>
			</nav>
		<?php endif; ?>

		<p class="site-footer__copyright">
			<?php
			printf(
				/* translators: 1: current year, 2: site name */
				esc_html__( '&copy; %1$s %2$s. All rights reserved.', 'mytheme' ),
				esc_html( wp_date( 'Y' ) ),
				esc_html( get_bloginfo( 'name' ) )
			);
			?>
		</p>

	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
