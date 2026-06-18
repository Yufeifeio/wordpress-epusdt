<?php
if (!defined('ABSPATH')) {
	exit;
}

class WordPress_EPUSDT_Gateway extends WC_Payment_Gateway {
	const REUSE_WINDOW = 900;

	public function __construct() {
		$this->id                 = 'wordpress_epusdt';
		$this->icon               = WORDPRESS_EPUSDT_URL . 'assets/images/usdt.ico';
		$this->has_fields         = false;
		$this->method_title       = 'EPusdt';
		$this->method_description = '通过 GMPay 官方 EPusdt 接口接入 USDT 支付。';
		$this->supports           = array('products');

		$this->init_form_fields();
		$this->init_settings();

		$this->title               = $this->get_option('title', 'EPusdt');
		$this->description         = $this->get_option('description', '使用 EPusdt 完成 USDT 支付。');
		$this->enabled             = $this->get_option('enabled', 'no');
		$this->api_url             = $this->get_option('api_url', '');
		$this->pid                 = $this->get_option('pid', '');
		$this->secret_key          = $this->get_option('secret_key', '');
		$this->token               = $this->get_option('token', '');
		$this->network             = $this->get_option('network', '');
		$this->currency            = $this->get_option('currency', 'cny');
		$this->order_prefix        = $this->get_option('order_prefix', 'wpeu');
		$this->debug               = $this->get_option('debug', 'no');

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_thankyou_' . $this->id, array($this, 'render_thankyou_page'));
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __('启用/停用', 'wordpress-epusdt'),
				'type'    => 'checkbox',
				'label'   => __('启用 EPusdt 支付', 'wordpress-epusdt'),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __('名称', 'wordpress-epusdt'),
				'type'        => 'text',
				'description' => __('前台结账页向用户显示的支付名称。', 'wordpress-epusdt'),
				'default'     => 'EPusdt',
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __('描述', 'wordpress-epusdt'),
				'type'        => 'textarea',
				'description' => __('前台结账页向用户显示的支付说明。', 'wordpress-epusdt'),
				'default'     => '使用 EPusdt 完成 USDT 支付。',
			),
			'api_url' => array(
				'title'       => __('API 地址', 'wordpress-epusdt'),
				'type'        => 'text',
				'description' => __('填写 EPusdt 站点地址或完整的 GMPay create-transaction 接口地址。', 'wordpress-epusdt'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'pid' => array(
				'title'       => __('PID', 'wordpress-epusdt'),
				'type'        => 'text',
				'description' => __('EPusdt API Key 中的商户 PID。', 'wordpress-epusdt'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'secret_key' => array(
				'title'       => __('密钥', 'wordpress-epusdt'),
				'type'        => 'password',
				'description' => __('与 PID 对应的 secret_key。', 'wordpress-epusdt'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'token' => array(
				'title'       => __('币种', 'wordpress-epusdt'),
				'type'        => 'text',
				'description' => __('可选，例如 usdt。', 'wordpress-epusdt'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'network' => array(
				'title'       => __('网络', 'wordpress-epusdt'),
				'type'        => 'text',
				'description' => __('可选，例如 tron。', 'wordpress-epusdt'),
				'default'     => '',
				'desc_tip'    => true,
			),
			'currency' => array(
				'title'       => __('法币币种', 'wordpress-epusdt'),
				'type'        => 'text',
				'description' => __('可选商户法币币种，默认 cny。', 'wordpress-epusdt'),
				'default'     => 'cny',
				'desc_tip'    => true,
			),
			'order_prefix' => array(
				'title'       => __('订单前缀', 'wordpress-epusdt'),
				'type'        => 'text',
				'description' => __('用于 out_trade_no，仅允许字母和数字。', 'wordpress-epusdt'),
				'default'     => 'wpeu',
				'desc_tip'    => true,
			),
			'debug' => array(
				'title'       => __('调试日志', 'wordpress-epusdt'),
				'type'        => 'checkbox',
				'label'       => __('启用 WooCommerce 日志', 'wordpress-epusdt'),
				'default'     => 'no',
				'description' => __('日志会写入 WooCommerce > 状态 > 日志。', 'wordpress-epusdt'),
			),
		);
	}

	public function admin_options() {
		parent::admin_options();

		$notify_url = esc_url(WordPress_EPUSDT_Helper::get_wc_api_url('wordpress_epusdt_notify'));
		echo '<p><strong>' . esc_html__('异步通知地址', 'wordpress-epusdt') . ':</strong> <code>' . esc_html($notify_url) . '</code></p>';
	}

	public function is_available() {
		if ('yes' !== $this->enabled) {
			return false;
		}

		if (!parent::is_available()) {
			return false;
		}

		return !empty($this->api_url) && !empty($this->pid) && !empty($this->secret_key);
	}

	public function process_admin_options() {
		parent::process_admin_options();

		$settings = get_option($this->plugin_id . $this->id . '_settings', array());
		if (!is_array($settings)) {
			return;
		}

		if (!empty($settings['api_url'])) {
			$settings['api_url'] = trim($settings['api_url']);
		}
		if (!empty($settings['pid'])) {
			$settings['pid'] = trim($settings['pid']);
		}
		if (!empty($settings['token'])) {
			$settings['token'] = strtolower(trim($settings['token']));
		}
		if (!empty($settings['network'])) {
			$settings['network'] = strtolower(trim($settings['network']));
		}
		if (!empty($settings['currency'])) {
			$settings['currency'] = strtolower(trim($settings['currency']));
		}
		if (!empty($settings['order_prefix'])) {
			$settings['order_prefix'] = preg_replace('/[^a-zA-Z0-9]/', '', $settings['order_prefix']);
		}

		update_option($this->plugin_id . $this->id . '_settings', $settings);
	}

	public function process_payment($order_id) {
		$order = wc_get_order($order_id);
		if (!$order) {
			wc_add_notice(__('订单不存在。', 'wordpress-epusdt'), 'error');
			return array('result' => 'failure');
		}

		try {
			$result = $this->create_or_reuse_payment($order);
		} catch (Exception $e) {
			WordPress_EPUSDT_Helper::log('create payment failed', array('order_id' => $order_id, 'error' => $e->getMessage()), 'error');
			wc_add_notice($e->getMessage(), 'error');
			return array('result' => 'failure');
		}

		$order->update_status('on-hold', __('等待 EPusdt 确认付款。', 'wordpress-epusdt'));
		$order->save();

		return array(
			'result'   => 'success',
			'redirect' => $result['payment_url'],
		);
	}

	protected function create_or_reuse_payment($order) {
		$recent = WordPress_EPUSDT_Helper::get_recent_attempt($order, self::REUSE_WINDOW);
		if ($recent) {
			WordPress_EPUSDT_Helper::log('reuse payment url', array('order_id' => $order->get_id(), 'out_trade_no' => $recent['out_trade_no']));
			return $recent;
		}

		$gateway = WordPress_EPUSDT_Helper::resolve_gateway_config($this->api_url);
		$out_trade_no = WordPress_EPUSDT_Helper::generate_out_trade_no($order, $this->order_prefix);
		$notify_url = WordPress_EPUSDT_Helper::get_wc_api_url('wordpress_epusdt_notify');
		$return_url = $this->get_return_url($order);

		$params = array(
			'pid'          => trim((string) $this->pid),
			'order_id'     => $out_trade_no,
			'amount'       => (float) wc_format_decimal($order->get_total(), wc_get_price_decimals()),
			'notify_url'   => $notify_url,
			'redirect_url' => $return_url,
			'name'         => WordPress_EPUSDT_Helper::get_order_name($order),
			'currency'     => $this->currency !== '' ? strtolower(trim((string) $this->currency)) : 'cny',
		);

		if ($this->token !== '' && $this->network !== '') {
			$params['token'] = strtolower(trim((string) $this->token));
			$params['network'] = strtolower(trim((string) $this->network));
		}

		$params['signature'] = WordPress_EPUSDT_Helper::make_gmpay_signature($params, $this->secret_key);

		WordPress_EPUSDT_Helper::log('request create gmpay transaction', array('order_id' => $order->get_id(), 'url' => $gateway['submit_url'], 'out_trade_no' => $out_trade_no));

		$response = wp_remote_post(
			$gateway['submit_url'],
			array(
				'timeout'     => 20,
				'user-agent'  => 'wordpress-epusdt/' . WORDPRESS_EPUSDT_VERSION,
				'sslverify'   => true,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'        => wp_json_encode($params),
			)
		);

		if (is_wp_error($response)) {
			throw new Exception('EPusdt 请求失败：' . $response->get_error_message());
		}

		$status_code = (int) wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$decoded = json_decode($body, true);
		$payment_url = '';

		if (is_array($decoded) && !empty($decoded['data']['payment_url']) && is_string($decoded['data']['payment_url'])) {
			$payment_url = trim($decoded['data']['payment_url']);
		}

		if ($status_code < 200 || $status_code >= 300 || $payment_url === '') {
			$message = WordPress_EPUSDT_Helper::extract_message_from_body($body);
			if ($message === '') {
				$message = 'EPusdt 下单失败。';
			}

			WordPress_EPUSDT_Helper::log(
				'create transaction bad response',
				array(
					'order_id'    => $order->get_id(),
					'status_code' => $status_code,
					'body'        => $body,
				),
				'error'
			);

			throw new Exception($message);
		}

		$payment_url = WordPress_EPUSDT_Helper::join_url($gateway['checkout_base'], $payment_url);
		WordPress_EPUSDT_Helper::remember_attempt($order, $out_trade_no, $payment_url);
		$order->update_meta_data(WordPress_EPUSDT_Helper::META_PID, trim((string) $this->pid));
		$order->update_meta_data(WordPress_EPUSDT_Helper::META_TRADE_NO, '');
		$order->save();

		return array(
			'out_trade_no' => $out_trade_no,
			'payment_url'  => $payment_url,
		);
	}

	public function render_thankyou_page($order_id) {
		$order = wc_get_order($order_id);
		if (!$order || $order->get_payment_method() !== $this->id) {
			return;
		}

		if ($order->is_paid()) {
			echo '<p>' . esc_html__('支付已确认。', 'wordpress-epusdt') . '</p>';
			return;
		}

		$payment_url = (string) $order->get_meta(WordPress_EPUSDT_Helper::META_PAYMENT_URL, true);
		if ($payment_url === '') {
			return;
		}

		echo '<p>' . esc_html__('订单正在等待 USDT 付款。如果你关闭了收银台页面，可从这里继续：', 'wordpress-epusdt') . '</p>';
		echo '<p><a class="button alt" href="' . esc_url($payment_url) . '" target="_blank" rel="noopener">' . esc_html__('打开 EPusdt 收银台', 'wordpress-epusdt') . '</a></p>';
	}
}
