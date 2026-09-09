<?php
/**
 * 搜索表单。
 *
 * 主题里存在这个文件时，get_search_form() 就会用它，
 * 替换掉 WordPress 自带的那份默认标记。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

// 一个页面上可能出现多个搜索框（页头一个、无结果区一个、404 页一个），
// id 写死会重复，而 <label for> 就会指向错误的输入框、无障碍失效。
// wp_unique_id() 每次调用返回一个递增的唯一值。
// 变量名带 mytheme_ 前缀：模板文件里的变量是全局作用域，不加前缀
// 有覆盖 WordPress 或插件同名变量的风险。
$mytheme_search_id = wp_unique_id( 'search-field-' );
?>
<?php
// 表单必须 method="get"、action 指向首页、输入框 name="s"——
// 这三样是 WordPress 识别搜索请求的固定约定，改任何一个搜索都会失效。
?>
<form class="search-form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<?php
	// label 用 .screen-reader-text 视觉上藏起来，但读屏软件能读到。
	// 不要用 placeholder 代替 label：placeholder 一输入就消失，
	// 部分读屏软件也根本不读它。
	?>
	<label class="screen-reader-text" for="<?php echo esc_attr( $mytheme_search_id ); ?>">
		<?php esc_html_e( 'Search for:', 'mytheme' ); ?>
	</label>
	<input
		id="<?php echo esc_attr( $mytheme_search_id ); ?>"
		class="search-form__input"
		type="search"
		name="s"
		<?php // 回填当前关键词，在结果页上用户能看到自己搜的是什么。 ?>
		value="<?php echo esc_attr( get_search_query() ); ?>"
		placeholder="<?php esc_attr_e( 'Search&hellip;', 'mytheme' ); ?>"
		<?php // required 挡掉空搜索——空关键词会把整个站的内容都列出来。 ?>
		required
	>
	<button class="search-form__submit" type="submit">
		<?php esc_html_e( 'Search', 'mytheme' ); ?>
	</button>
</form>
