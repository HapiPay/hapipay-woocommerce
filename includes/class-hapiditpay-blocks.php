<?php
/**
 * HapiditPay dans le tunnel de paiement « en blocs » de WooCommerce.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class HapiditPay_Blocks extends AbstractPaymentMethodType {

	protected $name = 'hapiditpay';

	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_hapiditpay_settings', [] );
	}

	public function is_active(): bool {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];

		return isset( $gateways['hapiditpay'] ) && $gateways['hapiditpay']->is_available();
	}

	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'hapiditpay-blocks',
			plugins_url( 'assets/blocks.js', HAPIDITPAY_WC_FILE ),
			[ 'wc-blocks-registry', 'wp-element', 'wp-html-entities' ],
			HAPIDITPAY_WC_VERSION,
			true
		);

		return [ 'hapiditpay-blocks' ];
	}

	public function get_payment_method_data(): array {
		return [
			'title'       => $this->settings['title'] ?? 'HapiditPay',
			'description' => $this->settings['description'] ?? '',
			'icon'        => plugins_url( 'assets/hapiditpay.svg', HAPIDITPAY_WC_FILE ),
		];
	}
}
