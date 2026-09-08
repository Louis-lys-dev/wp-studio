# WordPress 通用开发教程

> 不绑定具体项目的 WordPress 核心知识体系
> 配套阅读：`wordpress-theme.md`、`woocommerce.md`

---

## 目录

1. [架构总览](#1-架构总览)
2. [数据库：11 张表讲透](#2-数据库11-张表讲透)
3. [核心数据模型](#3-核心数据模型)
4. [请求生命周期](#4-请求生命周期)
5. [钩子系统原理](#5-钩子系统原理)
6. [主题体系](#6-主题体系)
7. [插件开发](#7-插件开发)
8. [mu-plugins](#8-mu-plugins)
9. [WP_Query 完全指南](#9-wp_query-完全指南)
10. [直接操作数据库：$wpdb](#10-直接操作数据库wpdb)
11. [用户、角色与权限](#11-用户角色与权限)
12. [Options 与设置 API](#12-options-与设置-api)
13. [Metabox 与自定义字段](#13-metabox-与自定义字段)
14. [短代码](#14-短代码)
15. [重写规则与自定义 URL](#15-重写规则与自定义-url)
16. [WP-Cron 定时任务](#16-wp-cron-定时任务)
17. [HTTP API：请求外部接口](#17-http-api请求外部接口)
18. [REST API](#18-rest-api)
19. [缓存体系](#19-缓存体系)
20. [多语言 i18n](#20-多语言-i18n)
21. [媒体与附件](#21-媒体与附件)
22. [邮件](#22-邮件)
23. [安全完整清单](#23-安全完整清单)
24. [性能优化](#24-性能优化)
25. [WP-CLI](#25-wp-cli)
26. [环境与部署](#26-环境与部署)
27. [调试工具箱](#27-调试工具箱)

---

## 1. 架构总览

### 目录结构

```
wordpress/
├── wp-admin/              ← 后台。永远不要改
├── wp-includes/           ← 核心函数库。永远不要改
├── wp-content/            ← 你的地盘
│   ├── themes/
│   ├── plugins/
│   ├── mu-plugins/        ← 必用插件（默认不存在，自己建）
│   ├── uploads/           ← 媒体库文件
│   └── languages/
├── wp-config.php          ← 配置（数据库、盐值、常量）
├── index.php              ← 唯一入口
└── .htaccess              ← 伪静态规则（Apache）
```

**升级 WP 会覆盖 `wp-admin/` 和 `wp-includes/`**。所有自定义代码必须放在 `wp-content/`。

### 三层结构

```
┌─────────────────────────────────────┐
│  主题 (Theme)                        │  外观、模板、前端
│  ─ 一次只能激活一个                    │
├─────────────────────────────────────┤
│  插件 (Plugins)                      │  功能、业务逻辑
│  ─ 可以激活多个                       │
├─────────────────────────────────────┤
│  核心 (Core)                         │  数据、API、钩子系统
└─────────────────────────────────────┘
```

**职责划分原则**：

| 放主题里 | 放插件里 |
|---|---|
| 模板文件 | 自定义文章类型 |
| 样式、脚本 | 分类法 |
| 菜单位置 | 业务逻辑 |
| 图片尺寸 | 第三方 API 对接 |
| 主题选项 | 表单处理 |

**判断标准**：换个主题之后还需要的功能 → 插件。只影响外观的 → 主题。

很多人把 CPT 写在主题里（包括大量商业主题），换主题时数据就"消失"了（其实还在数据库，只是不再注册）。做客户站时这是个真实风险。

---

## 2. 数据库：11 张表讲透

理解这 11 张表，就理解了 WordPress 的一切。（`wp_` 是可配置前缀）

```
wp_posts              所有内容（文章/页面/CPT/附件/菜单项/修订版）
wp_postmeta           内容的自定义字段
wp_comments           评论
wp_commentmeta        评论的自定义字段
wp_terms              分类项名称
wp_term_taxonomy      分类项属于哪个分类法
wp_term_relationships 内容 ↔ 分类项 的关联
wp_termmeta           分类项的自定义字段
wp_users              用户
wp_usermeta           用户的自定义字段
wp_options            全局设置
wp_links              （遗留表，基本没人用）
```

### wp_posts —— 最核心的表

**关键认知：几乎所有内容都存在这一张表里**，靠 `post_type` 字段区分。

| post_type | 是什么 |
|---|---|
| `post` | 文章 |
| `page` | 页面 |
| `attachment` | 媒体库里的每个文件 |
| `revision` | 每次保存产生的修订版 |
| `nav_menu_item` | 导航菜单的每一项 |
| `wp_block` | 可复用区块 |
| 你注册的 | 自定义文章类型 |

主要字段：

```sql
ID              bigint      主键
post_author     bigint      作者 user ID
post_date       datetime    发布时间（站点时区）
post_date_gmt   datetime    发布时间（UTC）
post_content    longtext    正文
post_title      text        标题
post_excerpt    text        摘要
post_status     varchar     publish/draft/pending/private/trash/future/auto-draft/inherit
post_name       varchar     slug（URL 里那段）
post_parent     bigint      父级 ID（页面层级、附件所属文章）
menu_order      int         排序号
post_type       varchar     类型
post_mime_type  varchar     附件的 MIME
guid            varchar     ⚠️ 不是 URL！只是唯一标识，永远别改
comment_count   bigint      评论数
```

**`guid` 的坑**：它长得像 URL，但**不是**用来访问的。迁移站点时批量替换 `guid` 会导致 RSS 订阅者收到全部重复内容。搜索替换时要排除这个字段。

**`post_status` 的 `inherit`**：附件用这个状态，表示继承父文章的状态。

### wp_postmeta —— 自定义字段

```sql
meta_id     bigint
post_id     bigint      关联 wp_posts.ID
meta_key    varchar     字段名
meta_value  longtext    字段值（全部按字符串存！）
```

**关键理解**：

1. **一个 post 可以有多条同 key 的记录**（这就是"多值字段"）
2. **所有值都存成字符串**，数组/对象会被 `serialize()` 序列化
3. **`_` 开头的 key 是"受保护"的**，不会显示在后台的「自定义字段」面板里

```php
add_post_meta( $id, 'color', 'red' );        // 追加一条
add_post_meta( $id, 'color', 'blue' );       // 再追加一条（现在有两条）
update_post_meta( $id, 'color', 'green' );   // 更新（如果有多条会全变成一条）

get_post_meta( $id, 'color', true );         // 单值 → 'green'
get_post_meta( $id, 'color', false );        // 数组 → ['green']
get_post_meta( $id );                        // 所有 meta

delete_post_meta( $id, 'color' );
delete_post_meta( $id, 'color', 'red' );     // 只删值为 red 的那条
```

**ACF 是怎么存的**：每个字段存两条记录

```
meta_key = 'hero_title'      meta_value = 'We build brands'
meta_key = '_hero_title'     meta_value = 'field_5f8a1b2c3d4e5'   ← 字段组 key
```

带下划线那条是 ACF 用来反查字段配置的。所以直接用 `get_post_meta()` 读 ACF 字段能读到值，但拿不到 ACF 的格式化处理（图片数组、日期格式等）——**所以要用 `get_field()`**。

**Repeater 的存储方式**：

```
features            = 2                    ← 行数
features_0_title    = 'Fast'
features_0_desc     = '...'
features_1_title    = 'Secure'
features_1_desc     = '...'
```

理解这个之后你就知道为什么 Repeater 数据量大时会慢——每行每字段都是独立的一条 meta。

### 分类法的三张表

这是 WP 数据库里最绕的设计：

```
wp_terms                    wp_term_taxonomy              wp_term_relationships
┌──────────────┐            ┌─────────────────────┐       ┌────────────────────┐
│ term_id  (PK)│───────────→│ term_id      (FK)   │       │ object_id     (文章)│
│ name         │            │ term_taxonomy_id(PK)│←──────│ term_taxonomy_id   │
│ slug         │            │ taxonomy            │       │ term_order         │
└──────────────┘            │ description         │       └────────────────────┘
                            │ parent              │
                            │ count               │
                            └─────────────────────┘
```

**为什么要拆成三张表**：

同一个名字（比如 "News"）可以同时是「分类」和「标签」。`wp_terms` 只存名字，`wp_term_taxonomy` 说明这个名字在哪个分类法下扮演什么角色。

所以**同名不同分类法 = 一条 term + 两条 term_taxonomy**。

`count` 字段是缓存的关联数量，不是实时算的。批量导入后可能不准，用 `wp_update_term_count()` 修复。

### wp_options —— 全局设置

```sql
option_id     bigint
option_name   varchar     唯一
option_value  longtext
autoload      varchar     'yes' / 'no'
```

**`autoload` 是最容易忽略的性能杀手**。所有 `autoload='yes'` 的选项在**每一个请求**都会被一次性查出来放进内存。插件乱存大数据会让站点整体变慢。

```sql
-- 查最大的自动加载选项
SELECT option_name, LENGTH(option_value) AS size
FROM wp_options
WHERE autoload = 'yes'
ORDER BY size DESC
LIMIT 20;

-- 看自动加载总量（超过 1MB 就该清理了）
SELECT SUM(LENGTH(option_value))/1024/1024 AS mb
FROM wp_options WHERE autoload = 'yes';
```

```php
add_option( 'my_key', $value, '', 'no' );      // 第 4 参数关掉 autoload
update_option( 'my_key', $value, false );      // 第 3 参数 = autoload
get_option( 'my_key', $default );
delete_option( 'my_key' );
```

**Transient 也存在这张表**（没有对象缓存时），key 形如 `_transient_xxx` 和 `_transient_timeout_xxx`。过期的 transient 不会自动清理，站点跑久了这张表会膨胀。

---

## 3. 核心数据模型

### 一切皆 Post

理解这一点能省掉大量困惑：

```
文章、页面、产品、案例  →  wp_posts (post_type 不同)
媒体库的每张图         →  wp_posts (post_type='attachment')
导航菜单的每一项        →  wp_posts (post_type='nav_menu_item')
每次保存的历史版本      →  wp_posts (post_type='revision', post_parent=原文ID)
```

所以：
- 媒体库图片也能有自定义字段（存在 `wp_postmeta`，`post_id` = 附件 ID）
- 图片的 alt 文本存在 `wp_postmeta` 的 `_wp_attachment_image_alt`
- 图片的各种尺寸信息存在 `_wp_attachment_metadata`（序列化数组）
- 导航菜单本身是一个分类法（`nav_menu`），菜单项通过 term_relationships 关联

### Post 对象

```php
$post = get_post( 42 );

$post->ID;
$post->post_title;
$post->post_content;
$post->post_status;
$post->post_type;
$post->post_parent;
$post->post_date;
$post->post_name;      // slug
$post->menu_order;
```

### 增删改

```php
// 新增
$id = wp_insert_post([
    'post_title'   => '标题',
    'post_content' => '正文',
    'post_status'  => 'publish',
    'post_type'    => 'case',
    'post_author'  => 1,
    'meta_input'   => [                    // 顺便写 meta
        'price' => 100,
    ],
    'tax_input'    => [                    // 顺便设分类
        'case_category' => ['design'],
    ],
], true );   // 第 2 参数 true = 出错时返回 WP_Error

if ( is_wp_error( $id ) ) {
    error_log( $id->get_error_message() );
}

// 更新
wp_update_post([ 'ID' => 42, 'post_title' => '新标题' ]);

// 删除
wp_trash_post( 42 );            // 移到回收站
wp_delete_post( 42, true );     // 彻底删除
```

⚠️ 在 `save_post` 钩子里调 `wp_update_post()` 会**无限递归**。要先解绑：

```php
add_action( 'save_post', 'my_save' );
function my_save( $post_id ) {
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;

    remove_action( 'save_post', 'my_save' );      // 解绑
    wp_update_post([ 'ID' => $post_id, /* ... */ ]);
    add_action( 'save_post', 'my_save' );         // 重新绑定
}
```

---

## 4. 请求生命周期

```
① 入口
   index.php → wp-blog-header.php → wp-load.php → wp-config.php → wp-settings.php

② 加载顺序（wp-settings.php 里）
   ├─ 核心函数库
   ├─ 数据库连接
   ├─ 【muplugins_loaded】     mu-plugins 已加载
   ├─ 激活的插件逐个 require
   ├─ 【plugins_loaded】       ★ 插件都在了，可以互相调用
   ├─ 【setup_theme】
   ├─ 加载 functions.php       ★ 主题代码在这里执行
   ├─ 【after_setup_theme】    ★ add_theme_support()
   ├─ 【init】                 ★ 注册 CPT / 分类法 / 短代码 / 菜单
   ├─ 【widgets_init】
   └─ 【wp_loaded】            ★ 所有加载完成

③ 解析请求
   ├─ WP::parse_request()      URL → 查询变量
   ├─ 【parse_request】
   ├─ 【send_headers】
   ├─ WP_Query 构建
   ├─ 【pre_get_posts】        ★ 改主查询的最后机会
   ├─ 执行 SQL
   ├─ 【posts_selection】
   └─ 【wp】

④ 选模板
   ├─ 【template_redirect】    ★ 重定向的最佳时机
   ├─ 模板层级匹配
   ├─ 【template_include】     （filter）可强制换模板
   └─ 加载模板文件

⑤ 渲染
   ├─ get_header() → 【get_header】→ header.php → 【wp_head】
   ├─ The Loop
   └─ get_footer() → 【get_footer】→ footer.php → 【wp_footer】

⑥ 结束
   └─ 【shutdown】
```

### 各阶段能做什么

| 阶段 | 能做 | 不能做 |
|---|---|---|
| `plugins_loaded` | 判断插件是否存在 | 用主题函数 |
| `after_setup_theme` | 主题支持、图片尺寸 | 注册 CPT（太早） |
| `init` | 注册 CPT/分类法/短代码 | 读当前文章（还没查询） |
| `wp` | 读 `$wp_query`、当前文章 | — |
| `template_redirect` | 重定向、`exit` | 已经不能改查询 |
| 模板中 | 输出 HTML | 重定向（头已发送） |

---

## 5. 钩子系统原理

### 底层是怎么实现的

```php
// 简化版原理
global $wp_filter;                     // 全局注册表

// add_filter 就是往里塞
$wp_filter['the_content'][10][] = [
    'function'      => 'wpautop',
    'accepted_args' => 1,
];

// apply_filters 就是遍历执行
function apply_filters( $tag, $value, ...$args ) {
    foreach ( $wp_filter[$tag] as $priority => $callbacks ) {   // 按优先级排序
        foreach ( $callbacks as $cb ) {
            $value = call_user_func( $cb['function'], $value, ...$args );
        }
    }
    return $value;
}
```

**Action 本质就是不要返回值的 Filter**——`do_action()` 内部和 `apply_filters()` 几乎一样，只是丢弃返回值。

### 两种钩子对比

| | Action | Filter |
|---|---|---|
| 触发 | `do_action('hook', $args)` | `apply_filters('hook', $value, $args)` |
| 注册 | `add_action()` | `add_filter()` |
| 回调返回值 | 忽略 | **必须 return** |
| 用途 | 执行副作用（输出、写库、发邮件） | 修改数据 |

### 完整 API

```php
// 注册
add_action( $hook, $callback, $priority = 10, $accepted_args = 1 );
add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 );

// 触发
do_action( $hook, $arg1, $arg2 );
apply_filters( $hook, $value, $arg1 );
do_action_ref_array( $hook, $args_array );
apply_filters_ref_array( $hook, $args_array );

// 移除（优先级必须匹配）
remove_action( $hook, $callback, $priority = 10 );
remove_filter( $hook, $callback, $priority = 10 );
remove_all_actions( $hook, $priority = false );
remove_all_filters( $hook, $priority = false );

// 查询
has_action( $hook, $callback = false );     // 返回优先级或 bool
has_filter( $hook, $callback = false );
did_action( $hook );                        // 执行过几次
doing_action( $hook );                      // 当前是否正在执行
current_action();  current_filter();
```

### 回调的四种写法

```php
add_action( 'init', 'my_function' );                    // 具名函数
add_action( 'init', function() { ... } );               // 匿名函数（无法 remove）
add_action( 'init', [ $obj, 'method' ] );               // 对象方法
add_action( 'init', [ 'MyClass', 'staticMethod' ] );    // 静态方法
add_action( 'init', 'MyClass::staticMethod' );          // 静态方法（字符串写法）
```

### 移除类方法的钩子

这是个经典难题——插件在类里注册的钩子，你拿不到那个对象实例：

```php
// 插件里：$this 是插件实例，你没法引用
add_action( 'wp_head', [ $this, 'output' ] );

// 通用移除方法
function remove_class_hook( $tag, $class_name, $method, $priority = 10 ) {
    global $wp_filter;
    if ( ! isset( $wp_filter[$tag][$priority] ) ) return false;

    foreach ( $wp_filter[$tag][$priority] as $key => $cb ) {
        if ( is_array( $cb['function'] )
          && is_object( $cb['function'][0] )
          && get_class( $cb['function'][0] ) === $class_name
          && $cb['function'][1] === $method ) {
            unset( $wp_filter[$tag]->callbacks[$priority][$key] );
            return true;
        }
    }
    return false;
}
```

### 自定义钩子（写可扩展代码）

```php
// 在你的代码里埋点
function render_card( $post_id ) {
    do_action( 'before_card', $post_id );

    $title = apply_filters( 'card_title', get_the_title($post_id), $post_id );
    echo '<h3>' . esc_html( $title ) . '</h3>';

    do_action( 'after_card', $post_id );
}
```

以后要加东西不用改这个函数，挂钩子就行。做客户站时这能让你的代码在下次改版时不用大动。

---

## 6. 主题体系

### 最小主题

一个合法主题只需要两个文件：

```
my-theme/
├── style.css     ← 必须，含主题信息注释
└── index.php     ← 必须
```

```css
/*
Theme Name: My Theme
Theme URI: https://example.com
Author: Your Name
Author URI: https://example.com
Description: 主题描述
Version: 1.0.0
Requires at least: 6.0
Requires PHP: 7.4
License: GPL v2 or later
Text Domain: my-theme
Tags: custom-menu, featured-images
*/
```

其他可选文件：`screenshot.png`（1200×900，后台预览图）、`functions.php`、各种模板。

### 子主题

改第三方主题时**必须用子主题**，否则父主题一升级你的改动全没。

```
my-theme-child/
├── style.css
└── functions.php
```

```css
/*
Theme Name: My Theme Child
Template: my-theme        ← 父主题的目录名，必须精确匹配
Version: 1.0.0
*/
```

```php
<?php
// functions.php —— 子主题的 functions.php 先于父主题加载
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'parent', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child', get_stylesheet_uri(), ['parent'] );
});
```

**覆盖规则**：
- 模板文件（`page.php` 等）—— 子主题的完全覆盖父主题
- `functions.php` —— **不覆盖，两个都执行**（子主题先）
- 想改父主题的函数，用 `remove_action()` 卸掉再重挂

### 路径函数对照

| 函数 | 子主题激活时返回 |
|---|---|
| `get_template_directory()` | **父**主题路径 |
| `get_template_directory_uri()` | **父**主题 URL |
| `get_stylesheet_directory()` | **子**主题路径 |
| `get_stylesheet_directory_uri()` | **子**主题 URL |
| `get_theme_file_path($rel)` | 子主题有就子，没有回父 ✅ |
| `get_theme_file_uri($rel)` | 同上 ✅ |
| `locate_template($files)` | 查找模板，返回第一个存在的路径 |

**写通用代码就用 `get_theme_file_*()`**，它自动处理父子主题回退。

---

## 7. 插件开发

### 最小插件

```
my-plugin/
└── my-plugin.php
```

```php
<?php
/**
 * Plugin Name:       My Plugin
 * Plugin URI:        https://example.com
 * Description:       插件描述
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-plugin
 * Domain Path:       /languages
 */

// 阻止直接访问 —— 每个 PHP 文件都该有
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MY_PLUGIN_VERSION', '1.0.0' );
define( 'MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
```

### 生命周期钩子

```php
// 激活时（只在点「启用」那一刻执行一次）
register_activation_hook( __FILE__, 'my_plugin_activate' );
function my_plugin_activate() {
    // 建表、设默认选项、注册 CPT 后刷新固定链接
    my_plugin_register_post_types();
    flush_rewrite_rules();

    add_option( 'my_plugin_version', MY_PLUGIN_VERSION );
}

// 停用时
register_deactivation_hook( __FILE__, 'my_plugin_deactivate' );
function my_plugin_deactivate() {
    flush_rewrite_rules();
    wp_clear_scheduled_hook( 'my_plugin_cron' );   // 清理定时任务
}
```

**卸载时**（点「删除」）—— 建一个 `uninstall.php` 放在插件根目录：

```php
<?php
// uninstall.php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'my_plugin_settings' );
delete_option( 'my_plugin_version' );

// 删除所有相关 meta
global $wpdb;
$wpdb->delete( $wpdb->postmeta, [ 'meta_key' => '_my_plugin_data' ] );
```

⚠️ 激活钩子里**不能**直接 `flush_rewrite_rules()` 而不先注册 CPT——顺序反了规则刷不出来。

### 推荐的插件结构

```
my-plugin/
├── my-plugin.php           ← 主文件（只做引导）
├── uninstall.php
├── includes/
│   ├── class-plugin.php
│   ├── class-post-types.php
│   ├── class-admin.php
│   └── functions.php
├── admin/
│   ├── views/
│   └── assets/
├── public/
│   └── assets/
└── languages/
```

### 判断插件是否激活

```php
// 需要先引入这个文件（前台默认没加载）
if ( ! function_exists( 'is_plugin_active' ) ) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) { ... }

// 更简单的办法：判断函数/类存在
if ( function_exists( 'get_field' ) ) { ... }
if ( class_exists( 'WooCommerce' ) ) { ... }
```

推荐后者——不依赖路径，也不用引额外文件。

---

## 8. mu-plugins

**MU = Must Use（必用插件）**。放在 `wp-content/mu-plugins/` 的 PHP 文件会：

- **自动激活**，无法在后台停用
- 在**普通插件之前**加载
- 不显示在插件列表的常规视图（有独立的 "Must-Use" 标签页）

### 适合放什么

- 客户站的关键定制（防止客户误停用）
- 全站钩子修改
- 环境相关配置
- 第三方服务对接

### 注意：不递归扫描子目录

```
mu-plugins/
├── my-fix.php           ✅ 会加载
└── my-plugin/
    └── index.php        ❌ 不会加载
```

要用子目录，得建一个加载器：

```php
<?php
// mu-plugins/loader.php
require __DIR__ . '/my-plugin/index.php';
```

### 示例

```php
<?php
/**
 * Plugin Name: Site Customizations
 * Description: 站点关键定制，请勿删除
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 禁用后台文件编辑器
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
    define( 'DISALLOW_FILE_EDIT', true );
}

// 隐藏 WP 版本号
remove_action( 'wp_head', 'wp_generator' );

// 限制登录失败次数、改邮件发件人等...
```

`register_activation_hook()` 在 mu-plugin 里**无效**（因为没有"激活"这个动作）。要初始化就用 `init` 或直接执行。

---

## 9. WP_Query 完全指南

### 三种查询方式

```php
// 1. WP_Query —— 最灵活，需要手动 reset
$q = new WP_Query( $args );
while ( $q->have_posts() ) { $q->the_post(); }
wp_reset_postdata();

// 2. get_posts() —— 返回数组，不影响全局，适合简单场景
$posts = get_posts( $args );          // 默认 numberposts=5, suppress_filters=true
foreach ( $posts as $p ) { ... }

// 3. query_posts() —— ⚠️ 永远不要用
// 它会替换主查询，破坏分页和条件标签
```

### 完整参数

```php
$args = [
    // ── 类型与状态 ──
    'post_type'   => 'post',            // 'any' / ['post','page']
    'post_status' => 'publish',         // 'any' / ['publish','draft']

    // ── 数量与分页 ──
    'posts_per_page' => 10,
    'paged'          => get_query_var('paged') ?: 1,
    'offset'         => 0,              // ⚠️ 和 paged 冲突，别同时用
    'nopaging'       => false,

    // ── 排序 ──
    'orderby' => 'date',
    // date / ID / title / name / type / rand / comment_count
    // menu_order / meta_value / meta_value_num / post__in / modified
    'order'   => 'DESC',
    // 多重排序
    'orderby' => [ 'menu_order' => 'ASC', 'date' => 'DESC' ],

    // ── 指定文章 ──
    'p'            => 42,               // 单篇 ID
    'name'         => 'my-slug',
    'page_id'      => 42,
    'pagename'     => 'about',
    'post__in'     => [1,2,3],
    'post__not_in' => [4,5],
    'post_parent'  => 10,
    'post_parent__in' => [10,11],

    // ── 作者 ──
    'author'       => 1,
    'author_name'  => 'admin',
    'author__in'   => [1,2],

    // ── 分类法 ──
    'cat'          => 5,
    'category_name'=> 'news',
    'tag'          => 'featured',
    'tax_query'    => [
        'relation' => 'AND',
        [
            'taxonomy' => 'genre',
            'field'    => 'slug',       // term_id / name / slug / term_taxonomy_id
            'terms'    => ['rock','jazz'],
            'operator' => 'IN',         // IN / NOT IN / AND / EXISTS / NOT EXISTS
            'include_children' => true,
        ],
    ],

    // ── 自定义字段 ──
    'meta_key'   => 'price',
    'meta_value' => '100',
    'meta_query' => [
        'relation' => 'OR',
        'cheap' => [                    // 命名子句（可用于 orderby）
            'key'     => 'price',
            'value'   => 100,
            'compare' => '<',
            'type'    => 'NUMERIC',
        ],
        [
            'key'     => 'featured',
            'compare' => 'EXISTS',
        ],
    ],
    'orderby' => [ 'cheap' => 'ASC' ],  // 按命名子句排序

    // ── 日期 ──
    'date_query' => [
        'relation' => 'AND',
        [ 'after'  => '2026-01-01', 'inclusive' => true ],
        [ 'before' => '2026-12-31' ],
        [ 'year' => 2026, 'month' => 8 ],
    ],

    // ── 搜索 ──
    's' => '关键词',

    // ── 性能 ──
    'no_found_rows'          => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
    'fields'                 => 'ids',   // 'ids' / 'id=>parent' / ''
    'cache_results'          => true,
];
```

### compare 运算符

```
=  !=  >  >=  <  <=
LIKE  NOT LIKE
IN  NOT IN
BETWEEN  NOT BETWEEN
EXISTS  NOT EXISTS
REGEXP  NOT REGEXP  RLIKE
```

### type 值

```
NUMERIC  DECIMAL  SIGNED  UNSIGNED
CHAR  BINARY
DATE  DATETIME  TIME
```

**meta_value 全部按字符串存**，所以数字比较必须指定 `'type' => 'NUMERIC'`，否则 `'100' < '9'` 会成立（字符串比较）。

### 查询对象的属性

```php
$q->posts;              // 文章数组
$q->post_count;         // 本页数量
$q->found_posts;        // 符合条件的总数
$q->max_num_pages;      // 总页数
$q->current_post;       // 当前索引
$q->request;            // 实际执行的 SQL ← 调试利器
$q->is_main_query();
```

### 修改主查询

**永远用 `pre_get_posts`，不要用 `query_posts()`**：

```php
add_action( 'pre_get_posts', function( $q ) {
    // 三重保护
    if ( is_admin() )          return;    // 不影响后台
    if ( ! $q->is_main_query() ) return;  // 不影响副查询

    if ( $q->is_post_type_archive('case') ) {
        $q->set( 'posts_per_page', 12 );
        $q->set( 'meta_key', 'sort_order' );
        $q->set( 'orderby', 'meta_value_num' );
    }

    // 搜索只搜文章和案例
    if ( $q->is_search() ) {
        $q->set( 'post_type', ['post','case'] );
    }
});
```

⚠️ `is_admin()` 对 **AJAX 请求也返回 true**。如果你的 AJAX 需要走这个逻辑，要额外判断 `wp_doing_ajax()`。

---

## 10. 直接操作数据库：$wpdb

**先问自己：能用 `WP_Query` / `get_posts()` 吗？** 90% 的情况能。只有跨表统计、自定义表才需要 `$wpdb`。

```php
global $wpdb;

// 表名（自动带前缀）
$wpdb->posts        $wpdb->postmeta
$wpdb->users        $wpdb->usermeta
$wpdb->terms        $wpdb->term_taxonomy    $wpdb->term_relationships
$wpdb->options      $wpdb->comments
$wpdb->prefix . 'my_custom_table'
```

### 查询方法

```php
$wpdb->get_var( $sql );        // 单个值
$wpdb->get_row( $sql );        // 单行（对象）
$wpdb->get_col( $sql );        // 单列（数组）
$wpdb->get_results( $sql );    // 多行（对象数组）
$wpdb->query( $sql );          // 执行，返回影响行数
```

### prepare —— 防 SQL 注入

```php
// ❌ 危险
$wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_author = {$_GET['id']}" );

// ✅ 安全
$wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE post_author = %d AND post_type = %s",
    $_GET['id'],
    'post'
) );
```

占位符：`%d` 整数 / `%s` 字符串 / `%f` 浮点 / `%i` 标识符（表名列名，WP 6.2+）

**LIKE 要特殊处理**：

```php
$term = $wpdb->esc_like( $_GET['q'] );
$wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE post_title LIKE %s",
    '%' . $term . '%'
) );
```

**IN 子句的占位符要动态生成**：

```php
$ids          = [1,2,3];
$placeholders = implode( ',', array_fill( 0, count($ids), '%d' ) );
$wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE ID IN ($placeholders)",
    ...$ids
) );
```

### 增删改

```php
$wpdb->insert( $table, [ 'name' => 'x', 'count' => 1 ], [ '%s', '%d' ] );
$insert_id = $wpdb->insert_id;

$wpdb->update( $table, [ 'count' => 2 ], [ 'id' => 1 ], [ '%d' ], [ '%d' ] );

$wpdb->delete( $table, [ 'id' => 1 ], [ '%d' ] );

$wpdb->replace( $table, $data );
```

### 建自定义表

```php
function my_create_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'my_data';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        value varchar(255) NOT NULL,
        created datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY post_id (post_id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );    // 智能建表/改表
}
register_activation_hook( __FILE__, 'my_create_table' );
```

**`dbDelta()` 的格式要求非常严格**：
- `PRIMARY KEY` 后面**两个空格**
- 每个字段一行
- 关键字大写
- 不能有多余空格

### 错误调试

```php
$wpdb->show_errors();
$wpdb->last_query;       // 最后执行的 SQL
$wpdb->last_error;
$wpdb->num_rows;
```

---

## 11. 用户、角色与权限

### 默认角色

| 角色 | 能力概要 |
|---|---|
| `administrator` | 全部 |
| `editor` | 管理所有人的文章、页面、分类、评论 |
| `author` | 发布和管理自己的文章、上传文件 |
| `contributor` | 写自己的文章但不能发布、不能上传 |
| `subscriber` | 只能改自己的资料 |

### 常用能力（capability）

```
manage_options          管理设置（≈ 管理员）
edit_posts              编辑文章
publish_posts           发布文章
edit_others_posts       编辑他人文章
delete_posts
upload_files            上传媒体
edit_theme_options      改主题选项、菜单
install_plugins
switch_themes
edit_pages
read
```

### 检查权限

```php
// 永远用 current_user_can()，不要判断角色名
if ( current_user_can( 'edit_posts' ) ) { ... }
if ( current_user_can( 'edit_post', $post_id ) ) { ... }   // 针对具体对象

// ❌ 不要这样
if ( in_array( 'editor', $user->roles ) ) { ... }
```

**为什么不判断角色名**：角色是能力的集合，客户可能装了会员插件自定义角色。判断能力才是稳的。

### 用户操作

```php
$user = wp_get_current_user();
$user->ID;
$user->user_login;
$user->user_email;
$user->display_name;
$user->roles;                    // 数组

get_user_by( 'email', 'x@y.com' );      // id / slug / email / login
get_userdata( 1 );

is_user_logged_in();
wp_get_current_user()->ID;
get_current_user_id();

// 用户 meta
get_user_meta( $user_id, 'key', true );
update_user_meta( $user_id, 'key', $value );

// 创建
$id = wp_insert_user([
    'user_login' => 'newuser',
    'user_email' => 'x@y.com',
    'user_pass'  => wp_generate_password(),
    'role'       => 'subscriber',
]);
```

### 自定义角色与能力

```php
// 加角色（只需执行一次，写在激活钩子里）
add_role( 'client', '客户', [
    'read'         => true,
    'edit_posts'   => true,
    'upload_files' => true,
] );

// 给现有角色加能力
$role = get_role( 'editor' );
$role->add_cap( 'edit_theme_options' );
$role->remove_cap( 'delete_posts' );

// 删角色
remove_role( 'client' );
```

⚠️ `add_role()` / `add_cap()` 会**写入数据库**，不要放在 `init` 里每次请求都跑。放激活钩子，或加版本号判断。

---

## 12. Options 与设置 API

### 简单读写

```php
add_option( 'my_key', $value, '', 'no' );    // 第 4 参数：autoload
update_option( 'my_key', $value, false );    // 第 3 参数：autoload
get_option( 'my_key', $default );
delete_option( 'my_key' );
```

**建议把所有设置存成一个数组**，只占一条记录：

```php
$settings = get_option( 'my_plugin_settings', [] );
$settings['api_key'] = 'xxx';
update_option( 'my_plugin_settings', $settings );
```

### Settings API（做后台设置页）

```php
// 1. 加菜单
add_action( 'admin_menu', function() {
    add_options_page(
        '我的设置',           // 页面标题
        '我的设置',           // 菜单名
        'manage_options',    // 权限
        'my-settings',       // slug
        'my_settings_page'   // 渲染回调
    );
});

// 2. 注册设置
add_action( 'admin_init', function() {
    register_setting( 'my_group', 'my_plugin_settings', [
        'sanitize_callback' => 'my_sanitize',
    ]);

    add_settings_section( 'main', '基本设置', null, 'my-settings' );

    add_settings_field( 'api_key', 'API Key', function() {
        $s = get_option( 'my_plugin_settings', [] );
        printf(
            '<input type="text" name="my_plugin_settings[api_key]" value="%s" class="regular-text">',
            esc_attr( $s['api_key'] ?? '' )
        );
    }, 'my-settings', 'main' );
});

// 3. 渲染页面
function my_settings_page() { ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'my_group' );          // nonce + option_page
            do_settings_sections( 'my-settings' );
            submit_button();
            ?>
        </form>
    </div>
<?php }

// 4. 净化
function my_sanitize( $input ) {
    return [ 'api_key' => sanitize_text_field( $input['api_key'] ?? '' ) ];
}
```

菜单位置函数：`add_menu_page()`（顶级）/ `add_submenu_page()` / `add_options_page()` / `add_theme_page()` / `add_management_page()`

---

## 13. Metabox 与自定义字段

不装 ACF 时的原生做法：

```php
// 添加
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'my_box',              // id
        '额外信息',             // 标题
        'my_box_render',       // 渲染回调
        'post',                // post type（可数组）
        'normal',              // normal / side / advanced
        'high'                 // high / core / default / low
    );
});

// 渲染
function my_box_render( $post ) {
    wp_nonce_field( 'my_box_save', 'my_box_nonce' );
    $value = get_post_meta( $post->ID, '_my_field', true );
    ?>
    <label>
        字段值
        <input type="text" name="my_field" value="<?php echo esc_attr( $value ); ?>" class="widefat">
    </label>
    <?php
}

// 保存
add_action( 'save_post', function( $post_id ) {
    // 四重检查，一个都不能少
    if ( ! isset( $_POST['my_box_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['my_box_nonce'], 'my_box_save' ) ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['my_field'] ) ) {
        update_post_meta( $post_id, '_my_field', sanitize_text_field( $_POST['my_field'] ) );
    }
});

// 移除别人的 metabox
add_action( 'add_meta_boxes', function() {
    remove_meta_box( 'postcustom', 'post', 'normal' );   // 自定义字段面板
    remove_meta_box( 'commentstatusdiv', 'post', 'normal' );
    remove_meta_box( 'postdivrich', 'page', 'normal' );  // 正文编辑器
}, 100 );
```

---

## 14. 短代码

```php
add_shortcode( 'my_button', function( $atts, $content = null, $tag = '' ) {
    $a = shortcode_atts([
        'url'   => '#',
        'style' => 'primary',
    ], $atts, $tag );

    return sprintf(
        '<a href="%s" class="btn btn-%s">%s</a>',
        esc_url( $a['url'] ),
        esc_attr( $a['style'] ),
        esc_html( $content ?: '点击' )
    );
});
```

使用：`[my_button url="/contact" style="ghost"]联系我们[/my_button]`

**短代码必须 `return`，不能 `echo`**。要输出复杂 HTML 用输出缓冲：

```php
add_shortcode( 'my_list', function( $atts ) {
    ob_start();
    ?>
    <ul class="my-list">
        <?php foreach ( get_posts(['numberposts' => 5]) as $p ) : ?>
            <li><a href="<?php echo esc_url( get_permalink($p) ); ?>">
                <?php echo esc_html( get_the_title($p) ); ?>
            </a></li>
        <?php endforeach; ?>
    </ul>
    <?php
    return ob_get_clean();
});
```

其他 API：

```php
do_shortcode( '[my_button]' );      // 在 PHP 里执行短代码
shortcode_exists( 'my_button' );
remove_shortcode( 'my_button' );
strip_shortcodes( $content );       // 去掉所有短代码
```

---

## 15. 重写规则与自定义 URL

### 添加规则

```php
add_action( 'init', function() {
    add_rewrite_rule(
        '^team/([^/]+)/?$',                    // 正则
        'index.php?pagename=team&member=$matches[1]',   // 映射
        'top'                                  // top / bottom
    );
});

// 注册查询变量（必须！否则 get_query_var 拿不到）
add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'member';
    return $vars;
});

// 模板中使用
$member = get_query_var( 'member' );
```

### 刷新规则

添加规则后**必须刷新**，否则 404：

- 手动：后台 → 设置 → 固定链接 → 保存
- 代码：`flush_rewrite_rules()` —— **只在激活钩子里调**

```php
register_activation_hook( __FILE__, function() {
    my_register_rules();
    flush_rewrite_rules();
});
```

⚠️ **永远不要在 `init` 里直接调 `flush_rewrite_rules()`**。它会重写 `.htaccess` 并重建全部规则，每个请求跑一次会严重拖慢站点。

### 调试规则

```php
global $wp_rewrite;
print_r( $wp_rewrite->wp_rewrite_rules() );
```

或用 Query Monitor 插件的 "Rewrites" 面板。

---

## 16. WP-Cron 定时任务

**重要认知：WP-Cron 不是真 cron**。它依赖有人访问站点才触发——没人访问就不执行，访问量大时又会频繁检查。

```php
// 注册（通常在激活钩子）
if ( ! wp_next_scheduled( 'my_daily_task' ) ) {
    wp_schedule_event( time(), 'daily', 'my_daily_task' );
}

// 绑定处理函数
add_action( 'my_daily_task', function() {
    // 干活
});

// 清理（停用钩子）
wp_clear_scheduled_hook( 'my_daily_task' );
```

内置间隔：`hourly` / `twicedaily` / `daily` / `weekly`

自定义间隔：

```php
add_filter( 'cron_schedules', function( $s ) {
    $s['every_15_min'] = [
        'interval' => 900,
        'display'  => '每 15 分钟',
    ];
    return $s;
});
```

一次性任务：

```php
wp_schedule_single_event( time() + 3600, 'my_one_time_task', [ $arg1 ] );
```

### 生产环境改用系统 cron

```php
// wp-config.php
define( 'DISABLE_WP_CRON', true );
```

```bash
# crontab -e
*/5 * * * * cd /path/to/wp && /usr/bin/php wp-cron.php > /dev/null 2>&1
# 或
*/5 * * * * /usr/local/bin/wp cron event run --due-now --path=/path/to/wp
```

这样定时任务才准时，也不会拖慢前台请求。

---

## 17. HTTP API：请求外部接口

**不要用 `curl_*` 或 `file_get_contents()`**——WP 的 HTTP API 会自动处理代理、SSL、超时、多种传输后端。

```php
$res = wp_remote_get( 'https://api.example.com/data', [
    'timeout' => 15,
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Accept'        => 'application/json',
    ],
]);

// 必须检查错误
if ( is_wp_error( $res ) ) {
    error_log( 'API 请求失败: ' . $res->get_error_message() );
    return false;
}

$code = wp_remote_retrieve_response_code( $res );
if ( 200 !== $code ) {
    error_log( "API 返回 {$code}" );
    return false;
}

$body = wp_remote_retrieve_body( $res );
$data = json_decode( $body, true );
```

POST：

```php
$res = wp_remote_post( 'https://api.example.com/subscribe', [
    'timeout' => 15,
    'headers' => [ 'Content-Type' => 'application/json' ],
    'body'    => wp_json_encode([ 'email' => $email ]),
]);
```

辅助函数：

```php
wp_remote_retrieve_body( $res );
wp_remote_retrieve_response_code( $res );
wp_remote_retrieve_response_message( $res );
wp_remote_retrieve_header( $res, 'content-type' );
wp_remote_retrieve_headers( $res );
```

**外部 API 一定要配缓存**，否则每次页面加载都发请求：

```php
function get_api_data() {
    $data = get_transient( 'my_api_data' );
    if ( false === $data ) {
        $res = wp_remote_get( '...' );
        if ( is_wp_error( $res ) ) {
            return get_transient( 'my_api_data_backup' ) ?: [];   // 降级
        }
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        set_transient( 'my_api_data', $data, HOUR_IN_SECONDS );
        set_transient( 'my_api_data_backup', $data, WEEK_IN_SECONDS );  // 长期备份
    }
    return $data;
}
```

---

## 18. REST API

### 内置端点

```
/wp-json/wp/v2/posts
/wp-json/wp/v2/pages
/wp-json/wp/v2/media
/wp-json/wp/v2/users
/wp-json/wp/v2/categories
/wp-json/wp/v2/{custom-post-type}     ← 需要 show_in_rest => true
```

### 自定义端点

```php
add_action( 'rest_api_init', function() {
    register_rest_route( 'myplugin/v1', '/items/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::READABLE,   // GET
        'callback'            => 'my_get_item',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'required'          => true,
                'validate_callback' => fn($p) => is_numeric($p),
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
});

function my_get_item( WP_REST_Request $req ) {
    $id   = $req['id'];
    $post = get_post( $id );

    if ( ! $post ) {
        return new WP_Error( 'not_found', '找不到', [ 'status' => 404 ] );
    }

    return rest_ensure_response([
        'id'    => $post->ID,
        'title' => get_the_title( $post ),
    ]);
}
```

方法常量：`READABLE`(GET) / `CREATABLE`(POST) / `EDITABLE`(POST,PUT,PATCH) / `DELETABLE`(DELETE) / `ALLMETHODS`

### 权限控制

```php
'permission_callback' => function() {
    return current_user_can( 'edit_posts' );
},
```

**`permission_callback` 是必填的**，省略会产生 `_doing_it_wrong` 警告。公开接口显式写 `'__return_true'`。

### 给内置端点加字段

```php
add_action( 'rest_api_init', function() {
    register_rest_field( 'post', 'featured_image_url', [
        'get_callback' => function( $post ) {
            return get_the_post_thumbnail_url( $post['id'], 'large' );
        },
        'schema' => [ 'type' => 'string' ],
    ]);
});
```

### 前端认证

```js
// 已登录用户（同源，用 nonce）
fetch('/wp-json/myplugin/v1/items', {
  headers: { 'X-WP-Nonce': MyData.nonce },   // wp_create_nonce('wp_rest')
  credentials: 'same-origin',
});
```

外部应用用 Application Passwords（WP 5.6+）或 JWT 插件。

---

## 19. 缓存体系

### 三层缓存

```
┌──────────────────────────────────────┐
│ 1. 对象缓存 (Object Cache)            │  单次请求内 / Redis-Memcached 持久
├──────────────────────────────────────┤
│ 2. Transient                         │  存 options 表 / 有对象缓存时走它
├──────────────────────────────────────┤
│ 3. 页面缓存                           │  整页 HTML（插件或服务器层）
└──────────────────────────────────────┘
```

### 对象缓存

**默认只在单次请求内有效**（非持久）。装了 Redis / Memcached 插件后才跨请求持久。

```php
wp_cache_get( $key, $group );
wp_cache_set( $key, $data, $group, $expire );
wp_cache_add( $key, $data, $group, $expire );     // 已存在则不覆盖
wp_cache_delete( $key, $group );
wp_cache_flush();                                  // 清空全部（慎用）
```

```php
function get_expensive_data() {
    $key   = 'my_expensive_data';
    $group = 'myplugin';

    $data = wp_cache_get( $key, $group );
    if ( false === $data ) {
        $data = /* 耗时计算 */;
        wp_cache_set( $key, $data, $group, 300 );
    }
    return $data;
}
```

### Transient

```php
set_transient( $key, $value, $expiration );
get_transient( $key );                     // 不存在或过期返回 false
delete_transient( $key );

// 站点级（多站点时全网共享）
set_site_transient(); get_site_transient(); delete_site_transient();
```

**`false` 是合法返回值的坑**：如果你缓存的数据本身可能是 `false`，就无法区分「缓存未命中」和「缓存了 false」。解决办法是缓存数组包装：

```php
$cached = get_transient( $key );
if ( false === $cached ) {
    $cached = [ 'data' => $result ];
    set_transient( $key, $cached, HOUR_IN_SECONDS );
}
$result = $cached['data'];
```

### 时间常量

```php
MINUTE_IN_SECONDS   // 60
HOUR_IN_SECONDS     // 3600
DAY_IN_SECONDS      // 86400
WEEK_IN_SECONDS     // 604800
MONTH_IN_SECONDS    // 2592000
YEAR_IN_SECONDS     // 31536000
```

### 内容更新时清缓存

```php
add_action( 'save_post', function( $post_id ) {
    delete_transient( 'homepage_featured' );
    wp_cache_delete( 'my_key', 'my_group' );
});
```

---

## 20. 多语言 i18n

### 基本函数

```php
__( 'Hello', 'textdomain' );              // 返回翻译
_e( 'Hello', 'textdomain' );              // 直接输出
esc_html__( 'Hello', 'textdomain' );      // 返回 + 转义 ✅ 推荐
esc_html_e( 'Hello', 'textdomain' );      // 输出 + 转义 ✅ 推荐
esc_attr__( 'Hello', 'textdomain' );      // 用于属性

_n( '%s item', '%s items', $count, 'textdomain' );        // 单复数
_x( 'Post', 'noun', 'textdomain' );                       // 带上下文消歧
_nx( '%s post', '%s posts', $n, 'context', 'textdomain' );
```

### 带变量

**永远用 `sprintf`，不要字符串拼接**（不同语言语序不同）：

```php
// ❌
echo __( 'Hello ', 'td' ) . $name;

// ✅
printf( esc_html__( 'Hello %s', 'td' ), esc_html( $name ) );

// 多个变量用位置占位符
printf(
    esc_html__( '%1$s wrote %2$s', 'td' ),
    esc_html( $author ),
    esc_html( $title )
);
```

### 加载语言包

```php
// 主题
add_action( 'after_setup_theme', function() {
    load_theme_textdomain( 'my-theme', get_template_directory() . '/languages' );
});

// 插件
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'my-plugin', false, dirname( plugin_basename(__FILE__) ) . '/languages' );
});
```

**text domain 必须是字符串字面量**，不能用变量或常量——翻译工具是静态扫描代码的。

### 生成翻译文件

```bash
wp i18n make-pot . languages/my-theme.pot
# 用 Poedit 翻译 → 生成 .po 和 .mo
# 文件名格式：{textdomain}-{locale}.mo，如 my-theme-zh_CN.mo
```

---

## 21. 媒体与附件

### 附件也是 post

```php
$attachment_id = 123;

wp_get_attachment_url( $id );                        // 原图 URL
wp_get_attachment_image_url( $id, 'medium' );        // 指定尺寸 URL
wp_get_attachment_image( $id, 'medium', false, $attr );  // 完整 <img> 带 srcset
wp_get_attachment_image_src( $id, 'medium' );        // [url, w, h, resized]
wp_get_attachment_metadata( $id );                   // 所有尺寸信息
get_attached_file( $id );                            // 服务器路径

// alt 文本存在 meta 里
get_post_meta( $id, '_wp_attachment_image_alt', true );
```

### 注册尺寸

```php
add_action( 'after_setup_theme', function() {
    add_image_size( 'card', 600, 400, true );          // 强裁
    add_image_size( 'banner', 1920, 600, ['center','top'] );  // 指定锚点
    add_image_size( 'wide', 1600, 9999, false );       // 只限宽
});

// 让自定义尺寸出现在后台下拉里
add_filter( 'image_size_names_choose', function( $sizes ) {
    return array_merge( $sizes, [ 'card' => '卡片图' ] );
});
```

**已上传的图不会自动生成新尺寸**。改了尺寸设置后要用 Regenerate Thumbnails 插件或 `wp media regenerate` 重新生成。

### 控制生成数量（省磁盘）

```php
add_filter( 'intermediate_image_sizes_advanced', function( $sizes ) {
    unset( $sizes['medium_large'] );    // 768px
    unset( $sizes['1536x1536'] );
    unset( $sizes['2048x2048'] );
    return $sizes;
});

// 关掉 scaled（WP 5.3+ 会把大图缩到 2560）
add_filter( 'big_image_size_threshold', '__return_false' );
```

### 代码上传文件

```php
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$attachment_id = media_sideload_image( $url, $post_id, $desc, 'id' );

// 或从本地文件
$attachment_id = media_handle_sideload( $file_array, $post_id );
```

### 允许更多文件类型

```php
add_filter( 'upload_mimes', function( $mimes ) {
    $mimes['svg']  = 'image/svg+xml';
    $mimes['webp'] = 'image/webp';
    return $mimes;
});
```

⚠️ **允许 SVG 有 XSS 风险**（SVG 里能嵌 `<script>`）。给客户站开 SVG 上传前，要么装净化插件（Safe SVG），要么限制只有管理员能传。

---

## 22. 邮件

```php
$to      = 'x@y.com';
$subject = '主题';
$body    = '<p>HTML 内容</p>';
$headers = [
    'Content-Type: text/html; charset=UTF-8',
    'From: 站点名 <noreply@example.com>',
    'Reply-To: info@example.com',
];

$sent = wp_mail( $to, $subject, $body, $headers, $attachments );
```

### 改默认发件人

```php
add_filter( 'wp_mail_from', fn() => 'noreply@example.com' );
add_filter( 'wp_mail_from_name', fn() => get_bloginfo('name') );
```

### 调试发信失败

```php
add_action( 'wp_mail_failed', function( $error ) {
    error_log( 'wp_mail 失败: ' . $error->get_error_message() );
});
```

**生产环境务必配 SMTP**（WP Mail SMTP 等插件）。PHP 的 `mail()` 函数发出去的信基本都进垃圾箱，甚至直接被拒收。

---

## 23. 安全完整清单

### 原则

```
输入 → sanitize（净化）
输出 → escape（转义）
操作 → nonce + capability（验证）
```

### 输出转义对照表

| 场景 | 函数 |
|---|---|
| HTML 文本 | `esc_html()` |
| HTML 属性 | `esc_attr()` |
| URL | `esc_url()` |
| `<textarea>` | `esc_textarea()` |
| 内联 JS 字符串 | `esc_js()` |
| 允许 HTML 的富文本 | `wp_kses_post()` |
| 自定义标签白名单 | `wp_kses( $html, $allowed )` |
| JSON | `wp_json_encode()` |

```php
// 自定义白名单
$allowed = [
    'a'      => [ 'href' => [], 'title' => [], 'target' => [] ],
    'strong' => [],
    'em'     => [],
    'br'     => [],
];
echo wp_kses( $content, $allowed );
```

### 输入净化对照表

| 类型 | 函数 |
|---|---|
| 单行文本 | `sanitize_text_field()` |
| 多行文本 | `sanitize_textarea_field()` |
| 邮箱 | `sanitize_email()` |
| URL | `esc_url_raw()`（入库用）/ `sanitize_url()` |
| 文件名 | `sanitize_file_name()` |
| slug | `sanitize_title()` |
| HTML 内容 | `wp_kses_post()` |
| 整数 | `absint()` / `intval()` |
| 键名 | `sanitize_key()` |

### Nonce

```php
// 表单
wp_nonce_field( 'my_action', 'my_nonce' );

// URL
$url = wp_nonce_url( admin_url('admin.php?page=x'), 'my_action', 'my_nonce' );

// AJAX
wp_create_nonce( 'my_action' );

// 验证
wp_verify_nonce( $_POST['my_nonce'], 'my_action' );      // 返回 1/2/false
check_admin_referer( 'my_action', 'my_nonce' );          // 失败直接 die
check_ajax_referer( 'my_action', 'nonce' );              // AJAX 版
```

**nonce 不是万能的**——它防 CSRF，但不防越权。必须同时检查 `current_user_can()`。

### wp-config.php 加固

```php
define( 'DISALLOW_FILE_EDIT', true );        // 禁用后台代码编辑器
define( 'DISALLOW_FILE_MODS', true );        // 禁止后台装插件/主题
define( 'FORCE_SSL_ADMIN', true );
define( 'WP_DEBUG_DISPLAY', false );         // 生产必须
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
```

### 其他

```php
// 隐藏版本号
remove_action( 'wp_head', 'wp_generator' );

// 禁用 XML-RPC（暴力破解常用入口）
add_filter( 'xmlrpc_enabled', '__return_false' );

// 禁用用户枚举（/?author=1 会暴露用户名）
add_action( 'template_redirect', function() {
    if ( ! is_admin() && isset($_GET['author']) ) {
        wp_safe_redirect( home_url(), 301 );
        exit;
    }
});

// 登录错误不透露是用户名错还是密码错
add_filter( 'login_errors', fn() => '登录信息有误。' );

// REST API 用户端点需登录
add_filter( 'rest_endpoints', function( $endpoints ) {
    if ( ! is_user_logged_in() ) {
        unset( $endpoints['/wp/v2/users'] );
        unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    }
    return $endpoints;
});
```

### 文件头防护

每个 PHP 文件顶部：

```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

---

## 24. 性能优化

### 查询层

```php
// 不要分页就关掉 COUNT
'no_found_rows' => true,

// 不读 meta/term 就别预加载
'update_post_meta_cache' => false,
'update_post_term_cache' => false,

// 只要 ID
'fields' => 'ids',

// 避免这些
'posts_per_page' => -1,        // 数据量大时内存爆
'orderby' => 'rand',           // 全表扫描 + filesort
'meta_query' 的 LIKE '%x%'      // 无法用索引
```

### N+1 问题

```php
// ❌ 循环里逐条查
foreach ( $ids as $id ) {
    $title = get_the_title( $id );      // 每次一个查询
}

// ✅ 一次查完（WP 会缓存）
$posts = get_posts([ 'post__in' => $ids, 'numberposts' => -1 ]);
```

### 资源层

```php
// 移除 emoji
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

// 移除古腾堡前端样式（自定义主题不需要）
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'wp-block-library-theme' );
    wp_dequeue_style( 'global-styles' );
    wp_dequeue_style( 'classic-theme-styles' );
}, 100 );

// 移除 jQuery Migrate
add_action( 'wp_default_scripts', function( $scripts ) {
    if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
        $scripts->registered['jquery']->deps = array_diff(
            $scripts->registered['jquery']->deps, ['jquery-migrate']
        );
    }
});
```

### 数据库层

```php
// wp-config.php
define( 'WP_POST_REVISIONS', 5 );       // 限制修订版数量
define( 'AUTOSAVE_INTERVAL', 300 );
define( 'EMPTY_TRASH_DAYS', 7 );
```

定期清理：

```sql
-- 删除所有修订版
DELETE FROM wp_posts WHERE post_type = 'revision';

-- 清理孤立 meta
DELETE pm FROM wp_postmeta pm
LEFT JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.ID IS NULL;

-- 清理过期 transient
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_%'
AND option_value < UNIX_TIMESTAMP();
```

（用 WP-Optimize 插件更安全，别手撸 SQL 除非你备份了）

### 检查 autoload 膨胀

```sql
SELECT SUM(LENGTH(option_value))/1024/1024 AS mb
FROM wp_options WHERE autoload = 'yes';
```

超过 1 MB 就该查是哪个插件在乱塞了。

---

## 25. WP-CLI

命令行管理 WordPress，做客户站运维时能省大量时间。

```bash
# 核心
wp core version
wp core update
wp core verify-checksums              # 校验核心文件有没有被改（查木马）

# 插件
wp plugin list
wp plugin install advanced-custom-fields --activate
wp plugin update --all
wp plugin deactivate --all

# 主题
wp theme list
wp theme activate my-theme

# 数据库
wp db export backup.sql
wp db import backup.sql
wp db optimize
wp db query "SELECT COUNT(*) FROM wp_posts"

# 搜索替换（迁移域名必备，自动处理序列化数据）
wp search-replace 'http://old.com' 'https://new.com' --dry-run
wp search-replace 'http://old.com' 'https://new.com' --skip-columns=guid

# 文章
wp post list --post_type=page --format=table
wp post create --post_type=page --post_title='测试' --post_status=publish
wp post delete 42 --force

# 用户
wp user list
wp user create bob bob@example.com --role=editor
wp user update 1 --user_pass=newpassword       # 忘记密码时救命

# 媒体
wp media regenerate --yes                       # 重新生成所有缩略图

# 缓存
wp cache flush
wp transient delete --all

# 重写规则
wp rewrite flush

# 定时任务
wp cron event list
wp cron event run --due-now

# 执行 PHP
wp eval 'echo home_url();'
wp eval-file script.php
```

`--skip-columns=guid` 在搜索替换时**必须加**——前面讲过 guid 不能改。

---

## 26. 环境与部署

### wp-config.php 分环境

```php
// 环境判断
define( 'WP_ENVIRONMENT_TYPE', 'staging' );   // production/staging/development/local

// 代码里用
if ( 'production' === wp_get_environment_type() ) {
    define( 'WP_DEBUG', false );
} else {
    define( 'WP_DEBUG', true );
    define( 'WP_DEBUG_LOG', true );
    define( 'WP_DEBUG_DISPLAY', false );
}
```

### 常用常量

```php
// 数据库
define( 'DB_NAME', '' );
define( 'DB_USER', '' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );

// URL（硬编码可以避免数据库里的地址出错）
define( 'WP_HOME', 'https://example.com' );
define( 'WP_SITEURL', 'https://example.com' );

// 路径
define( 'WP_CONTENT_DIR', dirname(__FILE__) . '/wp-content' );
define( 'UPLOADS', 'wp-content/uploads' );

// 性能与限制
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '512M' );
define( 'WP_POST_REVISIONS', 5 );
define( 'EMPTY_TRASH_DAYS', 7 );
define( 'DISABLE_WP_CRON', true );

// 安全
define( 'DISALLOW_FILE_EDIT', true );
define( 'FORCE_SSL_ADMIN', true );

// 多站点
define( 'WP_ALLOW_MULTISITE', true );
```

### 迁移站点的正确姿势

```bash
# 1. 导出
wp db export backup.sql

# 2. 传文件（wp-content 就够，核心可以重装）
rsync -avz wp-content/ user@newhost:/path/wp-content/

# 3. 导入
wp db import backup.sql

# 4. 换域名（关键：一定要用 wp-cli，不要用 SQL 的 REPLACE）
wp search-replace 'https://old.com' 'https://new.com' --skip-columns=guid

# 5. 刷新
wp rewrite flush
wp cache flush
```

**为什么不能用 SQL 的 `REPLACE()`**：WP 有大量序列化数据（`a:2:{s:5:"width";...}`），序列化格式里带字符串长度。直接替换会让长度对不上，数据整个失效。`wp search-replace` 会正确处理序列化。

### .gitignore 建议

```gitignore
wp-admin/
wp-includes/
wp-*.php
!wp-config-sample.php
wp-config.php
.htaccess
wp-content/uploads/
wp-content/cache/
wp-content/upgrade/
wp-content/plugins/          # 或只忽略第三方插件
!wp-content/plugins/my-custom-plugin/
node_modules/
*.log
.DS_Store
```

通常只把**自己写的主题和插件**进 Git，核心和第三方插件用 Composer 或直接在服务器装。

---

## 27. 调试工具箱

### wp-config.php

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );          // → wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );          // 加载未压缩的核心 JS/CSS
define( 'SAVEQUERIES', true );           // 记录 SQL（很耗内存，调完关掉）
@ini_set( 'display_errors', 0 );

// 自定义日志位置
define( 'WP_DEBUG_LOG', '/path/to/private/debug.log' );
```

### 调试函数

```php
error_log( print_r( $data, true ) );

// 带标记的日志助手
function dlog( $data, $label = '' ) {
    error_log( $label . ' ' . ( is_scalar($data) ? $data : print_r($data, true) ) );
}

// 页面输出
function dd( $data ) {
    echo '<pre style="background:#000;color:#0f0;padding:20px">';
    print_r( $data );
    echo '</pre>';
    die();
}
```

### 查看内部状态

```php
// 当前查询的 SQL
global $wp_query;
dlog( $wp_query->request );

// 所有 SQL（需 SAVEQUERIES）
global $wpdb;
dlog( $wpdb->queries );

// 某个钩子上挂了什么
global $wp_filter;
dlog( $wp_filter['the_content'] );

// 当前用了哪个模板
add_filter( 'template_include', function( $t ) {
    dlog( '模板: ' . $t );
    return $t;
}, 999 );

// 已加载的资源
global $wp_scripts, $wp_styles;
dlog( $wp_scripts->queue );

// 内存和耗时
echo memory_get_peak_usage(true)/1024/1024 . ' MB';
echo timer_stop();
```

### 常见错误速查

| 现象 | 排查方向 |
|---|---|
| 白屏 | PHP Fatal Error → 看 debug.log |
| "headers already sent" | `?>` 后有空行 / BOM / 提前输出 |
| filter 后页面空白 | 回调忘了 `return` |
| 自定义查询后标题错乱 | 忘了 `wp_reset_postdata()` |
| CPT 前台 404 | 没刷新固定链接 |
| `get_field()` 返回 null | 字段名错 / Location 不匹配 / 调用太早 |
| 样式改了不生效 | 版本号没变 → 用 `filemtime()` |
| 后台很慢 | autoload 选项膨胀 / 修订版过多 |
| 定时任务不跑 | WP-Cron 依赖访问量 → 改系统 cron |
| 迁移后到处乱码/失效 | 用了 SQL REPLACE 破坏了序列化 |

### 必装调试插件

**Query Monitor** —— 最重要的一个。装上就能看到：
- 所有 SQL 查询（耗时排序、重复查询标红）
- 钩子执行顺序
- 用了哪个模板文件
- HTTP 外部请求
- PHP 错误和警告
- 当前用户能力
- 重写规则匹配

其他：
- **User Switching** —— 一键切换用户身份
- **WP Crontrol** —— 查看和手动触发定时任务
- **Debug Bar** —— Query Monitor 的轻量替代

---

## 附录：常用函数速查

### 输出与获取

```php
the_title();        get_the_title( $id );
the_content();      get_the_content();
the_excerpt();      get_the_excerpt();
the_permalink();    get_permalink( $id );
the_ID();           get_the_ID();
the_date();         get_the_date( 'Y-m-d' );
the_author();       get_the_author();
the_post_thumbnail(); get_the_post_thumbnail_url( $id, 'large' );
```

### URL 与路径

```php
home_url( '/path' );
site_url();
admin_url( 'admin-ajax.php' );
rest_url( 'wp/v2/posts' );
wp_upload_dir();
get_theme_file_uri( '/assets/x.js' );
get_theme_file_path( '/assets/x.js' );
plugin_dir_url( __FILE__ );
plugin_dir_path( __FILE__ );
```

### 条件标签

```php
is_home()  is_front_page()  is_page()  is_single()  is_singular()
is_archive()  is_category()  is_tax()  is_search()  is_404()
is_admin()  is_user_logged_in()  current_user_can()
is_page_template()  is_post_type_archive()  wp_doing_ajax()
```

### 数据操作

```php
get_post( $id );            wp_insert_post();   wp_update_post();   wp_delete_post();
get_post_meta();            update_post_meta(); add_post_meta();    delete_post_meta();
get_option();               update_option();    add_option();       delete_option();
get_the_terms();            wp_set_object_terms();
get_users();                wp_insert_user();
```

### 安全

```php
esc_html()  esc_attr()  esc_url()  esc_textarea()  esc_js()  wp_kses_post()
sanitize_text_field()  sanitize_email()  absint()  sanitize_title()
wp_nonce_field()  wp_verify_nonce()  check_admin_referer()  current_user_can()
$wpdb->prepare()
```

---

## 学习路径

```
第 1 周   架构(1) + 数据库(2,3) + 生命周期(4)
          └─ 目标：理解 WP 的数据是怎么存的

第 2 周   钩子(5) + 主题(6)
          └─ 目标：能看懂任何主题的 functions.php

第 3 周   WP_Query(9) + 查询优化
          └─ 目标：能做任意复杂的列表页和筛选

第 4 周   插件(7,8) + 安全(23)
          └─ 目标：能把业务逻辑正确地放进插件

按需查阅  10-22, 24-27
```

### 官方资源

- [Developer Handbook](https://developer.wordpress.org/)
- [Theme Handbook](https://developer.wordpress.org/themes/)
- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Code Reference](https://developer.wordpress.org/reference/) —— 查函数、钩子、类
- [Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WP-CLI Commands](https://developer.wordpress.org/cli/commands/)
