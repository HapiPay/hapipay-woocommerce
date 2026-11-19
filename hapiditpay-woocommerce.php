<?php
/**
 * Plugin Name:       HapiditPay pour WooCommerce
 * Plugin URI:        https://hapiditpay.com/integrations
 * Description:       Encaissez vos commandes par mobile money, carte bancaire et PayPal avec HapiditPay. L'argent arrive directement sur votre propre compte opérateur, sans commission.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Hapidit Group
 * Author URI:        https://hapiditpay.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hapiditpay-woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   11.3
 */

defined( 'ABSPATH' ) || exit;

define( 'HAPIDITPAY_WC_VERSION', '0.2.0' );
define( 'HAPIDITPAY_WC_FILE', __FILE__ );
define( 'HAPIDITPAY_WC_DIR', plugin_dir_path( __FILE__ ) );

// Compatible avec le stockage des commandes de WooCommerce (HPOS) et le paiement en blocs.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', HAPIDITPAY_WC_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', HAPIDITPAY_WC_FILE, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'HapiditPay pour WooCommerce a besoin de WooCommerce : installez-le et activez-le d\'abord.', 'hapiditpay-woocommerce' ) . '</p></div>';
		} );

		return;
	}

	require_once HAPIDITPAY_WC_DIR . 'includes/class-hapiditpay-api.php';
	require_once HAPIDITPAY_WC_DIR . 'includes/class-hapiditpay-gateway.php';
	require_once HAPIDITPAY_WC_DIR . 'includes/class-hapiditpay-webhook.php';
	require_once HAPIDITPAY_WC_DIR . 'includes/class-hapiditpay-upgrade.php';

	// Réglages et commandes de l'extension sous son ancien nom, repris avant tout le reste.
	HapiditPay_Upgrade::register();

	add_filter( 'woocommerce_payment_gateways', function ( array $gateways ) {
		$gateways[] = HapiditPay_Gateway::class;

		return $gateways;
	} );

	HapiditPay_Webhook::register();
} );

// Paiement en blocs (le tunnel par défaut depuis WooCommerce 8.3).
add_action( 'woocommerce_blocks_loaded', function () {
	if ( ! class_exists( \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class ) ) {
		return;
	}

	require_once HAPIDITPAY_WC_DIR . 'includes/class-hapiditpay-blocks.php';

	add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
		$registry->register( new HapiditPay_Blocks() );
	} );
} );

// Liens « Réglages » et « Mon espace HapiditPay » dans la liste des extensions.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ) {
	$url  = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=hapiditpay' );
	$site = class_exists( 'HapiditPay_Api' ) ? ( new HapiditPay_Api( '', '' ) )->site_url( 'portail' ) : 'https://hapiditpay.com/portail';
	array_unshift(
		$links,
		'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Réglages', 'hapiditpay-woocommerce' ) . '</a>',
		'<a href="' . esc_url( $site ) . '" target="_blank" rel="noopener">' . esc_html__( 'Mon espace HapiditPay', 'hapiditpay-woocommerce' ) . '</a>'
	);

	return $links;
} );
