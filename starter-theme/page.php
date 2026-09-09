<?php
/**
 * 默认页面模板。
 *
 * 双轨制：页面配了 ACF 模块就渲染模块栈，没配就退回显示编辑器里的正文。
 * 这样一个页面在模块还没填之前也是能看的——对交付很重要，
 * 客户新建一个页面不会得到一片空白然后来问你是不是坏了。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	// 两道检查：ACF 装了没（插件停用时 have_rows 不存在），以及这一页有没有模块。
	if ( function_exists( 'have_rows' ) && have_rows( 'modules' ) ) {
		mytheme_render_modules();
	} else {
		// 退路：显示古腾堡/经典编辑器里的正文。
		?>
		<?php // post_class() 输出这篇内容相关的一串 class，还可以额外加自己的。 ?>
		<article <?php post_class( 'page-content' ); ?>>
			<div class="container">
				<h1 class="page-content__title"><?php the_title(); ?></h1>
				<div class="page-content__body">
					<?php
					// the_content() 输出正文。它不需要也不能再包 esc_*——
					// 正文本来就是 HTML，转义了会把标签当文字打印出来。
					// 内容安全由 WordPress 在保存时按用户角色过滤。
					the_content();

					// 编辑在正文里插入了 <!--nextpage--> 分页符时，这里输出页码。
					// 没有分页符时它什么都不输出，可以放心留着。
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
