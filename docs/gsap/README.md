# GSAP 动画

三份，按顺序读。

| 文件 | 内容 |
|---|---|
| [`gsap-core.md`](gsap-core.md) | GSAP 本体，26 节：补间、Timeline 与位置参数、缓动、stagger、utils、matchMedia、context、性能、无障碍、SVG/SplitText/Flip/Observer、框架集成、排错 |
| [`scrolltrigger.md`](scrolltrigger.md) | ScrollTrigger，12 节：start/end、scrub、pin、batch、matchMedia、配合 Lenis、坑与排错、实战片段 |
| [`demo.html`](demo.html) | 8 个可跑的场景，单文件、只依赖 CDN |

## demo 怎么跑

```bash
# 直接双击打开就行，或者起个本地服务
python3 -m http.server 8080
# 然后开 http://localhost:8080/docs/gsap/demo.html
```

里面 8 段，代码和文档里的写法一一对应：

1. Hero 时间轴进场
2. 逐个 reveal
3. `ScrollTrigger.batch()`
4. `scrub` 滚动条驱动进度
5. `pin` 钉住
6. 横向滚动
7. `pinSpacing: false` 卡片堆叠
8. 数字滚动计数

## 版本说明

**2025 年 4 月起 GSAP 及全部官方插件对所有人免费**，包括商业项目，不再需要 Club 会员。

网上 2024 年之前的教程里「SplitText / MorphSVG / ScrollSmoother 要付费」的说法已经全部作废——遇到这类说法直接忽略，但要留意那些教程的**代码**可能也是旧版本的。

两份文档都按 **GSAP 3.13** 写。CDN 引用一律写死版本号，别用 `@latest`。

## 和主题的关系

`starter-theme` 没有预置 GSAP。要用的话在 `functions/assets.php` 里加，依赖数组要写对：

```php
wp_enqueue_script( 'gsap', '…/gsap.min.js', [], '3.13.0', true );
wp_enqueue_script( 'gsap-scrolltrigger', '…/ScrollTrigger.min.js', [ 'gsap' ], '3.13.0', true );
wp_enqueue_script( 'theme-anim', get_theme_file_uri( 'assets/js/anim.js' ), [ 'gsap-scrolltrigger' ], null, true );
```

`gsap-core.md` 第 2 节有完整版。开局的项目模板在第 26 节末尾。
