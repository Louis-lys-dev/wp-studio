<?php
/**
 * 列表里的单条内容（卡片）。
 *
 * 由 index.php / archive.php / search.php 通过 get_template_part() 调用，
 * 调用时当前文章已经由 the_post() 设置好，所以这里可以直接用 the_title() 这些函数。
 *
 * 要给某个文章类型定制卡片，复制成 content-{类型}.php——
 * 调用方已经把类型传进去了，不需要改调用方的代码。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;
?>

<?php // post_class() 会输出 post-123、type-post、category-news 这类 class，是列表页写样式的主要抓手。 ?>
<article <?php post_class( 'card' ); ?>>

	<?php if ( has_post_thumbnail() ) : ?>
		<?php
		// 图片链接对读屏软件是多余的——下面标题里已经有一条指向同一篇文章的链接了。
		// aria-hidden 让它对读屏隐藏，tabindex="-1" 让它跳出 Tab 顺序，
		// 键盘用户就不用在同一张卡片上按两次 Tab。
		?>
		<a class="card__media" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
			<?php
			// 'card' 是 functions/setup.php 里注册的尺寸。
			// loading="lazy" 让首屏之外的图延后加载。注意首屏内的图反而不该 lazy，
			// 那会拖慢 LCP——真要精细控制就在这里按 $args['index'] 判断。
			the_post_thumbnail( 'card', [ 'loading' => 'lazy' ] );
			?>
		</a>
	<?php endif; ?>

	<div class="card__body">
		<?php // 列表里的标题用 h2：页面的 h1 归页面标题，一页只应该有一个 h1。 ?>
		<h2 class="card__title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>

		<div class="card__meta">
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
				<?php echo esc_html( get_the_date() ); ?>
			</time>
		</div>

		<div class="card__excerpt">
			<?php
			// the_excerpt() 优先用后台手填的摘要；没填则自动从正文截，
			// 长度和结尾符号由 functions/setup.php 里那两个过滤器控制。
			the_excerpt();
			?>
		</div>
	</div>

</article>
