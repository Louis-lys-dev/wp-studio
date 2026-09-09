<?php
/**
 * 404 模板。
 *
 * 访问不存在的 URL 时显示，同时 WordPress 会发出 404 状态码
 * （不需要自己写 header()，模板层级匹配到这个文件时状态码已经设好了）。
 *
 * 这一页的目标是把人留住：给搜索框、给回首页的路，别只写一句「页面不存在」。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container">
	<section class="error-404">
		<h1 class="error-404__title"><?php esc_html_e( 'Page not found', 'mytheme' ); ?></h1>
		<p class="error-404__text">
			<?php esc_html_e( 'The page you are looking for does not exist or has been moved.', 'mytheme' ); ?>
		</p>
		<?php // 出路一：搜索。 ?>
		<?php get_search_form(); ?>
		<?php // 出路二：回首页。 ?>
		<a class="btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Back to home', 'mytheme' ); ?>
		</a>
	</section>
</div>

<?php
get_footer();
