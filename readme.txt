=== HapiditPay pour WooCommerce ===
Contributors: hapiditpay
Tags: mobile money, airtel money, orange money, mvola, paiement
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
WC requires at least: 8.0
WC tested up to: 11.2
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Encaissez vos commandes par mobile money, carte bancaire et PayPal. L'argent arrive directement sur votre propre compte opérateur, sans commission.

== Description ==

HapiditPay relie votre boutique aux opérateurs de paiement d'Afrique centrale et de Madagascar : Airtel Money, Orange Money, MVola, la carte bancaire et PayPal.

* **Votre argent ne passe jamais par HapiditPay** : chaque paiement arrive directement sur votre propre compte opérateur.
* **Aucune commission** sur vos ventes : vous payez une formule fixe à HapiditPay.
* **Mode test** complet et gratuit : avec une clé de test, tous les paiements sont simulés.

À la validation de la commande, votre client est envoyé sur une page de paiement HapiditPay sécurisée, dans sa langue. Il paie, puis revient sur votre boutique. La commande passe à « payée » dès que HapiditPay le confirme.

== Installation ==

1. Installez et activez l'extension (WooCommerce doit être actif).
2. Dans votre espace HapiditPay, créez une clé d'API (menu Développeurs → Clés d'API).
3. Dans WooCommerce → Réglages → Paiements → HapiditPay, collez la clé. L'adresse de l'API est déjà remplie : https://hapiditpay.com/api/v1.
4. Dans votre espace HapiditPay, menu Développeurs → Webhooks, ajoutez l'adresse indiquée dans les réglages de l'extension, puis copiez son secret dans le champ « Secret du webhook ».
5. Faites une commande d'essai avec une clé de test, puis remplacez-la par votre clé de production.

== Upgrade Notice ==

= 0.2.0 =
Nouveau nom, HapiditPay. Vos réglages et vos commandes en attente sont repris automatiquement ; l'ancienne extension est désactivée et peut être supprimée.

== Frequently Asked Questions ==

= Mes prix ont des centimes =

HapiditPay encaisse des montants entiers. Les boutiques en FCFA, en ariary ou en francs congolais ne sont pas concernées. En euros ou en dollars, arrondissez vos prix.

= La commande reste « en attente » =

Vérifiez le webhook : son adresse, et le secret copié dans les réglages. Sans lui, la commande n'est confirmée que lorsque le client revient sur la boutique.

== Changelog ==

= 0.2.0 =
* Changement de nom : l'extension « HapiPay pour WooCommerce » devient « HapiditPay pour WooCommerce » (dossier, fichier principal, moyen de paiement et domaine de traduction `hapiditpay-woocommerce`).
* Reprise automatique : à la première ouverture, les réglages de l'ancienne extension (clé d'API, secret du webhook, textes de caisse, place parmi les moyens de paiement) et le suivi des commandes en attente sont recopiés sous le nouveau nom, puis effacés ; l'ancienne extension est désactivée et peut être supprimée.
* L'adresse du webhook devient `/wp-json/hapiditpay/v1/webhook` (affichée dans les réglages). L'ancienne adresse n'est plus reçue : déclarez la nouvelle dans votre espace HapiditPay.
* Adresse de l'API de production : https://hapiditpay.com/api/v1 (l'ancienne adresse enregistrée est remplacée). Clés d'API `hdp_…` et `hdp_test_…`. En-tête du webhook `HapiditPay-Signature`.

= 0.1.2 =
* Réglages : lien pour créer son compte HapiditPay, et pour ouvrir son espace (clés d'API, webhooks).
* Liste des extensions : lien « Mon espace HapiditPay » ; le site de l'extension mène à la page Intégrations de HapiditPay.
* Texte de caisse par défaut plus direct. Testé avec WordPress 7.1 et WooCommerce 11.3.

= 0.1.1 =
* Adresse de l'API de production proposée par défaut : https://hapiditpay.com/api/v1 (un réglage vide l'utilise).
* Une redirection de l'API n'est plus suivie : elle est signalée avec l'adresse à indiquer (la clé n'est jamais renvoyée ailleurs).
* Montants des notes de commande écrits comme sur HapiditPay : FCFA pour XAF et XOF.

= 0.1.0 =
* Première version : paiement par page HapiditPay, confirmation par webhook signé, tunnel de paiement classique et en blocs.
