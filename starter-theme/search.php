<?php
/**
 * 搜索结果页。
 *
 * 对应 URL 形如 /?s=关键词。查询由 WordPress 自动完成，
 * 这里只负责显示结果。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container">

	<header class="page-header">
		<h1 class="page-header__title">
			<?php
			// 关键词来自用户输入，是典型的不可信数据，必须转义。
			// 注意转义的位置：先把关键词 esc_html() 再拼进 <span>，
			// 整句用 esc_html__() 翻译。如果反过来把带 <span> 的字符串
			// 整体转义，标签会被当成文字打印出来。
			printf(
				/* translators: %s: search query */
				esc_html__( 'Search results for: %s', 'mytheme' ),
				'<span>' . esc_html( get_search_query() ) . '</span>'
			);
			?>
		</h1>
		<?php // 结果页顶部再放一个搜索框，方便直接改关键词重搜。 ?>
		<?php get_search_form(); ?>
	</header>

	<?php if ( have_posts() ) : ?>

		<div class="post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				// 这里固定传 'search'：搜索结果的卡片通常要突出摘要、
				// 弱化图片，和普通列表不一样。存在 content-search.php 就用它，
				// 没有则自动退回 content.php。
				get_template_part( 'template-parts/content', 'search' );
			endwhile;
			?>
		</div>

		<?php
		the_posts_pagination( [
			'mid_size'  => 2,
			'prev_text' => esc_html__( 'Previous', 'mytheme' ),
			'next_text' => esc_html__( 'Next', 'mytheme' ),
		] );
		?>

	<?php else : ?>
		<?php // 搜不到时走同一个「无结果」模板，它内部会判断是不是搜索页并给出不同文案。 ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>

</div>

<?php
get_footer();
