<?php
/**
 * ACF 接线：字段组 JSON 同步、选项页、模块渲染器。
 *
 * 这个文件是整套「模块化主题」的心脏。核心思路：
 *   页面内容 = ACF 灵活内容（Flexible Content）里的一串「布局」
 *   每个布局名 = modules/ 下的一个 PHP 文件
 *   顺序由编辑在后台拖拽决定，代码不关心顺序
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

// 告诉 ACF：在后台保存字段组时，同时把配置写成 JSON 存到主题的 acf-json/ 里。
// 这样字段配置就跟着代码走 Git，而不是只躺在数据库里。
add_filter( 'acf/settings/save_json', fn() => MYTHEME_DIR . '/acf-json' );

/**
 * 告诉 ACF：除了数据库，也从主题目录读取字段组。
 *
 * 加上上面那条 save_json，效果是：本地改字段 → JSON 文件变了 → 提交推送 →
 * 线上拉代码后字段自动就有了，不需要手工导出导入。
 *
 * unset( $paths[0] ) 是把 ACF 默认的加载路径（插件自己目录下那个）去掉，
 * 只留主题这一个来源。不去掉的话两边都会加载，容易出现同一个字段组两份的情况。
 *
 * 这里用匿名函数是因为逻辑短且只此一处用到，不需要给它起名字。
 *
 * @param string[] $paths ACF 已有的加载路径。
 * @return string[]
 */
add_filter( 'acf/settings/load_json', function ( array $paths ): array {
	unset( $paths[0] );
	$paths[] = MYTHEME_DIR . '/acf-json';

	return $paths;
} );

// acf/init 是 ACF 自己的初始化钩子，注册选项页必须挂在这里，
// 挂 init 会太早（ACF 还没准备好），挂太晚则菜单已经渲染完了。
add_action( 'acf/init', 'mytheme_acf_options_page' );
/**
 * 注册一个全站通用的选项页。
 *
 * 用来放不属于任何一篇内容的数据：页头联系电话、页脚地址、社交链接、
 * 全局的默认图等等。取值时 get_field( 'phone', 'option' ) —— 第二个参数
 * 固定传字符串 'option'。
 */
function mytheme_acf_options_page(): void {
	// 防御性检查：ACF 插件没装或者被停用时，这个函数不存在，
	// 直接调用就是致命错误、整站白屏。主题里凡是用插件的函数都要先这样挡一道。
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	acf_add_options_page( [
		'page_title' => __( 'Theme Settings', 'mytheme' ), // 页面里的大标题
		'menu_title' => __( 'Theme Settings', 'mytheme' ), // 左侧菜单里的文字
		'menu_slug'  => 'theme-settings',                  // URL 里的标识
		'capability' => 'manage_options',                  // 需要的权限，这里是管理员
		'position'   => 59,                                // 菜单位置，59 在「外观」上面一点
		'icon_url'   => 'dashicons-admin-generic',         // 菜单图标
		'redirect'   => false,                             // false = 点菜单直接进本页，
		                                                   // true 则会跳到第一个子页
	] );
}

/**
 * 渲染单个模块模板。
 *
 * 为什么不直接用 get_template_part()？因为它在文件不存在时是完全静默的：
 * 页面那一块就是空的，日志里什么都没有，你会怀疑是 ACF 没取到值。
 * 这层包装在开发环境下留下痕迹（HTML 注释 + 错误日志），排查快得多。
 *
 * @param string              $slug 模块别名，对应 modules/{slug}.php。
 * @param array<string,mixed> $args 传给模板，在模板里通过 $args 读取。
 */
function mytheme_module( string $slug, array $args = [] ): void {
	// ACF 的布局名习惯用下划线（hero_banner），文件名习惯用连字符（hero-banner.php），
	// 这里做转换。sanitize_key() 再兜一道底，防止布局名里有奇怪字符被拼进文件路径。
	$slug = sanitize_key( str_replace( '_', '-', $slug ) );
	$file = "modules/{$slug}.php";

	// locate_template() 会按「子主题 → 父主题」的顺序找文件，找不到返回空字符串。
	if ( ! locate_template( $file ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// 在页面源码里留一条注释（F12 就能看到是哪个模块缺文件）……
			printf( '<!-- missing module: %s -->', esc_html( $file ) );
			// ……同时写进 debug.log。
			error_log( "Missing module template: {$file}" );
		}

		return;
	}

	// 第三个参数 $args 会以 $args 变量的形式出现在模板文件里（WordPress 5.5+）。
	get_template_part( 'modules/' . $slug, null, $args );
}

/**
 * 按顺序渲染一个 ACF 灵活内容字段里的所有布局。
 *
 * 这就是整个页面构建机制：每个布局名对应 modules/ 下一个文件，
 * 顺序完全由后台编辑拖拽决定，模板文件（front-page.php 等）
 * 只需要调用这一个函数。
 *
 * @param string     $field   灵活内容字段的名字。
 * @param int|string $post_id 文章 ID；传 'option' 则读选项页上的模块。
 */
function mytheme_render_modules( string $field = 'modules', int|string $post_id = 0 ): void {
	// 传 0（默认）时用当前循环里的文章。
	// 注意 ?: 在这里是安全的：get_the_ID() 返回的合法 ID 不可能是 0。
	$post_id = $post_id ?: get_the_ID();

	// 两道检查合成一条：ACF 没装（have_rows 不存在）时直接退出，
	// 字段为空或没有任何行时也直接退出。
	if ( ! function_exists( 'have_rows' ) || ! have_rows( $field, $post_id ) ) {
		return;
	}

	// 记录当前是第几个模块（从 0 开始）。模块内部常常需要知道自己是不是
	// 页面第一块——第一块通常要承担 h1、要去掉顶部间距、要贴着页头。
	$index = 0;

	// have_rows / the_row 这一对和 The Loop 的 have_posts / the_post 是一个套路：
	// the_row() 把「当前行」切到下一条，之后 get_sub_field() 才能取到值。
	while ( have_rows( $field, $post_id ) ) {
		the_row();

		// get_row_layout() 返回当前这一行用的布局名，也就是模块文件名。
		mytheme_module( get_row_layout(), [
			'index'  => $index++,
			'layout' => get_row_layout(),
		] );
	}
}
