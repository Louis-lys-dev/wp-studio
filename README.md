# wp-studio

WordPress 建站与运维的完整教程集。面向实际交付，不是入门科普——每一节都是「原理 → 怎么做 → 底下发生了什么 → 出错了怎么查」。

## 目录

| 教程 | 篇幅 | 内容 |
|---|---|---|
| [WordPress 通用开发](docs/wordpress-core.md) | 27 节 | 数据库 11 张表、请求生命周期、钩子原理、WP_Query、$wpdb、权限、REST API、缓存、i18n、WP-CLI、安全与性能 |
| [WordPress 主题开发](docs/wordpress-theme.md) | 18 节 | 模板层级、The Loop、模块化、资源加载、ACF 深入、**静态前端 → ACF 模块的方法论**、转义与净化、代码审查清单 |
| [WooCommerce 开发](docs/woocommerce.md) | 27 节 | HPOS 与表结构、CRUD 对象、模板与钩子体系、购物车/结算/订单、支付网关与配送方式开发、邮件、Blocks、Store API、需求配方集 |
| [宝塔面板 + LNMP](docs/bt-lnmp.md) | 25 节 | LNMP 原理、面板加固、Nginx/PHP-FPM/MySQL 调优、三层缓存、备份与迁移、按症状索引的排错手册、安全加固、手工部署对照 |

## 怎么用

**新手按顺序读**：`wordpress-core` → `wordpress-theme` → `woocommerce`，部署阶段再看 `bt-lnmp`。

**有经验的当手册用**：每份文档末尾都有速查附录，`bt-lnmp` 的第 18 节是按症状索引的排错手册（502/504/500/白屏/慢/404/无限重定向），出故障时直接翻。

**关键的几节**（如果只读一部分）：

- `wordpress-theme.md` 第 10 节 —— 静态前端转 ACF 模块化主题的完整方法论
- `woocommerce.md` 第 5 节 —— WooCommerce 的钩子体系，这是二开的骨架
- `bt-lnmp.md` 第 12 节 —— 三层缓存，整页缓存是数量级的性能差异
- `bt-lnmp.md` 第 14 节 —— 备份，重点在「恢复演练」那一小节
- `bt-lnmp.md` 第 18 节 —— 排错手册

## 约定

- 代码示例中的主题前缀统一用 `mytheme`，替换成你自己的
- 服务器路径按宝塔的 `/www/` 结构写，`bt-lnmp.md` 第 24 节有到标准 Debian/Ubuntu 路径的完整对照表
- PHP 版本按 8.2 写，MySQL 按 8.0 写
- 所有配置参数都给了「怎么算」而不只是「填多少」，照抄之前先按自己机器的实际内存算一遍
