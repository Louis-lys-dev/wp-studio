<?php
/**
 * 主题支持特性、导航菜单、侧边栏。
 *
 * 这个文件回答一个问题：这个主题「支持」什么？
 * WordPress 很多功能默认是关的（缩略图、title 标签、HTML5 表单标记），
 * 主题必须显式声明支持，后台才会出现对应的界面。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

// after_setup_theme 是主题能用的最早的钩子之一：主题文件已加载，
// 但 WordPress 还没开始处理这次请求。声明主题特性必须挂在这里，
// 挂晚了（比如 init）有些特性 WordPress 已经检查过了，不生效。
add_action( 'after_setup_theme', 'mytheme_setup' );
/**
 * 声明主题支持的特性。
 */
function mytheme_setup(): void {
	// 加载翻译文件。语言包放在 languages/ 下，文件名是「语言代码.mo」，
	// 例如 zh_CN.mo。第一个参数必须和 style.css 里的 Text Domain 一致。
	load_theme_textdomain( 'mytheme', MYTHEME_DIR . '/languages' );

	// title-tag：让 WordPress 自己输出 <title>。
	// 声明了这个就不要再在 header.php 里手写 <title>，会重复。
	add_theme_support( 'title-tag' );

	// post-thumbnails：开启「特色图片」。不声明的话后台编辑页右侧
	// 根本不会出现特色图片那个框，the_post_thumbnail() 也永远是空。
	add_theme_support( 'post-thumbnails' );

	// 在 <head> 里自动输出 RSS 订阅链接。
	add_theme_support( 'automatic-feed-links' );

	// 定制器里改小工具时局部刷新，不整页重载。
	add_theme_support( 'customize-selective-refresh-widgets' );

	// 让嵌入的视频（YouTube 等）自适应容器宽度。
	add_theme_support( 'responsive-embeds' );

	// 让这几个 WordPress 自动生成的组件输出 HTML5 标记，
	// 而不是老式的 XHTML。不声明的话搜索框会带上 XHTML 的自闭合斜杠等旧写法。
	add_theme_support( 'html5', [
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	] );

	// 注册导航菜单位置。这里注册的是「位置」，不是菜单本身——
	// 菜单内容由客户在后台「外观 → 菜单」里建好后挂到这些位置上。
	// 数组的键在代码里用（wp_nav_menu 的 theme_location），值是后台显示的名字。
	register_nav_menus( [
		'primary' => __( 'Primary Menu', 'mytheme' ),
		'footer'  => __( 'Footer Menu', 'mytheme' ),
	] );

	// 自定义图片尺寸。第四个参数 true 表示硬裁剪（严格按这个宽高裁），
	// false 是等比缩放到不超过这个框。
	// 只加设计稿真正用到的尺寸——每多一个尺寸，每张上传的图就多生成一个文件，
	// 磁盘占用和上传耗时都跟着涨。
	// 注意：新增尺寸只对之后上传的图生效，已有的图要用 Regenerate Thumbnails 插件重新生成。
	add_image_size( 'card', 640, 480, true );
	add_image_size( 'hero', 1920, 900, true );
}

// widgets_init 是注册小工具区域的专用钩子。
add_action( 'widgets_init', 'mytheme_widgets_init' );
/**
 * 注册侧边栏（小工具区域）。设计稿里没有小工具区就把整个函数删掉。
 */
function mytheme_widgets_init(): void {
	register_sidebar( [
		'name'          => __( 'Footer Widgets', 'mytheme' ), // 后台显示的名字
		'id'            => 'footer-widgets',                  // 代码里用的 ID，dynamic_sidebar() 传的就是它
		// 下面四个是包裹标记：每个小工具外面套什么、标题用什么标签。
		// %1$s 会被替换成小工具的 ID，%2$s 是它的 class。
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget__title">',
		'after_title'   => '</h4>',
	] );
}

// 摘要（the_excerpt）默认截 55 个词、结尾是 [...]。这两行改成 25 个词、省略号。
// 用箭头函数是因为逻辑只有一行，不值得单独定义一个具名函数。
add_filter( 'excerpt_length', fn() => 25 );
add_filter( 'excerpt_more', fn() => '&hellip;' );

// body_class 过滤器：往 <body> 的 class 列表里加东西。
add_filter( 'body_class', 'mytheme_body_class' );
/**
 * 给 <body> 加一个标明当前页面的 class，方便写「只在某一页生效」的样式。
 *
 * 例如「关于我们」页（别名 about-us）会得到 class="... page--about-us"，
 * CSS 里就能写 .page--about-us .site-header { ... }。
 *
 * @param string[] $classes 已有的 body class。
 * @return string[]
 */
function mytheme_body_class( array $classes ): array {
	if ( is_page() ) {
		// sanitize_html_class() 把别名里不合法的字符去掉，
		// 直接拼接的话，一个带引号的别名就能破坏整个 class 属性。
		$classes[] = 'page--' . sanitize_html_class( get_post_field( 'post_name' ) );
	}

	// 过滤器必须把值返回回去。忘了 return 会让 <body> 一个 class 都不剩，
	// 整站样式集体失效——这是最常见的过滤器错误。
	return $classes;
}
