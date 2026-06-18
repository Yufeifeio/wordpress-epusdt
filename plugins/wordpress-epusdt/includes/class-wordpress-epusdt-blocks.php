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
		$asset_url = set_url_scheme(WORDPRESS_EPUSDT_URL . 'assets/js/blocks.js', is_ssl() ? 'https' : 'http');
		$asset_ver = file_exists(WORDPRESS_EPUSDT_PATH . 'assets/js/blocks.js')
			? (string) filemtime(WORDPRESS_EPUSDT_PATH . 'assets/js/blocks.js')
			: WORDPRESS_EPUSDT_VERSION;
		wp_register_script(
			'wordpress-epusdt-blocks',
			$asset_url,
			array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'),
			$asset_ver,
			true
		);

		return array('wordpress-epusdt-blocks');
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->settings['title'] ?? 'EPusdt',
			'description' => $this->settings['description'] ?? '使用 EPusdt 完成 USDT 支付。',
			'icon'        => set_url_scheme(WORDPRESS_EPUSDT_URL . 'assets/images/usdt.ico', is_ssl() ? 'https' : 'http'),
			'supports'    => array('products'),
		);
	}
}
