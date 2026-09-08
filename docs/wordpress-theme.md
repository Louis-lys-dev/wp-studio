# WordPress 主题开发完整教程

> 面向「静态前端 → ACF 模块化主题」工作流
> 配套阅读：`wordpress-core.md`、`woocommerce.md`

---

## 目录

1. [WordPress 是怎么跑起来的](#1-wordpress-是怎么跑起来的)
2. [钩子系统：Action 与 Filter](#2-钩子系统action-与-filter)
3. [常用钩子速查（按时机）](#3-常用钩子速查按时机)
4. [模板层级：WP 怎么决定加载哪个文件](#4-模板层级wp-怎么决定加载哪个文件)
5. [get_template_part 与模块化](#5-get_template_part-与模块化)
6. [主循环 The Loop 与 WP_Query](#6-主循环-the-loop-与-wp_query)
7. [常用模板函数速查](#7-常用模板函数速查)
8. [资源加载：wp_enqueue](#8-资源加载wp_enqueue)
9. [ACF 深入](#9-acf-深入)
10. [静态前端 → ACF 模块的方法论](#10-静态前端--acf-模块的方法论)
11. [自定义文章类型与分类法](#11-自定义文章类型与分类法)
12. [菜单、侧边栏、主题支持](#12-菜单侧边栏主题支持)
13. [安全：转义与净化](#13-安全转义与净化)
14. [性能：缓存与查询优化](#14-性能缓存与查询优化)
15. [AJAX 与 REST API](#15-ajax-与-rest-api)
16. [调试](#16-调试)
17. [常见反模式与代码审查清单](#17-常见反模式与代码审查清单)
18. [速查附录](#18-速查附录)

---

## 1. WordPress 是怎么跑起来的

理解钩子之前，必须先理解**为什么需要钩子**。

WordPress 的每个请求都走同一条流水线。你的主题代码不是「主程序」，而是**挂在这条流水线的某些节点上**的插件式代码。这就是钩子存在的理由：你没法改 WP 核心，但 WP 在每个关键节点都喊一嗓子「有人要插手吗」，你应声即可。

### 请求生命周期（关键节点按顺序）

```
浏览器请求
   ↓
index.php → wp-blog-header.php → wp-load.php → wp-settings.php
   ↓
【muplugins_loaded】      mu-plugins 加载完（最早能用的钩子之一）
   ↓
【plugins_loaded】        所有插件加载完（ACF 此时可用）
   ↓
【setup_theme】
   ↓
【after_setup_theme】     ← 注册主题支持：add_theme_support()
   ↓
【init】                  ← 注册 CPT、分类法、菜单、短代码
   ↓
【wp_loaded】             WP 完全加载完毕
   ↓
   parse_request          解析 URL
   ↓
【pre_get_posts】         ← 修改主查询的最后机会（重要！）
   ↓
   WP_Query 执行          数据库查出这个页面要显示什么
   ↓
【wp】
   ↓
【template_redirect】     ← 重定向的最佳时机（还没输出任何 HTML）
   ↓
【template_include】      （filter）决定加载哪个模板文件
   ↓
   加载模板文件           ← 你的 page.php / index.php 在这里被执行
      ↓
      get_header()
         【wp_head】      ← wp_enqueue_scripts 的输出在这里落地
      ↓
      The Loop           ← 输出内容
      ↓
      get_footer()
         【wp_footer】
   ↓
【shutdown】
```

**为什么这个顺序重要**：

- 在 `init` 之前调用 `get_field()` 会失败——ACF 还没准备好
- 在 `template_redirect` 之后再 `wp_redirect()` 会报 "headers already sent"
- `wp_enqueue_scripts` 必须在 `wp_head` 之前挂，否则资源不会输出
- 想改主查询（比如「归档页每页显示 12 篇」），只能在 `pre_get_posts`，`WP_Query` 执行之后就晚了

### 对应到你的主题

```php
// functions/menu.php —— 注册菜单必须在 init
add_action( 'init', 'register_my_menus' );

// functions.php —— 主题支持必须在 after_setup_theme
add_action( 'after_setup_theme', 'theme_functions' );
function theme_functions() {
    add_theme_support( 'title-tag' );
}

// functions/js-css.php —— 前端资源
add_action('wp_enqueue_scripts', 'my_enqueue_scripts_frontpage');
```

这三个的时机都是对的。

---

## 2. 钩子系统：Action 与 Filter

WordPress 只有两种钩子。搞清楚区别，你就掌握了 80% 的 WP 开发。

### Action（动作）—— 做一件事，不返回

「到这个时间点了，执行点什么」。回调函数**不需要返回值**。

```php
add_action( 'wp_footer', 'my_tracking_code' );
function my_tracking_code() {
    echo '<script>console.log("hi")</script>';   // 直接输出
}
```

### Filter（过滤器）—— 改一个值，必须返回

「这里有个数据，你要不要改改再还给我」。回调函数**必须 return**，忘了 return 那个值就变成 `null`，页面直接出问题。

```php
add_filter( 'excerpt_length', 'my_excerpt_length' );
function my_excerpt_length( $length ) {   // 收到原值
    return 20;                             // 必须返回新值
}
```

**最常见的新手错误**：写 filter 忘了 return。

```php
// ❌ 错误：菜单会变成空白
add_filter( 'wp_nav_menu_items', function( $items ) {
    $items .= '<li>额外项</li>';
    // 忘了 return！
});

// ✅ 正确
add_filter( 'wp_nav_menu_items', function( $items ) {
    $items .= '<li>额外项</li>';
    return $items;
});
```

### 完整签名

```php
add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 );
add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 );
```

| 参数 | 说明 |
|---|---|
| `$hook` | 钩子名 |
| `$callback` | 函数名字符串 / 匿名函数 / `[$obj, 'method']` / `['Class', 'staticMethod']` |
| `$priority` | 数字越小越早执行，默认 10。同优先级按注册顺序 |
| `$accepted_args` | 回调接收几个参数，默认 1。**要用第 2 个及以后的参数，必须显式声明** |

### $accepted_args 的坑

```php
// ❌ 只声明了默认的 1 个参数，$post 会是 null → PHP Fatal Error
add_filter( 'use_block_editor_for_post', 'my_func' );
function my_func( $can_edit, $post ) { ... }

// ✅ 声明接收 2 个
add_filter( 'use_block_editor_for_post', 'my_func', 10, 2 );
```

你的 `functions.php` 里这一行是对的：

```php
add_filter( 'use_block_editor_for_post', 'll_disable_editor_for_page_templates', 10, 2 );
```

### 优先级的实战用法

```php
add_action( 'wp_head', 'a' );        // 默认 10
add_action( 'wp_head', 'b', 1 );     // 先执行
add_action( 'wp_head', 'c', 999 );   // 最后执行
```

想覆盖插件的行为？用比它大的优先级。你的主题里：

```php
// 优先级 100，确保在 WP 和插件都添加完 metabox 之后再移除
add_action( 'add_meta_boxes', 'll_remove_page_editor_metabox', 100 );
```

这就是典型用法——移除别人加的东西，必须排在人家后面。

### remove_action / remove_filter

移除已注册的钩子。**必须同时匹配「函数名」和「优先级」**，优先级不对就移除失败（而且不报错，静默失效）。

```php
remove_action( 'wp_head', 'wp_generator' );          // 默认优先级 10
remove_action( 'wp_head', 'feed_links', 2 );         // 注册时是 2，移除也必须写 2
```

你的 `functions/other.php` 就是一整套 wp_head 清理。注意两个小问题：

```php
// remove_action 只接受 3 个参数，第 4 个 0 是多余的（无害但没意义）
remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 );

// index_rel_link 在 WP 3.3 就被移除了，这行是历史遗留的空操作
remove_action( 'wp_head', 'index_rel_link' );
```

**移除匿名函数是移除不掉的**——因为你拿不到它的引用。所以想让代码可被移除，就别用匿名函数：

```php
// ❌ 这个永远没法 remove
add_action( 'wp_head', function() { echo 'x'; } );

// ✅ 具名函数可以
add_action( 'wp_head', 'my_head_thing' );
remove_action( 'wp_head', 'my_head_thing' );
```

### 自定义钩子

在自己的主题里埋点，方便以后扩展：

```php
// 定义
do_action( 'mytheme_before_module', $layout_name );
$title = apply_filters( 'mytheme_module_title', $title, $layout_name );

// 使用
add_action( 'mytheme_before_module', function( $layout ) {
    echo '<!-- module: ' . esc_html( $layout ) . ' -->';
});
```

### 判断与调试

```php
has_action( 'wp_head', 'wp_generator' );   // 返回优先级数字，没挂返回 false
has_filter( 'the_content' );               // 有没有任何回调
did_action( 'init' );                      // init 执行过几次
current_filter();                          // 当前正在跑哪个钩子

// 查看某个钩子上挂了什么（调试神器）
global $wp_filter;
print_r( $wp_filter['wp_head'] );
```

---

## 3. 常用钩子速查（按时机）

### 初始化阶段

| 钩子 | 类型 | 用途 |
|---|---|---|
| `after_setup_theme` | action | `add_theme_support()`、`load_theme_textdomain()`、`add_image_size()` |
| `init` | action | 注册 CPT、分类法、菜单、短代码、会话 |
| `widgets_init` | action | `register_sidebar()` |
| `wp_loaded` | action | 一切就绪后的通用入口 |

### 前端输出

| 钩子 | 类型 | 用途 |
|---|---|---|
| `wp_enqueue_scripts` | action | 前端 CSS/JS |
| `admin_enqueue_scripts` | action | 后台 CSS/JS |
| `login_enqueue_scripts` | action | 登录页 CSS/JS（比你现在用的 `login_head` 更规范） |
| `wp_head` | action | `<head>` 内输出 |
| `wp_footer` | action | `</body>` 前输出 |
| `body_class` | filter | 给 `<body>` 加 class |
| `post_class` | filter | 给文章容器加 class |

### 查询相关

| 钩子 | 类型 | 用途 |
|---|---|---|
| `pre_get_posts` | action | **修改主查询**（每页数量、排序、筛选） |
| `template_redirect` | action | 重定向、403、自定义响应 |
| `template_include` | filter | 强制换用某个模板文件 |
| `posts_where` / `posts_join` | filter | 直接改 SQL（慎用） |

`pre_get_posts` 是最重要也最容易用错的钩子。**必须判断是不是主查询、是不是前台**，否则会把后台列表和所有侧边栏小工具一起改掉：

```php
add_action( 'pre_get_posts', 'my_archive_query' );
function my_archive_query( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;                                    // 保护：后台和副查询不动
    }
    if ( $query->is_post_type_archive( 'case' ) ) {
        $query->set( 'posts_per_page', 12 );
        $query->set( 'orderby', 'menu_order' );
        $query->set( 'order', 'ASC' );
    }
}
```

### 内容处理

| 钩子 | 类型 | 用途 |
|---|---|---|
| `the_content` | filter | 改正文 HTML |
| `the_title` | filter | 改标题 |
| `excerpt_length` | filter | 摘要字数 |
| `excerpt_more` | filter | 摘要省略号 |
| `wp_nav_menu_items` | filter | 菜单 HTML 追加项 |
| `nav_menu_css_class` | filter | 菜单项 class（比你现在的 `str_replace` 干净得多） |

### 后台

| 钩子 | 类型 | 用途 |
|---|---|---|
| `admin_menu` | action | 加/删后台菜单项 |
| `admin_init` | action | 后台初始化 |
| `add_meta_boxes` | action | 加/删 metabox |
| `save_post` | action | 保存文章时触发 |
| `upload_mimes` | filter | 允许上传的文件类型 |
| `use_block_editor_for_post` | filter | 开关古腾堡 |

### 媒体

| 钩子 | 类型 | 用途 |
|---|---|---|
| `upload_mimes` | filter | MIME 白名单 |
| `intermediate_image_sizes_advanced` | filter | 禁用某些自动生成的尺寸（省磁盘！） |
| `wp_handle_upload_prefilter` | filter | 上传前改文件名 |

---

## 4. 模板层级：WP 怎么决定加载哪个文件

WP 根据当前请求的类型，**从最具体到最通用**依次找文件，找到第一个就用它。

### 页面（Page）

```
1. 自定义模板（文件头有 Template Name，且页面选了它）  ← 优先级最高
2. page-{slug}.php        例：page-about.php
3. page-{id}.php          例：page-42.php
4. page.php               ← 你的主题在这一层
5. singular.php
6. index.php              ← 兜底
```

### 单篇文章 / CPT

```
1. single-{post_type}-{slug}.php
2. single-{post_type}.php      例：single-case.php
3. single.php
4. singular.php
5. index.php
```

### 归档

```
1. archive-{post_type}.php     例：archive-case.php
2. archive.php
3. index.php
```

### 分类法归档

```
1. taxonomy-{taxonomy}-{term}.php
2. taxonomy-{taxonomy}.php
3. taxonomy.php
4. archive.php
5. index.php
```

### 其他

| 页面类型 | 查找顺序 |
|---|---|
| 首页（最新文章） | `home.php` → `index.php` |
| 首页（静态页） | 走 Page 层级，另需 `front-page.php` 优先 |
| 搜索 | `search.php` → `index.php` |
| 404 | `404.php` → `index.php` |
| 作者 | `author-{nicename}.php` → `author-{id}.php` → `author.php` → `archive.php` |

### 自定义模板

在主题根目录任意 PHP 文件顶部写这个注释，WP 就会扫描到它，在页面编辑器的「模板」下拉里出现：

```php
<?php
/**
 * Template Name: Content page
 */
```

你的 `template.content.php` 就是这么做的。**文件名不重要**（WP 靠扫描文件头注释，不靠文件名），但建议统一成 `template-xxx.php`，可读性更好。

限定只给某种 post type 用：

```php
/**
 * Template Name: Case Detail
 * Template Post Type: case
 */
```

### 读取当前页面用了哪个模板

```php
$template = get_page_template_slug( $post->ID );   // 返回 'template.content.php' 或 ''
```

你的 `functions.php` 用这个来判断要不要禁用编辑器。注意它返回的是**文件名字符串**，默认模板返回空字符串，所以你那个 `$is_default` 判断是必要的。

---

## 5. get_template_part 与模块化

这是你整套工作流的核心机制。

```php
get_template_part( string $slug, string $name = null, array $args = [] );
```

查找顺序：`{slug}-{name}.php` → `{slug}.php`

```php
get_template_part( 'modules/hero' );          // 加载 modules/hero.php
get_template_part( 'modules', 'hero' );       // 加载 modules-hero.php
get_template_part( 'content', get_post_type() ); // content-case.php → content.php
```

### 你的 page.php 在做什么

```php
<?php if ( have_rows('builder') ) : ?>
  <?php while ( have_rows('builder') ) : the_row(); ?>
    <?php get_template_part( 'modules/' . get_row_layout() ); ?>
  <?php endwhile; ?>
<?php endif; ?>
```

逐行拆解：

1. `have_rows('builder')` —— 检查这个页面的 ACF Flexible Content 字段 `builder` 有没有内容
2. `the_row()` —— 把「当前行」指针推进一格，**并把该行的子字段设为全局上下文**
3. `get_row_layout()` —— 返回当前这一行用的 layout 名称，比如 `hero`
4. `get_template_part('modules/hero')` —— 加载 `modules/hero.php`

**关键点**：`modules/hero.php` 里面能直接用 `get_sub_field('title')`，因为 `the_row()` 已经把上下文设好了。这就是模块化能成立的原因——模板片段不需要接收参数，ACF 的全局行上下文帮你传了。

### 传变量给模板片段

WP 5.5 之后 `get_template_part` 支持第三个参数：

```php
// 父模板
get_template_part( 'modules/card', null, [ 'style' => 'dark', 'index' => $i ] );

// modules/card.php 里
<?php
$style = $args['style'] ?? 'light';
$index = $args['index'] ?? 0;
?>
```

WP 5.5 之前只能用全局变量或 `set_query_var()`：

```php
set_query_var( 'my_style', 'dark' );
get_template_part( 'modules/card' );
// card.php 里：get_query_var('my_style')
```

### get_template_part vs include

| | `get_template_part()` | `include`/`require` |
|---|---|---|
| 子主题覆盖 | ✅ 自动 | ❌ 不支持 |
| 文件不存在 | 静默跳过 | Fatal Error |
| 变量作用域 | 需要 `$args` 或全局 | 直接继承 |
| 适用 | 模板片段 | 函数库（你 functions.php 里那 4 个就该用 include） |

**「文件不存在时静默跳过」这一点对你很关键**：如果 ACF 里配了一个 `testimonial` layout，但 `modules/testimonial.php` 不存在，页面不会报错，只会**什么都不显示**。调试时很容易懵。建议加个开发期提示：

```php
<?php
$layout = get_row_layout();
if ( locate_template( "modules/{$layout}.php" ) ) {
    get_template_part( 'modules/' . $layout );
} elseif ( current_user_can('manage_options') ) {
    echo '<!-- ⚠️ 模块文件缺失: modules/' . esc_html($layout) . '.php -->';
}
?>
```

---

## 6. 主循环 The Loop 与 WP_Query

### 标准循环

```php
<?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
        <h2><?php the_title(); ?></h2>
        <?php the_content(); ?>
    <?php endwhile; ?>
<?php else : ?>
    <p>没有内容</p>
<?php endif; ?>
```

`the_post()` 做了三件事：推进指针、设置全局 `$post`、让所有 `the_xxx()` 函数知道「当前是哪篇」。

### 自定义查询

```php
$args = [
    'post_type'      => 'case',
    'posts_per_page' => 12,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
    'post_status'    => 'publish',
];
$query = new WP_Query( $args );

if ( $query->have_posts() ) :
    while ( $query->have_posts() ) : $query->the_post();
        get_template_part( 'modules/case-card' );
    endwhile;
    wp_reset_postdata();    // ⚠️ 必须！否则后面的 the_title() 等全乱
endif;
```

**`wp_reset_postdata()` 忘了写是最常见的 bug**——自定义循环跑完之后，全局 `$post` 还停在最后一篇，导致页面后半部分的标题、链接全部错位。

### 常用查询参数

```php
$args = [
    // 类型与数量
    'post_type'      => 'case',        // 或 ['post','case'] 或 'any'
    'posts_per_page' => 10,            // -1 = 全部（数据量大时危险）
    'paged'          => get_query_var('paged') ?: 1,
    'offset'         => 0,
    'post_status'    => 'publish',

    // 排序
    'orderby'        => 'date',        // date/title/menu_order/rand/meta_value/post__in
    'order'          => 'DESC',
    'meta_key'       => 'event_date',  // orderby 用 meta_value 时必填
    // 'orderby'     => 'meta_value_num',  // 数字排序用这个

    // 指定/排除
    'post__in'       => [1,2,3],
    'post__not_in'   => [ get_the_ID() ],
    'ignore_sticky_posts' => true,

    // 分类法筛选
    'tax_query' => [
        'relation' => 'AND',
        [
            'taxonomy' => 'case_category',
            'field'    => 'slug',       // slug / term_id / name
            'terms'    => ['design','branding'],
            'operator' => 'IN',         // IN / NOT IN / AND
        ],
    ],

    // 自定义字段筛选
    'meta_query' => [
        'relation' => 'AND',
        [
            'key'     => 'featured',
            'value'   => '1',
            'compare' => '=',           // = != > >= < <= LIKE IN BETWEEN EXISTS
        ],
        [
            'key'     => 'event_date',
            'value'   => date('Ymd'),
            'compare' => '>=',
            'type'    => 'NUMERIC',     // NUMERIC / DATE / CHAR
        ],
    ],

    // 日期
    'date_query' => [
        [ 'after' => '2026-01-01', 'inclusive' => true ],
    ],

    // 性能
    'no_found_rows'          => true,   // 不需要分页时开，省一次 COUNT 查询
    'update_post_meta_cache' => false,  // 不读 meta 时开
    'update_post_term_cache' => false,  // 不读分类时开
];
```

### 分页

```php
// 查询
$paged = max( 1, get_query_var('paged'), get_query_var('page') );
$query = new WP_Query([ 'post_type' => 'case', 'posts_per_page' => 12, 'paged' => $paged ]);

// 输出分页
echo paginate_links([
    'total'     => $query->max_num_pages,
    'current'   => $paged,
    'prev_text' => '上一页',
    'next_text' => '下一页',
    'mid_size'  => 2,
]);
```

**注意**：静态首页上分页要用 `get_query_var('page')`，归档页用 `get_query_var('paged')`。上面那行 `max()` 同时兼容两种。

### 只要 ID 更快

```php
$ids = get_posts([
    'post_type'   => 'case',
    'fields'      => 'ids',      // 只查 ID，不实例化完整 post 对象
    'numberposts' => -1,
]);
```

---

## 7. 常用模板函数速查

### the_ 与 get_ 的区别

WP 的命名有极强规律：

- `the_xxx()` —— **直接 echo**
- `get_the_xxx()` —— **返回值**，你自己处理

```php
the_title();                          // 直接输出
$title = get_the_title();             // 拿到变量

the_permalink();
$url = get_permalink();

the_content();                        // 会跑 the_content 过滤器（处理短代码、段落等）
$content = get_the_content();         // ⚠️ 不跑过滤器，短代码不解析

// 要手动跑过滤器：
echo apply_filters( 'the_content', get_the_content() );
```

### 文章数据

```php
the_ID();               get_the_ID();
the_title();            get_the_title( $post_id );
the_permalink();        get_permalink( $post_id );
the_content();          get_the_content();
the_excerpt();          get_the_excerpt();
the_date();             get_the_date( 'Y-m-d' );
the_time();             get_the_time( 'H:i' );
the_modified_date();
the_author();           get_the_author();
the_category();         get_the_category();
the_tags();             get_the_tags();
the_post_thumbnail();   get_the_post_thumbnail();
```

### 缩略图 / 特色图

```php
has_post_thumbnail();
the_post_thumbnail( 'large' );                        // 直接输出 <img>
the_post_thumbnail( '1200x', ['class' => 'cover'] );  // 用你注册的自定义尺寸

get_post_thumbnail_id();
wp_get_attachment_image_url( $id, '800x' );           // 只要 URL
wp_get_attachment_image( $id, '800x', false, ['alt' => '...'] );  // 完整 <img srcset>
wp_get_attachment_image_src( $id, '800x' );           // [url, width, height, is_resized]
```

**推荐用 `wp_get_attachment_image()`**——它自动生成 `srcset` 和 `sizes`，响应式图片直接就有了，比手写 `<img src>` 好得多。

### 条件判断标签

```php
is_home();              // 博客文章列表页
is_front_page();        // 网站首页
is_page();              is_page('about');    is_page(42);
is_single();            is_single('slug');
is_singular('case');    // 单篇 case
is_page_template('template.content.php');
is_archive();           is_post_type_archive('case');
is_category();          is_tax('case_category');
is_search();
is_404();
is_admin();             // 后台（注意：AJAX 请求也算 true！）
is_user_logged_in();
current_user_can('manage_options');
wp_is_mobile();         // 粗糙的移动端检测，不建议依赖
```

### 站点信息

```php
home_url();                      // https://example.com
home_url('/about');
site_url();                      // WP 安装地址（可能带子目录）
get_bloginfo('name');
get_bloginfo('description');
get_bloginfo('charset');
get_bloginfo('language');

get_stylesheet_directory_uri();  // 当前主题 URL（子主题时是子主题）
get_template_directory_uri();    // 父主题 URL
get_theme_file_uri('/assets/js/script.js');    // ✅ 推荐：自动处理子主题
get_theme_file_path('/assets/js/script.js');   // 服务器绝对路径
```

**`get_theme_file_uri()` / `get_theme_file_path()` 是现代写法**，比 `get_stylesheet_directory_uri()` 好，因为它会先在子主题找、找不到再回父主题。

### 菜单

```php
wp_nav_menu([
    'theme_location' => 'header-menu',
    'container'      => false,
    'menu_class'     => 'navbar-nav',
    'depth'          => 2,
    'echo'           => false,        // 返回字符串而不是输出
    'fallback_cb'    => false,        // 没配菜单时不显示默认页面列表
]);
```

你的 `header.php` 用 `str_replace` 把 `current-menu-item` 换成 `active`。**有更规范的做法**——用 `nav_menu_css_class` 过滤器：

```php
add_filter( 'nav_menu_css_class', function( $classes, $item, $args ) {
    $map = [
        'current-menu-item', 'current-menu-parent',
        'current-menu-ancestor', 'current-page-ancestor', 'current_page_item',
    ];
    if ( array_intersect( $classes, $map ) ) {
        $classes[] = 'active';
    }
    return $classes;
}, 10, 3 );
```

好处：不会误伤（`str_replace` 会把出现在文章标题、URL 里的同名字符串也换掉），而且不用 `'echo' => false` 再手动 echo。

---

## 8. 资源加载：wp_enqueue

**永远不要在模板里直接写 `<script src>` 或 `<link rel=stylesheet>`**。用 enqueue 系统，WP 才能处理依赖顺序、去重、版本号。

```php
wp_enqueue_style(
    string $handle,          // 唯一标识
    string $src,             // URL
    array  $deps = [],       // 依赖的其他 handle
    string $ver = false,     // 版本号（用于缓存失效）
    string $media = 'all'
);

wp_enqueue_script(
    string $handle,
    string $src,
    array  $deps = [],
    string $ver = false,
    bool   $in_footer = false   // true = 放到 </body> 前
);
```

### 你现在的写法有 bug

```php
// functions/js-css.php
wp_enqueue_style('style', get_stylesheet_directory_uri() . '/assets/css/style.css', array(),
    filemtime(get_theme_file_path('/assets/css/style.css')));
```

`assets/css/` 目录目前**不存在**。`filemtime()` 对不存在的文件会抛 PHP Warning 并返回 `false`，主题一激活每次加载都刷警告。

**修复版**：

```php
<?php
add_action( 'wp_enqueue_scripts', 'mytheme_assets' );
function mytheme_assets() {

    // 小工具：文件存在就用 mtime 当版本号，不存在就跳过
    $asset = function( $rel ) {
        $path = get_theme_file_path( $rel );
        return file_exists( $path )
            ? [ 'uri' => get_theme_file_uri( $rel ), 'ver' => filemtime( $path ) ]
            : null;
    };

    if ( $css = $asset( '/assets/css/style.css' ) ) {
        wp_enqueue_style( 'mytheme', $css['uri'], [], $css['ver'] );
    }
    if ( $slick = $asset( '/assets/js/slick.min.js' ) ) {
        wp_enqueue_script( 'slick', $slick['uri'], ['jquery'], $slick['ver'], true );
    }
    if ( $js = $asset( '/assets/js/script.js' ) ) {
        wp_enqueue_script( 'mytheme', $js['uri'], ['jquery','slick'], $js['ver'], true );
    }
}
```

用 `filemtime()` 当版本号是个好习惯——文件一改，URL 的 `?ver=` 就变，浏览器和 CDN 缓存自动失效，不用手动改版本。

### 给 JS 传 PHP 数据

```php
wp_localize_script( 'mytheme', 'ThemeData', [
    'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
    'restUrl'  => rest_url( 'mytheme/v1/' ),
    'nonce'    => wp_create_nonce( 'wp_rest' ),
    'homeUrl'  => home_url(),
] );
```

JS 里直接用 `ThemeData.ajaxUrl`。

更现代的做法（不需要伪装成「翻译」）：

```php
wp_add_inline_script( 'mytheme', 'const ThemeData = ' . wp_json_encode( $data ) . ';', 'before' );
```

### 条件加载

只在需要的页面加载重资源：

```php
if ( is_page_template('template-map.php') ) {
    wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/...', [], null, true );
}
```

### 后台和登录页

```php
add_action( 'admin_enqueue_scripts', function() {
    wp_enqueue_style( 'mytheme-admin', get_theme_file_uri('/assets/css/admin.css') );
});

// 登录页 logo —— 比你现在的 login_head + echo <style> 规范
add_action( 'login_enqueue_scripts', function() {
    $logo = get_theme_file_uri('/assets/images/logo.png');
    wp_add_inline_style( 'login', "
        #login h1 a {
            background-image: url({$logo});
            background-size: 320px;
            width: 320px;
        }
    " );
});
```

---

## 9. ACF 深入

ACF 是你工作流的核心，这一节最重要。

### 基础读取

```php
get_field( 'field_name' );                 // 当前文章
get_field( 'field_name', $post_id );       // 指定文章
get_field( 'field_name', 'options' );      // 选项页
get_field( 'field_name', 'term_' . $term_id );   // 分类项
get_field( 'field_name', 'user_' . $user_id );   // 用户

the_field( 'field_name' );                 // 直接输出（⚠️ 不转义）
```

### 各字段类型的返回值

| 字段类型 | 返回格式设置 | 返回值 |
|---|---|---|
| Text / Textarea | — | `string` |
| Number | — | `int` / `float` |
| True/False | — | `bool` |
| Image | Array | `['ID','url','alt','sizes'=>[...],'width','height']` |
| Image | URL | `string` |
| Image | ID | `int` |
| File | Array | `['ID','url','filename','filesize',...]` |
| Link | Array | `['title','url','target']` |
| Select | — | `string` 或 `array`（多选时） |
| Post Object | Post Object | `WP_Post` |
| Post Object | ID | `int` |
| Relationship | — | `WP_Post[]` |
| Taxonomy | — | `WP_Term[]` / `int[]` |
| Repeater | — | `array[]` |
| Gallery | — | `array[]` |

**Image 字段用 Array 格式最灵活**，能拿到所有尺寸：

```php
$img = get_field('hero_image');
if ( $img ) : ?>
    <img src="<?php echo esc_url( $img['sizes']['1200x'] ); ?>"
         srcset="<?php echo esc_attr( $img['sizes']['800x'] ); ?> 800w,
                 <?php echo esc_attr( $img['sizes']['1200x'] ); ?> 1200w,
                 <?php echo esc_attr( $img['sizes']['1600x'] ); ?> 1600w"
         sizes="(max-width: 768px) 100vw, 1200px"
         alt="<?php echo esc_attr( $img['alt'] ); ?>"
         width="<?php echo esc_attr( $img['width'] ); ?>"
         height="<?php echo esc_attr( $img['height'] ); ?>"
         loading="lazy">
<?php endif;
```

注意 `$img['sizes']['1200x']` 里的 `1200x` 就是你 `functions.php` 里 `add_image_size('1200x', 1200, 1200, false)` 注册的名字。

**更省事的写法**——用 ID 格式 + `wp_get_attachment_image()`，srcset 全自动：

```php
$img_id = get_field('hero_image');   // 返回格式设为 ID
echo wp_get_attachment_image( $img_id, '1200x', false, [
    'class'   => 'hero-img',
    'loading' => 'lazy',
] );
```

### Repeater（重复器）

```php
<?php if ( have_rows('features') ) : ?>
  <ul class="features">
    <?php while ( have_rows('features') ) : the_row(); ?>
      <li>
        <h3><?php echo esc_html( get_sub_field('title') ); ?></h3>
        <p><?php echo esc_html( get_sub_field('desc') ); ?></p>
      </li>
    <?php endwhile; ?>
  </ul>
<?php endif; ?>
```

### 嵌套 Repeater

```php
<?php while ( have_rows('sections') ) : the_row(); ?>
    <h2><?php the_sub_field('section_title'); ?></h2>

    <?php while ( have_rows('items') ) : the_row(); ?>
        <!-- 这里的 get_sub_field 取的是内层 items 的字段 -->
        <p><?php the_sub_field('item_text'); ?></p>
    <?php endwhile; ?>

<?php endwhile; ?>
```

嵌套时**内层循环必须完整跑完**，否则外层指针会乱。

### Flexible Content（你的 builder）

这是模块化主题的引擎。

**ACF 后台配置**：
- 字段名：`builder`
- 字段类型：Flexible Content
- 每个 Layout 的 **Name** 必须和 `modules/` 下的文件名一致（`hero` → `modules/hero.php`）

**模板端**（你的 `page.php`）：

```php
<?php if ( have_rows('builder') ) : ?>
  <?php while ( have_rows('builder') ) : the_row(); ?>
    <?php get_template_part( 'modules/' . get_row_layout() ); ?>
  <?php endwhile; ?>
<?php endif; ?>
```

**模块文件**（`modules/hero.php`）：

```php
<?php
$title    = get_sub_field('title');
$text     = get_sub_field('text');
$image    = get_sub_field('image');
$link     = get_sub_field('link');
$scheme   = get_sub_field('color_scheme') ?: 'white';
?>
<section class="hero" color-scheme="<?php echo esc_attr( $scheme ); ?>">
  <div class="container">
    <?php if ( $title ) : ?>
      <h1><?php echo esc_html( $title ); ?></h1>
    <?php endif; ?>

    <?php if ( $text ) : ?>
      <div class="text"><?php echo wp_kses_post( $text ); ?></div>
    <?php endif; ?>

    <?php if ( $link ) : ?>
      <a href="<?php echo esc_url( $link['url'] ); ?>"
         target="<?php echo esc_attr( $link['target'] ?: '_self' ); ?>"
         class="btn">
        <?php echo esc_html( $link['title'] ); ?>
      </a>
    <?php endif; ?>
  </div>
</section>
```

注意 `color-scheme` 属性——你的 `template.content.php` 里已经在用这个模式了（`<section class="article-main" color-scheme="white">`），和你 MorphKit 的 5 套配色是同一个思路。把它做成每个模块的 ACF 字段，编辑就能自由切换。

### 选项页

```php
// functions.php（你已经有了）
if ( function_exists('acf_add_options_page') ) {
    acf_add_options_page([
        'page_title' => 'Theme General Settings',
        'menu_title' => 'Theme Settings',
        'menu_slug'  => 'theme-general-settings',
        'capability' => 'edit_posts',
        'redirect'   => false,
    ]);
}

// 子页面
acf_add_options_sub_page([
    'page_title'  => 'Header Settings',
    'menu_title'  => 'Header',
    'parent_slug' => 'theme-general-settings',
]);
```

读取：`get_field('header_logo', 'options')`

### 常用 ACF 函数

```php
get_field($name, $post_id, $format = true);
the_field($name, $post_id);
get_sub_field($name);
the_sub_field($name);
have_rows($name, $post_id);
the_row();
get_row_layout();
get_row_index();                       // 当前行序号（从 1 开始）
get_field_object($name);               // 拿字段配置（label、choices 等）
update_field($name, $value, $post_id);
delete_field($name, $post_id);
reset_rows();                          // 重置行指针
```

**取 Select 字段的显示文本**（不是 value）：

```php
$obj   = get_field_object('color_scheme');
$value = $obj['value'];
$label = $obj['choices'][ $value ];
```

### ACF 性能注意

`get_field()` 每次调用都可能查一次数据库（有对象缓存时会命中缓存）。在循环里要小心：

```php
// ❌ 循环内重复取同一个 options 字段
while ( have_posts() ) : the_post();
    $color = get_field('brand_color', 'options');   // 每次循环都调用
endwhile;

// ✅ 提到循环外
$color = get_field('brand_color', 'options');
while ( have_posts() ) : the_post();
    // 用 $color
endwhile;
```

### 用 JSON 同步字段组（强烈推荐）

在主题里建 `acf-json/` 目录，ACF 会自动把字段组配置存成 JSON 文件。好处：

- 字段配置进 Git，可版本控制
- 部署到别的环境自动同步，不用手动导入导出
- 团队协作不会互相覆盖

```php
// functions.php
add_filter( 'acf/settings/save_json', function( $path ) {
    return get_stylesheet_directory() . '/acf-json';
});

add_filter( 'acf/settings/load_json', function( $paths ) {
    unset( $paths[0] );
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
});
```

然后 `mkdir acf-json && chmod 755 acf-json`。**这个你现在没有，建议加上**——你做多站点主题开发，这能省掉大量手动同步字段的时间。

---

## 10. 静态前端 → ACF 模块的方法论

这是你日常最高频的动作，单独讲。

### 步骤

**第 1 步：切模块**

拿到静态 HTML，按视觉区块横向切分。每个「整屏宽度的独立区块」= 一个 module。

```html
<!-- static/index.html -->
<section class="hero">...</section>          → modules/hero.php
<section class="intro">...</section>         → modules/intro.php
<section class="feature-grid">...</section>  → modules/feature-grid.php
<section class="cta">...</section>           → modules/cta.php
```

**粒度原则**：能被编辑单独增删、调顺序的，就是一个模块。按钮、卡片这种「只在某个模块内部重复」的，用 Repeater，不要单独做模块。

**第 2 步：找出可变内容**

把 HTML 里每一处「客户会想改」的内容标出来：

```html
<section class="hero">
  <h1>We build brands</h1>            ← 文本字段 title
  <p>Lorem ipsum dolor sit amet</p>   ← 文本域 text
  <img src="hero.jpg">                ← 图片字段 image
  <a href="/contact">Get in touch</a> ← 链接字段 link
</section>
```

**第 3 步：定字段命名规范**

统一命名，几十个模块之后你会感谢自己：

| 用途 | 字段名 |
|---|---|
| 主标题 | `title` |
| 副标题 | `subtitle` |
| 正文 | `text` |
| 图片 | `image` |
| 背景图 | `bg_image` |
| 按钮 | `link`（Link 类型，一个字段搞定文字+URL+target） |
| 重复项 | `items` |
| 配色 | `color_scheme` |
| 上下留白 | `spacing_top` / `spacing_bottom` |

**第 4 步：写模块文件**

固定套路——顶部取值，下面输出，每个字段都判空：

```php
<?php
/**
 * Module: Feature Grid
 */
$title  = get_sub_field('title');
$items  = get_sub_field('items');
$scheme = get_sub_field('color_scheme') ?: 'white';
?>
<section class="feature-grid" color-scheme="<?php echo esc_attr($scheme); ?>">
  <div class="container">

    <?php if ( $title ) : ?>
      <h2><?php echo esc_html( $title ); ?></h2>
    <?php endif; ?>

    <?php if ( have_rows('items') ) : ?>
      <div class="grid">
        <?php while ( have_rows('items') ) : the_row();
          $icon = get_sub_field('icon');
        ?>
          <div class="item">
            <?php if ( $icon ) : ?>
              <?php echo wp_get_attachment_image( $icon['ID'], '400x' ); ?>
            <?php endif; ?>
            <h3><?php echo esc_html( get_sub_field('title') ); ?></h3>
            <p><?php echo esc_html( get_sub_field('desc') ); ?></p>
          </div>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>

  </div>
</section>
```

**注意嵌套 Repeater 的坑**：外层是 Flexible Content 的行，内层 `have_rows('items')` 取的是当前行的子字段——这个能正常工作，因为 ACF 的行上下文是栈式的。

**第 5 步：CSS/JS 对应**

静态站的 CSS 直接搬到 `assets/css/style.css`。**类名保持完全一致**，这样静态原型和 WP 站的样式可以共用一份源码。

模块的 JS 用事件委托或按存在性初始化，避免某个模块不存在时报错：

```js
// assets/js/script.js
document.querySelectorAll('.feature-grid').forEach(el => {
  // 只在这个模块存在时初始化
});
```

### 常见陷阱

| 陷阱 | 后果 | 解法 |
|---|---|---|
| Layout Name 和文件名不一致 | 模块静默不显示 | 严格对齐；加缺失提示（见第 5 节） |
| 字段没判空 | 空 `<h2></h2>`、`<img src="">` | 每个输出都包 `if` |
| Image 字段返回 URL 格式 | 拿不到 alt、尺寸、srcset | 用 Array 或 ID 格式 |
| 忘了转义 | XSS 风险 | 见第 13 节 |
| 模块间距写死在 CSS | 编辑没法调 | 加 `spacing_top/bottom` 字段 |
| 字段组绑定条件太宽 | 所有页面都出现 builder | Location 规则限定到具体模板 |

### 字段组的 Location 规则

在 ACF 字段组底部设置「显示条件」。你的 `page.php` 用了 builder，所以：

```
Post Type == Page
AND Page Template == Default Template
```

如果 `template.content.php` 走的是 `the_content()` 而不是 builder，就要把它排除掉，否则编辑会在那个模板下看到用不上的 builder 字段。

---

## 11. 自定义文章类型与分类法

### 注册 CPT

```php
add_action( 'init', 'mytheme_register_post_types' );
function mytheme_register_post_types() {

    register_post_type( 'case', [
        'labels' => [
            'name'          => 'Cases',
            'singular_name' => 'Case',
            'add_new_item'  => 'Add New Case',
            'edit_item'     => 'Edit Case',
        ],
        'public'        => true,
        'has_archive'   => true,          // 启用 /case/ 归档页 → archive-case.php
        'rewrite'       => [ 'slug' => 'cases' ],   // URL 变成 /cases/xxx
        'menu_icon'     => 'dashicons-portfolio',
        'menu_position' => 20,
        'supports'      => [ 'title', 'thumbnail', 'page-attributes' ],
        // 'editor' 不写 = 不要正文编辑器，纯 ACF 驱动
        'show_in_rest'  => true,          // 需要 REST API / 古腾堡时开
    ] );
}
```

`supports` 可选值：`title` `editor` `thumbnail` `excerpt` `custom-fields` `page-attributes`（启用排序 menu_order）`revisions` `author` `comments`

**纯 ACF 驱动的 CPT，`supports` 只留 `['title','thumbnail','page-attributes']` 就够了**——把编辑器关掉，避免客户在两个地方填内容。

### 注册分类法

```php
register_taxonomy( 'case_category', [ 'case' ], [
    'labels' => [
        'name'          => 'Case Categories',
        'singular_name' => 'Case Category',
    ],
    'hierarchical'      => true,       // true = 像分类（层级+复选框）；false = 像标签
    'public'            => true,
    'show_admin_column' => true,       // 在列表页显示一列
    'rewrite'           => [ 'slug' => 'case-category' ],
    'show_in_rest'      => true,
] );
```

### ⚠️ 注册后必须刷新固定链接

新注册 CPT 或分类法之后，前台访问会 404。**去「设置 → 固定链接」点一下「保存」**即可（这会调用 `flush_rewrite_rules()`）。

不要在 `init` 里直接调 `flush_rewrite_rules()`——那是每次请求都重写一遍 rewrite 规则，性能极差。

### 读取分类

```php
$terms = get_the_terms( get_the_ID(), 'case_category' );
if ( $terms && ! is_wp_error($terms) ) {
    foreach ( $terms as $term ) {
        echo '<a href="' . esc_url( get_term_link($term) ) . '">'
           . esc_html( $term->name ) . '</a>';
    }
}

// 所有分类项
$all = get_terms([
    'taxonomy'   => 'case_category',
    'hide_empty' => true,
    'orderby'    => 'name',
]);
```

---

## 12. 菜单、侧边栏、主题支持

### 主题支持

```php
add_action( 'after_setup_theme', 'mytheme_setup' );
function mytheme_setup() {
    add_theme_support( 'title-tag' );              // 自动 <title>（你已有）
    add_theme_support( 'post-thumbnails' );        // 特色图（你在 other.php 里有）
    add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'style', 'script' ] );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'custom-logo' );

    // 关掉古腾堡的一堆前端 CSS（自定义主题通常不需要）
    remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
    remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
}
```

### 图片尺寸

```php
add_image_size( '1200x', 1200, 1200, false );
//              名称     宽    高     裁剪
// crop = false : 等比缩放，最长边不超过 1200
// crop = true  : 强制裁剪成 1200x1200
// crop = ['center','top'] : 指定裁剪锚点
```

你注册了 6 个尺寸（2560/1600/1400/1200/1000/800）。**注意代价**：每上传一张图，WP 会额外生成 6 个文件（加上 WP 自带的 thumbnail/medium/large/medium_large，一共 10+ 个）。你那台服务器只剩 9.9 GB，这个要留意。

想禁掉 WP 自带的尺寸：

```php
add_filter( 'intermediate_image_sizes_advanced', function( $sizes ) {
    unset( $sizes['medium_large'] );   // 768px，通常没用
    unset( $sizes['1536x1536'] );
    unset( $sizes['2048x2048'] );
    return $sizes;
});
```

### 侧边栏 / 小工具区

```php
add_action( 'widgets_init', function() {
    register_sidebar([
        'name'          => 'Footer Column 1',
        'id'            => 'footer-1',
        'before_widget' => '<div class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4>',
        'after_title'   => '</h4>',
    ]);
});

// 模板中
if ( is_active_sidebar('footer-1') ) {
    dynamic_sidebar('footer-1');
}
```

自定义主题通常用 ACF 选项页代替小工具，更可控。

---

## 13. 安全：转义与净化

规则：**输入时净化（sanitize），输出时转义（escape）**。

### 输出转义

```php
esc_html( $text );          // HTML 文本内容
esc_attr( $value );         // HTML 属性值
esc_url( $url );            // href / src
esc_textarea( $text );      // <textarea> 内容
esc_js( $string );          // 内联 JS 字符串
wp_kses_post( $html );      // 允许文章级 HTML 标签（用于富文本字段）
wp_kses( $html, $allowed ); // 自定义白名单
```

### 什么时候用哪个

```php
<!-- 纯文本 -->
<h1><?php echo esc_html( get_field('title') ); ?></h1>

<!-- 属性 -->
<div class="<?php echo esc_attr( $class ); ?>" data-id="<?php echo esc_attr( $id ); ?>">

<!-- URL -->
<a href="<?php echo esc_url( $link['url'] ); ?>">

<!-- ACF WYSIWYG / 富文本 —— 需要保留 HTML -->
<div><?php echo wp_kses_post( get_field('content') ); ?></div>

<!-- WP 自己的函数已经转义过，不要重复转 -->
<?php the_title(); ?>                          <!-- ✅ 安全 -->
<?php echo esc_html( get_the_title() ); ?>     <!-- ✅ 也可以 -->
```

**`the_field()` 不转义**。ACF 直接吐原始值。所以：

```php
// ⚠️ 有 XSS 风险
<h1><?php the_field('title'); ?></h1>

// ✅ 安全
<h1><?php echo esc_html( get_field('title') ); ?></h1>
```

### 你的 header.php 需要改

```php
// 现在
<link rel="icon" href="<?php echo $favicon['url']; ?>" type="image/x-icon" />
<img src="<?php echo $header_logo['url']; ?>" alt="">

// 问题：1) 没转义  2) 字段为空时 $favicon['url'] 会抛 PHP Warning

// 改成
<?php $favicon = get_field('favicon', 'options'); ?>
<?php if ( ! empty( $favicon['url'] ) ) : ?>
  <link rel="icon" href="<?php echo esc_url( $favicon['url'] ); ?>">
<?php endif; ?>

<?php $header_logo = get_field('header_logo', 'options'); ?>
<?php if ( ! empty( $header_logo['url'] ) ) : ?>
  <img src="<?php echo esc_url( $header_logo['url'] ); ?>"
       alt="<?php echo esc_attr( $header_logo['alt'] ?: get_bloginfo('name') ); ?>">
<?php endif; ?>
```

### 输入净化

```php
sanitize_text_field( $_POST['name'] );
sanitize_email( $_POST['email'] );
sanitize_textarea_field( $_POST['message'] );
sanitize_title( $string );            // 变成 slug
absint( $_GET['id'] );                // 正整数
intval( $value );
wp_kses_post( $_POST['content'] );
```

### 数据库查询

**永远用 `$wpdb->prepare()`**：

```php
global $wpdb;

// ❌ SQL 注入
$wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE ID = {$_GET['id']}" );

// ✅
$wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $_GET['id']
) );
// 占位符：%d 整数  %s 字符串  %f 浮点
```

不过 90% 的情况用 `WP_Query` / `get_posts()` 就够了，别直接写 SQL。

### Nonce（防 CSRF）

任何表单提交、AJAX 写操作都要验证：

```php
// 表单里
<?php wp_nonce_field( 'mytheme_save', 'mytheme_nonce' ); ?>

// 处理时
if ( ! isset( $_POST['mytheme_nonce'] )
  || ! wp_verify_nonce( $_POST['mytheme_nonce'], 'mytheme_save' ) ) {
    wp_die('校验失败');
}
```

### 权限检查

```php
if ( ! current_user_can( 'edit_posts' ) ) {
    wp_die( '权限不足' );
}
```

---

## 14. 性能：缓存与查询优化

### Transient（临时缓存）

存到数据库或对象缓存，带过期时间。适合缓存外部 API 结果、复杂查询：

```php
function mytheme_get_instagram() {
    $key  = 'mytheme_instagram_feed';
    $data = get_transient( $key );

    if ( false === $data ) {
        $res = wp_remote_get( 'https://api.example.com/feed' );
        if ( is_wp_error( $res ) ) {
            return [];
        }
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        set_transient( $key, $data, HOUR_IN_SECONDS );   // 缓存 1 小时
    }

    return $data;
}
```

时间常量：`MINUTE_IN_SECONDS` `HOUR_IN_SECONDS` `DAY_IN_SECONDS` `WEEK_IN_SECONDS` `MONTH_IN_SECONDS` `YEAR_IN_SECONDS`

清除：`delete_transient( $key );`

### 查询优化清单

```php
// 不需要分页 → 省掉 SQL_CALC_FOUND_ROWS
'no_found_rows' => true,

// 不读 meta / 分类 → 省掉预加载
'update_post_meta_cache' => false,
'update_post_term_cache' => false,

// 只要 ID
'fields' => 'ids',

// ⚠️ 避免（全表扫描，数据量大时会卡死）
'posts_per_page' => -1,
'orderby' => 'rand',
'meta_query' => [ ['key'=>'x','compare'=>'LIKE','value'=>'%y%'] ],
```

### N+1 问题

```php
// ❌ 循环里逐个查
foreach ( $ids as $id ) {
    $post = get_post( $id );
}

// ✅ 一次查完
$posts = get_posts([ 'post__in' => $ids, 'numberposts' => -1 ]);
```

### 其他

```php
// 关掉文章修订版（数据库会小很多）—— wp-config.php
define( 'WP_POST_REVISIONS', 5 );

// 关掉自动保存
define( 'AUTOSAVE_INTERVAL', 300 );

// 前台移除 emoji 脚本
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
```

---

## 15. AJAX 与 REST API

### admin-ajax（传统方式）

```php
// PHP
add_action( 'wp_ajax_load_cases',        'mytheme_load_cases' );   // 已登录
add_action( 'wp_ajax_nopriv_load_cases', 'mytheme_load_cases' );   // 未登录

function mytheme_load_cases() {
    check_ajax_referer( 'mytheme_nonce', 'nonce' );

    $page  = absint( $_POST['page'] ?? 1 );
    $query = new WP_Query([
        'post_type'      => 'case',
        'posts_per_page' => 12,
        'paged'          => $page,
    ]);

    ob_start();
    while ( $query->have_posts() ) : $query->the_post();
        get_template_part( 'modules/case-card' );
    endwhile;
    wp_reset_postdata();

    wp_send_json_success([
        'html'    => ob_get_clean(),
        'maxPage' => $query->max_num_pages,
    ]);
}
```

```js
// JS
const res = await fetch(ThemeData.ajaxUrl, {
  method: 'POST',
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  body: new URLSearchParams({
    action: 'load_cases',        // 对应 wp_ajax_{action}
    nonce:  ThemeData.nonce,
    page:   2,
  }),
});
const json = await res.json();
if (json.success) container.insertAdjacentHTML('beforeend', json.data.html);
```

**`action` 参数是必须的**，它决定调用哪个钩子。

### REST API（推荐）

```php
add_action( 'rest_api_init', function() {
    register_rest_route( 'mytheme/v1', '/cases', [
        'methods'             => 'GET',
        'callback'            => 'mytheme_rest_cases',
        'permission_callback' => '__return_true',   // 公开接口
        'args' => [
            'page' => [
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);
});

function mytheme_rest_cases( WP_REST_Request $req ) {
    $query = new WP_Query([
        'post_type'      => 'case',
        'posts_per_page' => 12,
        'paged'          => $req['page'],
    ]);

    $items = array_map( function( $p ) {
        return [
            'id'    => $p->ID,
            'title' => get_the_title( $p ),
            'url'   => get_permalink( $p ),
            'image' => get_the_post_thumbnail_url( $p, '800x' ),
        ];
    }, $query->posts );

    return rest_ensure_response([
        'items'   => $items,
        'maxPage' => $query->max_num_pages,
    ]);
}
```

访问：`/wp-json/mytheme/v1/cases?page=2`

**`permission_callback` 是必填的**，不写会有警告。公开接口用 `'__return_true'`。

---

## 16. 调试

### wp-config.php

```php
define( 'WP_DEBUG',         true );
define( 'WP_DEBUG_LOG',     true );    // 写入 wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );   // 不在页面上显示（生产必须 false）
define( 'SCRIPT_DEBUG',     true );    // 加载未压缩的核心 JS/CSS
define( 'SAVEQUERIES',      true );    // 记录所有 SQL（调试完要关，很耗内存）
```

### 打印调试

```php
// 输出到页面
echo '<pre>'; print_r( $data ); echo '</pre>';

// 输出到 debug.log
error_log( print_r( $data, true ) );

// 看当前查询
global $wp_query;
error_log( print_r( $wp_query->request, true ) );   // 实际 SQL

// 看所有查询（需要 SAVEQUERIES）
global $wpdb;
error_log( print_r( $wpdb->queries, true ) );
```

### 常见错误对照

| 现象 | 原因 |
|---|---|
| 白屏 | PHP Fatal Error，开 `WP_DEBUG_LOG` 看日志 |
| "headers already sent" | PHP 结束标签 `?>` 后有空行/BOM；或在输出后调 `wp_redirect()` |
| 模块不显示 | `modules/{layout}.php` 文件名和 ACF layout name 不一致 |
| `get_field()` 返回 null | 字段名拼错；字段组 Location 没匹配上；在 `init` 之前调用 |
| 自定义查询后标题错乱 | 忘了 `wp_reset_postdata()` |
| CPT 前台 404 | 没刷新固定链接 |
| filter 让页面空白 | 回调忘了 `return` |
| CSS 改了不生效 | 版本号没变，浏览器缓存；用 `filemtime()` 当 ver |

### 推荐插件

- **Query Monitor** —— 最重要的调试插件。看查询、钩子、HTTP 请求、PHP 错误、模板层级
- **ACF Theme Code Pro** —— 自动生成 ACF 字段的模板代码
- **User Switching** —— 快速切换用户身份测试

---

## 17. 常见反模式与代码审查清单

接手或交付一套主题时，按这个清单过一遍。左边是症状，右边是正确做法所在的章节。

### 🔴 资源引用指向不存在的文件

```php
wp_enqueue_style( 'main', get_template_directory_uri() . '/assets/css/style.css', [],
    filemtime( get_template_directory() . '/assets/css/style.css' ) );
```

`filemtime()` 拿到不存在的路径会抛 `Warning`，而且每次页面加载都抛一条。更隐蔽的是：版本号回落成 `false`，浏览器缓存再也刷不掉。

从别的项目复制主题时这是头号高发问题——目录结构没跟着复制过来。

→ 第 8 节有带存在性检查的封装写法。

**自查**：`grep -rn "filemtime" .` 然后逐个确认文件真的存在。

### 🔴 模板引用了不存在的模板片段

`get_template_part( 'modules/hero' )` 在文件缺失时**不报错、不返回值、什么都不输出**——页面就是空的，日志里也没有痕迹。

`locate_template()` 同理。这是「页面莫名其妙是空白」的最常见原因。

→ 第 5 节。开发期可以临时加一层守卫：

```php
function mytheme_module( $slug ) {
    $file = "modules/{$slug}.php";
    if ( ! locate_template( $file ) ) {
        if ( WP_DEBUG ) {
            echo '<!-- missing template: ' . esc_html( $file ) . ' -->';
            error_log( "Missing template part: {$file}" );
        }
        return;
    }
    get_template_part( "modules/{$slug}" );
}
```

### 🔴 用 JS 做重定向

```php
<?php echo '<script>window.location.href="' . home_url() . '"</script>'; ?>
```

页面已经完整渲染并发给浏览器了才跳走：用户会看到一次闪烁，搜索引擎会把这个页面当成正常的 200 页面收录，浏览器的返回按钮也会陷进死循环。

服务端跳：

```php
add_action( 'template_redirect', function () {
    if ( is_search() ) {
        wp_safe_redirect( home_url(), 302 );
        exit;
    }
} );
```

`wp_safe_redirect()` 会挡掉跳往站外的 URL，配合 `exit` 使用——**漏掉 `exit` 的话后面的代码照跑**，这是另一个高频错误。

### 🟡 ACF 字段不判空、不转义

```php
<img src="<?php echo get_field('favicon')['url']; ?>">
```

字段没填时 `get_field()` 返回 `false`，`false['url']` 在 PHP 8 上是 Warning，输出一个 `src=""`——浏览器会把它解析成「重新请求当前页」，等于每个页面白跑一次完整的 PHP 请求。

```php
<?php
$favicon = get_field( 'favicon' );
if ( ! empty( $favicon['url'] ) ) : ?>
    <img src="<?php echo esc_url( $favicon['url'] ); ?>"
         alt="<?php echo esc_attr( $favicon['alt'] ?? '' ); ?>">
<?php endif; ?>
```

→ 第 9 节（ACF 返回值形态）、第 13 节（转义）。

**自查**：`grep -rnE "get_field\(.*\)\[" .` 找出所有直接下标访问的地方。

### 🟡 用 str_replace 改类名 / 拼 HTML

```php
$menu = wp_nav_menu( [ 'echo' => false ] );
echo str_replace( 'current-menu-item', 'active', $menu );
```

能跑，但会误伤：菜单项的标题、URL、`title` 属性里只要出现这个字符串就一起被换掉。而且下次 WP 改了输出结构就悄悄失效。

WP 给每个可变的地方都留了 filter，用 filter：

```php
add_filter( 'nav_menu_css_class', function ( $classes ) {
    if ( in_array( 'current-menu-item', $classes, true ) ) {
        $classes[] = 'active';
    }
    return $classes;
}, 10, 1 );
```

→ 第 7 节。

**判据**：只要在写 `str_replace` / `preg_replace` 处理 WP 生成的 HTML，先去查有没有对应的 filter。基本都有。

### 🟡 复制主题留下的残留引用

```php
if ( is_page_template( 'template-cases-listing.php' ) ) { ... }
```

模板文件已经不在了，这段分支永远不成立——**不报错，只是功能静默失效**。等到有人以为这功能还在、开始基于它改东西的时候才会炸。

**自查**：把 `is_page_template()` / `get_page_template_slug()` / `get_template_part()` 里出现的每个文件名都拿去 `ls` 一遍。

### 🟡 index.php 是空的

```php
<?php get_header(); ?>
<?php get_footer(); ?>
```

`index.php` 是模板层级的最终兜底：任何没匹配到专用模板的请求都会落到这里。空着意味着这些请求会返回一个只有页头页脚的 200 页面——包括本该 404 的 URL。搜索引擎会把它们全部收录成重复内容。

至少要有一个 Loop，或者显式走 404。

→ 第 4 节。

### 🟢 无效的 remove_action

```php
remove_action( 'wp_head', 'index_rel_link' );                  // WP 3.3 已移除
remove_action( 'wp_head', 'parent_post_rel_link', 10, 0 );     // 第 4 个参数是多余的
```

`remove_action()` 的签名是 `(hook, callback, priority)`——**没有第 4 个参数**（那是 `add_action` 的 `accepted_args`）。多传不报错，但说明这行是从别处抄来的、没人核对过。

对着当前 WP 版本核一遍 `wp_head` 上到底挂了什么：

```php
add_action( 'wp_head', function () {
    global $wp_filter;
    error_log( print_r( array_keys( $wp_filter['wp_head']->callbacks ), true ) );
}, 9999 );
```

### 🟢 用 echo 往 login_head 里塞样式

```php
add_action( 'login_head', function () {
    echo '<style>.login h1 a { background-image: url(...); }</style>';
} );
```

能用，但绕过了 WP 的资源管线：不参与合并、不带版本号、插件也没法过滤。改用 `login_enqueue_scripts` + `wp_add_inline_style`。

→ 第 8 节。

### 💡 没有 acf-json/

主题根目录建一个 `acf-json/`（www-data 可写），ACF 会自动把字段组同步成 JSON 文件。字段配置从此进 Git、跟着代码走，不用再手动导入导出，多人协作时也不会互相覆盖。

一旦做多个站点，这个的收益是压倒性的。

→ 第 9 节。

### 💡 没有统一前缀

主题里所有全局函数、钩子名、`$GLOBALS` 键、option 名都应该带同一个前缀。没前缀的函数名（`get_hero()`、`setup()`）迟早会和某个插件撞车，撞车的结果是 **PHP Fatal error: Cannot redeclare**，整站白屏。

→ 第 18 节命名约定表。

### 交付前的自动检查

```bash
# PHP 语法（改完必跑）
find . -name '*.php' -print0 | xargs -0 -n1 php -l | grep -v 'No syntax errors'

# 找出所有引用了但不存在的模板片段
grep -rhoE "get_template_part\(\s*'[^']+'" . | sed -E "s/.*'([^']+)'/\1/" | sort -u | \
  while read -r t; do [ -f "$t.php" ] || echo "MISSING: $t.php"; done

# 未转义的输出（会有误报，逐条看）
grep -rnE "echo\s+\\\$[a-z_]+\s*;" --include='*.php' .

# 中文注释（交付代码里不该有）
grep -rnP "[\x{4e00}-\x{9fa5}]" --include='*.php' . | grep -E "//|/\*|\*"

# 调试残留
grep -rnE "var_dump|print_r|error_log|console\.log|dd\(" --include='*.php' --include='*.js' .
```

配上 WP-CLI 的话，`wp theme check`（需装 Theme Check 插件）能再扫一轮官方规范。

---

## 18. 速查附录

### 路径与 URL

```php
get_theme_file_uri( '/assets/js/script.js' );    // ✅ URL，支持子主题
get_theme_file_path( '/assets/js/script.js' );   // ✅ 绝对路径
get_stylesheet_directory_uri();                  // 当前主题 URL
get_stylesheet_directory();                      // 当前主题路径
get_template_directory_uri();                    // 父主题 URL
home_url( '/contact' );
admin_url( 'admin-ajax.php' );
rest_url( 'mytheme/v1/cases' );
wp_upload_dir();                                 // ['path','url','basedir','baseurl']
```

### 常量（wp-config.php）

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_POST_REVISIONS', 5 );
define( 'DISALLOW_FILE_EDIT', true );      // 禁用后台代码编辑器（安全）
define( 'WP_MEMORY_LIMIT', '256M' );
define( 'DISABLE_WP_CRON', true );         // 改用系统 cron
```

### 全局变量

```php
global $post;        // 当前文章对象
global $wp_query;    // 主查询
global $wpdb;        // 数据库
global $wp_filter;   // 所有已注册钩子（调试用）
```

### 文件结构建议

```
theme/
├── acf-json/                 ← 字段组 JSON（建议加）
├── assets/
│   ├── css/style.css         ← 缺失
│   ├── js/script.js          ← 缺失
│   ├── js/slick.min.js       ← 缺失
│   ├── fonts/
│   └── images/
├── functions/
│   ├── js-css.php
│   ├── menu.php
│   ├── other.php
│   ├── post-types.php        ← 建议：CPT 单独一个文件
│   └── aq_resizer.php
├── modules/                  ← 空，需要填充
│   ├── hero.php
│   ├── intro.php
│   └── ...
├── template-parts/           ← 建议：非 builder 的复用片段
├── functions.php
├── header.php
├── footer.php
├── index.php
├── page.php
├── single-case.php
├── archive-case.php
├── search.php
├── 404.php
└── style.css
```

### 命名规范

| 对象 | 规范 | 例 |
|---|---|---|
| 函数 | 主题前缀 + 下划线 | `mytheme_register_post_types()` |
| 钩子回调 | 同上 | `mytheme_enqueue_assets()` |
| CPT slug | 单数、小写 | `case` |
| 分类法 slug | `{cpt}_{name}` | `case_category` |
| ACF 字段 | 小写下划线 | `hero_title` |
| Flexible layout | 和文件名一致 | `feature_grid` → `modules/feature_grid.php` |
| CSS class | 和静态站保持一致 | `.feature-grid` |

---

## 学习路径建议

按你现在的情况，优先级这样排：

1. **第 2 节（钩子）** —— 这是 WP 的地基，看懂了再看别的
2. **第 4、5 节（模板层级 + get_template_part）** —— 直接对应你的 builder 工作流
3. **第 9、10 节（ACF + 方法论）** —— 你的日常主战场
4. **第 13 节（转义）** —— 养成习惯，以后不用返工
5. **第 6 节（WP_Query）** —— 做列表页、筛选时必用
6. 其余按需查阅

### 官方文档

- [Theme Handbook](https://developer.wordpress.org/themes/)
- [Hooks 全量索引](https://developer.wordpress.org/reference/hooks/)
- [函数参考](https://developer.wordpress.org/reference/functions/)
- [ACF 文档](https://www.advancedcustomfields.com/resources/)
- [模板层级图](https://developer.wordpress.org/themes/basics/template-hierarchy/)
