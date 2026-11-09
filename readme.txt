=== HapiPay pour WooCommerce ===
Contributors: hapipay
Tags: mobile money, airtel money, orange money, mvola, paiement
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
WC requires at least: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Encaissez vos commandes par mobile money, carte bancaire et PayPal. L'argent arrive directement sur votre propre compte opérateur, sans commission.

== Description ==

HapiPay relie votre boutique aux opérateurs de paiement d'Afrique centrale et de Madagascar : Airtel Money, Orange Money, MVola, la carte bancaire et PayPal.

* **Votre argent ne passe jamais par HapiPay** : chaque paiement arrive directement sur votre propre compte opérateur.
* **Aucune commission** sur vos ventes : vous payez une formule fixe à HapiPay.
* **Mode test** complet et gratuit : avec une clé de test, tous les paiements sont simulés.

À la validation de la commande, votre client est envoyé sur une page de paiement HapiPay sécurisée, dans sa langue. Il paie, puis revient sur votre boutique. La commande passe à « payée » dès que HapiPay le confirme.

== Installation ==

1. Installez et activez l'extension (WooCommerce doit être actif).
2. Dans votre espace HapiPay, créez une clé d'API (menu Développeurs → Clés d'API).
3. Dans WooCommerce → Réglages → Paiements → HapiPay, collez l'adresse de l'API et la clé.
4. Dans votre espace HapiPay, menu Développeurs → Webhooks, ajoutez l'adresse indiquée dans les réglages de l'extension, puis copiez son secret dans le champ « Secret du webhook ».
5. Faites une commande d'essai avec une clé de test, puis remplacez-la par votre clé de production.

== Frequently Asked Questions ==

= Mes prix ont des centimes =

HapiPay encaisse des montants entiers. Les boutiques en FCFA, en ariary ou en francs congolais ne sont pas concernées. En euros ou en dollars, arrondissez vos prix.

= La commande reste « en attente » =

Vérifiez le webhook : son adresse, et le secret copié dans les réglages. Sans lui, la commande n'est confirmée que lorsque le client revient sur la boutique.

== Changelog ==

= 0.1.0 =
* Première version : paiement par page HapiPay, confirmation par webhook signé, tunnel de paiement classique et en blocs.
