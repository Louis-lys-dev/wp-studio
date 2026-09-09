<?php
/**
 * 站点页脚与收尾标记。
 *
 * 由 get_footer() 调用。它闭合 header.php 打开的 <main>、<body>、<html>。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;
?>
</main><!-- .site-main -->

<footer class="site-footer" role="contentinfo">
	<div class="site-footer__inner">

		<?php
		// 小工具区。is_active_sidebar() 判断的是「这个区域里有没有小工具」，
		// 不是「这个区域有没有注册」——空的时候不输出外层容器。
		?>
		<?php if ( is_active_sidebar( 'footer-widgets' ) ) : ?>
			<div class="site-footer__widgets">
				<?php dynamic_sidebar( 'footer-widgets' ); ?>
			</div>
		<?php endif; ?>

		<?php // 页脚菜单。和页头同样的套路：先判断有没有挂菜单。 ?>
		<?php if ( has_nav_menu( 'footer' ) ) : ?>
			<nav class="site-footer__nav" aria-label="<?php esc_attr_e( 'Footer', 'mytheme' ); ?>">
				<?php
				wp_nav_menu( [
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'site-footer__list',
					'depth'          => 1, // 页脚一般不要下拉，限死一级
					'fallback_cb'    => false,
				] );
				?>
			</nav>
		<?php endif; ?>

		<p class="site-footer__copyright">
			<?php
			// 版权行。年份用 wp_date() 取，它按后台设置的时区算——
			// 用 PHP 原生的 date() 拿到的是服务器时区，跨年那几个小时会显示错。
			//
			// 占位符写成 %1$s / %2$s 而不是两个 %s，是为了让翻译者能调换顺序
			// （有些语言的语序和英文不同）。
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

<?php
// wp_footer() 是 </body> 之前的必备调用，对应 wp_head()。
// 所有 in_footer 的脚本（包括本主题的 main.js）都靠它输出，
// 后台工具栏、很多插件的功能也依赖它。漏掉这行的典型症状是「JS 全都不工作」。
wp_footer();
?>
</body>
</html>
