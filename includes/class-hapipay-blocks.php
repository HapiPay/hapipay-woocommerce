<?php
/**
 * HapiPay dans le tunnel de paiement « en blocs » de WooCommerce.
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class HapiPay_Blocks extends AbstractPaymentMethodType {

	protected $name = 'hapipay';

	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_hapipay_settings', [] );
	}

	public function is_active(): bool {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];

		return isset( $gateways['hapipay'] ) && $gateways['hapipay']->is_available();
	}

	public function get_payment_method_script_handles(): array {
		wp_register_script(
			'hapipay-blocks',
			plugins_url( 'assets/blocks.js', HAPIPAY_WC_FILE ),
			[ 'wc-blocks-registry', 'wp-element', 'wp-html-entities' ],
			HAPIPAY_WC_VERSION,
			true
		);

		return [ 'hapipay-blocks' ];
	}

	public function get_payment_method_data(): array {
		return [
			'title'       => $this->settings['title'] ?? 'HapiPay',
			'description' => $this->settings['description'] ?? '',
			'icon'        => plugins_url( 'assets/hapipay.svg', HAPIPAY_WC_FILE ),
		];
	}
}
