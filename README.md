# HapiPay pour WooCommerce

Extension WooCommerce pour encaisser par mobile money, carte bancaire et PayPal avec [HapiPay](https://github.com/hapipay). L'argent va directement du client au compte opérateur du commerçant : HapiPay ne le touche jamais.

## Fonctionnement

1. À la validation de la commande, l'extension crée une page de paiement HapiPay à usage unique (`POST /api/v1/liens`, référence `wc-<numéro>`, montant, devise, `url_retour`).
2. Le client y paie, puis revient sur la page de confirmation de la boutique.
3. HapiPay envoie le webhook signé `paiement.reussi` à `/wp-json/hapipay/v1/webhook` : la commande passe à « payée ». Le montant et la devise sont vérifiés.
4. Si le webhook n'est pas encore arrivé quand le client revient, l'extension demande l'état de la page à HapiPay.

Aucun secret dans ce dépôt : chaque commerçant saisit sa propre clé d'API et le secret de son webhook.

## Réglages

WooCommerce → Réglages → Paiements → HapiPay : adresse de l'API, clé d'API (`hpc_test_…` pour les essais), secret du webhook.

## Licence

GPL-2.0-or-later.
