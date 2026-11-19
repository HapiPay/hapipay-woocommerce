<?php
/**
 * Reprise des réglages et des commandes de l'extension sous son ancien nom
 * (« HapiPay pour WooCommerce », jusqu'à la version 0.1.2).
 *
 * Seul endroit du code qui nomme l'ancienne extension : ses réglages, ses
 * métadonnées de commande, l'identifiant de son moyen de paiement et
 * l'adresse de son webhook. Tout est recopié sous le nouveau nom une seule
 * fois, puis l'ancien est effacé, pour qu'aucun commerçant ne perde ses
 * réglages ni le suivi de ses commandes en attente.
 */

defined( 'ABSPATH' ) || exit;

final class HapiditPay_Upgrade {

	/** Identifiant du moyen de paiement de l'ancienne extension. */
	private const OLD_ID = 'hapipay';

	/** Fichier principal de l'ancienne extension, quel que soit son dossier. */
	private const OLD_FILE = 'hapipay-woocommerce.php';

	/** Métadonnées de commande : ancien nom => nouveau nom. */
	private const META = [
		'_hapipay_reference' => '_hapiditpay_reference',
		'_hapipay_link'      => '_hapiditpay_link',
		'_hapipay_mode'      => '_hapiditpay_mode',
	];

	/** Anciennes adresses de l'API de production, remplacées par la nouvelle. */
	private const OLD_API_URLS = [ 'https://pay.scigroup.td/api/v1', 'https://pay.hapiditgroup.co/api/v1' ];

	/** Version dont la reprise a déjà été faite. */
	private const VERSION_OPTION = 'hapiditpay_wc_version';

	public static function register(): void {
		self::maybe_run();

		// Pas d'alias de l'ancienne adresse du webhook (coupure nette, D53) :
		// le commerçant déclare la nouvelle adresse dans son espace.
		add_action( 'admin_init', [ self::class, 'deactivate_old_plugin' ] );
	}

	/** Une seule fois par version : rien à faire si la reprise est déjà passée. */
	public static function maybe_run(): void {
		if ( get_option( self::VERSION_OPTION ) === HAPIDITPAY_WC_VERSION ) {
			return;
		}

		self::settings();
		self::gateway_order();
		self::orders();

		update_option( self::VERSION_OPTION, HAPIDITPAY_WC_VERSION );
	}

	/** Réglages du moyen de paiement : clé d'API, secret du webhook, textes de caisse. */
	private static function settings(): void {
		$old_name = 'woocommerce_' . self::OLD_ID . '_settings';
		$new_name = 'woocommerce_' . HapiditPay_Gateway::ID . '_settings';
		$old      = get_option( $old_name, null );

		if ( null === $old ) {
			return;
		}

		if ( is_array( $old ) && false === get_option( $new_name, false ) ) {
			if ( isset( $old['api_url'] ) && in_array( rtrim( trim( (string) $old['api_url'] ), '/' ), self::OLD_API_URLS, true ) ) {
				$old['api_url'] = HapiditPay_Api::DEFAULT_URL;
			}

			foreach ( [ 'title', 'description' ] as $text ) {
				if ( isset( $old[ $text ] ) ) {
					$old[ $text ] = str_replace( 'HapiPay', 'HapiditPay', (string) $old[ $text ] );
				}
			}

			add_option( $new_name, $old );
		}

		delete_option( $old_name );
	}

	/** Place du moyen de paiement dans la liste réordonnée par le commerçant. */
	private static function gateway_order(): void {
		$order = get_option( 'woocommerce_gateway_order' );

		if ( ! is_array( $order ) || ! isset( $order[ self::OLD_ID ] ) ) {
			return;
		}

		if ( ! isset( $order[ HapiditPay_Gateway::ID ] ) ) {
			$order[ HapiditPay_Gateway::ID ] = $order[ self::OLD_ID ];
		}

		unset( $order[ self::OLD_ID ] );
		update_option( 'woocommerce_gateway_order', $order );
	}

	/**
	 * Commandes : moyen de paiement et métadonnées, dans les deux stockages
	 * de WooCommerce (tables des commandes HPOS et anciennes tables des articles).
	 */
	private static function orders(): void {
		global $wpdb;

		$hpos_orders = $wpdb->prefix . 'wc_orders';
		$hpos_meta   = $wpdb->prefix . 'wc_orders_meta';
		$has_hpos    = $hpos_orders === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_orders ) );

		foreach ( self::META as $old => $new ) {
			$wpdb->update( $wpdb->postmeta, [ 'meta_key' => $new ], [ 'meta_key' => $old ] );

			if ( $has_hpos ) {
				$wpdb->update( $hpos_meta, [ 'meta_key' => $new ], [ 'meta_key' => $old ] );
			}
		}

		$wpdb->update( $wpdb->postmeta, [ 'meta_value' => HapiditPay_Gateway::ID ], [ 'meta_key' => '_payment_method', 'meta_value' => self::OLD_ID ] );

		if ( $has_hpos ) {
			$wpdb->update( $hpos_orders, [ 'payment_method' => HapiditPay_Gateway::ID ], [ 'payment_method' => self::OLD_ID ] );
		}

		// Les commandes gardées en cache le seraient avec leurs anciennes valeurs.
		wp_cache_flush();
	}

	/** L'ancienne extension, si elle est encore active, est désactivée : ses réglages sont repris. */
	public static function deactivate_old_plugin(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$old = array_values( array_filter(
			(array) get_option( 'active_plugins', [] ),
			fn ( $plugin ) => is_string( $plugin ) && basename( $plugin ) === self::OLD_FILE
		) );

		if ( [] === $old ) {
			return;
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		deactivate_plugins( $old, true );

		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'HapiditPay pour WooCommerce a repris les réglages et les commandes de l\'extension sous son ancien nom, qui vient d\'être désactivée. Vous pouvez la supprimer.', 'hapiditpay-woocommerce' )
				. '</p></div>';
		} );
	}
}
