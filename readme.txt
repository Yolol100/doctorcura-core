=== DoctorCura Core ===
Contributors: openai
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.10
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
= 1.0.10 =
* De Duitse voorschrijfbevestiging blijft de brontekst van de checkout.
* Het bevestigingsveld is expliciet vertaalbaar gemaakt voor GTranslate.
* Een gerichte GTranslate-fallback volgt taalwissels en WooCommerce checkout-updates voor Duits, Engels en Frans zonder bestaande GTranslate-vertalingen te overschrijven.

= 1.0.9 =
* De extra medische voorschrijfbevestiging en bijbehorende validatiemelding zijn nu Duits.
* De zichtbare teksten blijven via de bestaande WordPress-i18n-output zonder notranslate-markering geschikt voor vertaling door GTranslate.

= 1.0.8 =
* Generieke WooCommerce-loginfouten voorkomen dat accountbestaan via de loginmelding kan worden afgeleid.
* Loginprivacy is alleen actief na een geldige WooCommerce-login-nonce.
* Pluginloader is beter bestand tegen oudere DoctorCura Core-kopieën en gemigreerde Code Snippets.
* Behoudt de privacy-safe lost-passwordflow en verplichte medische voorschrijfbevestiging uit 1.0.7.

= 1.0.7 =
* Lost-password confirmations stay on the same WooCommerce page and always use a generic anti-enumeration notice.
* Added a required prescribing-decision acknowledgement directly after the final medical questionnaire confirmation.
* Both final medical confirmations are stored on the WooCommerce order.

= 1.0.6 =
* Lost-password requests now use the same public confirmation flow for existing and unknown accounts to reduce account enumeration

= 1.0.5 =
* Engelse en Franse accountlabels gebruiken niet langer Duitse fallbackteksten
* Locale-afhankelijke orderstatus- en orderlinkteksten gecorrigeerd

= 1.0.4 =
* Rewrite lifecycle verbeterd voor cancelled-orders endpoint
* Text domain loading gecorrigeerd
* Readme opgeschoond
