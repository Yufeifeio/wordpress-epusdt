<?php
if (!defined('ABSPATH')) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WordPress_EPUSDT_Blocks_Support extends AbstractPaymentMethodType {
	protected $name = 'wordpress_epusdt';

	public function initialize() {
		$this->settings = get_option('woocommerce_wordpress_epusdt_settings', array());
	}

	public function is_active() {
		return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
	}

	public function get_payment_method_script_handles() {
		$asset_url = WORDPRESS_EPUSDT_URL . 'assets/js/blocks.js';
		wp_register_script(
			'wordpress-epusdt-blocks',
			$asset_url,
			array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'),
			WORDPRESS_EPUSDT_VERSION,
			true
		);

		return array('wordpress-epusdt-blocks');
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->settings['title'] ?? 'USDT',
			'description' => $this->settings['description'] ?? '使用 EPusdt 完成 USDT 支付。',
			'supports'    => array('products'),
		);
	}
}
