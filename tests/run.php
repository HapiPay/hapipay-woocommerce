<?php
/**
 * php tests/run.php
 * Avec HAPIPAY_API_URL et HAPIPAY_API_KEY (clé de test) : essai réel contre une instance HapiPay.
 */

require __DIR__ . '/bootstrap.php';

echo "Signatures des webhooks\n";
$secret = 'whsec_essai';
$body   = '{"evenement":"paiement.reussi","data":{"reference":"wc-12"}}';
$t      = time();
$good   = hash_hmac( 'sha256', $t . '.' . $body, $secret );

check( 'signature valide acceptée', HapiPay_Api::valid_signature( "t=$t,v1=$good", $body, $secret ) );
check( 'mauvais secret refusé', ! HapiPay_Api::valid_signature( "t=$t,v1=$good", $body, 'autre' ) );
check( 'corps modifié refusé', ! HapiPay_Api::valid_signature( "t=$t,v1=$good", $body . ' ', $secret ) );
$old = $t - 600;
check( 'horodatage trop ancien refusé', ! HapiPay_Api::valid_signature( "t=$old,v1=" . hash_hmac( 'sha256', $old . '.' . $body, $secret ), $body, $secret ) );
check( 'deux v1 après un changement de secret : l\'un suffit', HapiPay_Api::valid_signature( "t=$t,v1=" . hash_hmac( 'sha256', $t . '.' . $body, 'nouveau' ) . ",v1=$good", $body, $secret ) );
check( 'en-tête vide refusé', ! HapiPay_Api::valid_signature( '', $body, $secret ) );
check( 'secret non configuré refusé', ! HapiPay_Api::valid_signature( "t=$t,v1=$good", $body, '' ) );

$url = getenv( 'HAPIPAY_API_URL' );
$key = getenv( 'HAPIPAY_API_KEY' );

if ( $url && $key ) {
	echo "\nAPI réelle : $url\n";
	$api = new HapiPay_Api( $url, $key );
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

	$wrong = ( new HapiPay_Api( $url, 'hpc_test_mauvaise' ) )->whoami();
	check( 'une clé inconnue est refusée (401)', is_wp_error( $wrong ) && 401 === ( $wrong->get_error_data()['status'] ?? 0 ) );
}

echo $GLOBALS['hapipay_failures'] ? "\n{$GLOBALS['hapipay_failures']} échec(s).\n" : "\nTout est bon.\n";
exit( $GLOBALS['hapipay_failures'] ? 1 : 0 );
