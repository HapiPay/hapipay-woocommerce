<?php
/**
 * Réception des webhooks HapiditPay (signés) et vérification au retour du
 * client. Une commande ne passe à « payée » que sur une confirmation de
 * HapiditPay, jamais sur le seul retour du navigateur.
 */

defined( 'ABSPATH' ) || exit;

class HapiditPay_Webhook {

	public static function register(): void {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'hapiditpay/v1', '/webhook', [
				'methods'             => 'POST',
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => '__return_true', // la signature tient lieu d'authentification
			] );
		} );

		// Au retour du client : si le webhook n'est pas encore arrivé, on demande l'état à HapiditPay.
		// Avant l'affichage de la page, car WooCommerce peut demander l'e-mail du client
		// (session perdue en changeant d'application sur un téléphone) et ne rien afficher d'autre.
		add_action( 'template_redirect', [ self::class, 'on_return_page' ] );
	}

	public static function on_return_page(): void {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = $order_id ? wc_get_order( $order_id ) : null;
		$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';

		// La clé de commande (secrète) prouve qu'il s'agit bien du retour de cette commande.
		if ( $order && HapiditPay_Gateway::ID === $order->get_payment_method() && hash_equals( $order->get_order_key(), $key ) ) {
			self::check_on_return( $order_id );
		}
	}

	public static function url(): string {
		return rest_url( 'hapiditpay/v1/webhook' );
	}

	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$gateway = self::gateway();
		$raw     = (string) $request->get_body();

		if ( ! $gateway || ! HapiditPay_Api::valid_signature( (string) $request->get_header( 'hapiditpay-signature' ), $raw, (string) $gateway->get_option( 'webhook_secret' ) ) ) {
			return new WP_REST_Response( [ 'message' => 'Signature invalide.' ], 401 );
		}

		$event = json_decode( $raw, true ) ?: [];
		$name  = (string) ( $event['evenement'] ?? '' );
		$data  = (array) ( $event['data'] ?? [] );

		if ( 'test.ping' === $name ) {
			return new WP_REST_Response( [ 'ok' => true ], 200 );
		}

		$order = self::order_for( (string) ( $data['reference'] ?? '' ) );

		if ( ! $order ) {
			// Pas une commande de cette boutique : on accuse réception pour éviter les relances.
			return new WP_REST_Response( [ 'ignore' => true ], 200 );
		}

		match ( $name ) {
			'paiement.reussi'     => self::mark_paid( $order, $data ),
			'paiement.echoue'     => $order->add_order_note( __( 'HapiditPay : le paiement a échoué. Le client peut réessayer.', 'hapiditpay-woocommerce' ) ),
			'paiement.expire'     => $order->add_order_note( __( 'HapiditPay : le client n\'a pas validé à temps, rien n\'a été débité.', 'hapiditpay-woocommerce' ) ),
			'paiement.a_verifier' => $order->update_status( 'on-hold', __( 'HapiditPay : l\'opérateur n\'a pas répondu clairement. Le paiement est à vérifier dans votre espace HapiditPay.', 'hapiditpay-woocommerce' ) ),
			'remboursement.reussi' => $order->add_order_note( sprintf( __( 'HapiditPay : remboursement de %s effectué par l\'opérateur.', 'hapiditpay-woocommerce' ), HapiditPay_Api::format_amount( (int) ( $data['montant'] ?? 0 ), (string) ( $data['devise'] ?? '' ) ) ) ),
			default               => null,
		};

		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	/** Retour du client sur la boutique : on interroge HapiditPay si la commande attend encore. */
	public static function check_on_return( $order_id ): void {
		$order   = wc_get_order( $order_id );
		$gateway = self::gateway();
		$code    = $order ? (string) $order->get_meta( '_hapiditpay_link' ) : '';

		if ( ! $order || ! $gateway || '' === $code || $order->is_paid() ) {
			return;
		}

		$state = $gateway->api()->link_state( $code );

		if ( ! is_wp_error( $state ) && 'paye' === ( $state['etat'] ?? '' ) ) {
			self::mark_paid( $order, [
				'id'      => $state['paiement'] ?? $code,
				'montant' => $state['montant'] ?? null,
				'devise'  => $state['devise'] ?? null,
				'lien'    => $code,
			] );
		}
	}

	private static function mark_paid( WC_Order $order, array $data ): void {
		if ( $order->is_paid() ) {
			return; // une notification peut arriver deux fois
		}

		// Le montant et la devise confirmés doivent être ceux de la commande.
		if ( (int) round( (float) $order->get_total() ) !== (int) ( $data['montant'] ?? -1 ) || strtoupper( $order->get_currency() ) !== strtoupper( (string) ( $data['devise'] ?? '' ) ) ) {
			$order->update_status( 'on-hold', __( 'HapiditPay : un paiement a été confirmé, mais son montant ou sa devise ne correspond pas à la commande. Vérifiez avant d\'expédier.', 'hapiditpay-woocommerce' ) );

			return;
		}

		$order->payment_complete( (string) ( $data['id'] ?? '' ) );
		$order->add_order_note( sprintf(
			__( 'HapiditPay : paiement confirmé (%1$s%2$s).', 'hapiditpay-woocommerce' ),
			isset( $data['operateur'] ) ? $data['operateur'] . ', ' : '',
			'test' === $order->get_meta( '_hapiditpay_mode' ) ? __( 'simulé, mode test', 'hapiditpay-woocommerce' ) : __( 'production', 'hapiditpay-woocommerce' )
		) );
	}

	private static function order_for( string $reference ): ?WC_Order {
		if ( ! preg_match( '/^wc-(\d+)$/', $reference, $m ) ) {
			return null;
		}

		$order = wc_get_order( (int) $m[1] );

		return $order instanceof WC_Order && $order->get_meta( '_hapiditpay_reference' ) === $reference ? $order : null;
	}

	private static function gateway(): ?HapiditPay_Gateway {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];

		return $gateways[ HapiditPay_Gateway::ID ] ?? null;
	}
}
