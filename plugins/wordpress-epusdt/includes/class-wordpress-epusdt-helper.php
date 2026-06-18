<?php
if (!defined('ABSPATH')) {
	exit;
}

class WordPress_EPUSDT_Helper {
	const META_OUT_TRADE_NO = '_wordpress_epusdt_out_trade_no';
	const META_PAYMENT_URL = '_wordpress_epusdt_payment_url';
	const META_CREATED_AT = '_wordpress_epusdt_created_at';
	const META_ATTEMPTS = '_wordpress_epusdt_attempts';
	const META_PID = '_wordpress_epusdt_pid';
	const META_TRADE_NO = '_wordpress_epusdt_trade_no';

	public static function get_settings() {
		$settings = get_option('woocommerce_wordpress_epusdt_settings', array());

		if (!is_array($settings)) {
			$settings = array();
		}

		return wp_parse_args(
			$settings,
			array(
				'enabled'             => 'no',
				'title'               => 'EPusdt',
				'description'         => '使用 EPusdt 完成 USDT 支付。',
				'api_url'             => '',
				'pid'                 => '',
				'secret_key'          => '',
				'token'               => '',
				'network'             => '',
				'currency'            => '',
				'order_prefix'        => 'wpeu',
				'debug'               => 'no',
			)
		);
	}

	public static function get_wc_api_url($endpoint) {
		if (function_exists('WC') && WC()) {
			return WC()->api_request_url($endpoint);
		}

		return add_query_arg('wc-api', $endpoint, home_url('/'));
	}

	public static function get_order_name($order) {
		$site_name = trim(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
		$order_ref = is_object($order) && method_exists($order, 'get_order_number') ? $order->get_order_number() : '';
		$name = trim(sprintf('%s Order #%s', $site_name, $order_ref));
		$name = wp_strip_all_tags($name);

		if ($name === '') {
			$name = 'Order';
		}

		if (function_exists('mb_substr')) {
			return mb_substr($name, 0, 120);
		}

		return substr($name, 0, 120);
	}

	public static function make_gmpay_signature($params, $secret_key) {
		if (!is_array($params)) {
			$params = array();
		}

		unset($params['signature']);
		ksort($params, SORT_STRING);

		$pairs = array();
		foreach ($params as $key => $value) {
			if ($value === '' || $value === null) {
				continue;
			}

			if (is_bool($value)) {
				$value = $value ? '1' : '0';
			}

			$pairs[] = $key . '=' . $value;
		}

		return strtolower(md5(implode('&', $pairs) . (string) $secret_key));
	}

	public static function verify_gmpay_signature($params, $secret_key) {
		if (empty($params['signature']) || $secret_key === '') {
			return false;
		}

		$sign = strtolower(trim((string) $params['signature']));
		$expected = self::make_gmpay_signature($params, $secret_key);

		if (function_exists('hash_equals')) {
			return hash_equals($expected, $sign);
		}

		return $expected === $sign;
	}

	public static function normalize_url($url) {
		$url = trim((string) $url);

		if ($url === '') {
			throw new Exception('EPusdt API 地址不能为空。');
		}

		if (stripos($url, 'http://') !== 0 && stripos($url, 'https://') !== 0) {
			throw new Exception('EPusdt API 地址必须以 http:// 或 https:// 开头。');
		}

		return rtrim($url, '/');
	}

	public static function resolve_gateway_config($url) {
		$url = self::normalize_url($url);
		$path = (string) parse_url($url, PHP_URL_PATH);
		$path = rtrim($path, '/');

		if ($path && preg_match('#/payments/gmpay/v1/order/create-transaction$#i', $path)) {
			$submit_url = $url;
		} else {
			$submit_url = rtrim($url, '/') . '/payments/gmpay/v1/order/create-transaction';
		}

		return array(
			'submit_url'    => $submit_url,
			'checkout_base' => self::derive_checkout_base($url),
		);
	}

	public static function derive_checkout_base($url) {
		$url = self::normalize_url($url);
		$parts = parse_url($url);

		if (empty($parts['scheme']) || empty($parts['host'])) {
			return $url;
		}

		$origin = $parts['scheme'] . '://' . $parts['host'];
		if (isset($parts['port'])) {
			$origin .= ':' . $parts['port'];
		}

		$path = isset($parts['path']) ? rtrim($parts['path'], '/') : '/';
		if ($path === '' || $path === '/') {
			return $origin . '/';
		}

		$markers = array(
			'/payments/gmpay/v1/order/create-transaction',
		);

		foreach ($markers as $marker) {
			if (self::ends_with(strtolower($path), strtolower($marker))) {
				$prefix = substr($path, 0, -strlen($marker));
				return $origin . (empty($prefix) ? '/' : rtrim($prefix, '/') . '/');
			}
		}

		return $origin . rtrim($path, '/') . '/';
	}

	public static function build_public_checkout_url($location, $public_base_url, $checkout_base) {
		$location = trim((string) $location);
		if ($location === '') {
			throw new Exception('Epusdt did not return a checkout URL.');
		}

		if (stripos($location, 'http://') === 0 || stripos($location, 'https://') === 0) {
			return $location;
		}

		$base = trim((string) $public_base_url);
		if ($base === '') {
			$base = $checkout_base;
		} else {
			$base = self::derive_checkout_base($base);
		}

		if ($base === '' || self::is_local_base_url($base)) {
			throw new Exception('A public Epusdt cashier URL is required when the API URL is internal.');
		}

		return self::join_url($base, $location);
	}

	public static function join_url($base, $path) {
		$path = trim((string) $path);
		if ($path === '') {
			return rtrim($base, '/') . '/';
		}

		if (stripos($path, 'http://') === 0 || stripos($path, 'https://') === 0) {
			return $path;
		}

		if (strpos($path, '//') === 0) {
			$scheme = parse_url($base, PHP_URL_SCHEME);
			return ($scheme ? $scheme : 'https') . ':' . $path;
		}

		if (substr($path, 0, 1) === '/') {
			$parts = parse_url($base);
			if (empty($parts['scheme']) || empty($parts['host'])) {
				return $path;
			}

			$origin = $parts['scheme'] . '://' . $parts['host'];
			if (isset($parts['port'])) {
				$origin .= ':' . $parts['port'];
			}

			return $origin . $path;
		}

		return rtrim($base, '/') . '/' . ltrim($path, '/');
	}

	public static function is_local_base_url($url) {
		$host = parse_url($url, PHP_URL_HOST);
		if (empty($host)) {
			return false;
		}

		if (in_array($host, array('127.0.0.1', 'localhost', '::1'), true)) {
			return true;
		}

		if (filter_var($host, FILTER_VALIDATE_IP) === false) {
			return false;
		}

		return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
	}

	public static function ends_with($haystack, $needle) {
		if ($needle === '') {
			return true;
		}

		$length = strlen($needle);
		return substr($haystack, -$length) === $needle;
	}

	public static function amount_matches($left, $right) {
		return abs((float) $left - (float) $right) < 0.0001;
	}

	public static function generate_out_trade_no($order, $prefix) {
		$prefix = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $prefix));
		if ($prefix === '') {
			$prefix = 'wpeu';
		}

