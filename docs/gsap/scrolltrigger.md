# GSAP ScrollTrigger 快速入门教程

> 适用版本：GSAP 3.13+（2025 年 4 月起 GSAP 及全部官方插件对所有人免费，包括商业项目，无需 Club 会员）

---

## 目录

1. [引入与注册](#1-引入与注册)
2. [第一个 ScrollTrigger](#2-第一个-scrolltrigger)
3. [核心概念：start / end / 标记](#3-核心概念start--end--标记)
4. [两种模式：触发一次 vs 跟随滚动（scrub）](#4-两种模式触发一次-vs-跟随滚动scrub)
5. [pin：把元素钉住](#5-pin把元素钉住)
6. [批量处理：进场动画的正确写法](#6-批量处理进场动画的正确写法)
7. [时间轴 + ScrollTrigger（做复杂序列）](#7-时间轴--scrolltrigger做复杂序列)
8. [响应式：matchMedia](#8-响应式matchmedia)
9. [常用回调与属性速查](#9-常用回调与属性速查)
10. [配合平滑滚动（Lenis / ScrollSmoother）](#10-配合平滑滚动lenis--scrollsmoother)
11. [常见坑与排错清单](#11-常见坑与排错清单)
12. [实战片段合集](#12-实战片段合集)

---

## 1. 引入与注册

### CDN（最省事）

```html
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js"></script>
<script>
  gsap.registerPlugin(ScrollTrigger);
</script>
```

### npm / 打包工具

```bash
npm install gsap
```

```js
import gsap from "gsap";
import ScrollTrigger from "gsap/ScrollTrigger";

gsap.registerPlugin(ScrollTrigger);
```

> **必须 `registerPlugin`**，否则 tree-shaking 会把插件摇掉，或者报 "ScrollTrigger is not defined"。

### WordPress 主题里的写法

```php
// functions.php
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script('gsap', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/gsap.min.js', [], '3.13.0', true);
    wp_enqueue_script('gsap-st', 'https://cdn.jsdelivr.net/npm/gsap@3.13.0/dist/ScrollTrigger.min.js', ['gsap'], '3.13.0', true);
    wp_enqueue_script('theme-anim', get_template_directory_uri() . '/assets/js/anim.js', ['gsap-st'], null, true);
});
```

---

## 2. 第一个 ScrollTrigger

ScrollTrigger 不是独立动画，它是**给 tween / timeline 加的一个开关**。任何 `gsap.to/from/fromTo` 都能加 `scrollTrigger` 配置对象。

```js
gsap.from(".card", {
  y: 60,
  opacity: 0,
  duration: 1,
  ease: "power2.out",
  scrollTrigger: {
    trigger: ".card",   // 以谁为参照
    start: "top 80%",   // 参照元素顶部 碰到 视口 80% 高度时开始
  },
});
```

翻译成人话：**当 `.card` 的顶部滚到视口 80% 的位置时，播放这个动画。**

### `from` vs `to` vs `fromTo`

| 写法 | 含义 | 适用 |
|---|---|---|
| `gsap.from(el, {opacity:0, y:60})` | 从「透明+下移 60」动到当前 CSS 状态 | 进场动画首选 |
| `gsap.to(el, {opacity:1, y:0})` | 从当前状态动到目标 | 元素初始态已在 CSS 里写好时 |
| `gsap.fromTo(el, {…}, {…})` | 起止都明确指定 | 需要绝对可控时（重复播放最稳） |

> 进场动画用 `from` 最方便：不用在 CSS 里写 `opacity:0`，也就不会出现「JS 挂了内容全隐形」的事故。但要注意 FOUC——若担心闪现，用 CSS 写初始态 + `fromTo`/`to`，并给 `<html>` 加 `.gsap-ready` 类控制。

---

## 3. 核心概念：start / end / 标记

### 语法：`"触发元素的位置 视口的位置"`

```js
start: "top bottom"   // 元素顶部 碰到 视口底部
start: "top center"   // 元素顶部 碰到 视口中线
start: "top 80%"      // 元素顶部 碰到 视口 80% 高度处（从顶算）
start: "center center"// 元素中心 碰到 视口中心
start: "top top+=100" // 元素顶部 碰到 视口顶部再往下 100px
end:   "bottom top"   // 元素底部 碰到 视口顶部（元素完全滚出）
end:   "+=500"        // 从 start 位置再滚 500px 后结束
end:   () => "+=" + document.querySelector(".panel").offsetHeight
```

第一个值 = 触发元素身上的点，第二个值 = 视口（scroller）身上的点。**两点相遇 = 触发。**

### 打开可视化标记（开发必开）

```js
scrollTrigger: {
  trigger: ".box",
  start: "top 80%",
  end: "bottom 20%",
  markers: true,       // 屏幕上出现 start/end 绿红线
}
```

全局默认：

```js
ScrollTrigger.defaults({ markers: true });   // 开发时
// 上线前删掉，或 ScrollTrigger.defaults({ markers: false })
```

---

## 4. 两种模式：触发一次 vs 跟随滚动（scrub）

### A. 触发式（默认）

滚到 start 就正常播放动画，动画时长由 `duration` 决定，跟滚动速度无关。

```js
gsap.from(".title", {
  y: 40, opacity: 0, duration: .8,
  scrollTrigger: { trigger: ".title", start: "top 85%" }
});
```

配 `toggleActions` 控制进出行为：

```js
scrollTrigger: {
  trigger: ".title",
  toggleActions: "play none none reverse",
  // 顺序：onEnter, onLeave, onEnterBack, onLeaveBack
  // 可选值：play / pause / resume / reverse / restart / reset / complete / none
}
```

常用组合：

| 值 | 效果 |
|---|---|
| `"play none none none"` | 只播一次，往回滚不动（默认行为的常见写法） |
| `"play none none reverse"` | 向下播、向上倒放（来回都有动画） |
| `"restart none none reset"` | 每次进入重播，滚出去复位 |

只想播一次、播完就销毁：

```js
scrollTrigger: { trigger: ".title", start: "top 85%", once: true }
```

### B. scrub（滚动条驱动动画进度）

```js
gsap.to(".bar", {
  scaleX: 1,
  ease: "none",              // scrub 一定用 none，否则手感很怪
  scrollTrigger: {
    trigger: ".section",
    start: "top top",
    end: "bottom bottom",
    scrub: true,             // 动画进度 = 滚动进度，完全同步
    // scrub: 1,             // 加 1 秒缓动追赶，手感更顺滑（推荐）
  },
});
```

- `scrub: true` —— 硬跟手，滚动条在哪动画就在哪。
- `scrub: 1` —— 有 1 秒的平滑追赶，视觉上更高级，**大多数视差/进度条都建议用数字**。

> scrub 模式下 `duration` 失效（进度由滚动决定），但在 timeline 里 `duration` 仍决定各段的**相对占比**。

---

## 5. pin：把元素钉住

```js
gsap.to(".panel-inner", {
  xPercent: -300,
  ease: "none",
  scrollTrigger: {
    trigger: ".panel-wrap",
    start: "top top",
    end: "+=3000",     // 钉住期间的滚动距离
    pin: true,         // 钉住 trigger 元素
    scrub: 1,
    anticipatePin: 1,  // 快速滚动时减少抖动
  },
});
```

要点：

- `pin: true` 钉住 `trigger`；`pin: ".other"` 钉住别的元素。
- ScrollTrigger 会自动插入一个 `pin-spacer` 占位 div，**不要给被 pin 的元素或其父级加 `overflow: hidden`**。
- 被 pin 的元素及其祖先**不能有 `position: sticky` / `transform`**（会破坏 fixed 定位）。
- `end` 决定钉多久，`"+=3000"` 就是钉住 3000px 的滚动距离。
- 多个 pin 相邻时加 `pinSpacing: false` 可以让它们叠着来（做卡片堆叠效果）。

### 经典：横向滚动区

```js
const panels = gsap.utils.toArray(".panel");

gsap.to(panels, {
  xPercent: -100 * (panels.length - 1),
  ease: "none",
  scrollTrigger: {
    trigger: ".horizontal",
    pin: true,
    scrub: 1,
    snap: 1 / (panels.length - 1),   // 吸附到每一屏
    end: () => "+=" + document.querySelector(".horizontal").offsetWidth,
  },
});
```

配套 CSS：

```css
.horizontal { overflow: hidden; }
.horizontal .track { display: flex; width: max-content; }
.panel { width: 100vw; height: 100vh; flex: none; }
```

---

## 6. 批量处理：进场动画的正确写法

### 方式一：循环（最直观，控制力最强）

```js
gsap.utils.toArray(".reveal").forEach((el) => {
  gsap.from(el, {
    y: 50,
    opacity: 0,
    duration: 1,
    ease: "power3.out",
    scrollTrigger: {
      trigger: el,        // 注意是 el，不是 ".reveal"
      start: "top 85%",
      once: true,
    },
  });
});
```

> **最常见的新手错误**：写 `trigger: ".reveal"` —— 那样所有元素都以第一个 `.reveal` 为触发器，一起播完。必须传当前 `el`。

### 方式二：`ScrollTrigger.batch()`（性能更好，自动分组 stagger）

```js
ScrollTrigger.batch(".reveal", {
  start: "top 85%",
  once: true,
  onEnter: (batch) =>
    gsap.from(batch, {
      y: 50,
      opacity: 0,
      duration: .9,
      ease: "power3.out",
      stagger: 0.12,     // 同屏进入的元素依次出场
      overwrite: true,
    }),
});
```

`batch` 适合列表、卡片网格——一次进入视口的多个元素会被合并成一组，自动交错动画，比 N 个独立 ScrollTrigger 更省性能。

---

## 7. 时间轴 + ScrollTrigger（做复杂序列）

一个 ScrollTrigger 控制一整条时间轴，是做复杂场景的标准做法。

```js
const tl = gsap.timeline({
  scrollTrigger: {
    trigger: ".hero",
    start: "top top",
    end: "+=1500",
    scrub: 1,
    pin: true,
  },
});

tl.to(".hero-bg",    { scale: 1.2, duration: 2 })
  .to(".hero-title", { y: -100, opacity: 0, duration: 1 }, 0)      // 0 = 与上一条同时开始
  .from(".hero-sub", { y: 60, opacity: 0, duration: 1 }, ">-0.3")  // 上一条结束前 0.3s 开始
  .to(".hero-cta",   { scale: 1, duration: 1 }, "<");              // < = 与上一条同时开始
```

**位置参数速查：**

| 写法 | 含义 |
|---|---|
| 省略 | 接在上一条动画之后 |
| `0` / `1.5` | 绝对时间点（秒） |
| `"<"` | 与上一条动画**同时**开始 |
| `">"` | 上一条动画**结束**时 |
| `"<+=0.2"` | 上一条开始后 0.2s |
| `">-0.3"` | 上一条结束前 0.3s（重叠） |
| `"-=0.5"` | 相对时间轴末尾提前 0.5s |
| `"myLabel"` | 跳到标签处（`tl.addLabel("myLabel")`） |

> scrub 时间轴里，各段 `duration` 只表示**相对权重**：总滚动距离会按比例分配给每一段。

---

## 8. 响应式：matchMedia

`gsap.matchMedia()` 是 3.11+ 的正确做法，断点切换时会**自动清理**旧动画。

```js
const mm = gsap.matchMedia();

mm.add("(min-width: 1024px)", () => {
  // 只在桌面运行；离开断点时自动 revert
  gsap.to(".panel", {
    xPercent: -200,
    ease: "none",
    scrollTrigger: { trigger: ".wrap", pin: true, scrub: 1, end: "+=2000" },
  });
});

mm.add("(max-width: 1023px)", () => {
  gsap.from(".panel", {
    y: 40, opacity: 0, stagger: .1,
    scrollTrigger: { trigger: ".wrap", start: "top 80%" },
  });
});

// 尊重用户的「减少动态效果」偏好
mm.add("(prefers-reduced-motion: reduce)", () => {
  ScrollTrigger.getAll().forEach(st => st.kill());
  gsap.set(".reveal", { clearProps: "all" });
});
```

带条件对象的写法（更灵活）：

```js
mm.add(
  {
    isDesktop: "(min-width: 1024px)",
    isMobile:  "(max-width: 1023px)",
    reduce:    "(prefers-reduced-motion: reduce)",
  },
  (ctx) => {
    const { isDesktop, reduce } = ctx.conditions;
    if (reduce) return;
    gsap.from(".card", { y: isDesktop ? 80 : 30, opacity: 0, stagger: .1,
      scrollTrigger: { trigger: ".card-grid", start: "top 85%" } });
  }
);
```

---

## 9. 常用回调与属性速查

```js
ScrollTrigger.create({
  trigger: ".section",
  start: "top center",
  end: "bottom center",
  markers: true,

  // —— 回调 ——
  onEnter:     (self) => console.log("向下进入"),
  onLeave:     (self) => console.log("向下离开"),
  onEnterBack: (self) => console.log("向上回到"),
  onLeaveBack: (self) => console.log("向上离开"),
  onUpdate:    (self) => {
    self.progress;   // 0 → 1 的进度
    self.direction;  // 1 向下，-1 向上
    self.velocity(); // 滚动速度（px/s）
  },
  onToggle:    (self) => document.body.classList.toggle("in-view", self.isActive),
  onRefresh:   (self) => {},   // 每次重算位置后
});
```

### 配置项速查

| 属性 | 说明 |
|---|---|
| `trigger` | 参照元素（选择器或 DOM 节点） |
| `start` / `end` | 起止位置，支持字符串、数字、函数 |
| `scrub` | `true` 或数字，动画跟随滚动条 |
| `pin` | 钉住元素 |
| `pinSpacing` | `false` 取消占位（叠加效果） |
| `markers` | 调试标记 |
| `toggleActions` | 四段行为：enter / leave / enterBack / leaveBack |
| `toggleClass` | `"active"` 或 `{targets: ".nav", className: "on"}` |
| `once` | 只触发一次后销毁 |
| `snap` | 吸附：数字、数组、`{snapTo, duration, ease}` |
| `invalidateOnRefresh` | 刷新时重算 from/to 值（响应式必备） |
| `anticipatePin` | 0–1，预判 pin，减少抖动 |
| `scroller` | 自定义滚动容器（默认 window） |
| `id` | 给标记命名，方便调试多个触发器 |
| `refreshPriority` | 多个 pin 时控制刷新顺序（数字越大越先） |

### 静态方法

```js
ScrollTrigger.refresh();              // 重算所有位置（DOM 变化后必调）
ScrollTrigger.getAll();               // 获取全部实例
ScrollTrigger.getById("hero");        // 按 id 获取
ScrollTrigger.killAll();              // 全部销毁
ScrollTrigger.update();               // 手动更新
ScrollTrigger.sort();                 // 按位置排序（pin 顺序问题时用）
ScrollTrigger.config({ ignoreMobileResize: true }); // 忽略手机地址栏伸缩导致的 resize
ScrollTrigger.normalizeScroll(true);  // 接管滚动，解决移动端地址栏抖动
```

---

## 10. 配合平滑滚动（Lenis / ScrollSmoother）

### Lenis（最常用的第三方方案）

```js
import Lenis from "lenis";

const lenis = new Lenis({ duration: 1.1, smoothWheel: true });

// 把 Lenis 的 raf 交给 GSAP ticker 驱动（避免两套 rAF 打架）
lenis.on("scroll", ScrollTrigger.update);
gsap.ticker.add((time) => lenis.raf(time * 1000));
gsap.ticker.lagSmoothing(0);
```

三行是关键：
1. `lenis.on("scroll", ScrollTrigger.update)` —— Lenis 滚动时通知 ScrollTrigger；
2. 用 `gsap.ticker` 驱动 Lenis 的 raf —— 帧同步；
3. `lagSmoothing(0)` —— 关掉 GSAP 的掉帧补偿，否则慢帧时会跳。

### ScrollSmoother（GSAP 官方，现已免费）

```html
<div id="smooth-wrapper">
  <div id="smooth-content">
    <!-- 所有页面内容 -->
  </div>
</div>
```

```js
gsap.registerPlugin(ScrollTrigger, ScrollSmoother);

ScrollSmoother.create({
  wrapper: "#smooth-wrapper",
  content: "#smooth-content",
  smooth: 1.2,
  effects: true,          // 支持 data-speed / data-lag 属性
  normalizeScroll: true,
});
```

用了 `effects: true` 后可以直接在 HTML 上写视差：

```html
<img src="bg.jpg" data-speed="0.8">
<h2 data-speed="1.2">标题走得更快</h2>
<div data-lag="0.3">滞后跟随</div>
```

---

## 11. 常见坑与排错清单

### ① 图片/字体加载完后位置全错

图片没设尺寸 → 加载后页面高度变化 → start/end 算错。

```js
window.addEventListener("load", () => ScrollTrigger.refresh());
// 或者：给 img 加 width/height 属性 + aspect-ratio
```

任何动态改变页面高度的操作（手风琴展开、Ajax 加载、Tab 切换）后都要：

```js
ScrollTrigger.refresh();
```

### ② 所有元素一起动了

`trigger` 写成了类选择器而不是当前 `el`，见 [第 6 节](#6-批量处理进场动画的正确写法)。

### ③ pin 抖动 / 白屏跳动

- 加 `anticipatePin: 1`
- 检查祖先元素有没有 `overflow: hidden` / `transform` / `position: sticky`
- 移动端加 `ScrollTrigger.config({ ignoreMobileResize: true })`
- 极端情况用 `ScrollTrigger.normalizeScroll(true)`

### ④ 用了平滑滚动，ScrollTrigger 不响应

没接 `lenis.on("scroll", ScrollTrigger.update)`，见第 10 节。

### ⑤ SPA / React 路由切换后动画残留

用 `gsap.context()` 做作用域清理：

```js
useEffect(() => {
  const ctx = gsap.context(() => {
    gsap.from(".card", { y: 50, opacity: 0,
      scrollTrigger: { trigger: ".card", start: "top 85%" } });
  }, containerRef);           // 作用域限定在这个 ref 内
  return () => ctx.revert();  // 卸载时清理全部动画 + ScrollTrigger
}, []);
```

### ⑥ 响应式 resize 后动画值不对

```js
scrollTrigger: { invalidateOnRefresh: true }
// 或把值写成函数：y: () => window.innerHeight * 0.5
```

### ⑦ 元素闪一下才隐藏（FOUC）

用 `from` 时，JS 执行前元素是可见的。解决：

```css
.reveal { visibility: hidden; }
.gsap-ready .reveal { visibility: visible; }
```

```js
document.documentElement.classList.add("gsap-ready");
```

或直接改用 CSS 写初始态 + `gsap.to()`。

### ⑧ 性能

- 只动 `transform`（x/y/scale/rotate）和 `opacity`，别动 `top/left/width/height`
- `will-change: transform` 别滥用，GSAP 会自动加 `force3D`
- 大量元素用 `ScrollTrigger.batch()` 而不是 N 个实例
- `onUpdate` 里别做 DOM 查询，提前缓存

---

## 12. 实战片段合集

### 阅读进度条

```js
gsap.to(".progress-bar", {
  scaleX: 1,
  ease: "none",
  transformOrigin: "left center",
  scrollTrigger: { trigger: "body", start: "top top", end: "bottom bottom", scrub: .3 },
});
```

```css
.progress-bar { position: fixed; top: 0; left: 0; height: 3px; width: 100%;
                background: #000; transform: scaleX(0); transform-origin: left; z-index: 999; }
```

### 背景视差

```js
gsap.to(".hero-bg", {
  yPercent: 25,
  ease: "none",
  scrollTrigger: { trigger: ".hero", start: "top top", end: "bottom top", scrub: true },
});
```

### 滚动到某处切换 header 样式

```js
ScrollTrigger.create({
  start: "top -80",
  end: 99999,
  toggleClass: { targets: ".site-header", className: "is-scrolled" },
});
```

### 向下滚隐藏 header、向上滚显示

```js
const header = document.querySelector(".site-header");
ScrollTrigger.create({
  start: "top -200",
  end: 99999,
  onUpdate: (self) => {
    gsap.to(header, { yPercent: self.direction === 1 ? -100 : 0, duration: .4, ease: "power2.out" });
  },
});
```

### 数字滚动计数

```js
const counter = { val: 0 };
gsap.to(counter, {
  val: 1280,
  duration: 2,
  ease: "power1.out",
  snap: { val: 1 },
  onUpdate: () => (document.querySelector(".num").textContent = counter.val),
  scrollTrigger: { trigger: ".num", start: "top 85%", once: true },
});
```

### 逐字/逐行文字进场（不用 SplitText 的简易版）

```js
document.querySelectorAll(".split").forEach((el) => {
  el.innerHTML = el.textContent
    .split(" ")
    .map((w) => `<span class="w"><i>${w}</i></span>`)
    .join(" ");

  gsap.from(el.querySelectorAll("i"), {
    yPercent: 110,
    duration: .9,
    ease: "power3.out",
    stagger: .04,
    scrollTrigger: { trigger: el, start: "top 85%", once: true },
  });
});
```

```css
.split .w { display: inline-block; overflow: hidden; }
.split .w i { display: inline-block; font-style: normal; }
```

> 官方 SplitText 插件现在也免费了，正式项目建议直接用 `SplitText.create(el, {type:"lines,words"})`，处理换行和多语言更稳。

### 卡片堆叠（pinSpacing: false）

```js
gsap.utils.toArray(".stack-card").forEach((card, i, arr) => {
  ScrollTrigger.create({
    trigger: card,
    start: "top top",
    end: "bottom top",
    pin: true,
    pinSpacing: false,
  });
  if (i < arr.length - 1) {
    gsap.to(card, {
      scale: .9, opacity: .5, ease: "none",
      scrollTrigger: { trigger: card, start: "top top", end: "bottom top", scrub: true },
    });
  }
});
```

### 视频随滚动播放

```js
const video = document.querySelector("video");
video.pause();
video.addEventListener("loadedmetadata", () => {
  gsap.to(video, {
    currentTime: video.duration,
    ease: "none",
    scrollTrigger: { trigger: ".video-sec", start: "top top", end: "+=2000", pin: true, scrub: 1 },
  });
});
```

---

## 学习路径建议

1. 先只用 `gsap.from + scrollTrigger.start`，把进场动画做熟；
2. 再学 `scrub`，做视差和进度类效果；
3. 然后是 `pin` + `timeline`，做整屏叙事；
4. 最后补 `matchMedia`、`batch`、`context` 这些工程化的部分。

**官方资源：**

- 文档：https://gsap.com/docs/v3/Plugins/ScrollTrigger/
- 配置项交互演示：https://gsap.com/docs/v3/Plugins/ScrollTrigger/#demos
- 缓动曲线可视化：https://gsap.com/docs/v3/Eases
- 论坛（提问必贴 CodePen）：https://gsap.com/community/
