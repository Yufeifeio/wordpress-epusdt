<div align="center">
  <img src="https://capsule-render.vercel.app/api?type=waving&height=220&color=0:0B132B,50:1C2541,100:3A506B&text=wordpress-epusdt&fontColor=ffffff&fontSize=40&fontAlign=50&fontAlignY=40&desc=WordPress%20%E7%89%88%20EPusdt%20WooCommerce%20%E6%8F%92%E4%BB%B6&descAlign=50&descAlignY=62" alt="banner" />
</div>

<div align="center">
  <img src="https://img.shields.io/badge/WordPress-WooCommerce%20%E6%8F%92%E4%BB%B6-203A43?style=for-the-badge" alt="WordPress WooCommerce 插件" />
  <img src="https://img.shields.io/badge/EPusdt-Callback%20Ready-26A17B?style=for-the-badge&logo=tether&logoColor=white" alt="EPusdt Callback Ready" />
  <img src="https://img.shields.io/badge/GMPay-Official%20API-0F2027?style=for-the-badge" alt="GMPay Official API" />
</div>

# wordpress-epusdt

![](assets/icon/telegram.svg) 鱼肥肥 [@pyufc](https://t.me/pyufc) 这是一个面向 WordPress / WooCommerce 的 EPusdt 支付插件，使用 GMPay 官方 EPusdt 接口完成下单、跳转和回调。

<p align="center">
  <img src="assets/icon/usdt.ico" width="84" alt="USDT Icon" />
</p>

## 说明

本仓库提供 WordPress 版 EPusdt 插件发布包，适用于 WooCommerce 商店场景。安装后即可在 WooCommerce 支付方式中启用 EPusdt。

## 功能

- 对接 EPusdt 的 GMPay 官方下单接口
- 支持异步回调验签
- 支持 WooCommerce 经典结账页
- 支持 WooCommerce Blocks 结账页
- 支持 WooCommerce HPOS

## 仓库结构

- `plugins/wordpress-epusdt/`：插件源码目录
- `releases/wordpress-epusdt-plugin-v1.0.4.zip`：可直接上传安装的发布包
- `assets/icon/`：README 展示资源

## 安装

1. 先在 WordPress 安装并启用 WooCommerce。
2. 上传 `releases/wordpress-epusdt-plugin-v1.0.4.zip` 到 WordPress 插件安装页，或把 `plugins/wordpress-epusdt/` 整个目录放入 `wp-content/plugins/`。
3. 启用 `EPusdt` 插件。
4. 进入 `WooCommerce > 设置 > 支付 > EPusdt`。
5. 配置：
   - `API 地址`：EPusdt 根地址或完整 `payments/gmpay/v1/order/create-transaction` 接口地址
   - `PID`：EPusdt API Key 中的 PID
   - `密钥`：对应 PID 的 `secret_key`
   - 可选 `token`、`network`、`currency`
6. 保存后启用支付方式。

## 配置示例

- `API 地址`：`https://your-epusdt-domain.com/`
- `PID`：`1001`
- `密钥`：你在 EPusdt 后台创建 API Key 时得到的 `secret_key`
- `token`：`usdt`
- `network`：`tron`
- `currency`：`cny`

## 回调说明

- 异步通知地址：`/wc-api/wordpress_epusdt_notify/`
- GMPay 主回调为 `POST JSON`
- 验签通过且金额一致后，WooCommerce 订单会自动更新为已付款状态
- 异步回调成功返回 `success`

## 接口说明

- 支持将 `API 地址` 填写为站点根地址或完整的 GMPay 官方接口地址
- 插件默认使用 `POST /payments/gmpay/v1/order/create-transaction`
- 若同时填写 `token` 与 `network`，会直接创建具体链上订单
- 若两者留空，将按上游 GMPay 默认行为创建待选择支付网络的订单

## 发布包

- [点击查看仓库中的发布包](releases/wordpress-epusdt-plugin-v1.0.4.zip)
