# DoctorCura Core

> **Portfoliostatus:** Experiment · zelfstandig WordPress/WooCommerce-domeinplugin

DoctorCura Core bevat account-, verificatie-, checkout-, order- en aanvraagflows voor DoctorCura. De plugin blijft een afzonderlijk pakket van [DoctorCura UI](https://github.com/Yolol100/doctorcura-ui), zodat domeinlogica en storefrontpresentatie onafhankelijk kunnen worden getest, geactiveerd en teruggedraaid.

## Verantwoordelijkheid

- accountverificatie en resetflows;
- medische checkoutvragenlijst;
- orderannulering;
- medische ordernotities;
- aanvraagformulier.

## Relatie met DoctorCura UI

```text
DoctorCura Core → account-, checkout- en ordergedrag
DoctorCura UI   → storefront-, product- en presentatielaag
WordPress + WooCommerce → gedeeld runtimeplatform
```

De plugins hebben afzonderlijke versies en releasepakketten. Ze worden niet samengevoegd zolang onafhankelijk activeren, testen of terugdraaien nuttig blijft.

## Compatibiliteit

| Onderdeel | Ondersteuning |
| --- | --- |
| WordPress | 6.4 of nieuwer; getest tot 6.8 |
| PHP | 8.1 of nieuwer |
| Huidige versie | 1.0.7 |
| Combinatie | Test Core 1.0.7 samen met UI 1.3.2 op staging voordat beide naar productie gaan |

## Belangrijk in 1.0.7

- Lost-passwordverzoeken voor bestaande en onbekende accounts eindigen op dezelfde WooCommerce-pagina.
- De bevestigingsmelding zegt expliciet dat DoctorCura om veiligheidsredenen niet bevestigt of een account of e-mailadres bestaat.
- De medische checkout bevat direct onder de bestaande waarheidsverklaring een tweede verplichte bevestiging over de voorschrijfbeslissing en de risico’s van onjuiste, misleidende of onvolledige informatie.
- Beide laatste medische bevestigingen worden op de WooCommerce-bestelling opgeslagen.
- Lege invoer en ongeldige nonce blijven normale validatiefouten.

## Installatie

1. Upload de pluginmap naar `/wp-content/plugins/`.
2. Activeer DoctorCura Core.
3. Controleer WooCommerce, permalinks, accountflows en checkout op staging.
4. Activeer DoctorCura UI afzonderlijk wanneer de storefrontmodules nodig zijn.

## Releasegrens

Een nieuwe release vereist minimaal PHP-syntaxcontrole en stagingtests van account-, checkout-, order- en deactivatiegedrag. Plaats geen medische, account- of ordergegevens in publieke issues.

## Licentie

GPL-2.0-or-later, zoals vastgelegd in de pluginheader en `readme.txt`.
