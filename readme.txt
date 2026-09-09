=== DoctorCura Core ===
Contributors: openai
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: woocommerce, account, checkout, medical

DoctorCura Core bevat account-, verificatie-, checkout-, order- en aanvraagflows voor DoctorCura.

== Description ==
DoctorCura Core bundelt:
- account verificatie en reset flows
- medische checkout vragenlijst
- order cancellation endpoint
- medische ordernotities
- request form

== Installation ==
1. Upload de pluginmap naar /wp-content/plugins/
2. Activeer de plugin
3. Controleer WooCommerce en permalinks

== Changelog ==
= 1.0.8 =
* Voorkomt activatiefouten wanneer een oudere DoctorCura Core-kopie of gemigreerde Code Snippet dezelfde functies/classes al heeft geladen
* Nieuwe privacylaag kan naast een oudere Core-versie laden zonder bestaande hooks opnieuw te initialiseren
* Generieke loginmelding heeft een compatibiliteitsfallback voor oudere locale-code

= 1.0.7 =
* WooCommerce-loginfouten geven een generieke melding om account-enumeratie te voorkomen
* Loginprivacy wordt alleen toegepast na een geldige WooCommerce-login-nonce
* Pluginmetadata en versiedocumentatie bijgewerkt

= 1.0.5 =
* Engelse en Franse accountlabels gebruiken niet langer Duitse fallbackteksten
* Locale-afhankelijke orderstatus- en orderlinkteksten gecorrigeerd

= 1.0.4 =
* Rewrite lifecycle verbeterd voor cancelled-orders endpoint
* Text domain loading gecorrigeerd
* Readme opgeschoond
