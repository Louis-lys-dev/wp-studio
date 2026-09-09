<?php
/**
 * 循环没有取到任何内容时显示。
 *
 * 每一个有列表的模板都要留这一支。漏掉的话，分类下暂时没有文章、
 * 搜索没命中的时候，页面就是一片空白，看起来像坏了。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;
?>

<section class="no-results">
	<h2 class="no-results__title"><?php esc_html_e( 'Nothing found', 'mytheme' ); ?></h2>

	<?php
	// 分两种情况给不同文案：搜索没命中要给用户下一步动作（换个词再搜），
	// 而空分类/空归档没什么可搜的，说明情况就行。
	?>
	<?php if ( is_search() ) : ?>
		<p><?php esc_html_e( 'No results matched your search. Try different keywords.', 'mytheme' ); ?></p>
		<?php get_search_form(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'There is nothing to show here yet.', 'mytheme' ); ?></p>
	<?php endif; ?>
</section>
