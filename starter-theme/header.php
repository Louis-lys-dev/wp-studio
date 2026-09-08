<?php
/**
 * Document head and site header.
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main">
	<?php esc_html_e( 'Skip to content', 'mytheme' ); ?>
</a>

<header class="site-header" role="banner">
	<div class="site-header__inner">

		<div class="site-header__brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="site-header__title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php bloginfo( 'name' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<nav class="site-nav" role="navigation" aria-label="<?php esc_attr_e( 'Primary', 'mytheme' ); ?>">
				<?php
				wp_nav_menu( [
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'site-nav__list',
					'depth'          => 2,
					'fallback_cb'    => false,
				] );
				?>
			</nav>
		<?php endif; ?>

		<button class="site-nav__toggle" type="button" aria-expanded="false" aria-controls="site-nav">
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'mytheme' ); ?></span>
		</button>

	</div>
</header>

<main id="main" class="site-main">
