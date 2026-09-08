# GSAP 核心完整教程

> 适用版本：GSAP 3.13+
> **2025 年 4 月起 GSAP 及全部官方插件对所有人免费**，包括商业项目，不再需要 Club 会员——网上 2024 年之前的教程里「这个插件要付费」的说法已经全部作废
> 配套阅读：[`scrolltrigger.md`](scrolltrigger.md)、[`demo.html`](demo.html)

这份文档只讲 GSAP 本体（补间、时间轴、缓动、工具集）。滚动驱动的部分全在 `scrolltrigger.md` 里。

---

## 目录

1. [为什么用 GSAP](#1-为什么用-gsap)
2. [引入与注册](#2-引入与注册)
3. [四个基本方法：to / from / fromTo / set](#3-四个基本方法to--from--fromto--set)
4. [能动什么：属性一览](#4-能动什么属性一览)
5. [值的写法：单位、相对值、函数式、随机](#5-值的写法单位相对值函数式随机)
6. [缓动 ease](#6-缓动-ease)
7. [stagger：错峰](#7-stagger错峰)
8. [keyframes：多段关键帧](#8-keyframes多段关键帧)
9. [Timeline：时间轴](#9-timeline时间轴)
10. [位置参数：时间轴里最容易错的地方](#10-位置参数时间轴里最容易错的地方)
11. [控制动画：play / reverse / seek / timeScale](#11-控制动画play--reverse--seek--timescale)
12. [回调与事件](#12-回调与事件)
13. [gsap.utils 工具集](#13-gsaputils-工具集)
14. [gsap.matchMedia：响应式](#14-gsapmatchmedia响应式)
15. [gsap.context：作用域与清理](#15-gsapcontext作用域与清理)
16. [defaults 与 registerEffect](#16-defaults-与-registereffect)
17. [性能](#17-性能)
18. [无障碍：prefers-reduced-motion](#18-无障碍prefers-reduced-motion)
19. [SVG 动画](#19-svg-动画)
20. [文字动画：SplitText](#20-文字动画splittext)
21. [Flip：布局变化动画](#21-flip布局变化动画)
22. [Observer：统一的输入监听](#22-observer统一的输入监听)
23. [和框架集成](#23-和框架集成)
24. [调试](#24-调试)
25. [常见坑](#25-常见坑)
26. [速查附录](#26-速查附录)

---

## 1. 为什么用 GSAP

CSS 动画和 Web Animations API 都能做动画。GSAP 值得引入的场景是这几个：

| 需求 | CSS | WAAPI | GSAP |
|---|---|---|---|
| 简单的 hover / 淡入 | ✅ 最合适 | 可以 | 杀鸡用牛刀 |
| 精确编排的多元素序列 | ❌ 靠 `animation-delay` 硬拼 | 勉强 | ✅ Timeline |
| 中途改变目标值 | ❌ | 麻烦 | ✅ |
| 动画进度受滚动/拖拽驱动 | ❌ | ❌ | ✅ |
| 动画 SVG 的 `d`、`stroke-dashoffset` | 部分 | 部分 | ✅ |
| 跨浏览器一致的 transform 行为 | ⚠️ 有差异 | ⚠️ | ✅ 统一处理 |
| 动到一半反向、暂停、变速 | ❌ | 有限 | ✅ |

**判断标准**：如果动画是「一个元素、一次性、状态切换」，CSS transition 就够了，别引 GSAP（一个 gzip 后 ~24KB 的库）。**需要编排、需要控制、需要被外部进度驱动的时候，才是 GSAP 的地盘。**

### GSAP 相比 CSS 的一个隐性优势

CSS 的 `transform` 是一个整体属性：

```css
.el { transform: translateX(100px); }
.el.rotated { transform: rotate(45deg); }   /* ← 位移被抹掉了 */
```

两个人写两条规则动同一个元素的 `transform`，后写的会**静默删掉**前一条的位移。这在多人协作或「主题 + 插件」这种分层场景里是高频事故。

GSAP 把 `x` / `y` / `rotation` / `scale` 当成独立属性管理，各写各的互不干扰：

```js
gsap.to(el, { x: 100 });
gsap.to(el, { rotation: 45 });   // 位移还在
```

> ⚠️ 反过来也成立：**GSAP 动过 transform 的元素，你在 CSS 里写的 `transform` 会被覆盖**。如果某个元素本来靠 `transform: translate(-50%,-50%)` 做居中，GSAP 一动它就会跳位——用 `xPercent: -50, yPercent: -50` 交给 GSAP 管，别混用。

---

## 2. 引入与注册

### CDN

```html
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"></script>
<!-- 需要哪个插件就加哪个，每个都是独立文件 -->
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js"></script>
<script>
  gsap.registerPlugin(ScrollTrigger);
</script>
```

**版本号写死**，不要用 `@latest`——大版本升级会破坏行为，而你不会知道是哪天变的。

### npm

```bash
npm install gsap
```

```js
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

gsap.registerPlugin(ScrollTrigger);
```

> **必须 `registerPlugin`**。少了这句，打包工具的 tree-shaking 会把插件摇掉，运行时报 `ScrollTrigger is not defined`，或者 `scrollTrigger` 配置被静默忽略（动画立刻播完，看起来像"没有滚动触发"）。

### WordPress 主题里

```php
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script(
		'gsap',
		'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js',
		[],
		'3.13.0',
		true
	);
	wp_enqueue_script(
		'gsap-scrolltrigger',
		'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js',
		[ 'gsap' ],          // ★ 依赖声明，保证顺序
		'3.13.0',
		true
	);
	wp_enqueue_script(
		'theme-anim',
		get_theme_file_uri( 'assets/js/anim.js' ),
		[ 'gsap-scrolltrigger' ],
		null,
		true
	);
} );
```

依赖数组是关键——`wp_enqueue_script` 靠它排序。少写了的话，输出顺序取决于注册顺序，很容易变成 `anim.js` 在 GSAP 之前。

自托管的话把三个文件放 `assets/js/vendor/`，用 `get_theme_file_uri()` 引，别依赖第三方 CDN 的可用性。

---

## 3. 四个基本方法：to / from / fromTo / set

这四个覆盖 95% 的用法。

```js
// to —— 从当前状态动到指定值（最常用）
gsap.to(".box", { x: 200, duration: 1 });

// from —— 从指定值动到当前状态（进场动画）
gsap.from(".box", { y: 50, opacity: 0, duration: 1 });

// fromTo —— 起点终点都明确指定
gsap.fromTo(".box",
  { y: 50, opacity: 0 },        // from
  { y: 0,  opacity: 1, duration: 1 }   // to（duration 等配置写在这一侧）
);

// set —— 立即赋值，不做动画（等价于 duration: 0）
gsap.set(".box", { x: 0, opacity: 0 });
```

### to 和 from 的选择

| | `from` | `fromTo` |
|---|---|---|
| 终点 | 元素在 CSS 里的当前值 | 显式写死 |
| 优点 | CSS 改了动画自动跟着改 | 结果可预测 |
| 风险 | **重复执行时会出问题**（见下） | 无 |

**`from` 的重复执行陷阱**：

```js
// 假设 .box 的 CSS 是 opacity: 1
gsap.from(".box", { opacity: 0, duration: 1 });   // 0 → 1，正确

// 如果这段代码因为某种原因跑了第二次，
// 此时元素的"当前值"仍是 1，所以还是 0 → 1，没问题。

// 但如果第一次动画还没跑完就再跑一次：
// 当前值是 0.4，于是变成 0 → 0.4，元素永远到不了 1
```

**任何可能重复触发的地方（resize 回调、路由切换、ScrollTrigger 的 `toggleActions` 重播）都用 `fromTo`**，把两端都钉死。

### 一个高频的 FOUC 问题

用 `from` 做进场动画时，**JS 执行之前元素是按 CSS 的最终状态渲染的**——用户会先看到它，然后它突然跳回起点开始动。

三种解法，按推荐度：

```css
/* 方案 A：CSS 写初始态，JS 用 to（最稳） */
.reveal { opacity: 0; transform: translateY(50px); }
```
```js
gsap.to(".reveal", { opacity: 1, y: 0, duration: 0.8 });
```

```css
/* 方案 B：先整体藏起来，JS 就绪后放出来 */
.reveal { visibility: hidden; }
.gsap-ready .reveal { visibility: visible; }
```
```js
document.documentElement.classList.add("gsap-ready");
gsap.from(".reveal", { y: 50, opacity: 0 });
```

```js
// 方案 C：立刻 set 起始态（JS 必须在元素之后同步执行，否则还是会闪）
gsap.set(".reveal", { opacity: 0, y: 50 });
gsap.to(".reveal", { opacity: 1, y: 0 });
```

**方案 A 最可靠**，因为它不依赖 JS 的执行时机。代价是「动画的起点」写在 CSS 里、「终点」写在 JS 里，两边要对着改。

> ⚠️ 方案 A 有个连带的坑：**别把 `transition` 也写在那条初始态规则里**。加类进入起始态的那一瞬间会被浏览器当成动画播出来，元素还没沉到位就开始跳，位移看起来只剩一小截。`transition` 要写在「元素平时的样子」那条规则上，不是「停放态」那条。

---

## 4. 能动什么：属性一览

### Transform（最常用，也最该用）

GSAP 把 CSS 的 transform 拆成了独立属性：

| GSAP 属性 | 对应 CSS | 说明 |
|---|---|---|
| `x` / `y` / `z` | `translate3d()` | 数字按 px，字符串可带单位 |
| `xPercent` / `yPercent` | `translate()` 的百分比 | **相对元素自身尺寸**，做居中用这个 |
| `rotation` | `rotate()` | 单位是**度**，不用写 `deg` |
| `rotationX` / `rotationY` | 3D 旋转 | |
| `scale` | `scale()` | |
| `scaleX` / `scaleY` | | |
| `skewX` / `skewY` | `skew()` | 度 |
| `transformOrigin` | `transform-origin` | `"50% 50%"` / `"top left"` |
| `transformPerspective` | `perspective()` | |

```js
// 居中并放大：xPercent/yPercent 和 x/y 是叠加的，不冲突
gsap.set(el, { xPercent: -50, yPercent: -50 });
gsap.to(el, { scale: 1.2, x: 100 });   // 仍然保持居中偏移
```

### 其他常见属性

```js
gsap.to(el, {
  opacity: 0.5,
  autoAlpha: 0,          // ★ opacity + visibility，到 0 时自动 visibility:hidden
  backgroundColor: "#ff5a3c",
  color: "rgb(255,255,255)",
  width: 300,            // ⚠️ 触发布局重算，能用 scaleX 就别用它
  height: "50vh",
  borderRadius: "50%",
  boxShadow: "0 20px 40px rgba(0,0,0,.3)",
  filter: "blur(10px)",
  backdropFilter: "blur(8px)",
});
```

**`autoAlpha` 值得单独记**：它在 opacity 到 0 时顺手加上 `visibility: hidden`，元素就不再接收鼠标事件、也不进无障碍树。纯 `opacity: 0` 的元素是"看不见但还挡着"的，这是很多"按钮点不动"的成因。

### 动 CSS 变量

```js
gsap.to(":root", { "--accent": "#ff5a3c", duration: 1 });
gsap.to(el, { "--progress": 1, duration: 2 });
```

CSS 变量默认不能补间（浏览器不知道它是数字还是颜色）。GSAP 能动，但**如果你要在 CSS 里对它做计算，用 `@property` 声明类型会更稳**：

```css
@property --progress {
  syntax: '<number>';
  initial-value: 0;
  inherits: false;
}
```

### 动任意 JS 对象

GSAP 不限于 DOM——任何有数字属性的对象都能动。这是做数字滚动、Canvas、Three.js 的基础：

```js
const state = { n: 0 };

gsap.to(state, {
  n: 1280,
  duration: 2,
  ease: "power2.out",
  onUpdate() {
    el.textContent = Math.round(state.n).toLocaleString();
  },
});
```

### 不该动的属性

| 属性 | 为什么 | 用什么代替 |
|---|---|---|
| `top` / `left` / `right` / `bottom` | 触发 layout，每帧重排 | `x` / `y` |
| `width` / `height` | 同上 | `scaleX` / `scaleY`（注意会连子元素一起缩） |
| `margin` / `padding` | 同上 | 尽量改用 transform |
| `filter: blur()` | 每帧重新光栅化，非常贵 | 能用静态图就用图 |

> ⚠️ **`filter` 会跟着 `transform` 一起缩放**。一个 `scale(0.3)` 的元素上，`blur(20px)` 实际看起来只有 6px，边缘会发硬。缩放动画里的模糊要么写成函数式值反向补偿，要么别缩它。

---

## 5. 值的写法：单位、相对值、函数式、随机

### 单位

```js
gsap.to(el, {
  x: 100,          // 数字 → px
  x: "100px",
  x: "50%",        // ★ 百分比相对【元素自身】宽度（不是父元素！）
  x: "10vw",
  rotation: 45,    // 度，不写 deg
  duration: 1.5,   // 秒，不是毫秒
});
```

**`x: "50%"` 的坑**：它等价于 `xPercent: 50`，参照的是元素自己的宽度。想相对父元素定位，自己算：

```js
gsap.to(el, { x: () => el.parentElement.offsetWidth * 0.5 });
```

### 相对值

```js
gsap.to(el, { x: "+=100" });      // 在当前值基础上加 100
gsap.to(el, { x: "-=50" });
gsap.to(el, { rotation: "+=360" });   // 转一圈，不管当前角度是多少
```

**做「点一次转一圈」这种累加动画时必须用相对值**，写死 `rotation: 360` 的话第二次点击就不动了（已经在 360 了）。

### 函数式值

值可以写成函数，GSAP 会**对每个目标元素分别调用**：

```js
gsap.to(".box", {
  // i = 索引, el = 当前元素, arr = 全部元素
  x: (i, el, arr) => i * 100,
  rotation: (i) => (i % 2 ? 15 : -15),
  duration: 1,
});
```

**函数式值最重要的用途是响应式**——它在动画创建时求值，配合 `invalidateOnRefresh` 可以在 resize 时重新求值：

```js
gsap.to(".panel", {
  x: () => -(document.querySelector(".track").scrollWidth - window.innerWidth),
  scrollTrigger: {
    trigger: ".track",
    scrub: 1,
    invalidateOnRefresh: true,   // ★ resize 时重新调用上面的函数
  },
});
```

**写死像素值的横向滚动在 resize 后一定会错位**，这是最常见的原因。

### 随机

```js
gsap.to(".particle", {
  x: "random(-200, 200)",           // 区间随机
  y: "random(-100, 100, 10)",       // 第三个参数 = 步进，只取 10 的倍数
  rotation: "random([-90, 0, 90])", // 从数组里随机取
  duration: "random(1, 2)",
});
```

字符串形式的 `random()` 是 GSAP 解析的，**每个元素得到不同的值**。如果写成 `x: gsap.utils.random(-200, 200)`，那是先求出一个值再赋给所有元素，全部一样——这个区别很容易踩。

---

## 6. 缓动 ease

**缓动比时长更影响观感。** 同样 1 秒，`linear` 像机器，`power2.out` 像有质量的物体。

### 内置缓动

```js
gsap.to(el, { x: 100, ease: "power2.out" });
```

| 名字 | 手感 | 用在哪 |
|---|---|---|
| `none` / `linear` | 匀速 | 进度条、无限循环、跟随滚动 |
| `power1` ~ `power4` | 幂曲线，数字越大越"急" | 通用 |
| `back` | 冲过头再回弹 | 弹出、强调 |
| `elastic` | 橡皮筋震荡 | 玩具感，慎用 |
| `bounce` | 落地弹跳 | 同上 |
| `circ` | 前慢后极快 / 反之 | 强对比 |
| `expo` | 比 power4 更极端 | 快进快出 |
| `sine` | 最柔和 | 呼吸、漂浮循环 |
| `steps(n)` | 阶梯跳变 | 逐帧动画、打字机 |

### 三个方向

```js
ease: "power2.in"      // 慢启动，加速冲出 → 元素【离开】屏幕
ease: "power2.out"     // 快启动，减速停稳 → 元素【进入】屏幕（最常用）
ease: "power2.inOut"   // 两头慢中间快 → 位移距离大的、A 到 B 的移动
```

**默认值是 `power1.out`**。

**选择原则**：

- **进场用 `.out`**——元素快速出现然后稳住，符合"东西被放到位"的直觉
- **出场用 `.in`**——慢慢开始然后加速离开
- **A→B 的移动用 `.inOut`**
- **被滚动/拖拽驱动的（`scrub`）用 `none`**——外部输入本身已经有节奏了，再叠一层缓动会让人觉得"跟手不准"

### 自定义

```js
// 参数化
ease: "back.out(1.7)"        // 数字越大回弹越夸张
ease: "elastic.out(1, 0.3)"  // 幅度, 周期
ease: "steps(12)"

// CubicBezier（和 CSS 的 cubic-bezier 一致，可以直接从设计工具抄）
ease: CustomEase.create("custom", "M0,0 C0.25,0.1 0.25,1 1,1")   // 需要 CustomEase 插件
```

去 [gsap.com/docs/v3/Eases](https://gsap.com/docs/v3/Eases/) 有可视化对比，比看文字快得多。

### 时长怎么定

| 动作 | 参考时长 |
|---|---|
| hover 反馈 | 0.15 – 0.25s |
| 小元素进场 | 0.4 – 0.6s |
| 大区块进场 | 0.8 – 1.2s |
| 页面转场 | 0.6 – 1.0s |
| 强调/弹出 | 0.3 – 0.5s |

**超过 1.2 秒的单个动画基本都嫌慢**，除非它是被滚动驱动的。

> ⚠️ 调缓动时长时有个反直觉的点：**人眼判断"快慢"看的是首帧位移，不是平均速度**。`power4.out` 的 1 秒动画比 `linear` 的 1 秒感觉快得多，因为前 100ms 就走完了一半路程。所以换了 ease 之后通常要重调 duration。

---

## 7. stagger：错峰

让一组元素依次动，而不是齐刷刷一起动。

```js
// 最简单：每个延后 0.1 秒
gsap.from(".card", { y: 40, opacity: 0, duration: 0.6, stagger: 0.1 });
```

```js
// 完整配置
gsap.from(".card", {
  y: 40,
  opacity: 0,
  duration: 0.6,
  stagger: {
    each: 0.08,         // 每个之间的间隔
    // amount: 0.8,     // 或者：总共用 0.8 秒分配完（元素多时用这个，总时长可控）
    from: "start",      // start | center | end | edges | random | 索引数字
    ease: "power2.in",  // 错峰本身的分布曲线
    grid: "auto",       // 网格布局时按二维距离计算
    axis: "y",          // 只按某个轴算距离
  },
});
```

**`each` 和 `amount` 的区别很重要**：

- `each: 0.1` —— 10 个元素总共 1 秒，100 个元素总共 10 秒（**元素多了会失控**）
- `amount: 1` —— 不管几个元素，总共 1 秒分配完

**元素数量不确定时（CMS 驱动的列表）一律用 `amount`。**

```js
// 网格：从中心向外扩散
gsap.from(".grid-item", {
  scale: 0,
  opacity: 0,
  duration: 0.5,
  stagger: {
    amount: 1.2,
    grid: [5, 8],       // 5 行 8 列；写 "auto" 让 GSAP 自己量
    from: "center",
  },
});
```

> ⚠️ **stagger 不适合做「滚动进场」**。一个长列表用 stagger 的话，你滚到第 3 个的时候第 20 个已经播完了（它们是同一时刻开始计时的）。滚动进场要用 `ScrollTrigger.batch()` 或者给每个元素单独建 trigger，见 `scrolltrigger.md` 第 6 节。

---

## 8. keyframes：多段关键帧

一个补间里走多个阶段，不用建时间轴。

### 数组写法

```js
gsap.to(el, {
  keyframes: [
    { x: 100, duration: 0.5 },
    { y: 100, duration: 0.3, delay: 0.1 },   // delay 是相对上一段结束
    { x: 0, y: 0, duration: 0.5, ease: "back.out(2)" },
  ],
});
```

### 百分比写法（更像 CSS 的 @keyframes）

```js
gsap.to(el, {
  keyframes: {
    "0%":   { scale: 1,   rotation: 0 },
    "50%":  { scale: 1.3, rotation: 180, ease: "power2.out" },
    "100%": { scale: 1,   rotation: 360 },
  },
  duration: 2,
  ease: "none",        // 各段之间的整体缓动
});
```

**什么时候用 keyframes、什么时候用 timeline**：

- **keyframes** —— 单个元素的多阶段动作（一个按钮的"缩小-弹起-归位"）
- **timeline** —— 多个元素的编排，或者需要单独控制某一段

keyframes 做出来的还是**一个补间**，你没法单独 pause 其中一段。

---

## 9. Timeline：时间轴

**这是 GSAP 最有价值的部分。** 没有它，多个动画的先后关系只能靠 `delay` 硬算，改一个时长就要把后面所有的 delay 全改一遍。

```js
const tl = gsap.timeline();

tl.from(".title",    { y: 40, opacity: 0, duration: 0.8 })
  .from(".subtitle", { y: 30, opacity: 0, duration: 0.6 }, "-=0.4")
  .from(".btn",      { scale: 0.8, opacity: 0, duration: 0.4 }, "-=0.2")
  .from(".card",     { y: 50, opacity: 0, duration: 0.5, stagger: 0.1 });
```

**默认行为是依次排队**——每个动画在前一个结束时开始。第三个参数（位置参数）用来打破这个默认。

### 时间轴的配置

```js
const tl = gsap.timeline({
  paused: true,          // 创建后不自动播放
  repeat: 2,             // 重复 2 次（总共播 3 遍）；-1 = 无限
  repeatDelay: 0.5,
  yoyo: true,            // 每次重复反向播（配合 repeat 用）
  delay: 0.3,
  defaults: {            // ★ 这条时间轴里所有动画的默认值
    duration: 0.6,
    ease: "power2.out",
  },
  onComplete: () => console.log("done"),
});
```

**`defaults` 是最实用的一个**。有了它，上面那段可以简化成：

```js
const tl = gsap.timeline({ defaults: { duration: 0.6, ease: "power2.out" } });

tl.from(".title",    { y: 40, opacity: 0 })
  .from(".subtitle", { y: 30, opacity: 0 }, "-=0.4")
  .from(".btn",      { scale: 0.8, opacity: 0 }, "-=0.2");
```

调整整体节奏时只改一处。

### 嵌套时间轴

时间轴可以放进时间轴，这是组织复杂序列的方式：

```js
function heroIn() {
  const tl = gsap.timeline();
  tl.from(".hero-title", { y: 60, opacity: 0 })
    .from(".hero-img",   { scale: 1.1, opacity: 0 }, "-=0.5");
  return tl;
}

function statsIn() {
  const tl = gsap.timeline();
  tl.from(".stat", { y: 30, opacity: 0, stagger: 0.1 });
  return tl;
}

const master = gsap.timeline();
master.add(heroIn())
      .add(statsIn(), "-=0.3")
      .add(() => console.log("全部结束"));   // 也能加普通函数
```

**每个子时间轴独立开发和测试，主时间轴只管编排。** 序列一长，这个结构的价值就出来了。

---

## 10. 位置参数：时间轴里最容易错的地方

`tl.to(target, vars, position)` 的第三个参数。**GSAP 里最需要背下来的一张表**：

| 写法 | 含义 |
|---|---|
| （省略） | 上一个动画**结束时** |
| `0` 或 `"0"` | 时间轴的**绝对 0 秒** |
| `1.5` | 时间轴的绝对 1.5 秒 |
| `"+=0.5"` | 上一个动画结束后**再等** 0.5 秒（留空隙） |
| `"-=0.5"` | 上一个动画结束**前** 0.5 秒开始（**重叠**，最常用） |
| `"<"` | 和上一个动画**同时开始** |
| `">"` | 上一个动画结束时（= 省略） |
| `"<0.2"` | 上一个动画开始后 0.2 秒 |
| `">-0.3"` | 上一个动画结束前 0.3 秒（= `"-=0.3"`） |
| `"myLabel"` | 跳到某个标签处 |
| `"myLabel+=0.2"` | 标签后 0.2 秒 |

### 最容易混的两组

**`"-=0.5"` vs `"<"`**：

```js
tl.to(a, { x: 100, duration: 2 })
  .to(b, { x: 100, duration: 1 }, "-=0.5");   // b 在 1.5 秒时开始（a 结束前 0.5 秒）

tl.to(a, { x: 100, duration: 2 })
  .to(b, { x: 100, duration: 1 }, "<");       // b 在 0 秒时开始（和 a 同时）
```

**`"-="` 是相对「上一个动画的结束」，`"<"` 是相对「上一个动画的开始」。**

**数字 `0` vs 字符串 `"0"`**：两者相同，都是绝对时间 0。但 `"+=0"` 是相对——等于不偏移。

### 标签

时长会变的时候，硬编码的数字位置全是隐患。用标签：

```js
const tl = gsap.timeline();

tl.from(".a", { opacity: 0, duration: 1 })
  .addLabel("textIn")                         // 在当前位置打个标签
  .from(".b", { opacity: 0, duration: 1 }, "textIn")
  .from(".c", { opacity: 0, duration: 1 }, "textIn+=0.3")
  .addLabel("done");

tl.seek("textIn");        // 调试时直接跳过去
tl.play("textIn");
```

> ⚠️ **写死的偏移量会在它依赖的时长变小之后悄悄失效**。`"-=0.5"` 在前一个动画是 2 秒时是"重叠 25%"，等你把那个动画调到 0.4 秒，`-=0.5` 就变成"在它开始前就开始"了——顺序整个乱掉，而且不报错。
>
> 重叠关系写成比例更稳：`"-=" + (dur * 0.25)`，或者干脆用标签。

---

## 11. 控制动画：play / reverse / seek / timeScale

`gsap.to()` 和 `gsap.timeline()` 都返回一个实例，存下来才能控制它。

```js
const tl = gsap.timeline({ paused: true });
tl.to(".menu", { x: 0, duration: 0.5 });

// 播放控制
tl.play();          // 从当前位置播
tl.play(0);         // 从 0 秒播
tl.pause();
tl.resume();
tl.reverse();       // ★ 反向播（做开关菜单就靠它）
tl.restart();
tl.seek(1.5);       // 跳到 1.5 秒（不改变播放状态）
tl.seek("myLabel");
tl.progress(0.5);   // 跳到 50%（0~1）
tl.timeScale(2);    // 2 倍速；0.5 = 慢放
tl.kill();          // 销毁

// 读取状态
tl.progress();      // 当前进度 0~1
tl.duration();      // 总时长
tl.time();          // 当前时间（秒）
tl.isActive();      // 是否正在播
tl.paused();        // 是否暂停中
tl.reversed();      // 是否处于反向状态
```

### 开关型交互的标准写法

**做菜单、抽屉、手风琴，不要写两个时间轴，用一个 + `reverse()`**：

```js
const menu = gsap.timeline({ paused: true, defaults: { ease: "power3.out" } });

menu.to(".menu-panel", { xPercent: 0, duration: 0.5 })
    .from(".menu-item", { y: 20, opacity: 0, stagger: 0.06, duration: 0.4 }, "-=0.2");

let open = false;

btn.addEventListener("click", () => {
  open = !open;
  open ? menu.play() : menu.reverse();
  btn.setAttribute("aria-expanded", String(open));
});
```

好处：**开和关天然对称**，改一处两边都变。而且中途点击时 GSAP 会从当前进度平滑反向，不会跳。

### timeScale 做「加速关闭」

关闭动画通常应该比打开快：

```js
open ? menu.timeScale(1).play() : menu.timeScale(1.6).reverse();
```

---

## 12. 回调与事件

```js
gsap.to(el, {
  x: 100,
  duration: 1,

  onStart: () => {},
  onUpdate: () => {},          // ★ 每帧调用
  onComplete: () => {},
  onRepeat: () => {},
  onReverseComplete: () => {},

  // 传参
  onComplete: (a, b) => console.log(a, b),
  onCompleteParams: ["hello", 42],

  // 改变回调里的 this
  callbackScope: someObject,
});
```

### onUpdate 里读进度

```js
gsap.to(state, {
  n: 100,
  duration: 2,
  onUpdate() {
    // this 指向补间实例
    bar.style.width = this.progress() * 100 + "%";
  },
});
```

> ⚠️ **`onUpdate` 每帧都跑（60fps 下每秒 60 次）**。里面绝对不要做 `querySelector`、`getBoundingClientRect`、或者任何触发布局重算的操作——那是掉帧的头号原因。所有 DOM 引用和测量结果提前缓存在闭包里。

```js
// ❌ 每帧查询 + 每帧读布局
onUpdate() {
  document.querySelector(".bar").style.width =
    this.progress() * document.querySelector(".track").offsetWidth + "px";
}

// ✅ 提前缓存
const bar = document.querySelector(".bar");
const trackW = document.querySelector(".track").offsetWidth;
// ...
onUpdate() {
  bar.style.width = this.progress() * trackW + "px";
}
```

### Promise

```js
await gsap.to(el, { x: 100, duration: 1 });
console.log("动完了");

// 时间轴也可以
await tl.play();
```

`.then()` 也能用。做页面转场（旧页面淡出 → 切换内容 → 新页面淡入）时很顺手。

---

## 13. gsap.utils 工具集

这些和动画无关，但写交互时天天用。**很多人不知道 GSAP 自带这些，又去装了一个 lodash。**

```js
const { clamp, mapRange, interpolate, snap, random, wrap, normalize,
        toArray, selector, splitColor, pipe, distribute } = gsap.utils;
```

| 函数 | 作用 | 例子 |
|---|---|---|
| `clamp(min, max, v)` | 限制范围 | `clamp(0, 1, 1.4)` → `1` |
| `mapRange(a1,a2,b1,b2,v)` | 区间映射 | 滚动位置 → 进度 |
| `interpolate(a, b, t)` | 插值，支持数字/颜色/数组/对象 | `interpolate("#f00", "#00f", 0.5)` |
| `snap(step, v)` | 吸附到步进 | `snap(50, 73)` → `50` |
| `snap([...], v)` | 吸附到数组里最近的 | |
| `wrap(min, max, v)` | 循环（超出从头来） | 无限轮播的索引 |
| `random(min, max, step)` | 随机 | |
| `toArray(sel)` | 选择器/NodeList → 真数组 | `toArray(".card")` |
| `selector(el)` | 生成作用域内的选择器函数 | 见下 |
| `normalize(min, max, v)` | 映射到 0~1 | `normalize(0, 200, 50)` → `0.25` |
| `distribute(config)` | 按位置分配值 | 高级 stagger |
| `pipe(f1, f2, ...)` | 函数组合 | |

### 高频用法

```js
const { clamp, mapRange, toArray } = gsap.utils;

// 鼠标位置 → 视差偏移，并且钳住范围
window.addEventListener("mousemove", (e) => {
  const x = clamp(-20, 20, mapRange(0, window.innerWidth, -20, 20, e.clientX));
  gsap.to(".layer", { x, duration: 0.6, ease: "power2.out" });
});

// NodeList 转数组，直接用 map/filter
toArray(".card").forEach((card, i) => { /* ... */ });

// 作用域选择器：只在某个容器内查找
const q = gsap.utils.selector(containerRef);
gsap.from(q(".title"), { y: 40 });   // 等价于 container.querySelectorAll(".title")
```

`gsap.utils.selector()` 在组件化开发里特别有用——避免选到别的实例里的同名元素。

---

## 14. gsap.matchMedia：响应式

**不要在 resize 回调里手动 kill 和重建动画。** GSAP 3.11+ 提供了 `matchMedia`，它会在断点切换时自动清理旧动画、恢复元素状态、创建新动画。

```js
const mm = gsap.matchMedia();

mm.add("(min-width: 1024px)", () => {
  // 桌面端的动画
  const tl = gsap.timeline({ scrollTrigger: { trigger: ".sec", scrub: 1 } });
  tl.to(".panel", { xPercent: -300 });

  // 可选：返回清理函数，在离开这个断点时调用
  return () => { /* 手动清理非 GSAP 的东西，比如自己加的事件监听 */ };
});

mm.add("(max-width: 1023px)", () => {
  // 移动端换个简单的
  gsap.from(".panel", { opacity: 0, stagger: 0.1,
    scrollTrigger: { trigger: ".sec", start: "top 80%" } });
});
```

**回调里创建的所有 GSAP 动画和 ScrollTrigger 都会被自动 revert**——元素回到原始状态，不残留内联样式。这是它相比手写 resize 逻辑最大的价值。

### 多条件 + 变量

```js
mm.add({
  isDesktop: "(min-width: 1024px)",
  isMobile: "(max-width: 1023px)",
  reduceMotion: "(prefers-reduced-motion: reduce)",
}, (ctx) => {
  const { isDesktop, isMobile, reduceMotion } = ctx.conditions;

  if (reduceMotion) return;          // 直接不做动画

  gsap.to(".hero", {
    y: isDesktop ? -200 : -60,
    scrollTrigger: { scrub: isDesktop ? 1 : true },
  });
});

// 全部销毁
mm.revert();
```

> ⚠️ **别手挑断点去猜设备**。`(min-width: 1024px)` 在横屏平板上也成立，而平板的视口可能比某些笔记本还宽。判断"能不能 hover"用 `(hover: hover)`，判断触屏用 `(pointer: coarse)`，比宽度可靠。

---

## 15. gsap.context：作用域与清理

SPA、React 组件、或者任何「这段 DOM 会被销毁」的场景，都必须处理清理——否则元素没了动画还在跑，或者路由回来时重复创建。

```js
const ctx = gsap.context(() => {
  gsap.from(".title", { y: 40, opacity: 0 });
  gsap.to(".bg", { yPercent: -20, scrollTrigger: { scrub: true } });
}, containerElement);        // ★ 第二个参数 = 作用域

// 销毁时
ctx.revert();   // 杀掉里面所有动画和 ScrollTrigger，并把元素恢复到原始状态
```

**两件事它同时做了**：

1. **作用域限定** —— `".title"` 只在 `containerElement` 内查找，不会误伤页面上其他同名元素
2. **批量清理** —— 一次 `revert()` 收掉全部，不用逐个 kill

### revert vs kill

| | 做什么 |
|---|---|
| `kill()` | 停止动画，**元素保持在当前状态** |
| `revert()` | 停止动画，**元素恢复到动画开始前的状态**（移除 GSAP 加的内联样式） |

组件卸载用 `revert()`。如果只是想停下来但保留当前视觉状态，用 `kill()`。

---

## 16. defaults 与 registerEffect

### 全局默认值

```js
gsap.defaults({
  duration: 0.6,
  ease: "power2.out",
});
```

设一次，整个项目的动画手感统一。**做项目的第一件事就该定这个**，否则每个人写的时长和缓动都不一样，页面看起来就是散的。

### 注册可复用的效果

同一个进场动画在十几处用到时：

```js
gsap.registerEffect({
  name: "fadeUp",
  extendTimeline: true,          // ★ 让它能在时间轴上链式调用
  defaults: { duration: 0.7, y: 40, ease: "power2.out" },
  effect: (targets, config) =>
    gsap.from(targets, {
      y: config.y,
      opacity: 0,
      duration: config.duration,
      ease: config.ease,
      stagger: config.stagger,
    }),
});

// 用
gsap.effects.fadeUp(".card", { stagger: 0.1 });

// 在时间轴上用（需要 extendTimeline: true）
tl.fadeUp(".title").fadeUp(".sub", { y: 20 }, "-=0.4");
```

**改一处，全站的进场动画一起变。** 这在需要统一调整节奏时省掉大量返工。

---

## 17. 性能

### 只动合成层属性

```
transform (x/y/scale/rotate) 和 opacity
  → 只走【合成】，不触发布局和绘制，GPU 直接处理

width/height/top/left/margin/padding
  → 触发【布局】→ 绘制 → 合成，每帧重排整棵树

color/background/box-shadow/border-radius
  → 触发【绘制】→ 合成
```

**能用 transform 表达的就别用别的。** 想改宽度？用 `scaleX`（注意子元素会跟着被压扁，文字要反向 `scaleX` 补偿，或者改用 `clip-path`）。

### will-change 别滥用

```css
/* ❌ 全局加，每个元素都提升成一个合成层，显存爆掉 */
* { will-change: transform; }

/* ✅ 只给正在动的，且动完移除 */
```

GSAP 会自动加 `force3D`（`translateZ(0)`）来提升需要的元素，**大多数情况不用自己写 `will-change`**。

### 避免的写法

```js
// ❌ 100 个元素 100 个 ScrollTrigger 实例
document.querySelectorAll(".card").forEach(card => {
  gsap.from(card, { y: 40, scrollTrigger: { trigger: card } });
});

// ✅ 用 batch，共享一套计算
ScrollTrigger.batch(".card", {
  onEnter: (els) => gsap.from(els, { y: 40, opacity: 0, stagger: 0.08 }),
});
```

```js
// ❌ 每帧读布局（强制同步重排）
onUpdate() { el.style.height = container.offsetHeight * p + "px"; }

// ✅ 提前量好
const h = container.offsetHeight;
onUpdate() { el.style.height = h * p + "px"; }
```

### 大量元素

```js
// 复用同一个补间的配置，不要循环里建 N 个
gsap.to(".particle", { x: "random(-200,200)", stagger: { amount: 1 } });

// 需要极致性能时，动一个 JS 对象 + 手动写 transform
const state = { p: 0 };
gsap.to(state, { p: 1, duration: 2, onUpdate() {
  for (let i = 0; i < items.length; i++) {
    items[i].style.transform = `translate3d(0,${state.p * offsets[i]}px,0)`;
  }
}});
```

### 测量

打开 DevTools → Performance，录一段。看：

- **FPS 条有没有红色**
- **Main 线程有没有长任务**（超过 50ms 的黄块）
- **有没有 Layout / Recalculate Style 的紫色块**——有就说明动了会触发布局的属性

> ⚠️ 在共用的开发机上量性能数据不可靠。同事开着十几个浏览器窗口时，你的动画掉帧和代码没关系。判断"是不是我写的东西慢"，先在同一台机器上打开一个未修改的对照页面量一遍。

---

## 18. 无障碍：prefers-reduced-motion

**这不是可选项。** 前庭功能障碍的用户会因为大幅度的滚动动画产生眩晕和恶心。系统里有个开关表达这个诉求，尊重它。

```js
const mm = gsap.matchMedia();

mm.add("(prefers-reduced-motion: no-preference)", () => {
  // 完整动画只在这里创建
  gsap.from(".hero", { y: 100, opacity: 0, duration: 1 });
  gsap.to(".panel", { xPercent: -300, scrollTrigger: { scrub: 1, pin: true } });
});

mm.add("(prefers-reduced-motion: reduce)", () => {
  // 保留信息层级，去掉位移
  gsap.from(".hero", { opacity: 0, duration: 0.3 });
});
```

**要点**：

- **不是「关掉所有动画」**——淡入淡出通常没问题，有问题的是**位移、缩放、视差、自动播放的循环**
- **别让降级版本破坏布局**。`pin` 型模块在关掉动画后会少一屏高度，如果你的页面靠那一屏做了什么，会错位
- 测试方法：macOS「辅助功能 → 显示 → 减弱动态效果」，Windows「设置 → 辅助功能 → 视觉效果 → 动画效果」，或 DevTools 的 Rendering 面板里模拟

### 其他无障碍要点

```js
// 动画中的元素不该干扰读屏
gsap.set(".decorative", { attr: { "aria-hidden": "true" } });

// 用 autoAlpha 而不是 opacity，透明的元素同时被移出无障碍树和事件流
gsap.to(".overlay", { autoAlpha: 0 });

// 焦点管理：抽屉打开后把焦点移进去，关闭后还回去
```

---

## 19. SVG 动画

GSAP 对 SVG 的支持比 CSS 好得多，尤其是 transform 的原点问题。

```js
// SVG 元素的 transform-origin 用百分比更可靠
gsap.to("#circle", { rotation: 360, transformOrigin: "50% 50%", duration: 2 });

// 描边绘制（不用插件）
const path = document.querySelector("#line");
const len = path.getTotalLength();

gsap.set(path, { strokeDasharray: len, strokeDashoffset: len });
gsap.to(path, { strokeDashoffset: 0, duration: 2, ease: "power2.inOut" });
```

```js
// 属性动画用 attr
gsap.to("#rect", { attr: { width: 200, rx: 20 }, duration: 1 });
gsap.to("#grad-stop", { attr: { "stop-color": "#ff5a3c" }, duration: 1 });
```

### 常见问题

| 问题 | 原因 | 解决 |
|---|---|---|
| 旋转中心不对 | SVG 的 transform-origin 默认在坐标系原点 | 显式写 `transformOrigin: "50% 50%"` |
| 位移单位不对 | SVG 用的是用户坐标不是 px | 用 `x`/`y`（GSAP 会处理），或者 `attr` |
| 描边动画不动 | `stroke-dasharray` 没设 | 见上面的写法 |
| 图形被裁掉 | `overflow: hidden` 或 viewBox 不够 | 调 viewBox |

---

## 20. 文字动画：SplitText

按字符 / 单词 / 行拆分文字，做逐字进场。**2025 年起免费**。

```html
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/SplitText.min.js"></script>
```

```js
gsap.registerPlugin(SplitText);

const split = new SplitText(".headline", { type: "chars,words,lines" });

gsap.from(split.chars, {
  y: 40,
  opacity: 0,
  duration: 0.6,
  stagger: 0.02,
  ease: "power3.out",
});

// 用完还原（重要，见下）
split.revert();
```

### 必须处理的三件事

**1. 字体加载完再拆**

字体没加载完时行宽是错的，按行拆会拆错。

```js
document.fonts.ready.then(() => {
  const split = new SplitText(".headline", { type: "lines" });
  gsap.from(split.lines, { y: 30, opacity: 0, stagger: 0.1 });
});
```

**2. resize 后要重拆**

行的划分会随宽度变化。

```js
let split;
const setup = () => {
  split?.revert();
  split = new SplitText(".headline", { type: "lines" });
  gsap.from(split.lines, { y: 30, opacity: 0, stagger: 0.1 });
};
document.fonts.ready.then(setup);
window.addEventListener("resize", gsap.utils.debounce?.(setup, 250) ?? setup);
```

**3. 无障碍和 SEO**

SplitText 会把文字拆成一堆 `<div>`，读屏软件可能逐字读出来。新版会自动加 `aria-label`，但**检查一遍**：

```html
<h1 class="headline" aria-label="原始完整文字">…</h1>
```

> ⚠️ **手动换行会静默破坏按行拆分**。CMS 字段里编辑打的 `<br>` 或者 `\n`，在 `type: "lines"` 下会产生和视觉不一致的行划分。做 CMS 驱动的标题动画时，要么禁用手动换行，要么改成按词拆。

---

## 21. Flip：布局变化动画

**做「元素从 A 位置平滑移动到 B 位置」，但 A 和 B 是两套不同的 DOM 结构 / CSS 布局。** 手写这个非常痛苦，Flip 一行搞定。

典型场景：网格 → 列表切换、卡片展开成详情、共享元素转场、筛选后重排。

```html
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/Flip.min.js"></script>
```

```js
gsap.registerPlugin(Flip);

const items = gsap.utils.toArray(".item");

// 1. 记录当前状态
const state = Flip.getState(items);

// 2. 随便改 DOM / class / 布局（瞬间的，不用管动画）
container.classList.toggle("list-view");
// 或者 items.forEach(el => grid.appendChild(el));  移动 DOM 也行

// 3. 让 GSAP 从旧状态动到新状态
Flip.from(state, {
  duration: 0.6,
  ease: "power2.inOut",
  stagger: 0.03,
  absolute: true,          // 动画期间绝对定位，避免影响其他元素
  onEnter: (els) => gsap.from(els, { opacity: 0, scale: 0.8 }),   // 新增的元素
  onLeave: (els) => gsap.to(els, { opacity: 0, scale: 0.8 }),     // 消失的元素
});
```

**原理**：记录变化前每个元素的 rect → 应用新布局 → 计算差值 → 用 transform 把元素"骗"回旧位置 → 动画到 0。全程只动 transform，所以很流畅。

---

## 22. Observer：统一的输入监听

把滚轮、触摸、指针、键盘统一成一套事件，省掉自己处理各平台差异。

```js
gsap.registerPlugin(Observer);

Observer.create({
  target: window,
  type: "wheel,touch,pointer",
  wheelSpeed: -1,
  onDown: () => goToSection(current - 1),
  onUp:   () => goToSection(current + 1),
  tolerance: 10,           // 小于这个位移不触发
  preventDefault: true,    // ★ 接管默认滚动
});
```

**做整屏翻页（fullpage 效果）时这是最省事的方案**——不用自己判断滚轮方向、不用处理触摸的 start/move/end、不用管惯性。

> ⚠️ `preventDefault: true` 会**彻底接管页面滚动**。用它做整屏翻页时，键盘的 Page Up/Down、空格、Home/End 全部失效，这是无障碍问题。要么额外监听键盘补回来，要么别用这个模式。

---

## 23. 和框架集成

### React

```jsx
import { useRef } from "react";
import gsap from "gsap";
import { useGSAP } from "@gsap/react";   // npm i @gsap/react

function Hero() {
  const container = useRef();

  useGSAP(() => {
    // 这里创建的所有动画会在组件卸载时自动清理
    gsap.from(".title", { y: 40, opacity: 0, duration: 0.8 });
    gsap.from(".sub",   { y: 20, opacity: 0, duration: 0.6, delay: 0.2 });
  }, { scope: container });    // ★ 选择器只在 container 内生效

  return (
    <div ref={container}>
      <h1 className="title">Hello</h1>
      <p className="sub">World</p>
    </div>
  );
}
```

`useGSAP` 就是包好的 `gsap.context` + `useEffect`，**处理了 React 18 StrictMode 下 effect 跑两次的问题**。不用它的话自己写：

```jsx
useEffect(() => {
  const ctx = gsap.context(() => { /* 动画 */ }, container);
  return () => ctx.revert();
}, []);
```

### Vue

```vue
<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import gsap from "gsap";

const container = ref(null);
let ctx;

onMounted(() => {
  ctx = gsap.context(() => {
    gsap.from(".title", { y: 40, opacity: 0 });
  }, container.value);
});

onUnmounted(() => ctx?.revert());
</script>
```

### 通用原则

- **动画代码放在 DOM 挂载之后**（`onMounted` / `useEffect` / `DOMContentLoaded`）
- **一定要清理**，否则路由切换后动画对着已销毁的元素跑
- **用 scope**，避免选到别的组件实例里的元素
- 数据驱动的列表，元素数量变化后要 `ScrollTrigger.refresh()`

---

## 24. 调试

```js
// 1. 给动画命名（GSDevTools 里能看到）
gsap.to(el, { x: 100, id: "heroIn" });

// 2. 查当前有哪些动画在跑
console.log(gsap.globalTimeline.getChildren());

// 3. 全局慢放，看清每一帧
gsap.globalTimeline.timeScale(0.2);

// 4. 时间轴的时间点
console.log(tl.duration(), tl.labels);

// 5. 直接跳到某个位置看效果
tl.seek("labelName");
tl.progress(0.5);

// 6. 停掉某个元素上的所有动画
gsap.killTweensOf(el);
```

### GSDevTools

```html
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/GSDevTools.min.js"></script>
```
```js
gsap.registerPlugin(GSDevTools);
GSDevTools.create({ animation: tl });   // 页面上出现一个进度条 + 播放控制
```

**能拖动时间轴逐帧看**，调复杂序列时比反复刷新页面快十倍。上线记得删掉。

### 排查思路

| 现象 | 先查 |
|---|---|
| 完全没动 | 选择器有没有选中（`console.log(document.querySelectorAll(sel).length)`）；插件注册了吗 |
| 动了但看不见 | 元素是不是在视口外 / 被遮挡 / `overflow: hidden` 裁掉了 |
| 位置不对 | 有没有别的 CSS 也在写 transform；父元素有没有 transform（会改变 fixed 的参照系） |
| 闪一下 | FOUC，见第 3 节 |
| 第二次触发就不对了 | `from` 的重复执行问题，改 `fromTo` |
| 只在某些屏幕坏 | 写死的像素值，改函数式值 |

> ⚠️ **动画跑着的时候读不到目标值**。`getComputedStyle` 在过渡中间返回的是当前插值，不是终点。想验证"动画最终到了哪"，要等 `onComplete` 之后再读。
>
> 同理，`document.getAnimations()` 只返回**还没结束**的动画——事后采样量到的是哪一批，取决于你改过的时长。写时序断言要在动画创建时就记录，别事后刮。

---

## 25. 常见坑

### ① 用 from 做进场，元素先闪一下

见第 3 节。CSS 写初始态 + `to` 最稳。

### ② 同一个元素被两处动画抢

```js
gsap.to(el, { x: 100, duration: 2 });
gsap.to(el, { x: 0, duration: 1 });    // 后者接管，前者被 overwrite
```

GSAP 默认的 `overwrite` 是 `"auto"`——只覆盖冲突的属性。需要完全接管时写 `overwrite: true`。

**排查方法**：`gsap.getTweensOf(el)` 看有几个动画在动它。

### ③ CSS 的 transform 被 GSAP 抹掉

元素本来靠 `transform: translate(-50%, -50%)` 居中，GSAP 一动 `x` 就跳位。

```js
// ❌
gsap.to(el, { x: 100 });      // 居中的 -50% 没了

// ✅ 把居中也交给 GSAP
gsap.set(el, { xPercent: -50, yPercent: -50 });
gsap.to(el, { x: 100 });
```

### ④ 百分比参照的是元素自己

`x: "50%"` 是元素自身宽度的 50%，不是父元素的。要相对父元素就自己算。

### ⑤ 父元素有 transform 会毁掉 position: fixed

这不是 GSAP 的问题，是 CSS 规范：**任何非 `none` 的 transform 会让后代的 `position: fixed` 相对它定位，而不是视口。**

所以给一个包含 fixed 元素的容器加了动画之后，那个 fixed 元素会突然"跟着滚"。解法是把 fixed 元素移到动画容器外面。

### ⑥ 动画完成后元素带着一堆内联样式

GSAP 动完会在元素上留下 `style="transform: ...; opacity: ..."`。想清掉：

```js
gsap.to(el, { x: 100, clearProps: "transform" });   // 动完清掉 transform
gsap.to(el, { x: 100, clearProps: "all" });         // 清掉全部 GSAP 加的
```

**做完进场动画后 `clearProps: "all"` 是好习惯**——否则后续的 CSS hover 效果可能被内联样式压住。

### ⑦ 循环里创建动画，闭包抓错变量

```js
// ❌ var 的经典问题
for (var i = 0; i < items.length; i++) {
  gsap.to(items[i], { x: 100, delay: i * 0.1 });   // 这个其实没问题
}

// ⚠️ 但回调里就会出问题
for (var i = 0; i < items.length; i++) {
  gsap.to(items[i], { x: 100, onComplete: () => console.log(i) });   // 全打印 items.length
}

// ✅ 用 let，或者 forEach
items.forEach((el, i) => {
  gsap.to(el, { x: 100, onComplete: () => console.log(i) });
});
```

### ⑧ 动态内容加载后动画不生效

Ajax 加载、路由切换、手风琴展开之后，新元素上没有动画（创建时它们还不存在），且页面高度变了。

```js
// 新内容进来后
initAnimations(newContainer);
ScrollTrigger.refresh();      // 如果用了 ScrollTrigger
```

### ⑨ 引了平滑滚动库之后各种东西失灵

Lenis / ScrollSmoother 接管滚动之后，**所有直接写 `window.scrollTo` / `scrollY` 的代码都会失效或打架**。ScrollTrigger 也要显式接线，见 `scrolltrigger.md` 第 10 节。

这个坑的表现形式很多：钉住失效、配速不对、锚点跳转不动、测试脚本里 `element.hover()` 行为诡异（Playwright 的 hover 自带滚动）。**引平滑滚动是一个全局决定，不是加一个库那么简单。**

### ⑩ 移动端 100vh 的问题

用 `vh` 算动画距离时，移动端浏览器地址栏收起/展开会改变视口高度，触发 resize，动画值全变。

```js
// 用 dvh，或者锁定一次
const vh = window.innerHeight;
gsap.to(el, { y: () => vh * 0.5 });
```

配合 `ScrollTrigger.config({ ignoreMobileResize: true })`。

---

## 26. 速查附录

### 最常用的十行

```js
gsap.to(el, { x: 100, duration: 1, ease: "power2.out" });
gsap.from(el, { y: 40, opacity: 0, duration: 0.8 });
gsap.fromTo(el, { opacity: 0 }, { opacity: 1, duration: 0.5 });
gsap.set(el, { opacity: 0 });

const tl = gsap.timeline({ defaults: { duration: 0.6, ease: "power2.out" } });
tl.from(".a", { y: 40, opacity: 0 })
  .from(".b", { y: 30, opacity: 0 }, "-=0.4");

tl.play(); tl.reverse(); tl.timeScale(1.5); tl.progress(0.5);

gsap.killTweensOf(el);
```

### 位置参数

| 写法 | 含义 |
|---|---|
| 省略 / `">"` | 上一个结束时 |
| `"<"` | 和上一个同时开始 |
| `"-=0.5"` | 上一个结束前 0.5 秒（重叠） |
| `"+=0.5"` | 上一个结束后再等 0.5 秒 |
| `"<0.2"` | 上一个开始后 0.2 秒 |
| `0` / `2.5` | 绝对时间 |
| `"label"` / `"label+=0.3"` | 标签相对 |

### 缓动选择

| 场景 | ease |
|---|---|
| 进场 | `power2.out` / `power3.out` |
| 出场 | `power2.in` |
| A→B 移动 | `power2.inOut` |
| 跟随滚动 / 拖拽 | `none` |
| 强调、弹出 | `back.out(1.7)` |
| 呼吸、漂浮 | `sine.inOut` + `yoyo: true, repeat: -1` |

### 时长参考

| 动作 | 秒 |
|---|---|
| hover | 0.15–0.25 |
| 小元素进场 | 0.4–0.6 |
| 大区块进场 | 0.8–1.2 |
| 页面转场 | 0.6–1.0 |

### 该动 / 不该动

| ✅ 动这些 | ❌ 别动这些 |
|---|---|
| `x` `y` `scale` `rotation` `opacity` `autoAlpha` | `top` `left` `width` `height` `margin` |
| `xPercent` `yPercent` | `filter: blur()`（大面积时） |
| `backgroundColor` `color`（少量元素） | `box-shadow`（大量元素时） |

### 项目开局的模板

```js
// anim.js
gsap.registerPlugin(ScrollTrigger);

gsap.defaults({ duration: 0.6, ease: "power2.out" });

const mm = gsap.matchMedia();

mm.add("(prefers-reduced-motion: reduce)", () => {
  // 无位移的降级版本
  gsap.utils.toArray("[data-reveal]").forEach((el) => {
    gsap.from(el, { opacity: 0, duration: 0.3,
      scrollTrigger: { trigger: el, start: "top 90%" } });
  });
});

mm.add("(prefers-reduced-motion: no-preference)", () => {
  ScrollTrigger.batch("[data-reveal]", {
    start: "top 85%",
    onEnter: (els) => gsap.from(els, {
      y: 40, opacity: 0, stagger: { amount: 0.4 }, overwrite: true,
    }),
  });

  mm.add("(min-width: 1024px)", () => {
    // 只在桌面端跑的重型动画
  });
});

window.addEventListener("load", () => ScrollTrigger.refresh());
```

### 官方资源

- 文档：https://gsap.com/docs/v3/
- 缓动可视化：https://gsap.com/docs/v3/Eases/
- 论坛（作者本人常年回帖，质量极高）：https://gsap.com/community/
- CodePen 官方合集：https://codepen.io/collection/DrqPga

---

## 学习路径

**第一天** —— 第 2、3、6 节。能写出 `gsap.to()` 和挑对 ease，这时候已经能替代 80% 的 CSS transition 用法了。

**第二天** —— 第 9、10 节。Timeline 和位置参数，这是 GSAP 和 CSS 动画拉开差距的地方。位置参数那张表要背下来。

**第三天** —— 第 11、15 节 + `scrolltrigger.md`。控制方法（`reverse()` 做开关）和清理，这两个决定了代码能不能维护。

**然后** —— 第 14、18 节（响应式和无障碍）在真实项目里是必做项，不是加分项。

**遇到具体需求再翻** —— 第 19（SVG）、20（文字）、21（Flip）、22（Observer）。

**第 25 节当排错手册用。**
