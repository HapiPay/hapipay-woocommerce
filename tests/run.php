<?php
/**
 * php tests/run.php
 * Avec HAPIDITPAY_API_URL et HAPIDITPAY_API_KEY (clé de test) : essai réel contre une instance HapiditPay.
 */

require __DIR__ . '/bootstrap.php';

echo "Signatures des webhooks\n";
$secret = 'whsec_essai';
$body   = '{"evenement":"paiement.reussi","data":{"reference":"wc-12"}}';
$t      = time();
$good   = hash_hmac( 'sha256', $t . '.' . $body, $secret );

check( 'signature valide acceptée', HapiditPay_Api::valid_signature( "t=$t,v1=$good", $body, $secret ) );
check( 'mauvais secret refusé', ! HapiditPay_Api::valid_signature( "t=$t,v1=$good", $body, 'autre' ) );
check( 'corps modifié refusé', ! HapiditPay_Api::valid_signature( "t=$t,v1=$good", $body . ' ', $secret ) );
$old = $t - 600;
check( 'horodatage trop ancien refusé', ! HapiditPay_Api::valid_signature( "t=$old,v1=" . hash_hmac( 'sha256', $old . '.' . $body, $secret ), $body, $secret ) );
check( 'deux v1 après un changement de secret : l\'un suffit', HapiditPay_Api::valid_signature( "t=$t,v1=" . hash_hmac( 'sha256', $t . '.' . $body, 'nouveau' ) . ",v1=$good", $body, $secret ) );
check( 'en-tête vide refusé', ! HapiditPay_Api::valid_signature( '', $body, $secret ) );
check( 'secret non configuré refusé', ! HapiditPay_Api::valid_signature( "t=$t,v1=$good", $body, '' ) );

echo "\nMontants écrits pour une personne (FCFA pour XAF et XOF)\n";
check( 'XAF écrit FCFA', "25\u{202F}000 FCFA" === HapiditPay_Api::format_amount( 25000, 'XAF' ) );
check( 'XOF écrit FCFA', "1\u{202F}500 FCFA" === HapiditPay_Api::format_amount( 1500, 'xof' ) );
check( 'les autres devises gardent leur code', "5\u{202F}000 MGA" === HapiditPay_Api::format_amount( 5000, 'MGA' ) );

echo "\nAdresse de l'API\n";
$default = new ReflectionProperty( HapiditPay_Api::class, 'base_url' );
check( 'réglage vide : adresse de production', HapiditPay_Api::DEFAULT_URL === $default->getValue( new HapiditPay_Api( '', 'hdp_test_x' ) ) );
check( 'barre finale retirée', 'https://exemple.test/api/v1' === $default->getValue( new HapiditPay_Api( ' https://exemple.test/api/v1/ ', 'hdp_test_x' ) ) );

echo "\nLiens vers le site HapiditPay\n";
check( 'site déduit de l\'API de production', 'https://hapiditpay.com/portail/register' === ( new HapiditPay_Api( '', 'k' ) )->site_url( 'portail/register' ) );
check( 'site d\'une autre instance', 'https://exemple.test/portail' === ( new HapiditPay_Api( 'https://exemple.test/api/v1/', 'k' ) )->site_url( '/portail' ) );
check( 'racine du site', 'https://hapiditpay.com' === ( new HapiditPay_Api( '', 'k' ) )->site_url() );

