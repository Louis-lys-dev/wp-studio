<?php
/**
 * 精简 WordPress 默认输出。
 *
 * WordPress 会往 <head> 里塞一堆大多数项目用不上的东西。删掉它们能少几个
 * 请求、少暴露一点信息、让页面源码干净。
 *
 * 但这个文件里每一条删除都是一个「决定」，不是无脑照抄：
 * 网上流传的清理清单里有好几条钩子在新版 WordPress 里已经不存在了，
 * 抄过来就是一堆没有任何作用的代码。搬到新项目前对着当前版本核一遍。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

// 挂 init 而不是 after_setup_theme：这些 action 是 WordPress 在更早的时机
// 挂上去的，要移除它们，得等它们都挂完之后。
add_action( 'init', 'mytheme_cleanup_head' );
/**
 * 移除 wp_head 里用不到的输出。
 *
 * remove_action 的参数必须和当初 add_action 时完全一致（钩子名、函数名、优先级），
 * 差一个优先级就删不掉，而且不会报错——这是这类代码「写了没用」的头号原因。
 */
function mytheme_cleanup_head(): void {
	remove_action( 'wp_head', 'wp_generator' );                        // <meta name="generator"> 里的 WordPress 版本号
	remove_action( 'wp_head', 'rsd_link' );                            // Really Simple Discovery，配合 XML-RPC 用，早就没人用了
	remove_action( 'wp_head', 'wlwmanifest_link' );                    // Windows Live Writer 的清单，那个软件已停止开发
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );                // 短链接 <link rel="shortlink">
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 ); // 上一篇/下一篇的 rel 链接（注意这条带优先级 10）
	remove_action( 'wp_head', 'rest_output_link_wp_head' );            // REST API 的发现链接
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );       // oEmbed 发现链接（别人嵌你的文章时用）
	// 上面 REST 的发现链接还有一份是走 HTTP 响应头的，得单独删。
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
}

// RSS 里也有一处版本号，用这个过滤器清掉。
// __return_empty_string 是 WordPress 自带的现成函数，专门用来「返回空字符串」。
add_filter( 'the_generator', '__return_empty_string' );

// 优先级 100（默认是 10）：要保证在所有插件和核心都 enqueue 完之后再执行，
// 否则你 dequeue 的时候人家还没 enqueue，等于什么都没做。
// 「dequeue 不生效」十有八九是优先级不够大。
add_action( 'wp_enqueue_scripts', 'mytheme_dequeue_block_styles', 100 );
/**
 * 去掉区块编辑器（古腾堡）的前台样式。
 *
 * 只有在主题前台确实不用核心区块时才能这么做——ACF 模块化主题通常就是这种情况，
 * 页面全部由自己的模块渲染，核心区块的那几十 KB 样式一行都用不上。
 *
 * 反过来，如果页面正文（the_content）里还在用区块，删了这些样式，
 * 分栏、图集、按钮这类区块会当场散架。
 */
function mytheme_dequeue_block_styles(): void {
	wp_dequeue_style( 'wp-block-library' );       // 核心区块的样式
	wp_dequeue_style( 'wp-block-library-theme' ); // 区块的主题化样式
	wp_dequeue_style( 'global-styles' );          // theme.json 生成的 CSS 变量
	wp_dequeue_style( 'classic-theme-styles' );   // 给经典主题补的按钮等样式
}

// 让 WordPress 别去 s.w.org 拿 emoji 图（少一次外部请求，国内环境尤其明显）。
add_filter( 'emoji_svg_url', '__return_false' );

add_action( 'init', 'mytheme_disable_emojis' );
/**
 * 移除 emoji 检测脚本及其相关输出。
 *
 * WordPress 会在每个页面注入一段几 KB 的 JS，用来检测浏览器是否支持 emoji，
 * 不支持就换成图片。现在的浏览器全都支持，这段脚本纯属浪费。
 *
 * 要删干净得删七处（前台、后台、样式、RSS、邮件各有一份），
 * 少删一处就还留着半截。
 */
function mytheme_disable_emojis(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 ); // 注意优先级是 7 不是 10
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
}

// 最后一个参数 1 是「这个回调接收几个参数」。
// nav_menu_css_class 实际会传 4 个（classes、item、args、depth），
// 这里只要第一个，就声明 1，函数签名保持干净。
add_filter( 'nav_menu_css_class', 'mytheme_nav_menu_css_class', 10, 1 );
/**
 * 给「当前页」的菜单项加一个更短的 class 别名。
 *
 * WordPress 原生的当前项 class 有好几个（current-menu-item、
 * current-menu-ancestor、current_page_parent），写样式时要一条条列很烦，
 * 统一加一个 is-active，CSS 里只认这一个。
 *
 * 用过滤器做，而不是拿到渲染好的菜单 HTML 去 str_replace——后者会把
 * 菜单标题里、URL 里碰巧出现的同样字符串一起替换掉。
 *
 * @param string[] $classes 这个菜单项已有的 class。
 * @return string[]
 */
function mytheme_nav_menu_css_class( array $classes ): array {
	// array_intersect 求交集：只要命中三个之一就算「当前」。
	// 非空数组是真值，所以可以直接当条件用。
	if ( array_intersect( $classes, [ 'current-menu-item', 'current-menu-ancestor', 'current_page_parent' ] ) ) {
		$classes[] = 'is-active';
	}

	return $classes;
}
