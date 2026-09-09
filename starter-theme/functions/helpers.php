<?php
/**
 * 模板里通用的小工具函数。
 *
 * 判断一个函数该不该放进来：如果它在两个以上的模板里出现，就搬到这里；
 * 只有一个地方用，留在原地更好读。
 *
 * 这几个函数有一个共同点——它们都负责了转义。模板里直接 echo 是最容易
 * 出安全问题的地方，把转义收进函数里，调用方就不会漏。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

/**
 * 输出一个 ACF 图片字段对应的 <img>，字段为空则什么都不输出。
 *
 * 前提是 ACF 图片字段的「返回格式」设成「图片数组」。设成 URL 的话
 * 这里拿不到 ID，也就没法输出 srcset。
 *
 * 用 wp_get_attachment_image() 而不是手写 <img src>，好处是 WordPress
 * 会自动补上 srcset、sizes、width、height 和 alt——
 * 手写的话这几样通常都是缺的，图片会随加载抖动（CLS 变差），
 * 移动端也拿不到小尺寸图。
 *
 * @param array<string,mixed>|false|null $image ACF 图片数组。
 * @param string                         $size  已注册的图片尺寸名。
 * @param array<string,string>           $attr  额外属性，例如 [ 'loading' => 'lazy' ]。
 */
function mytheme_image( $image, string $size = 'large', array $attr = [] ): void {
	// 空字段的判断用 ID 而不是判断整个数组：ACF 在某些情况下会返回
	// 有键但没内容的数组，只判 empty($image) 会漏。
	if ( empty( $image['ID'] ) ) {
		return;
	}

	// wp_get_attachment_image() 自己会转义，这里不用再包 esc_*。
	echo wp_get_attachment_image( (int) $image['ID'], $size, false, $attr );
}

/**
 * 输出一个 ACF 链接字段对应的 <a>，字段为空则什么都不输出。
 *
 * ACF 链接字段返回的数组有三个键：url、title、target。
 *
 * @param array<string,string>|false|null $link  ACF 链接数组。
 * @param string                          $class 给 <a> 的 class。
 */
function mytheme_link( $link, string $class = 'btn' ): void {
	// 没填 URL 就不输出。少了这一道，页面上会出现一个 href="" 的空链接，
	// 点上去会重载当前页。
	if ( empty( $link['url'] ) ) {
		return;
	}

	printf(
		'<a class="%s" href="%s"%s>%s</a>',
		esc_attr( $class ),      // 属性值用 esc_attr
		esc_url( $link['url'] ), // URL 用 esc_url，它会挡掉 javascript: 这类协议
		// 新窗口打开时必须带 rel="noopener"：否则新页面能通过 window.opener
		// 操纵原页面跳转（反向钓鱼）。
		empty( $link['target'] ) ? '' : ' target="_blank" rel="noopener"',
		// 没填标题就拿 URL 顶上，保证链接有可见文字（否则屏幕阅读器读到的是空链接）。
		esc_html( $link['title'] ?: $link['url'] )
	);
}

/**
 * 按词数截断文本，不会把单词从中间切开。
 *
 * 和 the_excerpt() 的区别：这个可以对任意字符串用（比如 ACF 的文本域），
 * 不限于文章正文。
 *
 * @param string $text  原始文本。
 * @param int    $words 最多保留多少个词。
 * @return string
 */
function mytheme_excerpt( string $text, int $words = 25 ): string {
	// 先 wp_strip_all_tags 去掉 HTML 再截断。顺序不能反：
	// 先截断会把标签切成两半，输出一个没闭合的 <a，把后面的版面全带歪。
	return wp_trim_words( wp_strip_all_tags( $text ), $words, '&hellip;' );
}

/**
 * 把一组 class 拼成一个 class 属性值，自动丢掉空的那些。
 *
 * 用途是写条件 class 时不必到处拼字符串和处理多余空格：
 *   mytheme_classes( [ 'card', $featured ? 'card--featured' : '' ] )
 *
 * @param array<int,string|false|null> $classes class 列表，假值会被丢掉。
 * @return string
 */
function mytheme_classes( array $classes ): string {
	// 从里往外读：先给每一项 trim，再 array_filter 丢掉空串和 false/null，
	// 然后用空格连起来，最后 esc_attr 转义。
	// 函数自带转义，所以模板里可以直接 echo，不需要再包一层。
	return esc_attr( implode( ' ', array_filter( array_map( 'trim', $classes ) ) ) );
}
