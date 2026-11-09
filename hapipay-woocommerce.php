<?php
/**
 * Plugin Name:       HapiPay pour WooCommerce
 * Plugin URI:        https://github.com/hapipay/hapipay-woocommerce
 * Description:       Encaissez vos commandes par mobile money, carte bancaire et PayPal avec HapiPay. L'argent arrive directement sur votre propre compte opérateur, sans commission.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            HapiPay
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hapipay-woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   10.3
 */

defined( 'ABSPATH' ) || exit;

define( 'HAPIPAY_WC_VERSION', '0.1.0' );
define( 'HAPIPAY_WC_FILE', __FILE__ );
define( 'HAPIPAY_WC_DIR', plugin_dir_path( __FILE__ ) );

// Compatible avec le stockage des commandes de WooCommerce (HPOS) et le paiement en blocs.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', HAPIPAY_WC_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', HAPIPAY_WC_FILE, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'HapiPay pour WooCommerce a besoin de WooCommerce : installez-le et activez-le d\'abord.', 'hapipay-woocommerce' ) . '</p></div>';
		} );

		return;
	}

	require_once HAPIPAY_WC_DIR . 'includes/class-hapipay-api.php';
	require_once HAPIPAY_WC_DIR . 'includes/class-hapipay-gateway.php';
	require_once HAPIPAY_WC_DIR . 'includes/class-hapipay-webhook.php';

	add_filter( 'woocommerce_payment_gateways', function ( array $gateways ) {
		$gateways[] = HapiPay_Gateway::class;

		return $gateways;
	} );

	HapiPay_Webhook::register();
} );

// Paiement en blocs (le tunnel par défaut depuis WooCommerce 8.3).
add_action( 'woocommerce_blocks_loaded', function () {
	if ( ! class_exists( \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class ) ) {
		return;
	}

	require_once HAPIPAY_WC_DIR . 'includes/class-hapipay-blocks.php';

	add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
		$registry->register( new HapiPay_Blocks() );
	} );
} );

// Lien « Réglages » dans la liste des extensions.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ) {
	$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=hapipay' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Réglages', 'hapipay-woocommerce' ) . '</a>' );

	return $links;
} );
