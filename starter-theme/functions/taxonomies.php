<?php
/**
 * 自定义分类法。
 *
 * 分类法是「给内容分组的方式」。WordPress 自带两种：分类目录（category，
 * 有层级，像目录树）和标签（post_tag，无层级，平铺）。
 * 自定义分类法就是照这两种再造一个，挂到自己的文章类型上。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

// 和 CPT 一样挂 init。顺序上要注意：分类法注册时会引用文章类型，
// 但因为两个都挂在 init 的同一优先级、且注册顺序不影响关联，所以不必特意排序。
add_action( 'init', 'mytheme_register_taxonomies' );
/**
 * 注册本主题用到的分类法。
 */
function mytheme_register_taxonomies(): void {
	// 示例，按项目改名或删掉。
	// 参数依次是：分类法标识、挂到哪些文章类型上（数组，可以挂多个）、配置。
	register_taxonomy( 'project_category', [ 'project' ], [
		'labels'            => [
			'name'          => __( 'Project Categories', 'mytheme' ),
			'singular_name' => __( 'Project Category', 'mytheme' ),
		],
		'public'            => true,
		// hierarchical: true = 像「分类目录」，后台是带复选框的树，可以有父子级；
		// false = 像「标签」，后台是一个自由输入框。这个决定了后台的操作手感，
		// 选错了客户会觉得难用。
		'hierarchical'      => true,
		// 在后台列表页多加一列显示分类，客户扫一眼就知道每条内容归在哪。
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rewrite'           => [ 'slug' => 'project-category', 'with_front' => false ],
	] );
}
