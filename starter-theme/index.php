<?php
/**
 * 兜底模板。
 *
 * 模板层级里的最后一站：任何请求，如果找不到更具体的模板，最终都会落到这里。
 * 它也是 WordPress 认定「这是一个主题」的必需文件——没有 index.php，
 * 后台根本不会把这个目录识别成主题。
 *
 * 所以它必须能渲染出像样的东西。空的 index.php 会让所有未匹配的 URL
 * 变成一个返回 200 的白页——搜索引擎会把它当成正常页面收录。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

// 输出页头（header.php）。
get_header();
?>

<div class="container">

	<?php
	// The Loop 的标准结构。
	// have_posts() 问「还有内容吗」，the_post() 取出下一条并把它设为「当前文章」，
	// 之后 the_title()、the_content() 这些函数才知道该输出谁。
	//
	// 这里的查询是 WordPress 根据 URL 自动跑好的（主查询），
	// 模板只负责把结果显示出来，不需要自己 new WP_Query。
	?>
	<?php if ( have_posts() ) : ?>

		<?php // 首页不显示这个标题栏（首页通常另有自己的 hero 区）。 ?>
		<?php if ( ! is_front_page() ) : ?>
			<header class="page-header">
				<?php // wp_get_document_title() 返回和 <title> 一致的文字，省得为每种归档单独判断。 ?>
				<h1 class="page-header__title"><?php echo esc_html( wp_get_document_title() ); ?></h1>
			</header>
		<?php endif; ?>

		<div class="post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				// 每条内容的卡片标记抽到 template-parts/ 里，列表页、归档页、搜索页共用。
				// 第二个参数传当前文章类型：如果存在 content-project.php，
				// WordPress 会优先用它，否则退回 content.php——
				// 这样给某个 CPT 单独定制卡片时，不用改这里的代码。
				get_template_part( 'template-parts/content', get_post_type() );
			endwhile;
			?>
		</div>

		<?php
		// 分页。mid_size 是当前页码两侧各显示几个数字。
		// 这个函数自己会判断「只有一页」的情况，不用额外加 if。
		the_posts_pagination( [
			'mid_size'  => 2,
			'prev_text' => esc_html__( 'Previous', 'mytheme' ),
			'next_text' => esc_html__( 'Next', 'mytheme' ),
		] );
		?>

	<?php else : ?>
		<?php // 一条都没有时的提示。这一支千万不能省——省了就是一片空白。 ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>

</div>

<?php
// 输出页脚（footer.php）。
get_footer();
