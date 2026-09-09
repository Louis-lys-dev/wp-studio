<?php
/**
 * 前台和后台的静态资源加载。
 *
 * 主题里加载 CSS/JS 只有一条正确的路：wp_enqueue_style / wp_enqueue_script。
 * 不要在 header.php 里手写 <link> 和 <script>——那样做插件无法依赖你的脚本、
 * 缓存插件无法合并压缩、也没法控制加载顺序。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * 根据文件的修改时间生成版本号字符串。
 *
 * 作用是「缓存击穿」：URL 后面挂的 ?ver=xxx 变了，浏览器和 CDN 才会重新下载。
 * 用修改时间做版本号，等于改完文件自动换版本，不用手动记得改。
 *
 * 这里特意做了文件不存在的防护：filemtime() 遇到不存在的路径会每次请求
 * 都抛一个 warning，而且返回 false——false 会让 ?ver= 整个消失，
 * 缓存击穿静默失效，你改了文件却发现线上一直是旧的。
 * 文件不在就退回主题版本号。
 *
 * @param string $relative 相对主题根目录的路径，例如 'assets/css/main.css'。
 * @return string
 */
function mytheme_asset_version( string $relative ): string {
	// ltrim 去掉开头可能多写的斜杠，避免拼出 //assets/... 这种路径。
	$path = MYTHEME_DIR . '/' . ltrim( $relative, '/' );

	return is_readable( $path ) ? (string) filemtime( $path ) : MYTHEME_VERSION;
}

/**
 * 拼出主题内某个资源的完整 URL。
 *
 * 单独包一层是为了别在十几个地方重复写 MYTHEME_URI . '/' . ...，
 * 将来资源目录改名只改这一处。
 *
 * @param string $relative 相对主题根目录的路径。
 * @return string
 */
function mytheme_asset_uri( string $relative ): string {
	return MYTHEME_URI . '/' . ltrim( $relative, '/' );
}

// wp_enqueue_scripts 是前台加载资源的钩子。
// 注意这个钩子名字里虽然只有 scripts，样式也挂它——这是个历史遗留的坑。
add_action( 'wp_enqueue_scripts', 'mytheme_enqueue_assets' );
/**
 * 加载前台资源。
 */
function mytheme_enqueue_assets(): void {
	// 参数依次是：句柄（唯一名字，后面 inline script 要用它定位）、
	// URL、依赖数组（这里没有依赖）、版本号。
	wp_enqueue_style(
		'mytheme',
		mytheme_asset_uri( 'assets/css/main.css' ),
		[],
		mytheme_asset_version( 'assets/css/main.css' )
	);

	// 第五个参数在 WordPress 6.3 以后可以传数组：
	//   strategy: 'defer'  —— 加 defer 属性，脚本下载不阻塞渲染，但仍按顺序执行
	//   in_footer: true    —— 输出在 </body> 之前
	// 如果要兼容 6.3 以前的 WordPress，这个参数只能传 true/false（即只有 in_footer）。
	wp_enqueue_script(
		'mytheme',
		mytheme_asset_uri( 'assets/js/main.js' ),
		[],
		mytheme_asset_version( 'assets/js/main.js' ),
		[
			'strategy'  => 'defer',
			'in_footer' => true,
		]
	);

	// 把 PHP 里的数据传给前台 JS。
	// 第三个参数 'before' 表示这段内联脚本输出在 main.js 之前，
	// 所以 main.js 一执行就能读到 ThemeData。
	//
	// 绝对不要往这里放任何密钥——这段内容会原样出现在页面源码里，
	// 谁都能右键查看。这里放的是公开信息：接口地址、一个前台用的 nonce、首页地址。
	//
	// 旧写法是 wp_localize_script()，那个函数本来是给翻译用的，
	// 会把所有值强制转成字符串（true 变成 "1"，数字变成 "5"）。
	// 传数据用 wp_add_inline_script + wp_json_encode，类型才是对的。
	wp_add_inline_script(
		'mytheme',
		'const ThemeData = ' . wp_json_encode( [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'restUrl'  => esc_url_raw( rest_url( 'mytheme/v1/' ) ),
			'nonce'    => wp_create_nonce( 'mytheme_nonce' ),
			'homeUrl'  => home_url( '/' ),
		] ) . ';',
		'before'
	);

	// WordPress 自带的「回复评论」脚本，只在真正需要的页面加载：
	// 是单篇内容页、评论开着、且后台开了「嵌套评论」。
	// 三个条件缺一个这个脚本就是白下载。
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}

// 后台的资源钩子是另一个，别和前台那个搞混：
// 挂错钩子的典型症状是「后台样式跑到前台去了」或者「前台脚本在后台报错」。
add_action( 'admin_enqueue_scripts', 'mytheme_enqueue_admin_assets' );
/**
 * 加载后台资源（用来微调 ACF 字段界面之类）。
 */
function mytheme_enqueue_admin_assets(): void {
	$css = 'assets/css/admin.css';

	// 文件存在才加载。这样项目不需要后台样式时直接删掉文件就行，
	// 不用回来改这段代码，也不会留下一个 404 请求。
	if ( is_readable( MYTHEME_DIR . '/' . $css ) ) {
		wp_enqueue_style(
			'mytheme-admin',
			mytheme_asset_uri( $css ),
			[],
			mytheme_asset_version( $css )
		);
	}
}

// 登录页有自己独立的钩子，前台和后台的钩子都管不到它。
add_action( 'login_enqueue_scripts', 'mytheme_enqueue_login_assets' );
/**
 * 定制登录页。
 *
 * 走正常的资源管线（enqueue + inline style），而不是网上常见的
 * 「往 login_head 里 echo 一段 <style>」——后者绕过了 WordPress 的资源系统，
 * 没有版本号、不能被合并压缩、也没法被别的代码取消。
 */
function mytheme_enqueue_login_assets(): void {
	// 'login' 是 WordPress 登录页自己那份样式的句柄。
	// 先 enqueue 它，下面的内联样式才有地方挂。
	wp_enqueue_style( 'login' );

	// get_theme_file_uri() 比直接拼 URI 多一层好处：
	// 用子主题时，如果子主题里也有同名文件，会优先返回子主题那份。
	$logo = get_theme_file_uri( 'assets/img/logo.svg' );

	// 把默认的 WordPress logo 换成站点自己的。
	// width:auto + height:60px + background-size:contain 是为了适配任意比例的 logo，
	// 只写 background-image 的话会被 WordPress 默认的 84x84 方框裁掉。
	wp_add_inline_style( 'login', sprintf(
		'.login h1 a{background-image:url(%s);background-size:contain;width:auto;height:60px}',
		esc_url( $logo )
	) );
}
