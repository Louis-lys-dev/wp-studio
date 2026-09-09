/**
 * 前台脚本。
 *
 * 由 functions/assets.php 以句柄 `mytheme` 加载，带 defer、输出在页脚，
 * 所以执行时 DOM 已经就绪，不需要再包 DOMContentLoaded 监听。
 *
 * PHP 传过来的数据在全局变量 `ThemeData` 上（接口地址、nonce、首页地址），
 * 它由一段内联脚本输出在本文件之前，可以直接读。
 */
