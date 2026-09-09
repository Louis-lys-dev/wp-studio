<?php
/**
 * 主题入口文件（bootstrap）。
 *
 * 这里只做「接线」，不写任何业务逻辑。每一类功能各自占一个文件放在
 * functions/ 目录下，主题长大以后这个文件仍然一眼能看完。
 *
 * @package MyTheme
 */

// 防止这个文件被直接用浏览器访问。ABSPATH 只有在 WordPress 正常启动时
// 才存在，没有它就说明有人在直连 PHP 文件，直接退出。
// 主题里每个 PHP 文件开头都应该有这一行。
defined( 'ABSPATH' ) || exit;

// 三个全局常量，后面所有文件都会用到：
//   MYTHEME_VERSION —— 主题版本号，直接读 style.css 头信息，改版本只改一处
//   MYTHEME_DIR     —— 主题目录的服务器绝对路径，用于 require、file_exists
//   MYTHEME_URI     —— 主题目录的 URL，用于 <img src>、enqueue
// 路径和 URL 是两个东西，别混用：require 一个 URL 会失败，
// <img src> 写服务器路径浏览器也取不到。
define( 'MYTHEME_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'MYTHEME_DIR', get_template_directory() );
define( 'MYTHEME_URI', get_template_directory_uri() );

/**
 * 逐个加载 functions/ 下的文件。
 *
 * 开发环境（WP_DEBUG 为真）文件缺失时高声报警，生产环境则安静跳过：
 * 如果这里直接写 require，少一个文件就是每次请求都致命错误，整站白屏。
 * 少一个模块文件导致某块内容不显示，比整站打不开要好收拾得多。
 *
 * 数组的顺序就是加载顺序，helpers 必须排第一——后面的文件里会用到它定义的函数。
 */
foreach ( [
	'helpers',    // 通用小工具函数
	'setup',      // 主题支持特性、菜单、侧边栏
	'assets',     // CSS / JS 加载
	'acf',        // ACF 接线与模块渲染器
	'post-types', // 自定义文章类型
	'taxonomies', // 自定义分类法
	'cleanup',    // 清理 WordPress 默认输出
	'security',   // 前台安全加固
] as $file ) {
	$path = MYTHEME_DIR . "/functions/{$file}.php";

	if ( is_readable( $path ) ) {
		require_once $path;
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// 只在开发环境报警。这条会进 debug.log，也会在页面上显示。
		trigger_error(
			esc_html( "Missing theme include: functions/{$file}.php" ),
			E_USER_WARNING
		);
	}
}
