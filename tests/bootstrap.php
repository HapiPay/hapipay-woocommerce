<?php
/**
 * Banc d'essai sans WordPress : les quelques fonctions WordPress dont
 * HapiPay_Api a besoin, en version minimale (requêtes HTTP par cURL).
 */

define( 'ABSPATH', __DIR__ );
define( 'HAPIPAY_WC_VERSION', 'test' );

class WP_Error {
	public function __construct( private string $code = '', private string $message = '', private $data = null ) {}
	public function get_error_code(): string { return $this->code; }
	public function get_error_message(): string { return $this->message; }
	public function get_error_data() { return $this->data; }
}

function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
function __( string $text, string $domain = '' ): string { return $text; }
function wp_json_encode( $data ) { return json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); }
function wp_remote_retrieve_response_code( array $r ): int { return $r['code']; }
function wp_remote_retrieve_body( array $r ): string { return $r['body']; }

function wp_remote_request( string $url, array $args ) {
	$ch = curl_init( $url );
	$headers = [];
	foreach ( $args['headers'] as $k => $v ) { $headers[] = "$k: $v"; }
	curl_setopt_array( $ch, [
		CURLOPT_CUSTOMREQUEST  => $args['method'],
		CURLOPT_HTTPHEADER     => $headers,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => $args['timeout'],
	] );
	if ( isset( $args['body'] ) ) { curl_setopt( $ch, CURLOPT_POSTFIELDS, $args['body'] ); }
	$body = curl_exec( $ch );
	if ( false === $body ) { return new WP_Error( 'http', curl_error( $ch ) ); }
	return [ 'code' => curl_getinfo( $ch, CURLINFO_HTTP_CODE ), 'body' => $body ];
}

require dirname( __DIR__ ) . '/includes/class-hapipay-api.php';

$GLOBALS['hapipay_failures'] = 0;
function check( string $label, bool $ok ): void {
	echo ( $ok ? '  ok      ' : '  ÉCHEC   ' ) . $label . "\n";
	if ( ! $ok ) { $GLOBALS['hapipay_failures']++; }
}
