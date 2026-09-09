<?php
/**
 * 自定义文章类型（CPT）。
 *
 * 项目没有 CPT 就把下面的注册删掉；如果这些内容必须在换主题后依然可访问，
 * 就把注册代码搬到插件或 mu-plugin 里去。
 * 由主题注册的内容类型，在主题一停用的瞬间，前台就全部 404——
 * 数据还在数据库里，但 WordPress 不再知道怎么显示它们。
 *
 * 判断标准：内容属于「网站」的放插件，属于「这套设计」的才放主题。
 *
 * @package MyTheme
 */

defined( 'ABSPATH' ) || exit;

// 注册 CPT 必须挂 init，而且不能更晚。
// 挂晚了固定链接规则已经生成完毕，前台会 404。
add_action( 'init', 'mytheme_register_post_types' );
/**
 * 注册本主题用到的文章类型。
 */
function mytheme_register_post_types(): void {
	// 下面是示例，按项目改名或直接删掉。
	// 第一个参数是类型标识，最长 20 个字符，只能小写字母数字下划线连字符，
	// 而且一旦上线就不要再改——改了以后旧内容在后台会直接消失。
	register_post_type( 'project', [
		// labels 是后台各处显示的文案。给全一点，客户用起来才不别扭；
		// 只写 name 的话，后台到处都是「文章」这种驴唇不对马嘴的字眼。
		'labels'        => [
			'name'          => __( 'Projects', 'mytheme' ),
			'singular_name' => __( 'Project', 'mytheme' ),
			'add_new_item'  => __( 'Add New Project', 'mytheme' ),
			'edit_item'     => __( 'Edit Project', 'mytheme' ),
			'not_found'     => __( 'No projects found', 'mytheme' ),
		],
		// public: true 一次性打开一批默认值——前台可访问、后台有菜单、可搜索。
		'public'        => true,
		// has_archive: true 会生成列表页 /projects/，模板用 archive-project.php。
		// 设成 false 就只有单篇页，没有列表页。
		'has_archive'   => true,
		// rewrite 控制 URL。with_front 设 false 是为了不让固定链接设置里的
		// 前缀（比如 /blog）跑到 /projects 前面变成 /blog/projects。
		'rewrite'       => [ 'slug' => 'projects', 'with_front' => false ],
		'menu_icon'     => 'dashicons-portfolio', // 后台菜单图标，取值见 Dashicons 图标表
		'menu_position' => 20,                    // 菜单位置，20 在「页面」下面
		// supports 决定编辑页有哪些框。没列出来的框就不显示——
		// 比如漏了 'thumbnail'，特色图片那个框就找不到（一个高频的求助现场）。
		'supports'      => [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'page-attributes' ],
		// show_in_rest: true 是两件事的前提：用古腾堡编辑器，以及被 REST API 读到。
		// 前端框架取数据、ACF 的部分功能都依赖它。
		'show_in_rest'  => true,
	] );

	// 提醒：改完 rewrite 相关的参数后要去「设置 → 固定链接」点一次保存，
	// 否则新 URL 规则不生效、前台 404。不要在代码里调 flush_rewrite_rules()，
	// 那是每次请求都重建规则，性能代价很大。
}
