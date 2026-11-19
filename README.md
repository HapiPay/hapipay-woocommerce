# HapiditPay pour WooCommerce

Extension WooCommerce pour encaisser par mobile money, carte bancaire et PayPal avec [HapiditPay](https://hapiditpay.com). Pas encore de compte : [créez-le](https://hapiditpay.com/portail/register), l'environnement de test est gratuit. L'argent va directement du client au compte opérateur du commerçant : HapiditPay ne le touche jamais.

## Fonctionnement

1. À la validation de la commande, l'extension crée une page de paiement HapiditPay à usage unique (`POST /api/v1/liens`, référence `wc-<numéro>`, montant, devise, `url_retour`).
2. Le client y paie, puis revient sur la page de confirmation de la boutique.
3. HapiditPay envoie le webhook signé `paiement.reussi` à `/wp-json/hapiditpay/v1/webhook` : la commande passe à « payée ». Le montant et la devise sont vérifiés.
4. Si le webhook n'est pas encore arrivé quand le client revient, l'extension demande l'état de la page à HapiditPay.

Aucun secret dans ce dépôt : chaque commerçant saisit sa propre clé d'API et le secret de son webhook.

## Réglages

WooCommerce → Réglages → Paiements → HapiditPay : adresse de l'API (par défaut `https://hapiditpay.com/api/v1`, sans redirection), clé d'API (`hdp_test_…` pour les essais), secret du webhook. La page de réglages renvoie vers l'inscription et vers votre espace HapiditPay.

## Changement de nom (0.2.0)

L'extension s'appelait « HapiPay pour WooCommerce » jusqu'à la version 0.1.2. À la première ouverture de la 0.2.0, ses réglages (`woocommerce_hapipay_settings`, ordre des moyens de paiement), le moyen de paiement des commandes (`hapipay`) et leurs métadonnées (`_hapipay_reference`, `_hapipay_link`, `_hapipay_mode`) sont recopiés sous le nouveau nom puis effacés, et l'ancienne extension est désactivée. L'ancienne adresse du webhook n'est plus reçue : déclarez `/wp-json/hapiditpay/v1/webhook` dans votre espace HapiditPay. Voir `includes/class-hapiditpay-upgrade.php`.

## Licence

GPL-2.0-or-later.
