<?php
/**
 * 前台安全加固。
 *
 * 这里只做「必须在 WordPress 内部完成」的部分。
 * 服务器层面的规则——封掉 xmlrpc.php、禁止 uploads 目录执行 PHP、
 * 给 wp-login.php 加频率限制——都应该写在 Nginx 配置里，
 * 见 docs/bt-lnmp.md 第 7 节和第 19 节。
 *
 * 为什么分层：服务器规则在请求到达 PHP 之前就生效，既更省资源，
 * 也不会因为主题被换掉而失效。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

// 关掉 XML-RPC 接口。它是老式桌面客户端和 Jetpack 用的，现在基本没人用，
// 但一直是暴力破解和 pingback 放大攻击的主要入口。
// 站点如果确实在用 Jetpack 或者 WordPress 手机 App，这行要去掉。
add_filter( 'xmlrpc_enabled', '__return_false' );

// template_redirect 是「已经确定要显示哪个页面、但还没开始输出」的时机，
// 做重定向必须在这里——再晚就已经有内容输出了，header() 会失败。
add_action( 'template_redirect', 'mytheme_block_author_enumeration' );
/**
 * 把作者归档页重定向到首页。
 *
 * 攻击者访问 /?author=1，WordPress 会跳转到 /author/用户名/，
 * 于是第一个管理员的登录名就暴露了——暴力破解需要的两半信息，这就给了一半。
 *
 * 站点确实要用作者归档页（多作者博客）的话就删掉这个函数，
 * 改用「把用户名和显示名设成不同的」来防这一手。
 */
function mytheme_block_author_enumeration(): void {
	// 只拦未登录访客。登录用户（比如编辑在后台点预览）不受影响。
	if ( is_author() && ! is_user_logged_in() ) {
		// 用 wp_safe_redirect 而不是 wp_redirect：前者只允许跳到本站域名，
		// 目标地址万一被别的代码改成外部站点也跳不出去。
		wp_safe_redirect( home_url( '/' ), 301 );
		// 重定向之后必须 exit，否则 PHP 会继续把整个页面渲染完再发出去。
		exit;
	}
}

add_filter( 'rest_endpoints', 'mytheme_restrict_rest_users' );
/**
 * 对未登录请求隐藏 REST 的用户接口。
 *
 * /wp-json/wp/v2/users 默认公开，返回站点所有作者的用户名——
 * 和上面那条堵的是同一个洞的另一个入口，只堵一边等于没堵。
 *
 * @param array<string,mixed> $endpoints 已注册的接口。
 * @return array<string,mixed>
 */
function mytheme_restrict_rest_users( array $endpoints ): array {
	if ( ! is_user_logged_in() ) {
		// 两条都要删：列表接口和单个用户的接口（后者可以直接猜 ID 遍历）。
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}

	return $endpoints;
}

// 登录失败时给统一的模糊提示。
// WordPress 默认会明说「此用户名未注册」还是「密码错误」，
// 等于免费告诉攻击者哪个用户名是存在的。
add_filter( 'login_errors', fn() => __( 'The username or password is incorrect.', 'mytheme' ) );

add_filter( 'wp_headers', 'mytheme_security_headers' );
/**
 * 从 PHP 发送安全响应头。
 *
 * 优先在 Nginx 里配这些头（更快、对静态文件也生效）。这里这份是退路，
 * 给那些改不了服务器配置的虚拟主机用。
 *
 * 两边都配的话头会重复，取值以服务器那份为准——上线前确认一下只留一处。
 *
 * @param array<string,string> $headers 已有的响应头。
 * @return array<string,string>
 */
function mytheme_security_headers( array $headers ): array {
	// 禁止浏览器「猜」文件类型。防的是把上传的文本文件当脚本执行这类问题。
	$headers['X-Content-Type-Options'] = 'nosniff';
	// 跳到外站时只发送域名、不发送完整 URL，避免路径里的信息泄漏给第三方。
	$headers['Referrer-Policy']        = 'strict-origin-when-cross-origin';

	return $headers;
}
