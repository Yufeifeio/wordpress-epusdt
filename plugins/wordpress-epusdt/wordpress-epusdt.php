<?php
/**
 * Plugin Name: EPusdt
 * Plugin URI: https://github.com/Yufeifeio/wordpress-epusdt
 * Description: 基于 GMPay 官方接口的 EPusdt WooCommerce 支付插件。
 * Version: 1.0.4
 * Author: Yufeifeio
 * Author URI: https://github.com/Yufeifeio
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 6.0
 * WC tested up to: 10.8
 * Text Domain: wordpress-epusdt
 */

if (!defined('ABSPATH')) {
	exit;
}

define('WORDPRESS_EPUSDT_VERSION', '1.0.4');
define('WORDPRESS_EPUSDT_FILE', __FILE__);
define('WORDPRESS_EPUSDT_PATH', plugin_dir_path(__FILE__));
define('WORDPRESS_EPUSDT_URL', plugin_dir_url(__FILE__));

require_once WORDPRESS_EPUSDT_PATH . 'includes/class-wordpress-epusdt-helper.php';

function wordpress_epusdt_plugins_loaded() {
	if (!class_exists('WC_Payment_Gateway')) {
		add_action('admin_notices', 'wordpress_epusdt_missing_wc_notice');
		return;
	}

	require_once WORDPRESS_EPUSDT_PATH . 'includes/class-wordpress-epusdt-gateway.php';

	add_filter('woocommerce_payment_gateways', 'wordpress_epusdt_add_gateway');
	add_filter('plugin_action_links_' . plugin_basename(WORDPRESS_EPUSDT_FILE), 'wordpress_epusdt_action_links');
	add_action('woocommerce_api_wordpress_epusdt_notify', 'wordpress_epusdt_handle_notify');
	add_action('before_woocommerce_init', 'wordpress_epusdt_declare_hpos_compatibility');
	if (did_action('woocommerce_blocks_loaded')) {
		wordpress_epusdt_register_blocks_support();
	} else {
		add_action('woocommerce_blocks_loaded', 'wordpress_epusdt_register_blocks_support');
	}
}
add_action('plugins_loaded', 'wordpress_epusdt_plugins_loaded', 20);

function wordpress_epusdt_add_gateway($gateways) {
	$gateways[] = 'WordPress_EPUSDT_Gateway';
	return $gateways;
}

function wordpress_epusdt_action_links($links) {
	$settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=wordpress_epusdt');
	array_unshift(
		$links,
		sprintf(
			'<a href="%s">%s</a>',
			esc_url($settings_url),
			esc_html__('设置', 'wordpress-epusdt')
		)
	);

	return $links;
}

function wordpress_epusdt_missing_wc_notice() {
	if (!current_user_can('activate_plugins')) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__('EPusdt 需要先安装并启用 WooCommerce。', 'wordpress-epusdt');
	echo '</p></div>';
}

function wordpress_epusdt_declare_hpos_compatibility() {
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WORDPRESS_EPUSDT_FILE, true);
	}
}

function wordpress_epusdt_register_blocks_support() {
	if (!class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		return;
	}

	require_once WORDPRESS_EPUSDT_PATH . 'includes/class-wordpress-epusdt-blocks.php';
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function ($payment_method_registry) {
			$payment_method_registry->register(new WordPress_EPUSDT_Blocks_Support());
		}
	);
}

function wordpress_epusdt_handle_notify() {
	if (!function_exists('wc_get_order')) {
		status_header(500);
		echo 'fail';
		exit;
	}

	$settings = WordPress_EPUSDT_Helper::get_settings();
	$data = !empty($_POST) ? wp_unslash($_POST) : array();
	if (empty($data)) {
		$raw_body = file_get_contents('php://input');
		if (is_string($raw_body) && trim($raw_body) !== '') {
			$json = json_decode($raw_body, true);
			if (is_array($json)) {
				$data = $json;
			}
		}
	}

	if (empty($data) || !is_array($data)) {
		WordPress_EPUSDT_Helper::log('notify missing payload', array(), 'error');
		status_header(400);
		echo 'fail';
		exit;
	}

	$secret_key = (string) ($settings['secret_key'] ?? '');
	if (!WordPress_EPUSDT_Helper::verify_gmpay_signature($data, $secret_key)) {
		WordPress_EPUSDT_Helper::log('notify invalid sign', $data, 'error');
		status_header(400);
		echo 'fail';
		exit;
	}

	$out_trade_no = isset($data['order_id']) ? sanitize_text_field((string) $data['order_id']) : '';
	$order_id = WordPress_EPUSDT_Helper::parse_order_id_from_out_trade_no($out_trade_no);
	$order = $order_id ? wc_get_order($order_id) : false;

	if (!$order) {
		WordPress_EPUSDT_Helper::log('notify order not found', array('out_trade_no' => $out_trade_no), 'error');
		status_header(404);
		echo 'fail';
		exit;
	}

	if ($order->get_payment_method() !== 'wordpress_epusdt') {
		WordPress_EPUSDT_Helper::log('notify wrong payment method', array('order_id' => $order_id), 'error');
		status_header(400);
		echo 'fail';
		exit;
	}

	if (!WordPress_EPUSDT_Helper::has_attempt($order, $out_trade_no)) {
		WordPress_EPUSDT_Helper::log('notify out_trade_no mismatch', array('order_id' => $order_id, 'out_trade_no' => $out_trade_no), 'error');
		status_header(400);
		echo 'fail';
		exit;
	}

	$money = isset($data['amount']) ? (string) $data['amount'] : '';
	$trade_status = isset($data['status']) ? (string) $data['status'] : '';
	$trade_no = !empty($data['block_transaction_id'])
		? sanitize_text_field((string) $data['block_transaction_id'])
		: (isset($data['trade_id']) ? sanitize_text_field((string) $data['trade_id']) : '');
	$pid = isset($data['pid']) ? sanitize_text_field((string) $data['pid']) : '';

	if ($pid !== '' && trim((string) ($settings['pid'] ?? '')) !== '' && trim((string) $settings['pid']) !== $pid) {
		WordPress_EPUSDT_Helper::log('notify pid mismatch', array('order_id' => $order_id, 'pid' => $pid), 'error');
		status_header(400);
		echo 'fail';
		exit;
	}

	$status_ok = ('2' === (string) $trade_status || 2 === (int) $trade_status);
	if (!$status_ok || !WordPress_EPUSDT_Helper::amount_matches($money, $order->get_total())) {
		WordPress_EPUSDT_Helper::log(
			'notify status or amount mismatch',
			array(
				'order_id'     => $order_id,
				'trade_status' => $trade_status,
				'money'        => $money,
				'order_total'  => $order->get_total(),
			),
			'error'
		);
		status_header(400);
		echo 'fail';
		exit;
	}

	if (!$order->is_paid()) {
		$order->payment_complete($trade_no);
		$order->update_meta_data(WordPress_EPUSDT_Helper::META_TRADE_NO, $trade_no);
		$order->add_order_note(sprintf('EPusdt 支付已确认，trade_no: %s', $trade_no));
		$order->save();
		WordPress_EPUSDT_Helper::log('notify payment complete', array('order_id' => $order_id, 'trade_no' => $trade_no));
	}

	status_header(200);
	echo 'success';
	exit;
}
