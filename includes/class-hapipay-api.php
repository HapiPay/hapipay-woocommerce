<?php
/**
 * Appels à l'API HapiPay : création d'une page de paiement à usage unique,
 * lecture de son état, et vérification de la signature des webhooks.
 */

defined( 'ABSPATH' ) || exit;

class HapiPay_Api {

	public function __construct( private string $base_url, private string $api_key ) {
		$this->base_url = rtrim( $base_url, '/' );
	}

	/** Clé de test (hpc_test_…) : tous les paiements sont simulés. */
	public function is_test(): bool {
		return str_starts_with( $this->api_key, 'hpc_test_' );
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
			return new WP_Error( 'hapipay_reponse', __( 'Réponse inattendue de HapiPay.', 'hapipay-woocommerce' ) );
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
			return new WP_Error( 'hapipay_config', __( 'HapiPay n\'est pas configuré : adresse de l\'API et clé requises.', 'hapipay-woocommerce' ) );
		}

		$args = [
			'method'  => $method,
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'HapiPay-WooCommerce/' . HAPIPAY_WC_VERSION,
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

		if ( $code < 200 || $code >= 300 ) {
			$message = $data['message'] ?? sprintf( __( 'HapiPay a répondu %d.', 'hapipay-woocommerce' ), $code );

			return new WP_Error( 'hapipay_' . $code, $message, [ 'status' => $code, 'body' => $data ] );
		}

		return $data;
	}

	/**
	 * Vérifie l'en-tête HapiPay-Signature : t=<horodatage>,v1=<hmac>[,v1=<hmac>].
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