		$prefix = substr($prefix, 0, 6);
		$order_id = is_object($order) && method_exists($order, 'get_id') ? absint($order->get_id()) : 0;

		return sprintf('%s_%d_%s%02d', $prefix, $order_id, gmdate('ymdHis'), wp_rand(10, 99));
	}

	public static function parse_order_id_from_out_trade_no($out_trade_no) {
		$out_trade_no = trim((string) $out_trade_no);

		if (preg_match('/^[A-Za-z0-9]{1,6}_(\d+)_[0-9]+$/', $out_trade_no, $matches)) {
			return absint($matches[1]);
		}

		return 0;
	}

	public static function get_attempts($order) {
		$attempts = is_object($order) && method_exists($order, 'get_meta') ? $order->get_meta(self::META_ATTEMPTS, true) : array();

		if (!is_array($attempts)) {
			$attempts = array();
		}

		return $attempts;
	}

	public static function has_attempt($order, $out_trade_no) {
		$attempts = self::get_attempts($order);
		return isset($attempts[$out_trade_no]);
	}

	public static function remember_attempt($order, $out_trade_no, $payment_url) {
		$attempts = self::get_attempts($order);
		$attempts[$out_trade_no] = time();
		arsort($attempts);
		$attempts = array_slice($attempts, 0, 10, true);

		$order->update_meta_data(self::META_ATTEMPTS, $attempts);
		$order->update_meta_data(self::META_OUT_TRADE_NO, $out_trade_no);
		$order->update_meta_data(self::META_PAYMENT_URL, esc_url_raw($payment_url));
		$order->update_meta_data(self::META_CREATED_AT, time());
	}

	public static function get_recent_attempt($order, $ttl) {
		$out_trade_no = (string) $order->get_meta(self::META_OUT_TRADE_NO, true);
		$payment_url = (string) $order->get_meta(self::META_PAYMENT_URL, true);
		$created_at = (int) $order->get_meta(self::META_CREATED_AT, true);

		if ($out_trade_no === '' || $payment_url === '' || $created_at <= 0) {
			return false;
		}

		if ((time() - $created_at) > absint($ttl)) {
			return false;
		}

		return array(
			'out_trade_no' => $out_trade_no,
			'payment_url'  => $payment_url,
		);
	}

	public static function log($message, $context = array(), $level = 'info') {
		$settings = self::get_settings();
		if (($settings['debug'] ?? 'no') !== 'yes' || !function_exists('wc_get_logger')) {
			return;
		}

		$line = (string) $message;
		if (!empty($context)) {
			$line .= ' ' . wp_json_encode($context);
		}

		$logger = wc_get_logger();
		$logger->log($level, $line, array('source' => 'wordpress-epusdt'));
	}

	public static function extract_message_from_body($body) {
		$body = trim((string) $body);
		if ($body === '') {
			return '';
		}

		$json = json_decode($body, true);
		if (!is_array($json)) {
			return '';
		}

		if (!empty($json['message']) && is_string($json['message'])) {
			return $json['message'];
		}

		if (!empty($json['data']['message']) && is_string($json['data']['message'])) {
			return $json['data']['message'];
		}

		return '';
	}
}
