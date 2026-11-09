<?php
/**
 * Moyen de paiement « HapiPay » : à la validation de la commande, une page
 * de paiement HapiPay à usage unique est créée et le client y est envoyé.
 * Il paie par mobile money, carte ou PayPal, puis revient sur la boutique.
 * La commande passe à « payée » à réception du webhook signé.
 */

defined( 'ABSPATH' ) || exit;

class HapiPay_Gateway extends WC_Payment_Gateway {

	public const ID = 'hapipay';

	public function __construct() {
		$this->id                 = self::ID;
		$this->has_fields         = false;
		$this->method_title       = 'HapiPay';
		$this->method_description = __( 'Mobile money, carte bancaire et PayPal. L\'argent arrive directement sur votre propre compte opérateur : HapiPay ne le touche jamais, et ne prend aucune commission.', 'hapipay-woocommerce' );
		$this->supports           = [ 'products' ];
		$this->icon               = plugins_url( 'assets/hapipay.svg', HAPIPAY_WC_FILE );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
	}

	public function api(): HapiPay_Api {
		return new HapiPay_Api( (string) $this->get_option( 'api_url' ), (string) $this->get_option( 'api_key' ) );
	}

	public function init_form_fields(): void {
		$this->form_fields = [
			'enabled'        => [
				'title'   => __( 'Activer', 'hapipay-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Proposer HapiPay à la caisse', 'hapipay-woocommerce' ),
				'default' => 'no',
			],
			'title'          => [
				'title'   => __( 'Nom affiché à la caisse', 'hapipay-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'Mobile money ou carte bancaire', 'hapipay-woocommerce' ),
			],
			'description'    => [
				'title'   => __( 'Texte affiché à la caisse', 'hapipay-woocommerce' ),
				'type'    => 'textarea',
				'default' => __( 'Payez en toute sécurité avec Airtel Money, Orange Money, MVola, votre carte bancaire ou PayPal, sur la page de paiement HapiPay.', 'hapipay-woocommerce' ),
			],
			'api_url'        => [
				'title'       => __( 'Adresse de l\'API', 'hapipay-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Indiquée en tête de la documentation, dans votre espace HapiPay. Elle se termine par /api/v1.', 'hapipay-woocommerce' ),
				'placeholder' => 'https://…/api/v1',
			],
			'api_key'        => [
				'title'       => __( 'Clé d\'API', 'hapipay-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Créée dans votre espace HapiPay, menu Développeurs → Clés d\'API. Une clé hpc_test_… simule les paiements ; une clé hpc_… encaisse pour de vrai.', 'hapipay-woocommerce' ),
			],
			'webhook_secret' => [
				'title'       => __( 'Secret du webhook', 'hapipay-woocommerce' ),
				'type'        => 'password',
				'description' => sprintf(
					/* translators: %s: adresse du webhook */
					__( 'Dans votre espace HapiPay, menu Développeurs → Webhooks, ajoutez cette adresse : %s puis copiez ici son secret (bouton « Secret »). C\'est grâce à lui que les commandes passent à « payée ».', 'hapipay-woocommerce' ),
					'<code>' . esc_html( HapiPay_Webhook::url() ) . '</code>'
				),
			],
		];
	}

	public function is_available(): bool {
		return parent::is_available() && '' !== $this->get_option( 'api_key' ) && '' !== $this->get_option( 'api_url' );
	}

	/** Teste la clé à l'enregistrement des réglages, et le dit. */
	public function process_admin_options(): bool {
		$saved = parent::process_admin_options();

		$who = $this->api()->whoami();

		if ( is_wp_error( $who ) ) {
			WC_Admin_Settings::add_error( sprintf( __( 'HapiPay : la clé n\'a pas pu être vérifiée (%s).', 'hapipay-woocommerce' ), $who->get_error_message() ) );
		} else {
			WC_Admin_Settings::add_message( sprintf(
				__( 'HapiPay : clé reconnue pour « %1$s », en mode %2$s.', 'hapipay-woocommerce' ),
				$who['nom'] ?? $who['name'] ?? '?',
				$this->api()->is_test() ? __( 'test', 'hapipay-woocommerce' ) : __( 'production', 'hapipay-woocommerce' )
			) );
		}

		return $saved;
	}

	public function process_payment( $order_id ): array {
		$order = wc_get_order( $order_id );

		// HapiPay compte en unités entières de la devise (FCFA, ariary, francs congolais…).
		$total  = (float) $order->get_total();
		$amount = (int) round( $total );

		if ( abs( $total - $amount ) > 0.001 ) {
			wc_add_notice( __( 'Ce montant comporte des centimes : HapiPay n\'accepte que des montants entiers. Arrondissez vos prix, ou choisissez un autre moyen de paiement.', 'hapipay-woocommerce' ), 'error' );

			return [ 'result' => 'failure' ];
		}

		$reference = 'wc-' . $order->get_id();

		$link = $this->api()->create_link( [
			'reference'    => $reference,
			'titre'        => sprintf( __( 'Commande n° %s', 'hapipay-woocommerce' ), $order->get_order_number() ),
			'description'  => get_bloginfo( 'name' ),
			'montant'      => $amount,
			'devise'       => $order->get_currency(),
			'usage_unique' => true,
			'url_retour'   => $this->get_return_url( $order ),
			'langue'       => self::locale(),
		] );

		if ( is_wp_error( $link ) ) {
			$order->add_order_note( sprintf( __( 'HapiPay : la page de paiement n\'a pas pu être créée (%s).', 'hapipay-woocommerce' ), $link->get_error_message() ) );
			wc_add_notice( __( 'Le paiement HapiPay est momentanément indisponible. Réessayez dans un instant, ou choisissez un autre moyen de paiement.', 'hapipay-woocommerce' ), 'error' );

			return [ 'result' => 'failure' ];
		}

		$order->update_meta_data( '_hapipay_reference', $reference );
		$order->update_meta_data( '_hapipay_link', $link['code'] );
		$order->update_meta_data( '_hapipay_mode', $this->api()->is_test() ? 'test' : 'production' );
		$order->save();
		$order->add_order_note( sprintf( __( 'HapiPay : page de paiement %1$s créée (mode %2$s), en attente du paiement.', 'hapipay-woocommerce' ), $link['code'], $this->api()->is_test() ? 'test' : 'production' ) );

		return [ 'result' => 'success', 'redirect' => $link['url'] ];
	}

	/** Langue de la page de paiement : celle de la boutique, si HapiPay la parle. */
	private static function locale(): ?string {
		$lang = substr( determine_locale(), 0, 2 );

		return in_array( $lang, [ 'fr', 'en', 'ar', 'mg', 'es' ], true ) ? $lang : null;
	}
}
