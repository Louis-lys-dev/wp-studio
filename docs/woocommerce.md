# WooCommerce 开发完整教程

> 面向已有 WordPress 主题开发经验的人
> 配套阅读：`wordpress-core.md`、`wordpress-theme.md`

---

## 目录

1. [架构总览](#1-架构总览)
2. [数据存储：表结构与 HPOS](#2-数据存储表结构与-hpos)
3. [CRUD 对象体系](#3-crud-对象体系)
4. [模板系统](#4-模板系统)
5. [钩子体系：WooCommerce 的骨架](#5-钩子体系woocommerce-的骨架)
6. [商品](#6-商品)
7. [商品查询](#7-商品查询)
8. [商品归档页与循环](#8-商品归档页与循环)
9. [单品页](#9-单品页)
10. [购物车](#10-购物车)
11. [结算页](#11-结算页)
12. [订单](#12-订单)
13. [我的账户与端点](#13-我的账户与端点)
14. [支付网关开发](#14-支付网关开发)
15. [配送方式开发](#15-配送方式开发)
16. [邮件系统](#16-邮件系统)
17. [价格、货币与税](#17-价格货币与税)
18. [通知与会话](#18-通知与会话)
19. [AJAX](#19-ajax)
20. [REST API 与 Store API](#20-rest-api-与-store-api)
21. [WooCommerce Blocks](#21-woocommerce-blocks)
22. [ACF 与 WooCommerce 集成](#22-acf-与-woocommerce-集成)
23. [静态设计稿 → WooCommerce 主题](#23-静态设计稿--woocommerce-主题)
24. [性能优化](#24-性能优化)
25. [常见需求配方集](#25-常见需求配方集)
26. [调试](#26-调试)
27. [速查附录](#27-速查附录)

---

## 1. 架构总览

WooCommerce 是插件，但它自成一套体系，和 WP 核心的写法有明显区别。**最重要的三个认知**：

### ① 不要直接读写 post meta

WP 里你习惯 `get_post_meta($id, '_price', true)`。WooCommerce 有 CRUD 层：

```php
// ❌ 旧写法，WC 3.0 之后不保证有效
$price = get_post_meta( $product_id, '_price', true );

// ✅ 正确
$product = wc_get_product( $product_id );
$price   = $product->get_price();
```

**为什么**：WC 3.0 引入了数据存储抽象层（Data Store）。数据可能在 postmeta、可能在查找表、订单甚至可能在独立的 `wc_orders` 表。CRUD 层帮你屏蔽这些差异。直接读 meta 在 HPOS 开启后会直接拿不到订单数据。

### ② 模板几乎全是钩子

打开 WooCommerce 任何一个模板文件，你会发现里面没什么 HTML，全是 `do_action()`：

```php
<!-- templates/content-single-product.php 的实际内容 -->
<?php do_action( 'woocommerce_before_single_product' ); ?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class(); ?>>
    <?php do_action( 'woocommerce_before_single_product_summary' ); ?>
    <div class="summary entry-summary">
        <?php do_action( 'woocommerce_single_product_summary' ); ?>
    </div>
    <?php do_action( 'woocommerce_after_single_product_summary' ); ?>
</div>
```

**所以定制 WooCommerce 有两条路**：改钩子（推荐）或覆盖模板（必要时）。优先改钩子——升级时不会有兼容问题。

### ③ 它有自己的一套函数命名

```
wc_*          通用函数        wc_get_product()  wc_price()  wc_add_notice()
woocommerce_* 模板函数/钩子    woocommerce_template_single_price()
WC()          全局实例        WC()->cart  WC()->session  WC()->customer
```

### 目录结构

```
wp-content/plugins/woocommerce/
├── templates/              ← 所有模板，覆盖时从这里复制
│   ├── archive-product.php
│   ├── single-product.php
│   ├── content-product.php
│   ├── content-single-product.php
│   ├── cart/
│   ├── checkout/
│   ├── myaccount/
│   ├── emails/
│   ├── loop/
│   └── single-product/
├── includes/
│   ├── wc-template-hooks.php       ← ★ 所有默认钩子挂载在这里，必读
│   ├── wc-template-functions.php   ← ★ 所有模板函数实现
│   ├── wc-core-functions.php
│   ├── wc-product-functions.php
│   ├── wc-cart-functions.php
│   ├── wc-order-functions.php
│   ├── abstracts/
│   ├── gateways/
│   └── shipping/
└── woocommerce.php
```

**`includes/wc-template-hooks.php` 是最值得先通读一遍的文件**。它一页列出了所有默认钩子挂载，看完你就知道能在哪里插手。

---

## 2. 数据存储：表结构与 HPOS

### 商品

商品仍然是 WP 文章类型：

```
wp_posts        post_type = 'product'            商品
wp_posts        post_type = 'product_variation'  变体（post_parent = 父商品 ID）
wp_postmeta     商品的所有字段
```

常用 meta key：

```
_sku                    货号
_price                  当前有效价（用于查询排序，由 WC 自动维护）
_regular_price          原价
_sale_price             促销价
_sale_price_dates_from  促销开始
_sale_price_dates_to    促销结束
_stock                  库存数量
_stock_status           instock / outofstock / onbackorder
_manage_stock           yes / no
_weight  _length  _width  _height
_virtual                yes / no
_downloadable           yes / no
_product_attributes     属性（序列化数组）
_thumbnail_id           主图
_product_image_gallery  相册（逗号分隔的 ID）
_visibility             （旧）现在用 product_visibility 分类法
_featured               （旧）现在用 product_visibility
```

⚠️ **`_price` 是派生字段**。你手动改 `_regular_price` 不会自动更新 `_price`，导致前台显示和排序错乱。永远用 CRUD：

```php
$product = wc_get_product( $id );
$product->set_regular_price( 100 );
$product->save();      // save() 内部会同步 _price 和查找表
```

### 分类法

```
product_cat            商品分类
product_tag            商品标签
product_type           simple / variable / grouped / external
product_visibility     featured / exclude-from-search / exclude-from-catalog / outofstock / rated-1..5
pa_{attribute}         全局属性，如 pa_color、pa_size
```

`product_visibility` 是个容易忽略的设计——「推荐商品」「隐藏商品」「缺货」这些状态都是分类项，不是 meta。所以自定义查询时要处理它：

```php
$tax_query[] = [
    'taxonomy' => 'product_visibility',
    'field'    => 'name',
    'terms'    => 'exclude-from-catalog',
    'operator' => 'NOT IN',
];
```

### 查找表（性能优化用）

```
wp_wc_product_meta_lookup      商品关键字段的扁平副本（价格、库存、评分、销量）
wp_wc_order_product_lookup     订单商品行（分析用）
wp_wc_order_stats              订单统计
wp_wc_customer_lookup          客户
wp_wc_tax_rate_classes
```

`wc_product_meta_lookup` 让「按价格排序」「筛选库存」不用 join postmeta，快很多。它由 WC 自动维护，但批量导入或直接改数据库后可能不同步，需要重建：

```bash
wp wc tool run regenerate_product_lookup_tables --user=1
```

### 订单：HPOS 是分水岭

**WooCommerce 8.2 之后新装的店，订单默认存在独立表**（High-Performance Order Storage）：

```
wp_wc_orders                   订单主表
wp_wc_order_addresses          账单/配送地址
wp_wc_order_operational_data   运营数据（支付方式、下载权限等）
wp_wc_orders_meta              订单自定义字段
```

**旧站（Legacy 模式）** 订单还在 `wp_posts`：

```
wp_posts      post_type = 'shop_order'
wp_postmeta   订单字段（_billing_email 等）
```

**两种模式下都用的表**：

```
wp_woocommerce_order_items      订单行（商品、运费、税、优惠券）
wp_woocommerce_order_itemmeta   订单行的字段
```

### HPOS 对开发的影响

```php
// ❌ HPOS 下彻底失效
$email = get_post_meta( $order_id, '_billing_email', true );
$orders = get_posts([ 'post_type' => 'shop_order' ]);
new WP_Query([ 'post_type' => 'shop_order' ]);

// ✅ 两种模式都能用
$order = wc_get_order( $order_id );
$email = $order->get_billing_email();
$orders = wc_get_orders([ 'limit' => 10, 'status' => 'processing' ]);
```

**写插件必须声明兼容性**，否则后台会显示「不兼容」警告：

```php
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});
```

检测当前模式：

```php
if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class )
  && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
    // HPOS
}
```

### 其他表

```
wp_woocommerce_sessions              购物车会话（游客的购物车存这里）
wp_woocommerce_api_keys              REST API 密钥
wp_woocommerce_attribute_taxonomies  全局属性定义
wp_woocommerce_downloadable_product_permissions
wp_woocommerce_tax_rates
wp_woocommerce_tax_rate_locations
wp_woocommerce_shipping_zones
wp_woocommerce_shipping_zone_methods
wp_woocommerce_shipping_zone_locations
wp_woocommerce_payment_tokens
wp_woocommerce_log
wp_actionscheduler_actions           后台任务队列（WC 用它跑异步任务）
```

**`wp_woocommerce_sessions` 会膨胀**——每个游客访问都生成一条。WC 有定时清理，但站点流量大时这张表可能几十万行。

---

## 3. CRUD 对象体系

WooCommerce 3.0+ 的核心设计。所有实体都是对象，有统一的 getter/setter/save。

```
WC_Data (抽象基类)
├── WC_Product
│   ├── WC_Product_Simple
│   ├── WC_Product_Variable
│   ├── WC_Product_Variation
│   ├── WC_Product_Grouped
│   └── WC_Product_External
├── WC_Order
│   └── WC_Order_Refund
├── WC_Customer
├── WC_Coupon
└── WC_Order_Item
    ├── WC_Order_Item_Product
    ├── WC_Order_Item_Shipping
    ├── WC_Order_Item_Tax
    ├── WC_Order_Item_Fee
    └── WC_Order_Item_Coupon
```

### 通用模式

```php
// 读
$product = wc_get_product( $id );      // 工厂函数，自动返回正确的子类
$order   = wc_get_order( $id );
$coupon  = new WC_Coupon( $code );

// 改
$product->set_regular_price( 199 );
$product->set_stock_quantity( 10 );

// 存 —— 必须调 save()
$product->save();

// 建
$product = new WC_Product_Simple();
$product->set_name( '新商品' );
$product->set_regular_price( 99 );
$id = $product->save();
```

**忘记 `save()` 是最常见的 bug**——setter 只改内存里的对象，不写数据库。

### 自定义数据

```php
$order->update_meta_data( '_my_field', $value );
$order->save();

$value = $order->get_meta( '_my_field' );
$all   = $order->get_meta_data();
$order->delete_meta_data( '_my_field' );
```

**HPOS 下 `update_post_meta($order_id, ...)` 不生效**，必须用 `$order->update_meta_data()`。

### 批量操作时的性能

```php
// ❌ 每次 save() 都是一次写库 + 清缓存
foreach ( $ids as $id ) {
    $p = wc_get_product( $id );
    $p->set_stock_quantity( 0 );
    $p->save();
}

// ✅ 简单字段用专用函数
foreach ( $ids as $id ) {
    wc_update_product_stock( $id, 0, 'set' );
}
```

---

## 4. 模板系统

### 三种定制方式，按优先级选

```
1. 钩子（add_action / remove_action）        ← 首选，升级安全
2. 模板片段覆盖（复制单个模板到主题）           ← 需要改结构时
3. 完全接管（自己写 archive-product.php）      ← 最后手段
```

### 启用主题支持

```php
add_action( 'after_setup_theme', function() {
    add_theme_support( 'woocommerce' );

    // 商品相册功能（不声明就没有缩略图切换和放大）
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
});
```

带参数版本（控制默认布局）：

```php
add_theme_support( 'woocommerce', [
    'thumbnail_image_width' => 400,
    'single_image_width'    => 800,
    'product_grid'          => [
        'default_rows'    => 3,
        'min_rows'        => 1,
        'default_columns' => 4,
        'min_columns'     => 1,
        'max_columns'     => 6,
    ],
] );
```

### 模板覆盖

从 `plugins/woocommerce/templates/` 复制到 `themes/你的主题/woocommerce/`，保持相同的子目录结构：

```
plugins/woocommerce/templates/single-product/title.php
              ↓ 复制到
themes/my-theme/woocommerce/single-product/title.php
```

**只复制你真正要改的文件**。复制得越多，WC 升级时要维护的越多。

### 模板版本检查

每个模板文件头有版本号：

```php
/**
 * @package     WooCommerce\Templates
 * @version     3.6.0
 */
```

WC 升级后如果官方模板改了，后台「状态 → 模板覆盖」会红字提示你的覆盖版本过期。**这个提示要认真对待**——它意味着你的覆盖可能缺了新功能或修复。

### 主要模板文件

```
archive-product.php              商品归档（商店页、分类页）
single-product.php               单品页外壳
content-product.php              循环中的单个商品卡片 ★ 最常改
content-single-product.php       单品页内容
taxonomy-product_cat.php         分类页（可选，默认走 archive-product）

loop/
├── loop-start.php  loop-end.php
├── orderby.php                  排序下拉
├── result-count.php             "显示 1-12 / 共 50"
├── pagination.php
├── add-to-cart.php              循环中的加购按钮
├── price.php  rating.php  sale-flash.php
└── no-products-found.php

single-product/
├── add-to-cart/                 simple.php variable.php grouped.php external.php
├── product-image.php            主图
├── product-thumbnails.php       相册缩略图
├── tabs/                        tabs.php description.php additional-information.php
├── related.php  up-sells.php
├── meta.php  price.php  rating.php  sale-flash.php  short-description.php  title.php
└── review.php

cart/
├── cart.php                     ★ 购物车主模板
├── cart-empty.php
├── cart-totals.php
├── mini-cart.php                ★ 小购物车（下拉）
├── cross-sells.php
└── shipping-calculator.php

checkout/
├── form-checkout.php            ★ 结算页主模板
├── form-billing.php  form-shipping.php  form-coupon.php  form-pay.php
├── review-order.php             订单摘要
├── payment.php  payment-method.php
├── thankyou.php                 ★ 支付成功页
└── order-receipt.php

myaccount/
├── my-account.php
├── dashboard.php  orders.php  view-order.php
├── downloads.php  edit-account.php  edit-address.php
├── form-login.php  form-edit-account.php
└── navigation.php

emails/
├── customer-completed-order.php
├── customer-processing-order.php
├── customer-new-account.php
├── admin-new-order.php
├── email-header.php  email-footer.php
├── email-order-details.php  email-addresses.php
└── plain/                       纯文本版本
```

### 在自定义模板里输出 WC 内容

如果你要完全自己写 `archive-product.php`：

```php
<?php get_header(); ?>

<?php woocommerce_content(); ?>     <!-- 输出 WC 的完整归档/单品内容 -->

<?php get_footer(); ?>
```

或者手动组装：

```php
<?php if ( woocommerce_product_loop() ) : ?>

    <?php woocommerce_product_loop_start(); ?>

        <?php while ( have_posts() ) : the_post(); ?>
            <?php wc_get_template_part( 'content', 'product' ); ?>
        <?php endwhile; ?>

    <?php woocommerce_product_loop_end(); ?>

    <?php do_action( 'woocommerce_after_shop_loop' ); ?>

<?php else : ?>
    <?php do_action( 'woocommerce_no_products_found' ); ?>
<?php endif; ?>
```

### 模板加载函数

```php
wc_get_template( 'single-product/price.php', [ 'product' => $product ] );
wc_get_template_part( 'content', 'product' );          // content-product.php
wc_get_template_html( $template, $args );               // 返回而不输出
wc_locate_template( $template );                        // 返回路径
```

**在自己的插件里加载模板**，并允许主题覆盖：

```php
wc_get_template(
    'my-template.php',
    [ 'foo' => $bar ],
    'my-plugin/',                        // 主题里的覆盖路径
    plugin_dir_path( __FILE__ ) . 'templates/'   // 插件默认路径
);
// 主题可以在 themes/x/my-plugin/my-template.php 覆盖它
```

---

## 5. 钩子体系：WooCommerce 的骨架

**这一节是全文最重要的**。掌握了钩子位置，90% 的定制不需要覆盖模板。

### 基本操作

```php
// 移除默认内容
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

// 换位置（先移除再以新优先级加回）
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 25 );

// 插入自定义内容
add_action( 'woocommerce_single_product_summary', 'my_custom_block', 35 );
function my_custom_block() {
    global $product;
    echo '<div class="shipping-note">全场满 500 免运费</div>';
}
```

⚠️ **`remove_action` 必须在钩子触发前执行，且优先级要精确匹配**。放 `functions.php` 顶层通常可以；如果不生效，包一层 `init` 或 `wp`：

```php
add_action( 'init', function() {
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
});
```

### 单品页钩子全图

```
woocommerce_before_main_content
│
├─ woocommerce_before_single_product
│
└─ <div class="product">
   │
   ├─ woocommerce_before_single_product_summary
   │    10  woocommerce_show_product_sale_flash      促销标签
   │    20  woocommerce_show_product_images          主图 + 相册
   │
   ├─ <div class="summary">
   │  └─ woocommerce_single_product_summary
   │        5   woocommerce_template_single_title      标题
   │        10  woocommerce_template_single_rating     星级
   │        10  woocommerce_template_single_price      价格
   │        20  woocommerce_template_single_excerpt    简短描述
   │        30  woocommerce_template_single_add_to_cart 加购表单
   │        40  woocommerce_template_single_meta       SKU/分类/标签
   │        50  woocommerce_template_single_sharing    分享
   │  </div>
   │
   └─ woocommerce_after_single_product_summary
        10  woocommerce_output_product_data_tabs     选项卡
        15  woocommerce_upsell_display               追加销售
        20  woocommerce_output_related_products      相关商品
   </div>
│
└─ woocommerce_after_single_product

woocommerce_after_main_content
```

**加购表单内部**：

```
woocommerce_before_add_to_cart_form
woocommerce_before_add_to_cart_button
woocommerce_before_add_to_cart_quantity
woocommerce_after_add_to_cart_quantity
woocommerce_after_add_to_cart_button
woocommerce_after_add_to_cart_form

变体商品额外有：
woocommerce_before_variations_form
woocommerce_before_single_variation
woocommerce_single_variation            10 = 价格, 20 = 加购按钮
woocommerce_after_single_variation
woocommerce_after_variations_form
```

### 归档页 / 循环钩子全图

```
woocommerce_before_main_content
│
├─ woocommerce_archive_description
│    10  woocommerce_taxonomy_archive_description
│    10  woocommerce_product_archive_description
│
├─ woocommerce_before_shop_loop
│    10  woocommerce_output_all_notices
│    20  woocommerce_result_count            "显示 1–12 / 共 50"
│    30  woocommerce_catalog_ordering         排序下拉
│
├─ <ul class="products">
│  │
│  └─ 每个商品：
│     ├─ woocommerce_before_shop_loop_item
│     │    10  woocommerce_template_loop_product_link_open   <a> 开
│     │
│     ├─ woocommerce_before_shop_loop_item_title
│     │    10  woocommerce_show_product_loop_sale_flash      促销标签
│     │    10  woocommerce_template_loop_product_thumbnail   缩略图
│     │
│     ├─ woocommerce_shop_loop_item_title
│     │    10  woocommerce_template_loop_product_title       标题
│     │
│     ├─ woocommerce_after_shop_loop_item_title
│     │    5   woocommerce_template_loop_rating              星级
│     │    10  woocommerce_template_loop_price               价格
│     │
│     └─ woocommerce_after_shop_loop_item
│          5   woocommerce_template_loop_product_link_close  </a>
│          10  woocommerce_template_loop_add_to_cart         加购按钮
│
└─ </ul>
│
├─ woocommerce_after_shop_loop
│    10  woocommerce_pagination
│
woocommerce_after_main_content
```

### 购物车钩子

```
woocommerce_before_cart
woocommerce_before_cart_table
woocommerce_before_cart_contents
woocommerce_cart_contents
woocommerce_after_cart_contents
woocommerce_cart_coupon
woocommerce_cart_actions
woocommerce_after_cart_table
woocommerce_cart_collaterals          10 = 交叉销售, 10 = 购物车总计
woocommerce_before_cart_totals
woocommerce_cart_totals_before_shipping
woocommerce_cart_totals_after_shipping
woocommerce_cart_totals_before_order_total
woocommerce_cart_totals_after_order_total
woocommerce_proceed_to_checkout       20 = 去结算按钮
woocommerce_after_cart_totals
woocommerce_after_cart

空购物车：
woocommerce_cart_is_empty
woocommerce_cart_has_errors
```

### 结算页钩子

```
woocommerce_before_checkout_form
woocommerce_checkout_before_customer_details
│
├─ woocommerce_checkout_billing
│    woocommerce_before_checkout_billing_form
│    woocommerce_after_checkout_billing_form
│
├─ woocommerce_checkout_shipping
│    woocommerce_before_checkout_shipping_form
│    woocommerce_after_checkout_shipping_form
│
└─ woocommerce_before_order_notes
   woocommerce_after_order_notes

woocommerce_checkout_after_customer_details
woocommerce_checkout_before_order_review_heading
woocommerce_checkout_before_order_review
woocommerce_checkout_order_review
    10  woocommerce_order_review
    20  woocommerce_checkout_payment
woocommerce_review_order_before_cart_contents
woocommerce_review_order_after_cart_contents
woocommerce_review_order_before_shipping
woocommerce_review_order_after_shipping
woocommerce_review_order_before_order_total
woocommerce_review_order_after_order_total
woocommerce_review_order_before_payment
woocommerce_review_order_before_submit
woocommerce_review_order_after_submit
woocommerce_review_order_after_payment
woocommerce_checkout_after_order_review
woocommerce_after_checkout_form

处理流程：
woocommerce_checkout_process             验证前
woocommerce_after_checkout_validation     验证（可加自定义校验）
woocommerce_checkout_create_order         创建订单对象时
woocommerce_checkout_order_processed      订单创建完成 ★ 最常用
woocommerce_checkout_update_order_meta    存自定义字段
```

### 订单状态钩子

```php
woocommerce_new_order                        新订单
woocommerce_order_status_changed             任意状态变化（$id, $from, $to, $order）
woocommerce_order_status_pending
woocommerce_order_status_processing          ★ 付款成功后最常用
woocommerce_order_status_on-hold
woocommerce_order_status_completed           ★ 完成
woocommerce_order_status_cancelled
woocommerce_order_status_refunded
woocommerce_order_status_failed

woocommerce_order_status_pending_to_processing    定向转换
woocommerce_order_status_processing_to_completed

woocommerce_payment_complete                 支付完成（不一定改状态）
woocommerce_thankyou                         感谢页
woocommerce_thankyou_{gateway_id}
```

### 常用 Filter

```php
// 价格显示
woocommerce_get_price_html                   改价格 HTML
woocommerce_product_get_price
woocommerce_cart_item_price
woocommerce_cart_item_subtotal
woocommerce_price_format                     货币符号位置
woocommerce_currency_symbol

// 按钮文字
woocommerce_product_single_add_to_cart_text
woocommerce_product_add_to_cart_text
woocommerce_order_button_text                下单按钮

// 循环
loop_shop_per_page                           每页商品数
loop_shop_columns                            列数
woocommerce_output_related_products_args     相关商品参数
woocommerce_product_loop_start
woocommerce_sale_flash                       促销标签 HTML

// 结算
woocommerce_checkout_fields                  ★ 所有结算字段
woocommerce_billing_fields
woocommerce_shipping_fields
woocommerce_default_address_fields
woocommerce_form_field_args
woocommerce_enable_order_notes_field

// 购物车
woocommerce_add_to_cart_validation           加购校验
woocommerce_cart_item_name
woocommerce_cart_item_thumbnail
woocommerce_add_to_cart_fragments            AJAX 片段更新
woocommerce_cart_needs_shipping

// 选项卡
woocommerce_product_tabs

// 其他
woocommerce_get_availability_text            库存文案
woocommerce_breadcrumb_defaults
woocommerce_account_menu_items
woocommerce_email_classes
woocommerce_payment_gateways
woocommerce_shipping_methods
```

---

## 6. 商品

### 获取商品对象

```php
// 循环中
global $product;

// 按 ID
$product = wc_get_product( $id );

// 在单品页
$product = wc_get_product( get_the_ID() );

// 判空 —— 商品可能不存在或已删除
if ( ! $product instanceof WC_Product ) {
    return;
}
```

### 常用方法

```php
// 基本
$product->get_id();
$product->get_name();
$product->get_slug();
$product->get_permalink();
$product->get_type();                  // simple/variable/grouped/external/variation
$product->get_status();
$product->get_sku();
$product->get_description();
$product->get_short_description();

// 价格
$product->get_price();                 // 当前有效价（数字）
$product->get_regular_price();
$product->get_sale_price();
$product->get_price_html();            // 带 HTML 的价格（含划线价）✅ 前台用这个
$product->is_on_sale();

// 库存
$product->get_stock_quantity();
$product->get_stock_status();          // instock / outofstock / onbackorder
$product->is_in_stock();
$product->managing_stock();
$product->is_purchasable();
$product->get_availability();          // ['availability' => '有货', 'class' => 'in-stock']

// 图片
$product->get_image_id();
$product->get_image( 'woocommerce_thumbnail' );      // 完整 <img>
$product->get_gallery_image_ids();                   // 相册 ID 数组

// 分类与属性
$product->get_category_ids();
$product->get_tag_ids();
$product->get_attributes();
$product->get_attribute( 'pa_color' );               // 属性值（字符串）

// 尺寸重量
$product->get_weight();
$product->get_dimensions();            // 格式化字符串
$product->get_length();  get_width();  get_height();

// 评价
$product->get_average_rating();
$product->get_review_count();
$product->get_rating_count();

// 销量
$product->get_total_sales();

// 类型判断
$product->is_type( 'variable' );
$product->is_virtual();
$product->is_downloadable();
$product->is_featured();
$product->is_visible();
```

### 变体商品

```php
if ( $product->is_type( 'variable' ) ) {

    // 所有变体（数组，含完整数据，数量多时慢）
    $variations = $product->get_available_variations();

    // 只要 ID（更快）
    $ids = $product->get_children();

    // 价格区间
    $min = $product->get_variation_price( 'min' );
    $max = $product->get_variation_price( 'max' );
    echo $product->get_price_html();     // 自动显示 "¥100 – ¥200"

    // 可选属性
    $attributes = $product->get_variation_attributes();
    // ['pa_color' => ['red','blue'], 'pa_size' => ['s','m','l']]

    // 默认选中
    $defaults = $product->get_default_attributes();
}

// 变体对象
$variation = wc_get_product( $variation_id );
$variation->get_parent_id();
$variation->get_attributes();          // ['pa_color' => 'red']
$variation->get_variation_attributes();
```

**`get_available_variations()` 很重）**——它会把每个变体的完整数据（价格 HTML、图片、库存）都算一遍。50 个变体的商品可能要几百毫秒。只要 ID 就用 `get_children()`。

### 创建商品

```php
$product = new WC_Product_Simple();
$product->set_name( '测试商品' );
$product->set_status( 'publish' );
$product->set_catalog_visibility( 'visible' );
$product->set_description( '详细描述' );
$product->set_short_description( '简短描述' );
$product->set_sku( 'TEST-001' );
$product->set_regular_price( '199' );
$product->set_sale_price( '149' );
$product->set_manage_stock( true );
$product->set_stock_quantity( 100 );
$product->set_stock_status( 'instock' );
$product->set_weight( '1.5' );
$product->set_category_ids( [ 15, 16 ] );
$product->set_image_id( $attachment_id );
$product->set_gallery_image_ids( [ $id1, $id2 ] );

$product_id = $product->save();
```

### 自定义商品字段（不用 ACF 的原生做法）

```php
// 加字段到「商品数据 → 常规」
add_action( 'woocommerce_product_options_general_product_data', function() {
    woocommerce_wp_text_input([
        'id'          => '_custom_field',
        'label'       => '自定义字段',
        'description' => '说明文字',
        'desc_tip'    => true,
    ]);

    woocommerce_wp_checkbox([
        'id'    => '_is_special',
        'label' => '特殊商品',
    ]);

    woocommerce_wp_select([
        'id'      => '_badge',
        'label'   => '角标',
        'options' => [ '' => '无', 'new' => '新品', 'hot' => '热销' ],
    ]);

    woocommerce_wp_textarea_input([
        'id'    => '_care_instructions',
        'label' => '保养说明',
    ]);
});

// 保存
add_action( 'woocommerce_admin_process_product_object', function( $product ) {
    $product->update_meta_data( '_custom_field',
        sanitize_text_field( $_POST['_custom_field'] ?? '' ) );
    $product->update_meta_data( '_is_special',
        isset( $_POST['_is_special'] ) ? 'yes' : 'no' );
});
```

新增选项卡：

```php
add_filter( 'woocommerce_product_data_tabs', function( $tabs ) {
    $tabs['my_tab'] = [
        'label'    => '自定义',
        'target'   => 'my_tab_data',
        'class'    => [],
        'priority' => 80,
    ];
    return $tabs;
});

add_action( 'woocommerce_product_data_panels', function() {
    echo '<div id="my_tab_data" class="panel woocommerce_options_panel">';
    woocommerce_wp_text_input([ 'id' => '_my_tab_field', 'label' => '字段' ]);
    echo '</div>';
});
```

---

## 7. 商品查询

### wc_get_products()（推荐）

```php
$products = wc_get_products([
    'status'   => 'publish',
    'limit'    => 12,
    'page'     => 1,
    'orderby'  => 'date',        // date/id/title/menu_order/rand/popularity/rating/price
    'order'    => 'DESC',

    'type'     => 'simple',      // simple/variable/grouped/external
    'category' => [ 'shoes' ],   // slug 数组
    'tag'      => [ 'sale' ],

    'featured'      => true,
    'on_sale'       => true,
    'stock_status'  => 'instock',
    'sku'           => 'ABC',
    'include'       => [1,2,3],
    'exclude'       => [4,5],

    'price'         => 100,
    'min_price'     => 50,
    'max_price'     => 200,

    'return'        => 'objects',   // objects / ids
    'paginate'      => false,       // true 时返回带 total 的对象
]);

foreach ( $products as $product ) {
    echo $product->get_name();
}
```

带分页：

```php
$result = wc_get_products([ 'limit' => 12, 'page' => 2, 'paginate' => true ]);
$result->products;        // 商品数组
$result->total;           // 总数
$result->max_num_pages;
```

### WP_Query（需要复杂 tax_query 时）

```php
$args = [
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'tax_query'      => [
        'relation' => 'AND',
        [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => [ 'shoes' ],
        ],
        [
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => [ 'exclude-from-catalog' ],
            'operator' => 'NOT IN',
        ],
    ],
    'meta_query' => [
        [
            'key'     => '_stock_status',
            'value'   => 'instock',
        ],
    ],
];

// WC 提供的可见性条件（推荐用它，省得手写）
$args['tax_query'][] = WC()->query->get_tax_query();
```

### 用查找表排序（性能好）

按价格排序时，走 `wc_product_meta_lookup` 比 join postmeta 快得多。WC 的 `woocommerce_get_catalog_ordering_args` 已经处理了，但自定义查询可以手动利用：

```php
add_filter( 'posts_clauses', function( $clauses, $query ) {
    if ( ! $query->get('my_price_sort') ) return $clauses;

    global $wpdb;
    $clauses['join']   .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} lookup
                            ON {$wpdb->posts}.ID = lookup.product_id ";
    $clauses['orderby'] = " lookup.min_price ASC ";
    return $clauses;
}, 10, 2 );
```

### 相关商品 / 交叉销售

```php
$related_ids   = wc_get_related_products( $product->get_id(), 4 );
$upsell_ids    = $product->get_upsell_ids();
$crosssell_ids = $product->get_cross_sell_ids();
```

---

## 8. 商品归档页与循环

### 改每页数量和列数

```php
add_filter( 'loop_shop_per_page', fn() => 12, 20 );
add_filter( 'loop_shop_columns', fn() => 3 );
```

### 改循环内容

```php
// 移除默认加购按钮
remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

// 换成自定义
add_action( 'woocommerce_after_shop_loop_item', 'my_loop_button', 10 );
function my_loop_button() {
    global $product;
    printf(
        '<a href="%s" class="btn btn-view">查看详情</a>',
        esc_url( $product->get_permalink() )
    );
}
```

### 完全自定义商品卡片

覆盖 `content-product.php`：

```php
<?php
defined( 'ABSPATH' ) || exit;
global $product;

if ( empty( $product ) || ! $product->is_visible() ) {
    return;
}
?>
<li <?php wc_product_class( 'product-card', $product ); ?>>
    <a href="<?php echo esc_url( $product->get_permalink() ); ?>" class="product-card__link">

        <div class="product-card__media">
            <?php echo $product->get_image( 'woocommerce_thumbnail' ); ?>
            <?php if ( $product->is_on_sale() ) : ?>
                <span class="badge badge--sale">SALE</span>
            <?php endif; ?>
            <?php if ( ! $product->is_in_stock() ) : ?>
                <span class="badge badge--oos">售罄</span>
            <?php endif; ?>
        </div>

        <div class="product-card__body">
            <h3 class="product-card__title"><?php echo esc_html( $product->get_name() ); ?></h3>
            <div class="product-card__price"><?php echo $product->get_price_html(); ?></div>
        </div>

    </a>

    <?php woocommerce_template_loop_add_to_cart(); ?>
</li>
```

**`wc_product_class()` 一定要保留**——它输出 `product`、`type-simple`、`instock` 等 class，WC 的 JS（尤其是 AJAX 加购）依赖它们。

### 排序选项

```php
add_filter( 'woocommerce_catalog_orderby', function( $options ) {
    unset( $options['rating'] );
    $options['price'] = '价格从低到高';
    $options['my_custom'] = '自定义排序';
    return $options;
});

add_filter( 'woocommerce_get_catalog_ordering_args', function( $args ) {
    if ( isset( $_GET['orderby'] ) && 'my_custom' === $_GET['orderby'] ) {
        $args['orderby']  = 'meta_value_num';
        $args['meta_key'] = '_my_sort_field';
        $args['order']    = 'ASC';
    }
    return $args;
});
```

### 商品图尺寸

```php
// 后台 外观 → 自定义 → WooCommerce → 商品图片 里也能设
add_filter( 'woocommerce_get_image_size_thumbnail', function( $size ) {
    return [ 'width' => 400, 'height' => 500, 'crop' => 1 ];
});

add_filter( 'woocommerce_get_image_size_single', function( $size ) {
    return [ 'width' => 900, 'height' => 0, 'crop' => 0 ];
});
```

WC 的图片尺寸名：`woocommerce_thumbnail`、`woocommerce_single`、`woocommerce_gallery_thumbnail`。

改完要重新生成缩略图：`wp media regenerate --yes`

---

## 9. 单品页

### 重排元素

```php
add_action( 'init', function() {
    // 移除简短描述，加到价格上面
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
    add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 8 );

    // 移除 SKU/分类
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

    // 移除相关商品
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
});
```

### 选项卡

```php
add_filter( 'woocommerce_product_tabs', function( $tabs ) {

    // 改标题
    $tabs['description']['title'] = '商品详情';

    // 改顺序
    $tabs['reviews']['priority'] = 5;

    // 删除
    unset( $tabs['additional_information'] );

    // 新增
    $tabs['shipping'] = [
        'title'    => '配送说明',
        'priority' => 30,
        'callback' => 'my_shipping_tab',
    ];

    return $tabs;
}, 98 );

function my_shipping_tab() {
    global $product;
    echo '<h2>配送说明</h2>';
    echo wpautop( wp_kses_post( get_field( 'shipping_note', $product->get_id() ) ) );
}
```

**优先级用 98**——很多插件在 99 或 100 加自己的选项卡，用 98 能保证你的改动不被覆盖，同时又在 WC 默认（10）之后。

### 加购按钮文字

```php
add_filter( 'woocommerce_product_single_add_to_cart_text', function( $text, $product ) {
    if ( $product->is_type('variable') ) return '选择规格';
    return '立即购买';
}, 10, 2 );

// 归档页的
add_filter( 'woocommerce_product_add_to_cart_text', fn( $text, $product ) => '加入购物车', 10, 2 );
```

### 库存文案

```php
add_filter( 'woocommerce_get_availability_text', function( $text, $product ) {
    if ( ! $product->is_in_stock() ) return '暂时缺货';
    if ( $product->managing_stock() ) {
        $qty = $product->get_stock_quantity();
        if ( $qty <= 5 ) return "仅剩 {$qty} 件";
    }
    return '现货';
}, 10, 2 );
```

### 数量输入框

```php
// 隐藏数量框（每次只能买 1 个）
add_filter( 'woocommerce_is_sold_individually', '__return_true' );

// 设置步进和上下限
add_filter( 'woocommerce_quantity_input_args', function( $args, $product ) {
    $args['min_value'] = 2;
    $args['max_value'] = 10;
    $args['step']      = 2;
    return $args;
}, 10, 2 );
```

---

## 10. 购物车

### 访问购物车

```php
$cart = WC()->cart;

$cart->get_cart();                      // 所有商品项（数组）
$cart->get_cart_contents_count();       // 商品总件数
$cart->get_cart_contents();
$cart->is_empty();

$cart->get_cart_subtotal();             // 小计（HTML）
$cart->get_cart_total();                // 总计（HTML）
$cart->get_subtotal();                  // 小计（数字）
$cart->get_total( 'edit' );             // 总计（数字）
$cart->get_total_tax();
$cart->get_shipping_total();
$cart->get_discount_total();

$cart->get_applied_coupons();
$cart->get_coupon_discount_amount( $code );
```

### 遍历购物车

```php
foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
    $product    = $item['data'];              // WC_Product 对象
    $product_id = $item['product_id'];
    $variation_id = $item['variation_id'];
    $quantity   = $item['quantity'];
    $line_total = $item['line_total'];
    $line_subtotal = $item['line_subtotal'];

    echo $product->get_name() . ' × ' . $quantity;
    echo wc_price( $line_total );
}
```

### 操作购物车

```php
// 加入
WC()->cart->add_to_cart(
    $product_id,
    $quantity = 1,
    $variation_id = 0,
    $variation = [],                          // ['attribute_pa_color' => 'red']
    $cart_item_data = []                      // 自定义数据
);

// 删除
WC()->cart->remove_cart_item( $cart_item_key );

// 改数量
WC()->cart->set_quantity( $cart_item_key, 3 );

// 清空
WC()->cart->empty_cart();

// 重算
WC()->cart->calculate_totals();

// 优惠券
WC()->cart->apply_coupon( 'SAVE10' );
WC()->cart->remove_coupon( 'SAVE10' );
```

### 加购校验

```php
add_filter( 'woocommerce_add_to_cart_validation', function( $passed, $product_id, $qty ) {
    // 例：限购
    foreach ( WC()->cart->get_cart() as $item ) {
        if ( $item['product_id'] == $product_id && $item['quantity'] + $qty > 3 ) {
            wc_add_notice( '该商品每单限购 3 件。', 'error' );
            return false;
        }
    }
    return $passed;
}, 10, 3 );
```

### 自定义购物车项数据

```php
// 加购时存
add_filter( 'woocommerce_add_cart_item_data', function( $data, $product_id ) {
    if ( ! empty( $_POST['engraving'] ) ) {
        $data['engraving'] = sanitize_text_field( $_POST['engraving'] );
        $data['unique_key'] = md5( microtime() . rand() );   // 防止合并成一行
    }
    return $data;
}, 10, 2 );

// 在购物车/结算页显示
add_filter( 'woocommerce_get_item_data', function( $item_data, $cart_item ) {
    if ( ! empty( $cart_item['engraving'] ) ) {
        $item_data[] = [
            'key'   => '刻字',
            'value' => wc_clean( $cart_item['engraving'] ),
        ];
    }
    return $item_data;
}, 10, 2 );

// 写入订单行
add_action( 'woocommerce_checkout_create_order_line_item',
    function( $item, $cart_item_key, $values, $order ) {
        if ( ! empty( $values['engraving'] ) ) {
            $item->add_meta_data( '刻字', $values['engraving'] );
        }
    }, 10, 4 );
```

**`unique_key` 那行很关键**——不加的话，同一个商品的不同刻字会被合并成一行，数量 +1。

### 附加费用

```php
add_action( 'woocommerce_cart_calculate_fees', function( $cart ) {
    if ( is_admin() && ! defined('DOING_AJAX') ) return;

    // 例：满 1000 减 100
    if ( $cart->get_subtotal() >= 1000 ) {
        $cart->add_fee( '满额优惠', -100 );
    }

    // 例：加包装费
    $cart->add_fee( '包装费', 20, true );   // 第 3 参数 = 是否计税
});
```

### 小购物车 AJAX 更新

```php
add_filter( 'woocommerce_add_to_cart_fragments', function( $fragments ) {
    ob_start();
    ?>
    <span class="cart-count"><?php echo WC()->cart->get_cart_contents_count(); ?></span>
    <?php
    $fragments['.cart-count'] = ob_get_clean();
    return $fragments;
});
```

**片段的 key 必须是能匹配到页面上现有元素的 CSS 选择器**。WC 的 JS 会用返回的 HTML 替换掉那个元素。

---

## 11. 结算页

### 修改字段

```php
add_filter( 'woocommerce_checkout_fields', function( $fields ) {

    // 删除
    unset( $fields['billing']['billing_company'] );
    unset( $fields['order']['order_comments'] );

    // 改属性
    $fields['billing']['billing_phone']['required'] = true;
    $fields['billing']['billing_phone']['label']    = '手机号';
    $fields['billing']['billing_phone']['placeholder'] = '请输入 11 位手机号';
    $fields['billing']['billing_phone']['priority'] = 25;      // 排序
    $fields['billing']['billing_phone']['class']    = [ 'form-row-wide' ];

    // 新增
    $fields['billing']['billing_wechat'] = [
        'type'        => 'text',
        'label'       => '微信号',
        'required'    => false,
        'class'       => [ 'form-row-wide' ],
        'priority'    => 120,
    ];

    return $fields;
});
```

字段类型：`text` `textarea` `select` `radio` `checkbox` `password` `email` `tel` `country` `state` `date`

### 自定义校验

```php
add_action( 'woocommerce_after_checkout_validation', function( $data, $errors ) {
    if ( ! empty( $data['billing_phone'] )
      && ! preg_match( '/^1[3-9]\d{9}$/', $data['billing_phone'] ) ) {
        $errors->add( 'validation', '手机号格式不正确。' );
    }
}, 10, 2 );
```

### 保存自定义字段到订单

```php
add_action( 'woocommerce_checkout_create_order', function( $order, $data ) {
    if ( ! empty( $_POST['billing_wechat'] ) ) {
        $order->update_meta_data( '_billing_wechat',
            sanitize_text_field( $_POST['billing_wechat'] ) );
    }
}, 10, 2 );
```

**用 `woocommerce_checkout_create_order` 而不是 `woocommerce_checkout_update_order_meta`**——前者在订单保存前修改对象，只写一次库；后者是订单存完再更新，多一次写入，HPOS 下还可能有时序问题。

### 后台显示自定义字段

```php
add_action( 'woocommerce_admin_order_data_after_billing_address', function( $order ) {
    $wechat = $order->get_meta( '_billing_wechat' );
    if ( $wechat ) {
        echo '<p><strong>微信号：</strong>' . esc_html( $wechat ) . '</p>';
    }
});
```

### 字段排序

WC 用 `priority` 排序，默认值：

```
billing_first_name  10      billing_last_name   20
billing_company     30      billing_country     40
billing_address_1   50      billing_address_2   60
billing_city        70      billing_state       80
billing_postcode    90      billing_phone       100
billing_email       110
```

### 简化结算流程

```php
// 关掉配送（纯虚拟商品店）
add_filter( 'woocommerce_cart_needs_shipping', '__return_false' );

// 关掉「配送到不同地址」
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

// 关掉优惠券
add_filter( 'woocommerce_coupons_enabled', '__return_false' );

// 关掉订单备注
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

// 下单按钮文字
add_filter( 'woocommerce_order_button_text', fn() => '提交订单' );
```

### 跳过购物车直接结算

```php
add_filter( 'woocommerce_add_to_cart_redirect', fn() => wc_get_checkout_url() );
```

---

## 12. 订单

### 获取订单

```php
$order = wc_get_order( $order_id );

if ( ! $order instanceof WC_Order ) {
    return;   // 订单不存在
}
```

### 常用方法

```php
// 基本
$order->get_id();
$order->get_order_number();            // 可能被插件改成自定义单号
$order->get_status();                  // 不带 wc- 前缀
$order->get_date_created();            // WC_DateTime 对象
$order->get_date_paid();
$order->get_date_completed();
$order->get_customer_id();
$order->get_customer_note();
$order->get_order_key();
$order->get_checkout_payment_url();
$order->get_view_order_url();

// 金额
$order->get_total();
$order->get_subtotal();
$order->get_total_tax();
$order->get_shipping_total();
$order->get_discount_total();
$order->get_currency();
$order->get_formatted_order_total();   // 带货币符号的 HTML

// 账单
$order->get_billing_first_name();
$order->get_billing_last_name();
$order->get_billing_company();
$order->get_billing_email();
$order->get_billing_phone();
$order->get_billing_address_1();
$order->get_billing_city();
$order->get_billing_state();
$order->get_billing_postcode();
$order->get_billing_country();
$order->get_formatted_billing_address();

// 配送（同上，把 billing 换成 shipping）
$order->get_formatted_shipping_address();
$order->get_shipping_method();

// 支付
$order->get_payment_method();          // gateway id
$order->get_payment_method_title();    // 显示名
$order->get_transaction_id();

// 状态
$order->has_status( 'completed' );
$order->has_status( [ 'processing', 'completed' ] );
$order->is_paid();
$order->needs_payment();
$order->needs_processing();
```

### 遍历订单商品

```php
foreach ( $order->get_items() as $item_id => $item ) {
    $product      = $item->get_product();      // WC_Product 或 false
    $product_id   = $item->get_product_id();
    $variation_id = $item->get_variation_id();
    $name         = $item->get_name();
    $qty          = $item->get_quantity();
    $subtotal     = $item->get_subtotal();     // 折扣前
    $total        = $item->get_total();        // 折扣后
    $tax          = $item->get_total_tax();

    // 自定义 meta
    $engraving = $item->get_meta( '刻字' );
    foreach ( $item->get_formatted_meta_data() as $meta ) {
        echo $meta->display_key . ': ' . $meta->display_value;
    }
}

// 其他行类型
$order->get_items( 'shipping' );
$order->get_items( 'fee' );
$order->get_items( 'tax' );
$order->get_items( 'coupon' );
```

### 修改订单

```php
$order->update_status( 'completed', '手动完成。' );      // 会触发状态钩子和邮件
$order->set_status( 'completed' );                       // 不触发，需自己 save()
$order->save();

$order->add_order_note( '内部备注' );                     // 只有后台看得到
$order->add_order_note( '给客户的通知', true );            // 第 2 参数 true = 通知客户

$order->update_meta_data( '_tracking_number', 'SF123456' );
$order->save();

$order->payment_complete( $transaction_id );             // 标记已付款
$order->calculate_totals();
```

### 创建订单

```php
$order = wc_create_order([ 'customer_id' => $user_id ]);

$order->add_product( wc_get_product( 123 ), 2 );

$order->set_address([
    'first_name' => '张',
    'last_name'  => '三',
    'email'      => 'x@y.com',
    'phone'      => '13800138000',
    'address_1'  => '某某路 1 号',
    'city'       => '上海',
    'country'    => 'CN',
], 'billing' );

$order->set_payment_method( 'bacs' );
$order->calculate_totals();
$order->update_status( 'processing' );
$order->save();
```

### 查询订单

```php
$orders = wc_get_orders([
    'limit'        => 10,
    'status'       => [ 'wc-processing', 'wc-completed' ],   // 注意带 wc- 前缀
    'customer_id'  => $user_id,
    'customer'     => 'x@y.com',
    'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ),
    'orderby'      => 'date',
    'order'        => 'DESC',
    'return'       => 'objects',      // objects / ids
    'paginate'     => true,

    // 按 meta 查（HPOS 也支持）
    'meta_query'   => [
        [ 'key' => '_tracking_number', 'compare' => 'EXISTS' ],
    ],
]);
```

### 订单状态

| 状态 | slug | 含义 |
|---|---|---|
| 待付款 | `pending` | 已下单未付款 |
| 处理中 | `processing` | 已付款，待发货 |
| 保留 | `on-hold` | 等待确认（如银行转账） |
| 已完成 | `completed` | 已发货完成 |
| 已取消 | `cancelled` | |
| 已退款 | `refunded` | |
| 失败 | `failed` | 支付失败 |
| 草稿 | `checkout-draft` | 结算区块产生的临时订单 |

**数据库里带 `wc-` 前缀**（`wc-processing`），但 `$order->get_status()` 返回不带前缀的。查询时用带前缀的。

### 自定义订单状态

```php
add_action( 'init', function() {
    register_post_status( 'wc-shipped', [
        'label'                     => '已发货',
        'public'                    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( '已发货 (%s)', '已发货 (%s)' ),
    ]);
});

add_filter( 'wc_order_statuses', function( $statuses ) {
    $new = [];
    foreach ( $statuses as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'wc-processing' === $key ) {
            $new['wc-shipped'] = '已发货';       // 插在处理中后面
        }
    }
    return $new;
});
```

---

## 13. 我的账户与端点

### 添加自定义标签页

```php
// 1. 注册端点
add_action( 'init', function() {
    add_rewrite_endpoint( 'my-points', EP_ROOT | EP_PAGES );
});

// 2. 注册查询变量
add_filter( 'woocommerce_get_query_vars', function( $vars ) {
    $vars['my-points'] = 'my-points';
    return $vars;
});

// 3. 加到菜单
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    // 插到「订单」后面
    $new = [];
    foreach ( $items as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'orders' === $key ) {
            $new['my-points'] = '我的积分';
        }
    }
    return $new;
});

// 4. 渲染内容
add_action( 'woocommerce_account_my-points_endpoint', function() {
    $points = get_user_meta( get_current_user_id(), '_points', true ) ?: 0;
    echo '<h3>当前积分</h3><p>' . esc_html( $points ) . '</p>';
});
```

**注册端点后必须刷新固定链接**（设置 → 固定链接 → 保存），否则 404。

### 调整菜单

```php
add_filter( 'woocommerce_account_menu_items', function( $items ) {
    unset( $items['downloads'] );
    $items['edit-address'] = '收货地址';
    return $items;
});
```

### 常用函数

```php
wc_get_account_endpoint_url( 'orders' );
wc_get_page_permalink( 'myaccount' );
wc_get_page_permalink( 'shop' );
wc_get_cart_url();
wc_get_checkout_url();
is_account_page();
is_wc_endpoint_url( 'orders' );
```

---

## 14. 支付网关开发

```php
add_action( 'plugins_loaded', 'init_my_gateway' );
function init_my_gateway() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Gateway_My extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'my_gateway';
            $this->icon               = '';
            $this->has_fields         = false;      // true = 结算页显示自定义表单
            $this->method_title       = '我的支付';
            $this->method_description = '后台设置页的说明';
            $this->supports           = [ 'products', 'refunds' ];

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->api_key     = $this->get_option( 'api_key' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
                [ $this, 'process_admin_options' ] );

            // 回调（webhook）
            add_action( 'woocommerce_api_' . $this->id, [ $this, 'handle_callback' ] );
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title'   => '启用',
                    'type'    => 'checkbox',
                    'label'   => '启用我的支付',
                    'default' => 'no',
                ],
                'title' => [
                    'title'   => '标题',
                    'type'    => 'text',
                    'default' => '在线支付',
                ],
                'description' => [
                    'title'   => '描述',
                    'type'    => 'textarea',
                    'default' => '',
                ],
                'api_key' => [
                    'title' => 'API Key',
                    'type'  => 'password',
                ],
                'testmode' => [
                    'title'   => '测试模式',
                    'type'    => 'checkbox',
                    'default' => 'yes',
                ],
            ];
        }

        // 结算页显示的自定义字段（has_fields = true 时）
        public function payment_fields() {
            if ( $this->description ) {
                echo wpautop( wp_kses_post( $this->description ) );
            }
            echo '<div id="my-gateway-form"></div>';
        }

        // 校验
        public function validate_fields() {
            if ( empty( $_POST['my_field'] ) ) {
                wc_add_notice( '请填写必填项。', 'error' );
                return false;
            }
            return true;
        }

        // ★ 核心：处理支付
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            $res = wp_remote_post( 'https://api.gateway.com/charge', [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'amount'      => $order->get_total() * 100,
                    'currency'    => $order->get_currency(),
                    'order_id'    => $order->get_id(),
                    'return_url'  => $this->get_return_url( $order ),
                    'notify_url'  => WC()->api_request_url( $this->id ),
                ]),
            ]);

            if ( is_wp_error( $res ) ) {
                wc_add_notice( '支付网关连接失败，请稍后重试。', 'error' );
                $order->add_order_note( '网关错误: ' . $res->get_error_message() );
                return [ 'result' => 'failure' ];
            }

            $body = json_decode( wp_remote_retrieve_body( $res ), true );

            if ( empty( $body['success'] ) ) {
                wc_add_notice( $body['message'] ?? '支付失败。', 'error' );
                return [ 'result' => 'failure' ];
            }

            // 方式 A：跳转到第三方支付页
            $order->update_status( 'pending', '等待支付。' );
            return [
                'result'   => 'success',
                'redirect' => $body['pay_url'],
            ];

            // 方式 B：直接扣款成功
            // $order->payment_complete( $body['transaction_id'] );
            // WC()->cart->empty_cart();
            // return [ 'result' => 'success', 'redirect' => $this->get_return_url( $order ) ];
        }

        // 异步回调
        public function handle_callback() {
            $payload = json_decode( file_get_contents('php://input'), true );

            // ⚠️ 必须验签！
            if ( ! $this->verify_signature( $payload ) ) {
                status_header( 400 );
                exit( 'invalid signature' );
            }

            $order = wc_get_order( $payload['order_id'] );
            if ( ! $order ) {
                status_header( 404 );
                exit;
            }

            // 幂等：可能收到重复回调
            if ( $order->is_paid() ) {
                status_header( 200 );
                exit( 'ok' );
            }

            if ( 'paid' === $payload['status'] ) {
                $order->payment_complete( $payload['transaction_id'] );
                $order->add_order_note( '支付成功，交易号 ' . $payload['transaction_id'] );
            } else {
                $order->update_status( 'failed', '支付失败。' );
            }

            status_header( 200 );
            exit( 'ok' );
        }

        private function verify_signature( $payload ) {
            // 按网关文档实现
            return true;
        }

        // 退款
        public function process_refund( $order_id, $amount = null, $reason = '' ) {
            $order = wc_get_order( $order_id );
            // 调用网关退款 API...
            return true;    // 或 new WP_Error(...)
        }
    }
}

// 注册
add_filter( 'woocommerce_payment_gateways', function( $gateways ) {
    $gateways[] = 'WC_Gateway_My';
    return $gateways;
});
```

**回调 URL**：`WC()->api_request_url( 'my_gateway' )` → `https://site.com/wc-api/my_gateway`

**三个必须做对的点**：
1. **验签** —— 不验签任何人都能伪造「支付成功」
2. **幂等** —— 网关可能重发回调，重复处理会导致订单状态错乱、库存扣多次
3. **回调里不要依赖 session** —— 那是服务器对服务器的请求，没有用户会话

---

## 15. 配送方式开发

```php
add_action( 'woocommerce_shipping_init', function() {

    if ( class_exists( 'WC_My_Shipping' ) ) return;

    class WC_My_Shipping extends WC_Shipping_Method {

        public function __construct( $instance_id = 0 ) {
            $this->id                 = 'my_shipping';
            $this->instance_id        = absint( $instance_id );
            $this->method_title       = '自定义配送';
            $this->method_description = '按重量计费';
            $this->supports           = [
                'shipping-zones',
                'instance-settings',
                'instance-settings-modal',
            ];
            $this->init();
        }

        public function init() {
            $this->instance_form_fields = [
                'title' => [
                    'title'   => '名称',
                    'type'    => 'text',
                    'default' => '标准配送',
                ],
                'base_cost' => [
                    'title'   => '基础运费',
                    'type'    => 'price',
                    'default' => '10',
                ],
                'per_kg' => [
                    'title'   => '每公斤加价',
                    'type'    => 'price',
                    'default' => '2',
                ],
                'free_over' => [
                    'title'   => '满额包邮',
                    'type'    => 'price',
                    'default' => '500',
                ],
            ];

            $this->title = $this->get_option( 'title' );

            add_action( 'woocommerce_update_options_shipping_' . $this->id,
                [ $this, 'process_admin_options' ] );
        }

        public function calculate_shipping( $package = [] ) {
            $subtotal = WC()->cart->get_displayed_subtotal();
            $free_over = (float) $this->get_option( 'free_over' );

            if ( $free_over > 0 && $subtotal >= $free_over ) {
                $cost = 0;
            } else {
                $weight = 0;
                foreach ( $package['contents'] as $item ) {
                    $weight += (float) $item['data']->get_weight() * $item['quantity'];
                }
                $cost = (float) $this->get_option('base_cost')
                      + $weight * (float) $this->get_option('per_kg');
            }

            $this->add_rate([
                'id'      => $this->get_rate_id(),
                'label'   => $this->title,
                'cost'    => $cost,
                'package' => $package,
            ]);
        }
    }
});

add_filter( 'woocommerce_shipping_methods', function( $methods ) {
    $methods['my_shipping'] = 'WC_My_Shipping';
    return $methods;
});
```

### 改现有运费

```php
add_filter( 'woocommerce_package_rates', function( $rates, $package ) {
    foreach ( $rates as $key => $rate ) {
        if ( 'flat_rate' === $rate->method_id ) {
            $rates[$key]->cost = $rate->cost * 0.9;      // 打九折
            // 税也要重算
            $rates[$key]->taxes = array_map( fn($t) => $t * 0.9, $rate->taxes );
        }
    }
    return $rates;
}, 10, 2 );
```

**运费结果有缓存**。调试时改了代码没变化，清一下：

```php
// 开发时临时加
add_filter( 'woocommerce_shipping_rates_transient_version', fn() => (string) time() );
```

或者在后台清空购物车重新加。

---

## 16. 邮件系统

### 内置邮件

```
customer-new-account          新账户
customer-processing-order     订单处理中（付款后）
customer-completed-order      订单完成
customer-on-hold-order
customer-refunded-order
customer-invoice              发票/付款链接
customer-reset-password
customer-note                 客户备注
admin-new-order               管理员：新订单
admin-cancelled-order
admin-failed-order
```

### 覆盖邮件模板

复制 `plugins/woocommerce/templates/emails/` 下的文件到 `themes/你的主题/woocommerce/emails/`。

**别忘了 `plain/` 子目录**——纯文本版本也要改，否则两个版本内容不一致。

### 修改内容

```php
// 在订单详情前插入
add_action( 'woocommerce_email_before_order_table',
    function( $order, $sent_to_admin, $plain_text, $email ) {
        if ( 'customer_completed_order' === $email->id ) {
            $tracking = $order->get_meta( '_tracking_number' );
            if ( $tracking ) {
                echo '<p><strong>快递单号：</strong>' . esc_html( $tracking ) . '</p>';
            }
        }
    }, 10, 4 );
```

邮件钩子：

```
woocommerce_email_header
woocommerce_email_before_order_table
woocommerce_email_order_details
woocommerce_email_order_meta
woocommerce_email_after_order_table
woocommerce_email_customer_details
woocommerce_email_footer
```

### 自定义邮件类

```php
add_filter( 'woocommerce_email_classes', function( $emails ) {
    require_once __DIR__ . '/class-wc-email-shipped.php';
    $emails['WC_Email_Shipped'] = new WC_Email_Shipped();
    return $emails;
});
```

```php
class WC_Email_Shipped extends WC_Email {

    public function __construct() {
        $this->id             = 'customer_shipped_order';
        $this->title          = '订单已发货';
        $this->description    = '订单标记为已发货时发送给客户。';
        $this->customer_email = true;
        $this->template_html  = 'emails/customer-shipped-order.php';
        $this->template_plain = 'emails/plain/customer-shipped-order.php';
        $this->template_base  = plugin_dir_path( __FILE__ ) . 'templates/';

        // 触发时机
        add_action( 'woocommerce_order_status_processing_to_shipped_notification',
            [ $this, 'trigger' ], 10, 2 );

        parent::__construct();
    }

    public function get_default_subject() { return '您的订单 {order_number} 已发货'; }
    public function get_default_heading() { return '订单已发货'; }

    public function trigger( $order_id, $order = false ) {
        if ( ! $order ) $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $this->object    = $order;
        $this->recipient = $order->get_billing_email();

        if ( ! $this->is_enabled() || ! $this->get_recipient() ) return;

        $this->send( $this->get_recipient(), $this->get_subject(),
                     $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }

    public function get_content_html() {
        return wc_get_template_html( $this->template_html, [
            'order'         => $this->object,
            'email_heading' => $this->get_heading(),
            'sent_to_admin' => false,
            'plain_text'    => false,
            'email'         => $this,
        ], '', $this->template_base );
    }

    public function get_content_plain() {
        return wc_get_template_html( $this->template_plain, [
            'order'      => $this->object,
            'plain_text' => true,
            'email'      => $this,
        ], '', $this->template_base );
    }
}
```

### 关掉某个邮件

```php
add_filter( 'woocommerce_email_enabled_customer_processing_order', '__return_false' );
```

---

## 17. 价格、货币与税

```php
// 格式化输出
echo wc_price( 199.5 );                          // ¥199.50
echo wc_price( 199.5, [ 'currency' => 'USD' ] );

// 获取应显示的价格（含税/不含税，按设置）
wc_get_price_to_display( $product );
wc_get_price_including_tax( $product );
wc_get_price_excluding_tax( $product );

// 货币
get_woocommerce_currency();              // 'CNY'
get_woocommerce_currency_symbol();       // '¥'
wc_get_price_decimals();
wc_get_price_decimal_separator();
wc_get_price_thousand_separator();

// 数字格式化
wc_format_decimal( '1,234.5678', 2 );    // '1234.57'
wc_format_localized_price( 1234.5 );
```

### 改价格显示

```php
add_filter( 'woocommerce_get_price_html', function( $html, $product ) {
    if ( ! $product->is_in_stock() ) {
        return '<span class="oos">暂时缺货</span>';
    }
    if ( $product->is_type('variable') ) {
        $min = $product->get_variation_price('min');
        return '<span class="from">起价 </span>' . wc_price( $min );
    }
    return $html;
}, 10, 2 );
```

### 动态改价格

```php
// 会员 9 折
add_action( 'woocommerce_before_calculate_totals', function( $cart ) {
    if ( is_admin() && ! defined('DOING_AJAX') ) return;
    if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) return;   // 防重复

    if ( ! current_user_can( 'vip_member' ) ) return;

    foreach ( $cart->get_cart() as $item ) {
        $item['data']->set_price( $item['data']->get_price() * 0.9 );
    }
}, 20 );
```

**`did_action()` 那行防护很重要**——这个钩子在一次请求里可能触发多次，不防护会重复打折。

---

## 18. 通知与会话

### 通知

```php
wc_add_notice( '添加成功！', 'success' );
wc_add_notice( '出错了。', 'error' );
wc_add_notice( '请注意。', 'notice' );

wc_print_notices();          // 输出并清空
wc_get_notices( 'error' );
wc_clear_notices();
wc_has_notice( '文本', 'error' );
```

在自定义模板里输出通知：

```php
do_action( 'woocommerce_before_shop_loop' );      // 包含 woocommerce_output_all_notices
// 或直接
wc_print_notices();
```

### 会话

```php
WC()->session->set( 'my_key', $value );
$value = WC()->session->get( 'my_key' );
WC()->session->__unset( 'my_key' );

// 会话 ID
WC()->session->get_customer_id();
```

**会话只在有购物车或明确初始化后才存在**。在早期钩子里 `WC()->session` 可能是 null：

```php
if ( ! WC()->session ) return;
// 或强制初始化
if ( ! WC()->session->has_session() ) {
    WC()->session->set_customer_session_cookie( true );
}
```

### 客户对象

```php
$customer = WC()->customer;
$customer->get_billing_country();
$customer->get_shipping_postcode();
$customer->set_shipping_country( 'CN' );
$customer->is_vat_exempt();

// 已注册用户
$customer = new WC_Customer( $user_id );
$customer->get_order_count();
$customer->get_total_spent();
$customer->get_last_order();
```

---

## 19. AJAX

### WC 专用端点（推荐，比 admin-ajax 快）

```php
// 注册
add_action( 'wc_ajax_my_action', 'my_ajax_handler' );          // 已登录
add_action( 'wc_ajax_nopriv_my_action', 'my_ajax_handler' );   // 未登录

function my_ajax_handler() {
    check_ajax_referer( 'my_nonce', 'nonce' );

    $product_id = absint( $_POST['product_id'] ?? 0 );

    wp_send_json_success([ 'html' => '...' ]);
}
```

请求 URL：

```php
echo WC_AJAX::get_endpoint( 'my_action' );
// → https://site.com/?wc-ajax=my_action
```

**为什么比 `admin-ajax.php` 快**：`wc-ajax` 端点不加载后台环境，省掉一大堆 `admin_init` 相关的钩子。

### 内置 AJAX 动作

```
get_refreshed_fragments      刷新小购物车
apply_coupon
remove_coupon
update_shipping_method
get_cart_totals
update_order_review          结算页金额刷新
add_to_cart
remove_from_cart
checkout
add_order_item
```

### AJAX 加购

WC 自带（归档页开启「AJAX 加入购物车」设置后）。手动触发：

```js
jQuery.post(
  wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'),
  { product_id: 123, quantity: 1 },
  function (response) {
    if (response.fragments) {
      jQuery.each(response.fragments, function (key, value) {
        jQuery(key).replaceWith(value);
      });
    }
    jQuery(document.body).trigger('wc_fragments_refreshed');
  }
);
```

### 常用 JS 事件

```js
jQuery(document.body).on('added_to_cart', function(e, fragments, hash, button) {});
jQuery(document.body).on('removed_from_cart', function() {});
jQuery(document.body).on('updated_cart_totals', function() {});
jQuery(document.body).on('update_checkout', function() {});
jQuery(document.body).on('updated_checkout', function() {});
jQuery(document.body).on('checkout_error', function() {});
jQuery(document.body).on('wc_fragments_refreshed', function() {});

// 变体商品
jQuery('.variations_form').on('found_variation', function(e, variation) {});
jQuery('.variations_form').on('reset_data', function() {});
```

**结算页的自定义 JS 要绑在 `updated_checkout` 上**——每次金额刷新 WC 都会重绘那块 DOM，绑在 `ready` 上的事件会失效。

---

## 20. REST API 与 Store API

### 管理 REST API（需认证）

```
/wp-json/wc/v3/products
/wp-json/wc/v3/products/{id}
/wp-json/wc/v3/products/{id}/variations
/wp-json/wc/v3/orders
/wp-json/wc/v3/customers
/wp-json/wc/v3/coupons
/wp-json/wc/v3/reports/sales
/wp-json/wc/v3/settings
```

认证：后台 WooCommerce → 设置 → 高级 → REST API 生成 Consumer Key/Secret。

```bash
curl https://site.com/wp-json/wc/v3/products \
  -u ck_xxxxx:cs_xxxxx
```

HTTPS 下用 Basic Auth，HTTP 下必须用 OAuth 1.0a。

```php
// PHP 里调用
$res = wp_remote_get( 'https://site.com/wp-json/wc/v3/products', [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode( $ck . ':' . $cs ),
    ],
]);
```

### Store API（前端用，无需认证）

给 WooCommerce Blocks 和 headless 前端用：

```
/wp-json/wc/store/v1/products
/wp-json/wc/store/v1/products/{id}
/wp-json/wc/store/v1/cart
/wp-json/wc/store/v1/cart/add-item
/wp-json/wc/store/v1/cart/update-item
/wp-json/wc/store/v1/cart/remove-item
/wp-json/wc/store/v1/cart/apply-coupon
/wp-json/wc/store/v1/checkout
```

```js
// 加入购物车
const res = await fetch('/wp-json/wc/store/v1/cart/add-item', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Nonce': wcStoreApiNonce,       // 从 wcSettings 拿
  },
  body: JSON.stringify({ id: 123, quantity: 1 }),
});
```

Store API 用 `Nonce` header（不是 `X-WP-Nonce`），响应头会返回新的 `Nonce`，要更新保存。

### 扩展 Store API

```php
add_action( 'woocommerce_blocks_loaded', function() {
    woocommerce_store_api_register_endpoint_data([
        'endpoint'        => Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema::IDENTIFIER,
        'namespace'       => 'my-plugin',
        'data_callback'   => function( $product ) {
            return [ 'custom_badge' => get_post_meta( $product->get_id(), '_badge', true ) ];
        },
        'schema_callback' => function() {
            return [ 'custom_badge' => [ 'type' => 'string', 'readonly' => true ] ];
        },
    ]);
});
```

---

## 21. WooCommerce Blocks

**重要变化**：WooCommerce 8.3+ 新装的店，购物车和结算页默认用**区块**（Cart Block / Checkout Block），不再是 `[woocommerce_cart]` 短代码。

### 这对主题开发的影响

| | 短代码（经典） | 区块 |
|---|---|---|
| 模板覆盖 | ✅ `cart/cart.php` 等 | ❌ 无效 |
| PHP 钩子 | ✅ 全部可用 | ❌ 大部分无效 |
| 定制方式 | PHP | JS / Store API / 区块过滤器 |

**如果你的定制依赖 PHP 钩子**（比如 `woocommerce_review_order_before_submit`），在区块结算页上**不会生效**。

### 判断当前用的哪个

```php
function my_is_block_checkout() {
    $page_id = wc_get_page_id( 'checkout' );
    if ( $page_id <= 0 ) return false;
    $post = get_post( $page_id );
    return $post && has_block( 'woocommerce/checkout', $post );
}
```

### 切回经典结算页

如果项目重度依赖 PHP 钩子，最省事的做法是把结算页内容换成短代码：

```
页面编辑器 → 删除结算区块 → 插入短代码区块 → [woocommerce_checkout]
```

购物车同理：`[woocommerce_cart]`

### 区块的定制方式

```js
// 注册结算页自定义字段（WC 8.9+ 有官方 API）
import { registerCheckoutFilters } from '@woocommerce/blocks-checkout';

registerCheckoutFilters('my-namespace', {
  cartItemPrice: (value, extensions, args) => value,
  itemName: (value) => value,
});
```

或者用 PHP 注册额外结算字段（WC 8.9+）：

```php
add_action( 'woocommerce_init', function() {
    woocommerce_register_additional_checkout_field([
        'id'       => 'my-plugin/wechat',
        'label'    => '微信号',
        'location' => 'contact',      // contact / address / order
        'type'     => 'text',
        'required' => false,
    ]);
});
```

**做客户站时，先确认用哪个模式再报价**——区块结算页的深度定制成本比经典模式高不少。

---

## 22. ACF 与 WooCommerce 集成

### 给商品加 ACF 字段

字段组 Location 规则：

```
Post Type == Product
```

或按商品分类：

```
Post Type == Product
AND Product Category == 特定分类
```

读取：

```php
global $product;
$care = get_field( 'care_instructions', $product->get_id() );
```

**ACF 字段会显示在商品编辑页的正文下方**，不在「商品数据」面板里。想放进去需要用原生的 `woocommerce_product_options_*` 钩子（见第 6 节）。

### 给商品分类加字段

```
Taxonomy == Product Category
```

```php
$term = get_queried_object();                     // 分类归档页
$banner = get_field( 'category_banner', $term );  // 直接传 term 对象

// 或
$banner = get_field( 'category_banner', 'product_cat_' . $term_id );
```

### 用 Flexible Content 做商品页模块

和你现有的 builder 工作流一致：

```php
// 覆盖 woocommerce/single-product/tabs/tabs.php
// 或挂在 woocommerce_after_single_product_summary

add_action( 'woocommerce_after_single_product_summary', 'my_product_builder', 25 );
function my_product_builder() {
    global $product;
    $id = $product->get_id();

    if ( have_rows( 'builder', $id ) ) :
        while ( have_rows( 'builder', $id ) ) : the_row();
            get_template_part( 'modules/' . get_row_layout() );
        endwhile;
    endif;
}
```

注意 `have_rows()` 要显式传 `$id`——在 WC 钩子里全局 `$post` 不一定是当前商品。

### 给订单加 ACF 字段

**ACF 对 HPOS 的支持有限**。HPOS 开启后，订单不是 post，ACF 的字段组绑不上。安全做法是用订单 CRUD：

```php
$order->update_meta_data( '_my_field', $value );
$order->save();
$value = $order->get_meta( '_my_field' );
```

后台展示自己写 metabox（见第 11 节）。

### 选项页存全局商城设置

```php
$free_shipping_note = get_field( 'free_shipping_note', 'options' );
```

---

## 23. 静态设计稿 → WooCommerce 主题

结合你的工作流，落地步骤：

### 第 1 步：确认要做哪些页面

WooCommerce 站至少这些：

```
商店/归档页      archive-product.php  + content-product.php
单品页          single-product.php   + 各 summary 片段
购物车          cart/cart.php
结算页          checkout/form-checkout.php
感谢页          checkout/thankyou.php
我的账户        myaccount/*
小购物车        cart/mini-cart.php
```

**先和客户确认购物车/结算用经典还是区块**（见第 21 节）——这决定了工作量。

### 第 2 步：定策略——钩子优先

拿到设计稿，先看能不能只靠钩子实现：

| 设计需求 | 实现方式 |
|---|---|
| 元素换顺序 | `remove_action` + `add_action` 改优先级 |
| 加一块新内容 | `add_action` 到合适的钩子 |
| 删掉某块 | `remove_action` |
| 商品卡片结构完全不同 | 覆盖 `content-product.php` |
| 单品页布局完全重排 | 覆盖 `content-single-product.php` |
| 购物车表格变卡片式 | 覆盖 `cart/cart.php` |

**只在结构真的对不上时才覆盖模板。**

### 第 3 步：商品卡片（最高频）

覆盖 `content-product.php`（示例见第 8 节）。要点：

- 保留 `wc_product_class()`
- 保留 `<li>` 标签（WC 的 `loop-start.php` 输出 `<ul class="products">`）
- 想改成 `<div>` 就同时覆盖 `loop/loop-start.php` 和 `loop-end.php`

### 第 4 步：CSS 策略

WC 自带三个样式表：

```
woocommerce-layout.css      布局
woocommerce-smallscreen.css 响应式
woocommerce.css             通用样式
```

自定义主题通常全部关掉，自己写：

```php
add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );
```

或只保留一部分：

```php
add_filter( 'woocommerce_enqueue_styles', function( $styles ) {
    unset( $styles['woocommerce-layout'] );
    unset( $styles['woocommerce-smallscreen'] );
    return $styles;    // 保留 woocommerce-general
});
```

**注意**：全关掉之后，一些交互组件的样式要自己补——变体下拉、数量加减、通知条、星级评分、选项卡。别忘了这些。

### 第 5 步：必须保留的 class 和结构

WC 的 JS 依赖这些，改了会坏：

```
.variations_form        变体表单（JS 绑定在这）
.single_add_to_cart_button
.qty                    数量输入框
.woocommerce-notices-wrapper
.cart_item
.shop_table
.woocommerce-cart-form
.checkout                结算表单
.woocommerce-checkout-review-order
input[name="update_cart"]
```

**改结构前先在浏览器里搜一下这个 class 有没有被 WC 的 JS 用到**。

### 第 6 步：把 ACF builder 接进来

商品页、商店页也可以用你的模块化 builder（见第 22 节）。常见做法：

- 商店页顶部：ACF 选项页 或 分类的 ACF 字段 → 一个 banner 模块
- 单品页底部：商品的 ACF Flexible Content → 详情模块

### 常见坑

| 坑 | 说明 |
|---|---|
| 覆盖了模板但没生效 | 目录名必须是 `woocommerce/`，路径要和插件里完全一致 |
| 改了 `content-product.php` 但 AJAX 加购坏了 | 丢了 `wc_product_class()` 或按钮 class |
| 结算页 JS 只生效一次 | 要绑 `updated_checkout` 事件 |
| 关掉 WC 样式后变体下拉很丑 | 需要自己写这部分样式 |
| 区块结算页上 PHP 钩子不生效 | 换回短代码，或用区块 API |
| 商品图尺寸不对 | 改完设置要 `wp media regenerate` |
| 自定义模板版本过期警告 | 后台 状态 → 模板覆盖 会提示，对照官方新版更新 |

---

## 24. 性能优化

WooCommerce 站慢是常态，主要瓶颈：

### ① 不缓存的页面

购物车、结算、我的账户**不能做整页缓存**（内容因人而异）。缓存插件要排除：

```
/cart/
/checkout/
/my-account/
?wc-ajax=*
```

Cookie 排除：`woocommerce_cart_hash`、`woocommerce_items_in_cart`、`wp_woocommerce_session_*`

### ② 购物车片段 AJAX

每个页面加载都会发一个 `?wc-ajax=get_refreshed_fragments` 请求。流量大时这是很大的负担。

```php
// 非商城页面禁用
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
        wp_dequeue_script( 'wc-cart-fragments' );
    }
}, 99 );
```

⚠️ 禁用后小购物车数量不会实时更新。如果头部有购物车图标显示数量，要么保留，要么改成页面加载时渲染 + 加购后手动更新。

### ③ 只在需要的页面加载 WC 资源

```php
add_action( 'wp_enqueue_scripts', function() {
    if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) return;

    wp_dequeue_style( 'woocommerce-general' );
    wp_dequeue_style( 'woocommerce-layout' );
    wp_dequeue_style( 'woocommerce-smallscreen' );
    wp_dequeue_script( 'woocommerce' );
    wp_dequeue_script( 'wc-cart-fragments' );
    wp_dequeue_script( 'wc-add-to-cart' );
}, 99 );
```

### ④ 数据库

```bash
# 重建查找表
wp wc tool run regenerate_product_lookup_tables --user=1

# 清理过期会话
wp wc tool run clear_sessions --user=1

# 清理孤立变体
wp wc tool run delete_orphaned_variations --user=1
```

定期清理：`wp_woocommerce_sessions`、过期 transient、`wp_actionscheduler_actions` 里的已完成记录（这张表最容易失控，几十万行很常见）。

### ⑤ 查询

```php
// ❌ 慢
$products = wc_get_products([ 'limit' => -1 ]);

// ✅ 只要 ID
$ids = wc_get_products([ 'limit' => 100, 'return' => 'ids' ]);

// ❌ 变体商品循环里调这个非常慢
$product->get_available_variations();

// ✅
$product->get_children();
```

### ⑥ 关掉不用的功能

```php
// 关掉营销中心和后台通知
add_filter( 'woocommerce_admin_disabled', '__return_true' );

// 关掉 WC Analytics（大站后台会很慢）
add_filter( 'woocommerce_analytics_enabled', '__return_false' );

// 关掉小部件
remove_action( 'widgets_init', 'woocommerce_register_widgets' );
```

---

## 25. 常见需求配方集

### 隐藏缺货商品

```php
add_filter( 'woocommerce_product_query_meta_query', function( $meta_query, $query ) {
    $meta_query[] = [
        'key'     => '_stock_status',
        'value'   => 'outofstock',
        'compare' => '!=',
    ];
    return $meta_query;
}, 10, 2 );
```

（后台「WooCommerce → 设置 → 产品 → 库存 → 缺货时隐藏」也能实现）

### 商品最低起订量

```php
add_filter( 'woocommerce_quantity_input_args', function( $args, $product ) {
    $min = get_post_meta( $product->get_id(), '_min_qty', true );
    if ( $min ) {
        $args['min_value']   = $min;
        $args['input_value'] = max( $args['input_value'], $min );
    }
    return $args;
}, 10, 2 );
```

### 满额包邮提示条

```php
add_action( 'woocommerce_before_cart', function() {
    $threshold = 500;
    $subtotal  = WC()->cart->get_displayed_subtotal();

    if ( $subtotal < $threshold ) {
        $diff = $threshold - $subtotal;
        wc_print_notice(
            sprintf( '再买 %s 即可免运费！', wc_price( $diff ) ),
            'notice'
        );
    } else {
        wc_print_notice( '已享受免运费 🎉', 'success' );
    }
});
```

### 结算页加复选框（同意条款）

```php
add_action( 'woocommerce_review_order_before_submit', function() {
    woocommerce_form_field( 'agree_terms', [
        'type'     => 'checkbox',
        'class'    => [ 'form-row-wide' ],
        'label'    => '我已阅读并同意服务条款',
        'required' => true,
    ], WC()->checkout->get_value( 'agree_terms' ) );
});

add_action( 'woocommerce_checkout_process', function() {
    if ( empty( $_POST['agree_terms'] ) ) {
        wc_add_notice( '请先同意服务条款。', 'error' );
    }
});
```

### 订单加快递单号

```php
// 后台字段
add_action( 'woocommerce_admin_order_data_after_shipping_address', function( $order ) {
    woocommerce_wp_text_input([
        'id'    => '_tracking_number',
        'label' => '快递单号',
        'value' => $order->get_meta( '_tracking_number' ),
        'wrapper_class' => 'form-field-wide',
    ]);
});

// 保存
add_action( 'woocommerce_process_shop_order_meta', function( $order_id ) {
    if ( ! isset( $_POST['_tracking_number'] ) ) return;
    $order = wc_get_order( $order_id );
    $order->update_meta_data( '_tracking_number',
        sanitize_text_field( $_POST['_tracking_number'] ) );
    $order->save();
});

// 前台「查看订单」页显示
add_action( 'woocommerce_order_details_after_order_table', function( $order ) {
    $t = $order->get_meta( '_tracking_number' );
    if ( $t ) echo '<p><strong>快递单号：</strong>' . esc_html( $t ) . '</p>';
});
```

### 按用户角色显示不同价格

```php
add_filter( 'woocommerce_product_get_price', 'my_role_price', 10, 2 );
add_filter( 'woocommerce_product_variation_get_price', 'my_role_price', 10, 2 );

function my_role_price( $price, $product ) {
    if ( is_admin() && ! wp_doing_ajax() ) return $price;
    if ( current_user_can( 'wholesale' ) ) {
        return (float) $price * 0.8;
    }
    return $price;
}

// 价格 HTML 也要处理，否则显示的还是原价
add_filter( 'woocommerce_get_price_html', function( $html, $product ) {
    if ( current_user_can( 'wholesale' ) ) {
        return wc_price( $product->get_price() ) . ' <small>批发价</small>';
    }
    return $html;
}, 10, 2 );
```

### 移除面包屑 / 改分隔符

```php
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

add_filter( 'woocommerce_breadcrumb_defaults', function( $args ) {
    $args['delimiter']   = ' / ';
    $args['wrap_before'] = '<nav class="breadcrumb">';
    $args['wrap_after']  = '</nav>';
    $args['home']        = '首页';
    return $args;
});
```

### 相关商品数量和列数

```php
add_filter( 'woocommerce_output_related_products_args', function( $args ) {
    $args['posts_per_page'] = 4;
    $args['columns']        = 4;
    return $args;
}, 20 );
```

### 分类页显示子分类

```php
add_action( 'woocommerce_before_shop_loop', function() {
    if ( ! is_product_category() ) return;

    $term = get_queried_object();
    $children = get_terms([
        'taxonomy'   => 'product_cat',
        'parent'     => $term->term_id,
        'hide_empty' => true,
    ]);

    if ( empty( $children ) ) return;

    echo '<ul class="subcategory-filter">';
    foreach ( $children as $child ) {
        printf( '<li><a href="%s">%s</a></li>',
            esc_url( get_term_link( $child ) ),
            esc_html( $child->name )
        );
    }
    echo '</ul>';
}, 15 );
```

---

## 26. 调试

### 内置工具

**WooCommerce → 状态**
- 系统状态报告（提交 bug 时要附上）
- 模板覆盖列表 + 版本过期提示 ★
- 工具页：清缓存、重建查找表、清会话

**WooCommerce → 状态 → 日志**

```php
$logger = wc_get_logger();
$logger->info( '消息', [ 'source' => 'my-plugin' ] );
$logger->error( '出错了', [ 'source' => 'my-plugin' ] );
$logger->debug( print_r( $data, true ), [ 'source' => 'my-plugin' ] );
```

级别：`emergency` `alert` `critical` `error` `warning` `notice` `info` `debug`

日志文件在 `wp-content/uploads/wc-logs/`。**做支付网关时这是唯一靠谱的调试手段**（回调是服务器对服务器，你看不到浏览器）。

### 查钩子挂了什么

```php
add_action( 'wp_footer', function() {
    if ( ! current_user_can('manage_options') ) return;
    global $wp_filter;
    echo '<pre style="background:#000;color:#0f0;font-size:11px">';
    foreach ( $wp_filter['woocommerce_single_product_summary']->callbacks as $prio => $cbs ) {
        foreach ( $cbs as $cb ) {
            $name = is_string( $cb['function'] ) ? $cb['function'] : 'closure/method';
            echo "$prio  $name\n";
        }
    }
    echo '</pre>';
});
```

### 查当前用了哪个模板

Query Monitor 装上后有 "Template" 面板。或者：

```php
add_filter( 'wc_get_template', function( $located, $template_name ) {
    if ( current_user_can('manage_options') ) {
        error_log( "WC 模板: {$template_name} → {$located}" );
    }
    return $located;
}, 10, 2 );
```

### 常见问题

| 现象 | 排查 |
|---|---|
| 模板覆盖不生效 | 目录必须叫 `woocommerce/`；路径要和插件内完全一致；子主题优先 |
| `remove_action` 无效 | 优先级不匹配；或执行太晚（包进 `init`） |
| HPOS 下拿不到订单数据 | 用了 `get_post_meta`，改成 `$order->get_meta()` |
| 订单查询返回空 | 用了 `WP_Query('shop_order')`，改成 `wc_get_orders()` |
| 价格改了前台没变 | 只改了 `_regular_price` 没同步 `_price`；用 CRUD 的 `save()` |
| 变体价格区间不对 | 需要 `WC_Product_Variable::sync( $product_id )` |
| 运费改了不生效 | 运费有 transient 缓存，清购物车或改 version |
| 结算页自定义 JS 失效 | 绑到 `updated_checkout` 事件 |
| 区块结算页钩子不触发 | 区块不走 PHP 钩子，换短代码或用区块 API |
| AJAX 加购坏了 | 商品卡片丢了 `wc_product_class()` 或按钮 class |
| 后台很慢 | `wp_actionscheduler_actions` 表膨胀；关掉 Analytics |
| 缩略图尺寸不对 | `wp media regenerate --yes` |

### WP-CLI

```bash
wp wc --help
wp wc product list --user=1
wp wc product create --name="测试" --type=simple --regular_price=99 --user=1
wp wc order list --user=1
wp wc tool run regenerate_product_lookup_tables --user=1
wp wc tool run clear_sessions --user=1

# Action Scheduler
wp action-scheduler run
wp action-scheduler clean
```

**WC 的 CLI 命令基本都要加 `--user=1`**（需要一个有权限的用户上下文）。

---

## 27. 速查附录

### 条件标签

```php
is_woocommerce();          // 商店/分类/单品 任一
is_shop();                 // 商店主页
is_product_category();
is_product_tag();
is_product();              // 单品页
is_product_taxonomy();
is_cart();
is_checkout();
is_checkout_pay_page();
is_order_received_page();  // 感谢页
is_account_page();
is_wc_endpoint_url( 'orders' );
is_store_notice_showing();
```

### 常用函数

```php
// 商品
wc_get_product( $id );
wc_get_products( $args );
wc_get_product_id_by_sku( $sku );
wc_get_related_products( $id, $limit );
wc_update_product_stock( $id, $qty, 'set' );

// 订单
wc_get_order( $id );
wc_get_orders( $args );
wc_create_order( $args );
wc_get_order_statuses();
wc_get_is_paid_statuses();

// 购物车
WC()->cart->add_to_cart( $id );
wc_get_cart_url();
wc_get_checkout_url();
wc_empty_cart();

// 价格
wc_price( $amount );
wc_format_decimal( $val, $dp );
get_woocommerce_currency_symbol();
wc_get_price_to_display( $product );

// 页面
wc_get_page_id( 'shop' );        // shop/cart/checkout/myaccount/terms
wc_get_page_permalink( 'shop' );

// 通知
wc_add_notice( $msg, $type );
wc_print_notices();
wc_clear_notices();

// 模板
wc_get_template( $name, $args );
wc_get_template_part( $slug, $name );
wc_get_template_html( $name, $args );

// 工具
wc_clean( $var );                // 递归 sanitize
wc_string_to_bool( 'yes' );      // true
wc_bool_to_string( true );       // 'yes'
wc_get_logger();
wc_doing_it_wrong();
WC()->api_request_url( $gateway_id );
WC_AJAX::get_endpoint( $action );
```

### 表单字段辅助函数

```php
// 前台
woocommerce_form_field( $key, $args, $value );

// 后台商品面板
woocommerce_wp_text_input( $args );
woocommerce_wp_textarea_input( $args );
woocommerce_wp_select( $args );
woocommerce_wp_checkbox( $args );
woocommerce_wp_radio( $args );
woocommerce_wp_hidden_input( $args );
woocommerce_wp_note( $args );
```

### 全局对象

```php
WC();                  // WooCommerce 主实例
WC()->cart;            // WC_Cart
WC()->customer;        // WC_Customer
WC()->session;         // WC_Session
WC()->checkout();      // WC_Checkout
WC()->countries;       // WC_Countries
WC()->mailer();        // WC_Emails
WC()->query;           // WC_Query
WC()->shipping();      // WC_Shipping
WC()->payment_gateways();

global $product;       // 循环和单品页中的当前商品
global $woocommerce;   // 旧写法，等同 WC()
```

---

## 学习路径

```
第 1 步   第 1、2、3 章
          └─ 理解 CRUD 和 HPOS。不理解这两个，写出来的代码在新站上会直接坏

第 2 步   第 4、5 章 ★ 核心
          └─ 通读 includes/wc-template-hooks.php，对照第 5 章的钩子全图
             这一步做扎实，后面 80% 的需求都是查表

第 3 步   第 6、8、9 章
          └─ 商品和展示层，你做主题最常碰的部分

第 4 步   第 23 章
          └─ 把设计稿落地的完整流程

第 5 步   第 10-13 章
          └─ 购物车/结算/订单，做完整商城时必须

按需      第 14-21 章（支付、配送、邮件、API、区块）
          第 25 章（配方集，遇到需求直接查）
```

### 官方资源

- [WooCommerce 开发者文档](https://developer.woocommerce.com/docs/)
- [代码参考（函数/钩子/类）](https://woocommerce.github.io/code-reference/)
- [钩子索引](https://woocommerce.github.io/code-reference/hooks/hooks.html)
- [模板结构](https://woocommerce.com/document/template-structure/)
- [HPOS 说明](https://developer.woocommerce.com/docs/hpos/)
- [Store API 文档](https://developer.woocommerce.com/docs/apis/store-api/)
- [REST API 文档](https://woocommerce.github.io/woocommerce-rest-api-docs/)

### 本地必读的两个文件

```
plugins/woocommerce/includes/wc-template-hooks.php       所有默认钩子挂载
plugins/woocommerce/includes/wc-template-functions.php   所有模板函数实现
```

遇到「这块内容是哪来的」，在第一个文件里搜钩子名，在第二个文件里看实现。比查在线文档快得多。
