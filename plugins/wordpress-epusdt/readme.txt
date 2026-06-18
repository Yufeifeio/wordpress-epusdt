=== EPusdt ===
Contributors: Yufeifeio
Tags: woocommerce, payment gateway, usdt, epusdt, crypto
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

基于 EPay 兼容接口的 EPusdt WooCommerce 支付插件。

== Description ==

这个插件为 WooCommerce 增加 EPusdt 支付方式。

功能：

- 使用 EPusdt 的 EPay 兼容接口下单
- 支持内网 API 地址配合公网收银台地址
- 支持异步回调验签
- 支持同步返回兜底补单
- 支持经典结账页和 WooCommerce Blocks 结账页
- 兼容 WooCommerce HPOS

== Installation ==

1. 安装并启用 WooCommerce。
2. 在 WordPress 后台上传插件 zip，或把插件目录复制到 `wp-content/plugins/`。
3. 启用 `EPusdt` 插件。
4. 进入 `WooCommerce > 设置 > 支付 > EPusdt`。
5. 配置以下参数：
   - `API 地址`：EPusdt 根地址或完整 EPay `submit.php` 接口地址
   - `PID`：EPusdt API Key 对应的 PID
   - `密钥`：对应 PID 的 `secret_key`
   - `公网收银台地址`：当 API 地址是内网地址时必填
   - 可选的 `token`、`network`、`currency`
6. 保存并启用支付方式。

异步通知地址：

`/wc-api/wordpress_epusdt_notify`

后台支付设置页会显示完整地址。

== Frequently Asked Questions ==

= `token` 和 `network` 应该填什么？ =

常见值是 `usdt` 和 `tron`。如果你的 EPusdt 服务端已经配置默认值，也可以留空。

= 如果 API 地址是内网地址怎么办？ =

把 `公网收银台地址` 填成实际对外访问 EPusdt 收银台的域名即可。

== Changelog ==

= 1.0.0 =

- 首个版本
