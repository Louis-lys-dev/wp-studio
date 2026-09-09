<?php
/**
 * 归档模板：分类、标签、日期、自定义文章类型的列表页。
 *
 * 这一个文件同时兜住了好几种归档。需要单独定制时，按模板层级建更具体的文件：
 *   archive-project.php    某个 CPT 的列表
 *   category.php           分类归档
 *   taxonomy-{分类法}.php   某个自定义分类法
 * WordPress 会优先用更具体的那个，这里保持通用。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="container">

	<header class="page-header">
		<?php
		// the_archive_title() 自动按归档类型给出标题（「分类：新闻」这样）。
		// 想去掉前缀的话用 get_the_archive_title 过滤器改，别在这里手写判断——
		// 那样每加一种归档就得回来补一个分支。
		?>
		<h1 class="page-header__title"><?php the_archive_title(); ?></h1>
		<?php // 分类/标签在后台填的描述。没填就整个不输出。 ?>
		<?php the_archive_description( '<div class="page-header__description">', '</div>' ); ?>
	</header>

	<?php // 和 index.php 同一套循环结构，见那边的详细注释。 ?>
	<?php if ( have_posts() ) : ?>

		<div class="post-list">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', get_post_type() );
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
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>

</div>

<?php
get_footer();
