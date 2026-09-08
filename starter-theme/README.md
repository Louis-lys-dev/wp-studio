# Starter Theme

空白的 ACF 模块化 WordPress 主题骨架，配合 [`docs/wordpress-theme.md`](../docs/wordpress-theme.md) 使用。

没有任何样式和业务逻辑——结构、钩子接线和安全习惯是现成的，页面长什么样由你写。

---

## 目录

- [目录结构总览](#目录结构总览)
- [加载顺序](#加载顺序)
- [根目录：模板文件](#根目录模板文件)
- [functions/：功能模块](#functions功能模块)
- [modules/：ACF 模块](#modulesacf-模块)
- [template-parts/：可复用片段](#template-parts可复用片段)
- [数据目录](#数据目录)
- [模板层级速查](#模板层级速查)
- [命名约定](#命名约定)
- [改动指引](#改动指引)
- [常见任务](#常见任务)
- [这个骨架已经替你做掉的事](#这个骨架已经替你做掉的事)
- [还需要你自己补的](#还需要你自己补的)

---

## 目录结构总览

```
starter-theme/
│
├── style.css                  ★ 主题头信息。WordPress 靠它识别主题，删了主题就消失
├── functions.php              ★ 唯一入口。只做接线，不写业务
├── .editorconfig                 编辑器统一（tab 缩进、LF、UTF-8）
├── README.md                     本文件
│
├── ── 模板层级文件（WordPress 按请求类型自动选用）───────────────
├── index.php                  ★ 最终兜底。任何没匹配到专用模板的请求都落这里
├── front-page.php                首页（「显示最新文章」时不生效，见下）
├── page.php                      所有页面的默认模板
├── single.php                    文章 / CPT 详情
├── archive.php                   归档：分类、标签、日期、作者、CPT 列表
├── search.php                    搜索结果
├── 404.php                       未找到
│
├── ── 被 get_header/get_footer/get_search_form 调用 ────────────
├── header.php                    <!doctype> 到 <main> 开标签
├── footer.php                    </main> 到 </html>
├── searchform.php                get_search_form() 的输出
│
├── functions/                 ★ 按职责拆分，functions.php 按固定顺序加载
│   ├── helpers.php               模板里用的小工具（无依赖，最先加载）
│   ├── setup.php                 theme supports / 菜单 / 图片尺寸 / 侧边栏
│   ├── assets.php                CSS/JS 加载 + 版本号 + 传数据给前端 JS
│   ├── acf.php                ★ acf-json 同步、选项页、模块渲染器
│   ├── post-types.php            自定义文章类型
│   ├── taxonomies.php            自定义分类法
│   ├── cleanup.php               精简 wp_head、去区块编辑器前台样式、菜单类名
│   └── security.php              禁 xmlrpc、挡枚举、统一登录报错、安全响应头
│
├── modules/                   ★ 一个 ACF layout 一个文件，这里是页面的积木
│   └── _example.php              模板，复制它建新模块（下划线开头 = 不会被当模块用）
│
├── template-parts/               跨模板复用的片段
│   ├── content.php               列表里的一条（卡片）
│   └── content-none.php          空状态
│
├── acf-json/                  ★ ACF 字段组自动写到这里，跟着 Git 走
│   └── .gitkeep                  （Git 不跟踪空目录，占位用）
│
├── assets/
│   ├── css/main.css              前台样式（现在是空的）
│   ├── css/admin.css             后台样式（文件存在才加载）
│   ├── js/main.js                前台脚本（defer，在 footer）
│   ├── img/                      主题自带的图（logo、图标；内容图走媒体库）
│   └── fonts/                    自托管字体
│
└── languages/                    .pot / .po / .mo 翻译文件
```

---

## 加载顺序

搞清楚这个，才知道自己的代码该往哪放。

### 一、主题初始化（每个请求都跑）

```
WordPress 启动
  ↓
加载 functions.php
  ↓
  定义 MYTHEME_VERSION / MYTHEME_DIR / MYTHEME_URI
  ↓
  按数组顺序 require functions/ 下的文件：
      1. helpers.php       ← 无依赖，被后面的文件用
      2. setup.php
      3. assets.php
      4. acf.php
      5. post-types.php
      6. taxonomies.php
      7. cleanup.php
      8. security.php
  ↓
  （这些文件里全是 add_action / add_filter，此刻只是注册，还没执行）
  ↓
after_setup_theme  → mytheme_setup()      注册菜单、supports、图片尺寸
init               → 注册 CPT / 分类法、cleanup 的 remove_action
acf/init           → 注册选项页
  ↓
路由：WordPress 决定这个 URL 是什么类型的请求
  ↓
模板层级：选中一个模板文件
```

**顺序是有意的**：`helpers.php` 里的函数被 `modules/` 和模板用，必须最先在；`acf.php` 的模块渲染器要在模板执行前就定义好。

在 `functions.php` 的数组里加新文件时，**放在依赖它的文件之前**。

### 二、页面渲染（以一个 Page 请求为例）

```
page.php
  ↓
  get_header()                    → header.php
  ↓
  the_post()
  ↓
  have_rows('modules') ?
      是 → mytheme_render_modules()
              ↓  循环每一行
              mytheme_module( get_row_layout() )
              ↓  layout 名下划线转连字符
              get_template_part( 'modules/hero-banner' )
              ↓
              modules/hero-banner.php      ← 你的模块代码
      否 → the_content()          回退到编辑器内容
  ↓
  get_footer()                    → footer.php
```

`wp_head()` 在 `header.php` 里，`wp_footer()` 在 `footer.php` 里——**这两个必须保留**，所有插件和 `wp_enqueue_*` 注册的资源都从这两个钩子输出。删了它们，样式和脚本全部不加载。

---

## 根目录：模板文件

### 必须保留的两个

| 文件 | 为什么不能删 |
|---|---|
| `style.css` | WordPress 读它的头注释来识别主题。删了主题在后台就不存在了。**样式不一定写在里面**（本骨架的样式在 `assets/css/main.css`），但文件本身必须在 |
| `index.php` | 模板层级的最终兜底。WordPress 判定一个目录是不是主题，看的就是有没有 `style.css` + `index.php` |

### 模板文件逐个

| 文件 | 什么时候被用 | 里面有什么 | 要改吗 |
|---|---|---|---|
| `index.php` | 所有模板都没匹配上时 | 完整的 Loop + 分页 + 空状态 | 一般不用改 |
| `front-page.php` | 首页 | **只有 `mytheme_render_modules()`**，首页完全由模块拼 | 通常不改，加东西就加模块 |
| `page.php` | 所有 Page | 有模块走模块，没有回退到 `the_content()` | 通常不改 |
| `single.php` | 文章 / CPT 详情 | 标题、日期、分类、特色图、正文、标签、上下篇、评论 | **按设计改**，这是最常动的 |
| `archive.php` | 分类/标签/日期/作者/CPT 归档 | 归档标题 + 描述 + 列表 + 分页 | 按设计改 |
| `search.php` | 搜索结果 | 同上，外加搜索框和查询词回显 | 按设计改 |
| `404.php` | 找不到 | 提示 + 搜索框 + 回首页 | 按设计改 |
| `header.php` | `get_header()` | doctype、meta、`wp_head()`、skip link、logo、主菜单、`<main>` 开标签 | **一定会改** |
| `footer.php` | `get_footer()` | `</main>`、页脚菜单、widget 区、版权、`wp_footer()` | **一定会改** |
| `searchform.php` | `get_search_form()` | 一个带 label 和 aria 的搜索表单 | 按设计改 |

### front-page.php 的一个坑

它**只在「设置 → 阅读 → 首页显示 = 一个静态页面」时生效**。

如果选的是「您的最新文章」，WordPress 走的是 `home.php`（本骨架没有）→ 回退到 `index.php`。所以做纯模块化首页时，记得去后台把首页设成一个静态页。

### 需要时再建的模板

本骨架没建，用到了自己加（文件名是 WordPress 约定的，建了就自动生效）：

| 文件 | 用途 |
|---|---|
| `home.php` | 「显示最新文章」时的首页 / 博客列表页 |
| `comments.php` | 评论区。`single.php` 调了 `comments_template()`，没这个文件会用 WP 默认模板 |
| `sidebar.php` | 侧边栏 |
| `single-{cpt}.php` | 某个 CPT 的详情，如 `single-project.php` |
| `archive-{cpt}.php` | 某个 CPT 的归档，如 `archive-project.php` |
| `taxonomy-{tax}.php` | 某个分类法的归档 |
| `page-{slug}.php` | 某个具体页面，如 `page-contact.php` |
| `template-{name}.php` | 可在后台手选的页面模板（**文件顶部要写 `Template Name:` 注释**） |

---

## functions/：功能模块

| 文件 | 职责 | 关键内容 | 改动频率 |
|---|---|---|---|
| **`helpers.php`** | 模板里用的小工具 | `mytheme_image()` `mytheme_link()` `mytheme_excerpt()` `mytheme_classes()` | 中，按需加 |
| **`setup.php`** | 主题能力声明 | `after_setup_theme`：textdomain、title-tag、post-thumbnails、html5、**菜单位置**、**自定义图片尺寸**；另有 widget 区、摘要长度、body class | **高**，开局就要改 |
| **`assets.php`** | 资源管线 | `mytheme_asset_version()` 带存在性守卫；前台/后台/登录页三处 enqueue；`ThemeData` 全局对象传给 JS | 中，加文件时改 |
| **`acf.php`** | ACF 集成 | `save_json`/`load_json` 接线、选项页、**`mytheme_module()`**、**`mytheme_render_modules()`** | 低，接好就不动 |
| **`post-types.php`** | 自定义文章类型 | 示例 `project` | **高**，或整个删掉 |
| **`taxonomies.php`** | 自定义分类法 | 示例 `project_category` | **高**，或整个删掉 |
| **`cleanup.php`** | 精简输出 | 去掉 wp_generator/rsd/wlwmanifest/shortlink/oembed；去掉区块编辑器前台样式；去 emoji；菜单 `is-active` 类名 | 低 |
| **`security.php`** | 前台加固 | 禁 xmlrpc、挡 `?author=N`、挡 REST `/users`、统一登录报错、安全响应头 | 低 |

### 几个要点

**`setup.php` 里的图片尺寸**要按设计稿定：

```php
add_image_size( 'card', 640, 480, true );   // true = 硬裁剪
add_image_size( 'hero', 1920, 900, true );
```

每加一个尺寸，**每张上传的图都会多生成一个文件**。只加真正用到的。加在已有内容之后的话，老图不会自动生成新尺寸，要用 Regenerate Thumbnails 插件或 `wp media regenerate` 补。

**`assets.php` 里的 `ThemeData`** 是传数据给前端 JS 的地方：

```php
'ajaxUrl' => admin_url( 'admin-ajax.php' ),
'restUrl' => esc_url_raw( rest_url( 'mytheme/v1/' ) ),
'nonce'   => wp_create_nonce( 'mytheme_nonce' ),
```

JS 里直接 `ThemeData.ajaxUrl`。**别往里放任何密钥**——这东西是明文写进页面源码的。

**`cleanup.php` 里去掉了区块编辑器的前台样式**（`wp-block-library` 等）。ACF 模块化主题前台一般不用核心区块，去掉能省几十 KB。**但如果你在正文里用了区块（表格、引用、画廊），样式会全丢**——那就把 `mytheme_dequeue_block_styles` 这个 `add_action` 注释掉。

**`security.php` 挡了作者归档**（`?author=1` 会泄露第一个管理员的登录名）。站点真的要用作者页的话，删掉 `mytheme_block_author_enumeration`。

**服务器层的加固不在这里**——挡 xmlrpc 的请求、禁止上传目录执行 PHP、限制 wp-login 频率，这些在 Nginx 里做，见 [`docs/bt-lnmp.md`](../docs/bt-lnmp.md) 第 7 节和第 19 节。主题里这份只是「WordPress 内部必须做的那半」。

---

## modules/：ACF 模块

**这是整个主题的核心机制**：一个 ACF Flexible Content 的 layout ↔ 一个 PHP 文件。编辑在后台拖拽排序，前台按顺序渲染。

### 命名映射

`mytheme_module()` 做的唯一一次转换是**下划线转连字符**：

| ACF layout name | 文件名 |
|---|---|
| `hero` | `modules/hero.php` |
| `hero_banner` | `modules/hero-banner.php` |
| `logo_wall` | `modules/logo-wall.php` |
| `two_column_text` | `modules/two-column-text.php` |

下划线开头的文件（`_example.php`）永远不会被当成模块——ACF 的 layout name 不允许下划线开头。

### 模块里的约定

```php
// 1. 读字段用 get_sub_field()，不是 get_field()
//    循环里当前行已经激活了
$heading = get_sub_field( 'heading' );

// 2. 空模块直接 return，不要渲染一个空 <section>
if ( ! $heading && ! $text && ! $image ) {
    return;
}

// 3. $args 由 mytheme_module() 传入
$index = $args['index'] ?? 0;   // 本模块在页面上的位置，从 0 开始
$layout = $args['layout'] ?? '';  // layout 名

// 4. 首个模块承载 h1，后面的用 h2
echo 0 === $index ? '<h1>' : '<h2>';

// 5. 一律转义
echo esc_html( $heading );          // 纯文本
echo wp_kses_post( $text );         // 富文本
echo esc_url( $link['url'] );       // URL
echo esc_attr( $class );            // 属性值
```

### 为什么不直接用 get_template_part

`get_template_part()` 在文件不存在时**不报错、不返回值、什么都不输出**——页面就是空的，日志里也没痕迹。这是「页面莫名其妙一片空白」最常见的成因。

`mytheme_module()` 包了一层，`WP_DEBUG` 打开时会输出 HTML 注释并写 `error_log`：

```html
<!-- missing module: modules/hero-banner.php -->
```

查页面源码就能看到少了哪个。

---

## template-parts/：可复用片段

和 `modules/` 的区别：

- **`modules/`** = 编辑在后台拼装的积木，一个 layout 一个文件，由 ACF 驱动
- **`template-parts/`** = 代码里复用的片段，由模板直接 `get_template_part()` 调用

| 文件 | 被谁调用 |
|---|---|
| `content.php` | `index.php` / `archive.php` 里的 Loop，渲染一条卡片 |
| `content-none.php` | Loop 为空时 |

`get_template_part( 'template-parts/content', get_post_type() )` 会**先找 `content-project.php`，找不到才回退 `content.php`**。所以给 CPT 做专属卡片样式，只要建一个 `template-parts/content-project.php` 就行，不用改调用方。

`search.php` 调的是 `content-search`，同样规则——需要搜索结果长得不一样时建 `content-search.php`。

---

## 数据目录

### acf-json/

ACF 的 Local JSON。你在后台保存字段组时，ACF 同时把配置写成 `group_xxxxx.json` 放这里，进 Git 跟着代码走，线上拉了代码字段就有了，不用手动导出导入。

接线在 `functions/acf.php:10` 和 `:18`。

> ⚠️ **目录必须对 `www` 可写，否则 ACF 静默不写文件**（不报错）。配完第一件事：后台随便存一个字段组，然后 `git status` 看有没有文件冒出来。
>
> ```bash
> chown -R www:www wp-content/themes/yourtheme/acf-json
> ```

JSON 里只有**字段组的定义**，没有任何**字段的值**——它不是备份。

### assets/

| 目录 | 放什么 | 不放什么 |
|---|---|---|
| `css/` | `main.css`（前台）、`admin.css`（后台，文件存在才加载） | |
| `js/` | `main.js`（defer，在 footer） | |
| `img/` | **主题自带的**图：logo、图标、装饰用的 SVG | 内容图片——那些走媒体库，由编辑上传 |
| `fonts/` | 自托管字体（woff2 优先） | |

判断一张图放哪：**换个客户还需要它吗**？需要（比如通用的箭头图标）→ `assets/img/`；不需要（这个客户的产品照）→ 媒体库。

用 Sass/构建工具的话，源文件放 `assets/src/`，构建产物输出到 `assets/css/` 和 `assets/js/`，然后在 `.gitignore` 里决定产物进不进 Git。

### languages/

翻译文件。`setup.php` 里已经 `load_theme_textdomain()` 了。

所有面向用户的字符串都用 `__()` / `esc_html__()` / `esc_html_e()` 包起来（骨架里已经是这样），配合 Poedit 或 `wp i18n make-pot` 生成 `.pot`。

文件名格式：`{textdomain}-{locale}.mo`，如 `mytheme-zh_CN.mo`。

### .gitkeep 是什么

Git 不跟踪空目录。`acf-json/`、`assets/img/`、`assets/fonts/`、`languages/` 现在是空的，放一个 `.gitkeep`（约定俗成的空文件，Git 本身不认识这个名字）让目录能被提交。**目录里有真实文件之后可以删掉它。**

---

## 模板层级速查

WordPress 拿到一个请求后，按这个顺序找模板，**第一个存在的文件胜出**。粗体是本骨架已有的。

| 请求 | 查找顺序 |
|---|---|
| 首页（静态页） | `front-page.php` → `page.php` → **`index.php`** |
| 首页（最新文章） | `front-page.php` → `home.php` → **`index.php`** |
| 单个页面 | `page-{slug}.php` → `page-{id}.php` → **`page.php`** → `singular.php` → **`index.php`** |
| 单篇文章 | `single-post-{slug}.php` → `single-post.php` → **`single.php`** → `singular.php` → **`index.php`** |
| 单个 CPT | `single-{cpt}-{slug}.php` → `single-{cpt}.php` → **`single.php`** → **`index.php`** |
| 分类归档 | `category-{slug}.php` → `category-{id}.php` → `category.php` → **`archive.php`** → **`index.php`** |
| CPT 归档 | `archive-{cpt}.php` → **`archive.php`** → **`index.php`** |
| 分类法归档 | `taxonomy-{tax}-{term}.php` → `taxonomy-{tax}.php` → `taxonomy.php` → **`archive.php`** → **`index.php`** |
| 搜索 | **`search.php`** → **`index.php`** |
| 404 | **`404.php`** → **`index.php`** |
| 附件 | `{mime-type}.php` → `attachment.php` → **`single.php`** → **`index.php`** |

想确认当前页面实际用了哪个模板，装 Query Monitor，或者临时加：

```php
add_filter( 'template_include', function ( $t ) {
    if ( WP_DEBUG ) {
        error_log( 'Template: ' . $t );
    }
    return $t;
}, 999 );
```

---

## 命名约定

**所有全局的东西都带前缀**。没前缀的函数名迟早会和某个插件撞车，撞车的结果是 `Fatal error: Cannot redeclare`，整站白屏。

| 类型 | 约定 | 例子 |
|---|---|---|
| 函数 | `前缀_` + 小写下划线 | `mytheme_render_modules()` |
| 自定义钩子 | `前缀_` | `do_action( 'mytheme_before_module' )` |
| 常量 | `前缀_` 全大写 | `MYTHEME_DIR` |
| Text domain | 前缀本身 | `__( 'Menu', 'mytheme' )` |
| option / transient | `前缀_` | `mytheme_instagram_cache` |
| REST 命名空间 | `前缀/v1` | `mytheme/v1/projects` |
| CSS 类 | BEM，不带前缀 | `.card__title`、`.module--first` |
| 模块文件 | 连字符 | `modules/logo-wall.php` |
| 模板文件 | WordPress 约定 | `single-project.php` |

CSS 类不带前缀是有意的——它们只在这个主题的页面里出现，加前缀只会让选择器变长。但**状态类要小心**：`.active` 这种名字容易和第三方 JS 撞车，用 `.is-active` / `.has-*` 会好一些。

---

## 改动指引

| 我想… | 改哪里 |
|---|---|
| 改主题名字/作者 | `style.css` 头注释 |
| 换前缀 | 全局 sed，见下面「怎么用」 |
| 加一个菜单位置 | `functions/setup.php` 的 `register_nav_menus()` + `header.php`/`footer.php` 里输出 |
| 加一个图片尺寸 | `functions/setup.php` 的 `add_image_size()` |
| 加 CSS/JS 文件 | `functions/assets.php` 的 `mytheme_enqueue_assets()` |
| 传数据给前端 JS | `functions/assets.php` 的 `ThemeData` |
| 加一个模块 | 建 `modules/xxx.php` + ACF 里加同名 layout |
| 加一个 CPT | `functions/post-types.php` |
| 加一个页面模板 | 建 `template-xxx.php`，顶部写 `Template Name:` |
| 改文章详情页版式 | `single.php` |
| 改列表卡片 | `template-parts/content.php` |
| 加全局的工具函数 | `functions/helpers.php` |
| 加一个新的功能模块 | 建 `functions/xxx.php` + 在 `functions.php` 的数组里登记 |

**不建议改的**：

- `functions.php` 的加载循环——除非是往数组里加文件名
- `functions/acf.php` 的 `mytheme_module()` / `mytheme_render_modules()`——改了所有模块的契约就变了
- `header.php` 里的 `wp_head()`、`footer.php` 里的 `wp_footer()`、`<body>` 上的 `body_class()`——删了会以各种诡异的方式坏掉

---

## 常见任务

### 改前缀（开局第一件事）

```bash
cp -r starter-theme /path/to/wp-content/themes/yourtheme
cd /path/to/wp-content/themes/yourtheme

grep -rl 'mytheme\|MYTHEME\|MyTheme' . | xargs sed -i \
  's/mytheme/yourtheme/g; s/MYTHEME/YOURTHEME/g; s/MyTheme/YourTheme/g'
```

然后手改 `style.css` 头部的 Theme Name / Author / Description / Theme URI。

### 加一个模块

```bash
# 1. 建文件
cp modules/_example.php modules/logo-wall.php
```

```
2. ACF 里：Flexible Content 字段 `modules` → 添加 Layout
   Label: Logo Wall
   Name:  logo_wall          ← 必须和文件名对应（下划线 ↔ 连字符）
   加子字段：heading、logos（Repeater/Gallery）…

3. 保存 → acf-json/ 下应该出现/更新一个 .json 文件（git status 确认）

4. 写 modules/logo-wall.php 的实际内容
```

### 加一个 CPT 和它的归档

```
1. functions/post-types.php   register_post_type( 'service', [...] )
2. functions/taxonomies.php   如果需要分类
3. archive-service.php        列表页（不建就用通用的 archive.php）
4. single-service.php         详情页（不建就用 single.php）
5. template-parts/content-service.php   专属卡片（不建就用 content.php）
6. ★ 后台「设置 → 固定链接」点一次保存，刷新重写规则
   或 wp rewrite flush --hard
```

**第 6 步漏了的话，CPT 的所有 URL 都是 404**——这是最高频的一个坑。

> CPT 注册在主题里意味着**换主题内容就访问不到了**（数据还在，但没有 URL、后台没有入口）。长期项目、或者内容属于客户的项目，应该把 `register_post_type()` 挪到一个插件里。骨架放在主题里只是为了「拷过去就能跑」。

### 加一个可选的页面模板

```php
<?php
/**
 * Template Name: Contact
 *
 * @package MyTheme
 */
```

存成 `template-contact.php`，后台编辑页面时右侧「页面属性 → 模板」就能选到。

---

## 这个骨架已经替你做掉的事

这几条对应 [`docs/wordpress-theme.md`](../docs/wordpress-theme.md) 第 17 节的反模式清单：

- **资源版本号带守卫** —— `filemtime()` 对不存在的文件会每次请求抛 Warning，并让版本号变成 `false`（浏览器缓存永远刷不掉）。`mytheme_asset_version()` 先查文件在不在。
- **模块缺失会留痕** —— `get_template_part()` 找不到文件时完全静默。`mytheme_module()` 在 `WP_DEBUG` 下写 HTML 注释和 error_log。
- **acf-json 已接好** —— 字段组配置进 Git，不用手动导入导出。
- **菜单类名用 filter 不用 str_replace** —— 后者会误伤标题和 URL 里的同名字符串。
- **`index.php` 不是空的** —— 空的 `index.php` 会把所有未匹配的 URL 变成只有页头页脚的 200 页面，被搜索引擎当重复内容收录。
- **全部输出都转义** —— `esc_html` / `esc_attr` / `esc_url` / `wp_kses_post`，照这个习惯往下写。
- **无障碍基础** —— skip link、`screen-reader-text`、导航的 `aria-label`、搜索框的 `<label>`。

---

## 还需要你自己补的

| 项 | 说明 |
|---|---|
| `screenshot.png` | 1200×900，后台主题列表里的预览图 |
| `assets/css/main.css` | 现在是空的 |
| `assets/js/main.js` | 现在是空的 |
| `comments.php` | `single.php` 调了 `comments_template()`，没这个文件会回退到 WP 默认模板 |
| 示例 CPT | `functions/post-types.php` 和 `taxonomies.php` 里的 `project` 要改成真的，或整个删掉 |
| `.gitignore` | 用构建工具的话，决定 `node_modules/` 和构建产物进不进 Git |

## 服务器侧

上传目录禁止执行 PHP、限制 wp-login 频率、屏蔽 `.git` 和 `wp-config.php`——这些是 Nginx 的事，不在主题里做。见 [`docs/bt-lnmp.md`](../docs/bt-lnmp.md) 第 7 节和第 19 节。
