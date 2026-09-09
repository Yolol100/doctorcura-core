=== DoctorCura Core ===
Contributors: openai
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.6
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
= 1.0.6 =
* Lost-password requests now use the same public confirmation flow for existing and unknown accounts to reduce account enumeration

= 1.0.5 =
* Engelse en Franse accountlabels gebruiken niet langer Duitse fallbackteksten
* Locale-afhankelijke orderstatus- en orderlinkteksten gecorrigeerd

= 1.0.4 =
* Rewrite lifecycle verbeterd voor cancelled-orders endpoint
* Text domain loading gecorrigeerd
* Readme opgeschoond