echo "\nReprise des réglages de l'ancienne extension\n";
// Réglages en mémoire et base factice : seulement ce dont la reprise a besoin.
$GLOBALS['options'] = [
	'woocommerce_hapipay_settings' => [ 'enabled' => 'yes', 'api_url' => 'https://pay.scigroup.td/api/v1/', 'api_key' => 'cle', 'webhook_secret' => 'whsec', 'title' => 'Payer avec HapiPay' ],
	'woocommerce_gateway_order'    => [ 'bacs' => 0, 'hapipay' => 1 ],
];
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function add_option( $name, $value ) { $GLOBALS['options'][ $name ] = $value; return true; }
function update_option( $name, $value ) { $GLOBALS['options'][ $name ] = $value; return true; }
function delete_option( $name ) { unset( $GLOBALS['options'][ $name ] ); return true; }
function wp_cache_flush() { return true; }
class HapiditPay_Gateway { public const ID = 'hapiditpay'; }
$GLOBALS['wpdb'] = new class {
	public string $prefix = 'wp_';
	public string $postmeta = 'wp_postmeta';
	public array $updates = [];
	public function prepare( $query, ...$args ) { return vsprintf( str_replace( '%s', "'%s'", $query ), $args ); }
	public function get_var( $query ) { return 'wp_wc_orders'; }
	public function update( $table, $data, $where ) { $this->updates[] = [ $table, $data, $where ]; return 1; }
};
require dirname( __DIR__ ) . '/includes/class-hapiditpay-upgrade.php';
HapiditPay_Upgrade::maybe_run();
$new = $GLOBALS['options']['woocommerce_hapiditpay_settings'] ?? [];
check( 'réglages recopiés sous le nouveau nom', 'cle' === ( $new['api_key'] ?? '' ) && 'whsec' === ( $new['webhook_secret'] ?? '' ) && 'yes' === ( $new['enabled'] ?? '' ) );
check( 'anciens réglages effacés', ! isset( $GLOBALS['options']['woocommerce_hapipay_settings'] ) );
check( 'ancienne adresse de l\'API remplacée', HapiditPay_Api::DEFAULT_URL === $new['api_url'] );
check( 'ancien nom remplacé dans le texte de caisse', 'Payer avec HapiditPay' === $new['title'] );
check( 'place parmi les moyens de paiement gardée', [ 'bacs' => 0, 'hapiditpay' => 1 ] === $GLOBALS['options']['woocommerce_gateway_order'] );
$updated = array_map( fn ( $u ) => $u[0] . ':' . json_encode( $u[1] ) . ':' . json_encode( $u[2] ), $GLOBALS['wpdb']->updates );
check( 'métadonnées des commandes renommées (deux stockages)', in_array( 'wp_postmeta:{"meta_key":"_hapiditpay_link"}:{"meta_key":"_hapipay_link"}', $updated, true ) && in_array( 'wp_wc_orders_meta:{"meta_key":"_hapiditpay_reference"}:{"meta_key":"_hapipay_reference"}', $updated, true ) );
check( 'moyen de paiement des commandes renommé', in_array( 'wp_wc_orders:{"payment_method":"hapiditpay"}:{"payment_method":"hapipay"}', $updated, true ) && in_array( 'wp_postmeta:{"meta_value":"hapiditpay"}:{"meta_key":"_payment_method","meta_value":"hapipay"}', $updated, true ) );
check( 'reprise notée pour cette version', HAPIDITPAY_WC_VERSION === $GLOBALS['options']['hapiditpay_wc_version'] );
$GLOBALS['wpdb']->updates = [];
$GLOBALS['options']['woocommerce_hapiditpay_settings']['api_key'] = 'nouvelle';
HapiditPay_Upgrade::maybe_run();
check( 'une seule fois : rien de refait ensuite', [] === $GLOBALS['wpdb']->updates && 'nouvelle' === $GLOBALS['options']['woocommerce_hapiditpay_settings']['api_key'] );

$url = getenv( 'HAPIDITPAY_API_URL' );
$key = getenv( 'HAPIDITPAY_API_KEY' );

if ( $url && $key ) {
	echo "\nAPI réelle : $url\n";
	$api = new HapiditPay_Api( $url, $key );
	check( 'clé de test reconnue comme telle', $api->is_test() );

	$who = $api->whoami();
	check( 'la clé ouvre le compte (' . ( is_wp_error( $who ) ? $who->get_error_message() : $who['name'] ) . ')', ! is_wp_error( $who ) );

	$link = $api->create_link( [ 'reference' => 'wc-42', 'titre' => 'Commande n° 42', 'montant' => 15000, 'devise' => 'XAF', 'usage_unique' => true, 'url_retour' => 'http://boutique.test/commande-recue/42', 'langue' => 'fr' ] );
	check( 'page de paiement créée' . ( is_wp_error( $link ) ? ' : ' . $link->get_error_message() : ' : ' . $link['url'] ), ! is_wp_error( $link ) );

	if ( ! is_wp_error( $link ) ) {
		$state = $api->link_state( $link['code'] );
		check( 'la page est ouverte, au bon montant', ! is_wp_error( $state ) && 'ouvert' === $state['etat'] && 15000 === $state['montant'] && 'wc-42' === $state['reference'] );
	}

	$bad = $api->create_link( [ 'reference' => 'wc-43', 'montant' => 100, 'devise' => 'JPY', 'usage_unique' => true ] );
	check( 'une devise refusée remonte une erreur claire', is_wp_error( $bad ) && 422 === ( $bad->get_error_data()['status'] ?? 0 ) );

	$wrong = ( new HapiditPay_Api( $url, 'hdp_test_mauvaise' ) )->whoami();
	check( 'une clé inconnue est refusée (401)', is_wp_error( $wrong ) && 401 === ( $wrong->get_error_data()['status'] ?? 0 ) );
}

echo $GLOBALS['hapiditpay_failures'] ? "\n{$GLOBALS['hapiditpay_failures']} échec(s).\n" : "\nTout est bon.\n";
exit( $GLOBALS['hapiditpay_failures'] ? 1 : 0 );
