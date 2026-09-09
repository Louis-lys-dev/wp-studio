<?php
/**
 * 文档头部与站点页头。
 *
 * 这个文件由 get_header() 调用，输出从 <!doctype> 一直到 <main> 开标签。
 * 对应的闭合部分在 footer.php 里——两个文件的标签必须配对，
 * 只改一边会让整个页面的 DOM 结构错乱。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<?php // language_attributes() 输出 lang="zh-CN" 之类，取的是后台设置的站点语言。 ?>
<html <?php language_attributes(); ?>>
<head>
	<?php // 字符集读后台设置，别写死 UTF-8——虽然 99% 的情况就是 UTF-8。 ?>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<?php // 移动端必须有这一行，否则手机会按 980px 的虚拟宽度渲染再缩小，响应式全部失效。 ?>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php
	// wp_head() 是 </head> 之前的必备调用。WordPress 核心、所有插件、
	// 以及 functions/assets.php 里 enqueue 的样式脚本，全都靠它输出。
	// 漏掉这一行，主题的 CSS 一条都不会出现，插件也会大面积失灵。
	wp_head();
	?>
</head>

<?php // body_class() 输出一串描述当前页面的 class（是首页/是单篇/是哪个模板…），是写样式的主要抓手。 ?>
<body <?php body_class(); ?>>
<?php
// wp_body_open() 是 <body> 之后的必备调用，对应 wp_head()。
// 需要紧跟 body 插代码的东西（GTM 的 noscript、各种统计像素）都挂在这里。
wp_body_open();
?>

<?php
// 跳转到正文的无障碍链接。默认用 .screen-reader-text 藏起来，
// 键盘用户按 Tab 时它会获得焦点、显示出来，一下跳过整个导航。
// 这是无障碍检查的必查项，成本极低。
?>
<a class="skip-link screen-reader-text" href="#main">
	<?php esc_html_e( 'Skip to content', 'mytheme' ); ?>
</a>

<header class="site-header" role="banner">
	<div class="site-header__inner">

		<?php // 品牌区：后台在「定制器 → 站点身份」上传了 logo 就用 logo，否则退回文字站点名。 ?>
		<div class="site-header__brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="site-header__title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php bloginfo( 'name' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php
		// 先判断这个位置有没有挂菜单，再输出 <nav>。
		// 不判断的话，客户还没建菜单时页面上会留下一个空的 <nav> 壳子，
		// 而它身上的 padding、border 照样占位，看起来像布局出了 bug。
		?>
		<?php if ( has_nav_menu( 'primary' ) ) : ?>
			<nav class="site-nav" role="navigation" aria-label="<?php esc_attr_e( 'Primary', 'mytheme' ); ?>">
				<?php
				wp_nav_menu( [
					'theme_location' => 'primary',        // 对应 functions/setup.php 里注册的位置
					'container'      => false,            // 不要 WordPress 自动包的那层 <div>，标记自己控制
					'menu_class'     => 'site-nav__list', // 给 <ul> 的 class
					'depth'          => 2,                // 最多两级；设 0 表示不限层级
					// fallback_cb 设 false 很关键：默认值会在没有菜单时
					// 自动列出站点所有页面，客户会看到一堆不该出现的链接。
					'fallback_cb'    => false,
				] );
				?>
			</nav>
		<?php endif; ?>

		<?php
		// 移动端菜单按钮。aria-expanded 的 true/false 由 JS 切换，
		// aria-controls 指向被它控制的元素 id。
		// 按钮里必须有可读文字（这里用屏幕阅读器专用文本），
		// 只放一个汉堡图标的话，读屏软件读出来是「按钮」，用户不知道按了会怎样。
		?>
		<button class="site-nav__toggle" type="button" aria-expanded="false" aria-controls="site-nav">
			<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'mytheme' ); ?></span>
		</button>

	</div>
</header>

<?php // id="main" 是上面跳转链接的落点；这个标签在 footer.php 里闭合。 ?>
<main id="main" class="site-main">
