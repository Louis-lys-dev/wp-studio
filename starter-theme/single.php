<?php
/**
 * 单篇文章模板。
 *
 * 模板层级：单篇 post 走这里；自定义文章类型会先找 single-{类型}.php，
 * 找不到才退到这个文件。给 project 单独定制详情页时，
 * 复制一份改名 single-project.php 即可，不用动这里。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

// 单篇页的主查询里只有一条内容，循环只转一圈，但仍要走 The Loop
// 来建立「当前文章」的上下文。
while ( have_posts() ) :
	the_post();
	?>

	<article <?php post_class( 'single' ); ?>>
		<div class="container">

			<header class="single__header">
				<h1 class="single__title"><?php the_title(); ?></h1>
				<div class="single__meta">
					<?php
					// <time> 的 datetime 属性要机器可读的格式，'c' 就是 ISO 8601；
					// 标签里显示的则是后台设置的人类可读格式。两者分开写是有意的。
					?>
					<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
						<?php echo esc_html( get_the_date() ); ?>
					</time>
					<?php // 输出分类链接，参数是多个分类之间的分隔符。 ?>
					<?php the_category( ', ' ); ?>
				</div>
			</header>

			<?php // 有特色图才输出 <figure>，避免留下一个空壳子占着间距。 ?>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="single__thumbnail">
					<?php // 'hero' 是 functions/setup.php 里注册的尺寸。 ?>
					<?php the_post_thumbnail( 'hero' ); ?>
				</figure>
			<?php endif; ?>

			<div class="single__body">
				<?php
				the_content();

				wp_link_pages( [
					'before' => '<nav class="page-links">',
					'after'  => '</nav>',
				] );
				?>
			</div>

			<?php
			// the_tags() 的三个参数是：前缀、标签之间的分隔符、后缀。
			// 没有标签时整个（含前后缀）都不输出，所以不用自己加 if。
			the_tags( '<div class="single__tags">', ' ', '</div>' );
			?>

			<nav class="single__nav">
				<?php
				// 上一篇/下一篇。'%link' 是链接本身的占位符，
				// 第二个参数是链接文字，其中 '%title' 会被换成对应文章的标题。
				previous_post_link( '%link', '&larr; %title' );
				next_post_link( '%link', '%title &rarr;' );
				?>
			</nav>

			<?php
			// 评论区。两个条件是「评论开着」或者「已经有评论了」——
			// 后者保证关闭评论后，已有的评论不会凭空消失。
			if ( comments_open() || get_comments_number() ) {
				// comments_template() 会去找主题里的 comments.php；
				// 主题没有这个文件时，WordPress 用核心自带的那份，所以不会报错。
				comments_template();
			}
			?>

		</div>
	</article>

	<?php
endwhile;

get_footer();
