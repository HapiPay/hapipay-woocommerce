<?php
/**
 * Réception des webhooks HapiPay (signés) et vérification au retour du
 * client. Une commande ne passe à « payée » que sur une confirmation de
 * HapiPay, jamais sur le seul retour du navigateur.
 */

defined( 'ABSPATH' ) || exit;

class HapiPay_Webhook {

	public static function register(): void {
		add_action( 'rest_api_init', function () {
			register_rest_route( 'hapipay/v1', '/webhook', [
				'methods'             => 'POST',
				'callback'            => [ self::class, 'handle' ],
				'permission_callback' => '__return_true', // la signature tient lieu d'authentification
			] );
		} );

		// Au retour du client : si le webhook n'est pas encore arrivé, on demande l'état à HapiPay.
		add_action( 'woocommerce_thankyou_' . HapiPay_Gateway::ID, [ self::class, 'check_on_return' ], 5 );
	}

	public static function url(): string {
		return rest_url( 'hapipay/v1/webhook' );
	}

	public static function handle( WP_REST_Request $request ): WP_REST_Response {
		$gateway = self::gateway();
		$raw     = (string) $request->get_body();

		if ( ! $gateway || ! HapiPay_Api::valid_signature( (string) $request->get_header( 'hapipay-signature' ), $raw, (string) $gateway->get_option( 'webhook_secret' ) ) ) {
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
			'paiement.echoue'     => $order->add_order_note( __( 'HapiPay : le paiement a échoué. Le client peut réessayer.', 'hapipay-woocommerce' ) ),
			'paiement.expire'     => $order->add_order_note( __( 'HapiPay : le client n\'a pas validé à temps, rien n\'a été débité.', 'hapipay-woocommerce' ) ),
			'paiement.a_verifier' => $order->update_status( 'on-hold', __( 'HapiPay : l\'opérateur n\'a pas répondu clairement. Le paiement est à vérifier dans votre espace HapiPay.', 'hapipay-woocommerce' ) ),
			'remboursement.reussi' => $order->add_order_note( sprintf( __( 'HapiPay : remboursement de %s effectué par l\'opérateur.', 'hapipay-woocommerce' ), wc_price( (float) ( $data['montant'] ?? 0 ), [ 'currency' => $data['devise'] ?? '' ] ) ) ),
			default               => null,
		};

		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	/** Retour du client sur la boutique : on interroge HapiPay si la commande attend encore. */
	public static function check_on_return( $order_id ): void {
		$order   = wc_get_order( $order_id );
		$gateway = self::gateway();
		$code    = $order ? (string) $order->get_meta( '_hapipay_link' ) : '';

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
			$order->update_status( 'on-hold', __( 'HapiPay : un paiement a été confirmé, mais son montant ou sa devise ne correspond pas à la commande. Vérifiez avant d\'expédier.', 'hapipay-woocommerce' ) );

			return;
		}

		$order->payment_complete( (string) ( $data['id'] ?? '' ) );
		$order->add_order_note( sprintf(
			__( 'HapiPay : paiement confirmé (%1$s%2$s).', 'hapipay-woocommerce' ),
			isset( $data['operateur'] ) ? $data['operateur'] . ', ' : '',
			'test' === $order->get_meta( '_hapipay_mode' ) ? __( 'simulé, mode test', 'hapipay-woocommerce' ) : __( 'production', 'hapipay-woocommerce' )
		) );
	}

	private static function order_for( string $reference ): ?WC_Order {
		if ( ! preg_match( '/^wc-(\d+)$/', $reference, $m ) ) {
			return null;
		}

		$order = wc_get_order( (int) $m[1] );

		return $order instanceof WC_Order && $order->get_meta( '_hapipay_reference' ) === $reference ? $order : null;
	}

	private static function gateway(): ?HapiPay_Gateway {
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];

		return $gateways[ HapiPay_Gateway::ID ] ?? null;
	}
}
