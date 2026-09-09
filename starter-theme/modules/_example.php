<?php
/**
 * 示例模块 —— 新建模块时复制这个文件来改。
 *
 * 【文件名规则】
 * 文件名必须和 ACF 灵活内容里的「布局名」对应，下划线换成连字符：
 *   布局 hero_banner  →  modules/hero-banner.php
 * 对不上就渲染不出来（开发环境下会在页面源码里留一条 <!-- missing module --> 注释，
 * 见 functions/acf.php 里的 mytheme_module）。
 * 文件名以下划线开头的（比如本文件）不会被任何布局匹配到，所以它只是个模板，不会被渲染。
 *
 * 【取值规则】
 * 进到模块内部时，ACF 的「当前行」已经由 the_row() 切好了，
 * 所以子字段一律用 get_sub_field()，不能用 get_field()——
 * 后者会去文章级别找同名字段，通常返回空，这是新手最常踩的坑。
 *
 * 【$args】
 * 由 mytheme_module() 传进来，包含：
 *   - index  int    这个模块在页面上的位置，从 0 开始
 *   - layout string ACF 的布局名
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

// 先把用到的字段全部取出来，放在最上面。
// 这样做的好处：一眼能看清这个模块依赖哪些字段，下面的标记里不再夹杂取值逻辑，
// 改字段名时也只需要改这一处。
$heading = get_sub_field( 'heading' );
$text    = get_sub_field( 'text' );
$image   = get_sub_field( 'image' );
$link    = get_sub_field( 'link' );

// 关键字段全空就直接退出，不要渲染一个空的 <section>。
// 空 section 身上的 padding 会在页面中间留一大块莫名其妙的空白，
// 客户会当成 bug 报过来。
if ( ! $heading && ! $text && ! $image ) {
	return;
}

// ?? 0 是防御：万一模块被别处直接 get_template_part 调用，$args 可能不存在。
$index = $args['index'] ?? 0;
?>

<?php
// mytheme_classes() 把这个数组拼成 class 字符串，空项自动丢掉，并且自带转义。
// module--first 用来给页面第一块去掉顶部间距、或者做贴着页头的通栏效果。
?>
<section class="<?php echo mytheme_classes( [
	'module',
	'module--example',
	0 === $index ? 'module--first' : '',
] ); ?>">
	<div class="container">

		<?php if ( $heading ) : ?>
			<?php
			// 页面上的第一个模块承担 h1，其余用 h2。
			// 因为模块化页面里没有固定的「页面标题」位置，h1 只能由第一块来出，
			// 否则整页会没有 h1（SEO 和无障碍都会扣分）。
			?>
			<<?php echo 0 === $index ? 'h1' : 'h2'; ?> class="module__heading">
				<?php // 纯文本字段用 esc_html：把 < > 变成实体，杜绝标签注入。 ?>
				<?php echo esc_html( $heading ); ?>
			</<?php echo 0 === $index ? 'h1' : 'h2'; ?>>
		<?php endif; ?>

		<?php if ( $text ) : ?>
			<div class="module__text">
				<?php
				// 富文本字段用 wp_kses_post：保留正文里允许的标签（p、a、strong…），
				// 剥掉 <script> 这类危险标签。
				// 这里不能用 esc_html（会把标签打印成文字），也不能直接 echo（等于不设防）。
				echo wp_kses_post( $text );
				?>
			</div>
		<?php endif; ?>

		<?php if ( $image ) : ?>
			<figure class="module__media">
				<?php // 见 functions/helpers.php：内部走 wp_get_attachment_image，自带 srcset 和 alt。 ?>
				<?php mytheme_image( $image, 'hero', [ 'loading' => 'lazy' ] ); ?>
			</figure>
		<?php endif; ?>

		<?php // 链接字段为空时这个函数什么都不输出，所以外面不用再包 if。 ?>
		<?php mytheme_link( $link, 'btn module__cta' ); ?>

	</div>
</section>
