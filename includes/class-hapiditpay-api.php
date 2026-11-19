<?php
/**
 * Appels à l'API HapiditPay : création d'une page de paiement à usage unique,
 * lecture de son état, et vérification de la signature des webhooks.
 */

defined( 'ABSPATH' ) || exit;

class HapiditPay_Api {

	/** Adresse de l'API de production, utilisée quand le réglage est vide. */
	public const DEFAULT_URL = 'https://hapiditpay.com/api/v1';

	public function __construct( private string $base_url, private string $api_key ) {
		$this->base_url = rtrim( '' !== trim( $base_url ) ? trim( $base_url ) : self::DEFAULT_URL, '/' );
	}

	/**
	 * Adresse d'une page du site HapiditPay (inscription, espace du marchand),
	 * déduite de l'adresse de l'API : elle suit l'instance configurée.
	 */
	public function site_url( string $path = '' ): string {
		$root = (string) preg_replace( '#/api/v\d+$#', '', $this->base_url );

		return '' === $path ? $root : $root . '/' . ltrim( $path, '/' );
	}

	/**
	 * Montant HapiditPay écrit pour une personne : « 25 000 FCFA » pour les francs
	 * CFA (XAF et XOF), le code ISO pour les autres devises (« 5 000 MGA »).
	 */
	public static function format_amount( int $amount, string $currency ): string {
		$currency = strtoupper( $currency );

		return number_format( $amount, 0, ',', "\u{202F}" ) . ' ' . ( in_array( $currency, [ 'XAF', 'XOF' ], true ) ? 'FCFA' : $currency );
	}

	/** Clé de test (hdp_test_…) : tous les paiements sont simulés. */
	public function is_test(): bool {
		return str_starts_with( $this->api_key, 'hdp_test_' );
	}

	/**
	 * Crée une page de paiement à usage unique pour une commande.
	 *
	 * @return array{code: string, url: string}|WP_Error
	 */
	public function create_link( array $body ) {
		$response = $this->request( 'POST', '/liens', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['data']['code'] ) || empty( $response['data']['url'] ) ) {
			return new WP_Error( 'hapiditpay_reponse', __( 'Réponse inattendue de HapiditPay.', 'hapiditpay-woocommerce' ) );
		}

		return [ 'code' => $response['data']['code'], 'url' => $response['data']['url'] ];
	}

	/** État d'une page : ouvert, paye, expire, desactive, marchand_indisponible. */
	public function link_state( string $code ) {
		$response = $this->request( 'GET', '/liens/' . rawurlencode( $code ) );

		return is_wp_error( $response ) ? $response : ( $response['data'] ?? [] );
	}

	/** Teste la clé : renvoie le nom du compte reconnu. */
	public function whoami() {
		$response = $this->request( 'GET', '/plateforme' );

		return is_wp_error( $response ) ? $response : ( $response['data'] ?? [] );
	}

	private function request( string $method, string $path, ?array $body = null ) {
		if ( '' === $this->base_url || '' === $this->api_key ) {
			return new WP_Error( 'hapiditpay_config', __( 'HapiditPay n\'est pas configuré : adresse de l\'API et clé requises.', 'hapiditpay-woocommerce' ) );
		}

		$args = [
			'method'      => $method,
			'timeout'     => 20,
			// Jamais de redirection suivie : la clé et le corps n'iraient pas
			// ailleurs que là où ils ont été envoyés.
			'redirection' => 0,
			'headers'     => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'HapiditPay-WooCommerce/' . HAPIDITPAY_WC_VERSION,
			],
		];

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->base_url . $path, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true ) ?: [];

		if ( $code >= 300 && $code < 400 ) {
			return new WP_Error( 'hapiditpay_' . $code, sprintf(
				/* translators: 1: code HTTP, 2: adresse de l'API */
				__( 'HapiditPay a répondu par une redirection (%1$d) : vérifiez l\'adresse de l\'API, en HTTPS, par exemple %2$s.', 'hapiditpay-woocommerce' ),
				$code,
				self::DEFAULT_URL
			), [ 'status' => $code ] );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = $data['message'] ?? sprintf( __( 'HapiditPay a répondu %d.', 'hapiditpay-woocommerce' ), $code );

			return new WP_Error( 'hapiditpay_' . $code, $message, [ 'status' => $code, 'body' => $data ] );
		}

		return $data;
	}

	/**
	 * Vérifie l'en-tête HapiditPay-Signature : t=<horodatage>,v1=<hmac>[,v1=<hmac>].
	 * Deux v1 pendant les 24 h qui suivent un changement de secret.
	 */
	public static function valid_signature( string $header, string $raw_body, string $secret, int $tolerance = 300 ): bool {
		if ( '' === $secret || '' === $header ) {
			return false;
		}

		$timestamp  = null;
		$signatures = [];

		foreach ( explode( ',', $header ) as $part ) {
			[ $key, $value ] = array_pad( explode( '=', trim( $part ), 2 ), 2, null );

			if ( 't' === $key ) {
				$timestamp = $value;
			} elseif ( 'v1' === $key && null !== $value ) {
				$signatures[] = $value;
			}
		}

		if ( null === $timestamp || ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > $tolerance ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $timestamp . '.' . $raw_body, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}
}
