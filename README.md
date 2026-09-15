# DoctorCura Core

> **Portfoliostatus:** Experiment · zelfstandige WordPress/WooCommerce-domeinplugin

DoctorCura Core bevat de account-, verificatie-, checkout-, order-, annulerings- en aanvraaglogica voor DoctorCura. De plugin blijft bewust gescheiden van [DoctorCura UI](https://github.com/Yolol100/doctorcura-ui), zodat domeinlogica en storefrontpresentatie onafhankelijk kunnen worden geactiveerd, getest en teruggedraaid.

## Verantwoordelijkheid

DoctorCura Core bundelt onder meer:

- accountverificatie en password-resetflows;
- WooCommerce account- en loginbeveiliging;
- medische checkoutvragen en verplichte bevestigingen;
- orderannulering en ordergerelateerde medische metadata;
- aanvraag-/requestflows;
- de bijbehorende validatie en WordPress/WooCommerce-integratie.

## Relatie met DoctorCura UI

```text
DoctorCura Core → account-, checkout-, order- en aanvraaggedrag
DoctorCura UI   → storefront-, product- en presentatielaag
WordPress + WooCommerce → gedeeld runtimeplatform
```

Core en UI hebben afzonderlijke versies en releasepakketten. DoctorCura Core mag daarom niet aan één specifieke UI-versie worden vastgepind in documentatie; test altijd de daadwerkelijk gebruikte combinatie op staging.

## Compatibiliteit

- WordPress 6.4 of nieuwer.
- PHP 8.1 of nieuwer.
- WooCommerce voor de account-, checkout- en orderflows.
- Huidige pluginversie: `1.0.11`.
- De WordPress-format `readme.txt` is leidend voor de actuele stable tag en changelog.

## Huidige release

Versie `1.0.11` bevat de huidige checkoutfix voor de extra voorschrijfbevestiging. De plugin vraagt WooCommerce expliciet om de betreffende veldmarkup als string terug te geven en controleert het type voordat de markup wordt aangepast, zodat een onverwachte `null`-waarde niet opnieuw tot een TypeError leidt.

De eerdere privacymaatregelen rond lost-password- en loginflows blijven onderdeel van de huidige code: publieke meldingen zijn bedoeld om geen accountbestaan prijs te geven en medische bevestigingen worden via de bestaande WooCommerce-flow verwerkt.

## Installatie

1. Upload de pluginmap naar `wp-content/plugins/`.
2. Activeer **DoctorCura Core**.
3. Controleer dat WooCommerce en de gebruikte permalinks correct werken.
4. Test account-, password-reset-, checkout-, order-, annulering- en aanvraagflows op staging.
5. Activeer DoctorCura UI afzonderlijk wanneer de storefrontmodules nodig zijn.

## Veiligheid en privacy

Deze plugin raakt account-, order- en medische workflowdata. Gebruik daarom geen echte patiënt-, account- of ordergegevens in publieke issues, screenshots of debugexports. Test wijzigingen eerst op staging en behoud voor productie-updates een rollbackpad.

## Repository structure

- `doctorcura-core.php` — pluginbootstrap en runtime-metadata.
- `includes/` — account-, checkout-, order-, aanvraag- en integratielogica.
- `assets/` — pluginassets.
- `uninstall.php` — plugin-cleanup.
- `readme.txt` — WordPress-format release-informatie en changelog.

## Licentie

GPL-2.0-or-later, zoals vastgelegd in de pluginheader en `readme.txt`.