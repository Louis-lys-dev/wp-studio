# Starter Theme

空白的 ACF 模块化 WordPress 主题骨架，配合 [`docs/wordpress-theme.md`](../docs/wordpress-theme.md) 使用。

没有任何样式和业务逻辑——结构、钩子接线和安全习惯是现成的，页面长什么样由你写。

## 目录结构

```
starter-theme/
├── style.css                主题头信息（WordPress 靠它识别主题，必须保留）
├── functions.php            只做接线：按顺序 require functions/ 下的文件
├── index.php                模板层级兜底
├── front-page.php           首页 → 纯模块渲染
├── page.php                 页面 → 有模块走模块，没有则回退到编辑器内容
├── single.php               文章详情
├── archive.php              归档（分类/标签/日期/CPT）
├── search.php               搜索结果
├── 404.php
├── header.php / footer.php
├── searchform.php
│
├── functions/
│   ├── helpers.php          模板里用的小工具（图片/链接/截断/类名拼接）
│   ├── setup.php            theme supports、菜单、图片尺寸、侧边栏
│   ├── assets.php           资源加载 + filemtime 版本号（带存在性守卫）
│   ├── acf.php              acf-json 同步、选项页、★ 模块渲染器
│   ├── post-types.php       CPT（示例是 project，改掉或删掉）
│   ├── taxonomies.php       分类法（示例是 project_category）
│   ├── cleanup.php          精简 wp_head、去掉区块编辑器前台样式、菜单类名
│   └── security.php         禁 xmlrpc、挡作者枚举、挡 REST users、统一登录报错
│
├── modules/
│   └── _example.php         ★ 模块模板，复制它来建新模块
│
├── template-parts/
│   ├── content.php          列表里的一条
│   └── content-none.php     空状态
│
├── acf-json/                ACF 字段组自动同步到这里，跟着 Git 走
├── assets/{css,js,img,fonts}/
└── languages/
```

## 怎么用

**1. 改名**

```bash
cp -r starter-theme /path/to/wp-content/themes/yourtheme
cd /path/to/wp-content/themes/yourtheme

# 全局把前缀换掉（函数名、钩子名、text domain 都用这个前缀）
grep -rl 'mytheme\|MYTHEME\|MyTheme' . | xargs sed -i 's/mytheme/yourtheme/g; s/MYTHEME/YOURTHEME/g; s/MyTheme/YourTheme/g'
```

然后改 `style.css` 头部的 Theme Name / Author / Description。

**2. 建模块**

ACF 里建一个 Flexible Content 字段，字段名 `modules`，挂到 Page（和需要模块化的 CPT）上。每加一个 layout，就在 `modules/` 下建一个同名文件：

| ACF layout name | 文件 |
|---|---|
| `hero` | `modules/hero.php` |
| `hero_banner` | `modules/hero-banner.php` |
| `logo_wall` | `modules/logo-wall.php` |

下划线转连字符——这是 `mytheme_module()` 里做的唯一一次转换。

复制 `modules/_example.php` 开始写。模块里用 `get_sub_field()` 不是 `get_field()`。

**3. 前缀里带下划线的坑**

如果你的主题前缀本身带下划线（比如 `my_theme`），layout 名和文件名的映射不受影响——转换只作用于 layout 名。

## 这个骨架已经替你做掉的事

- **资源版本号带守卫**：`filemtime()` 对不存在的文件会每次请求抛 Warning 并让版本号变成 `false`（缓存永远刷不掉）。`mytheme_asset_version()` 先查文件在不在。
- **模块缺失会留痕**：`get_template_part()` 找不到文件时不报错、不输出，页面就是空的。`mytheme_module()` 在 `WP_DEBUG` 下会写 HTML 注释和 error_log。
- **acf-json 已配好**：字段组配置进 Git，不用再手动导入导出。目录要对 `www` 可写。
- **菜单类名用 filter 不用 str_replace**：后者会误伤标题和 URL 里的同名字符串。
- **全部输出都转义**：`esc_html` / `esc_attr` / `esc_url` / `wp_kses_post`，照着这个习惯往下写。
- **index.php 不是空的**：空的 `index.php` 会把所有未匹配的 URL 变成只有页头页脚的 200 页面，被搜索引擎当重复内容收录。

## 还需要你自己补的

- `screenshot.png`（1200×900，后台主题列表里的预览图）
- `assets/css/main.css` 和 `assets/js/main.js` 现在是空的
- `functions/post-types.php` 和 `taxonomies.php` 里的示例 CPT 要改成真的，或者整个删掉
- **CPT 注册在主题里意味着换主题内容就消失**——长期项目建议挪到插件
- `comments.php`（`single.php` 调了 `comments_template()`，没有这个文件会回退到 WP 默认模板）

## 服务器侧

上传目录禁止执行 PHP、限制 wp-login 频率、屏蔽 `.git` 和 `wp-config.php` 这些是 Nginx 的事，不在主题里做——见 [`docs/bt-lnmp.md`](../docs/bt-lnmp.md) 第 7 节和第 19 节。
