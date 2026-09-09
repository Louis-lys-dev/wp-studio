<?php
/**
 * 首页模板。
 *
 * 整页由 ACF 灵活内容模块拼出来，这个文件本身几乎不含任何标记。
 *
 * 生效条件要注意：只有当后台「设置 → 阅读」把首页设为「一个静态页面」时，
 * WordPress 才会用这个文件。如果首页设的是「最新文章」，
 * 它会走 home.php 或 index.php，这个文件会被完全跳过——
 * 「首页改了没反应」十次有九次是这个原因。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

get_header();

// 这里仍然要走一遍 The Loop：ACF 的 have_rows()/get_sub_field() 需要知道
// 「当前是哪篇文章」，而这个上下文是 the_post() 建立起来的。
// 首页只有一条内容，所以这个循环实际只转一圈，但不能省。
while ( have_posts() ) :
	the_post();

	// 一行渲染整个页面：按后台拖好的顺序，逐个输出 modules/ 下的模块。
	// 实现见 functions/acf.php。
	mytheme_render_modules();
endwhile;

get_footer();
