# DoctorCura Core

> **Experiment · WordPress/PHP · WooCommerce · account, checkout and order workflow logic**

**Developer profile:** [Andrew Baeten](https://github.com/Yolol100) · [Portfolio cases](https://andrewbaeten.nl/category/cases)

DoctorCura Core is the domain-logic plugin for the DoctorCura experiment. It contains account, verification, checkout, order, cancellation and request workflows while keeping presentation concerns in the separate [DoctorCura UI](https://github.com/Yolol100/doctorcura-ui) plugin.

## What it demonstrates

| Area | Implementation |
| --- | --- |
| Account flows | WooCommerce login, account verification and password-reset behaviour |
| Checkout | Additional questionnaire and required confirmation handling |
| Orders | Order metadata, cancellation flow and related workflow logic |
| Privacy | Generic public login/reset responses intended to avoid exposing account existence |
| Compatibility | Guards for older plugin copies and migrated snippets |
| Separation | Domain behaviour remains independent from DoctorCura UI presentation code |

## Relationship with DoctorCura UI

```text
DoctorCura Core -> account, checkout, order and request behaviour
DoctorCura UI   -> storefront, product and presentation behaviour
WordPress + WooCommerce -> shared runtime platform
```

Core and UI have independent versions and release packages. Compatibility should be tested against the exact combination deployed rather than assuming a fixed cross-version pairing.

## Requirements

- WordPress 6.4+
- PHP 8.1+
- WooCommerce for account, checkout and order flows
- Current plugin version: **1.0.11**

`readme.txt` remains the source for the current stable tag and changelog.

## Current release focus

Version 1.0.11 hardens the additional checkout confirmation flow by explicitly requesting field markup as a string and validating the returned type before modifying it. Earlier privacy-oriented login and lost-password behaviour remains part of the current codebase.

## Installation and verification

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate **DoctorCura Core**.
3. Confirm WooCommerce and the site's permalink configuration are working.
4. Test account, password-reset, checkout, order, cancellation and request flows on staging.
5. Activate DoctorCura UI separately when the storefront modules are required.

## Privacy and safety

This plugin can process account, order and questionnaire data. Do not use real customer or medical data in public issues, screenshots or debug exports. Validate updates on staging and keep a rollback path for production changes.

## Repository structure

```text
doctorcura-core.php   Plugin bootstrap and runtime metadata
includes/             Account, checkout, order, request and integration logic
assets/               Plugin assets
readme.txt            WordPress release information and changelog
uninstall.php         Plugin cleanup
```

## Portfolio context

This repository is intentionally labelled as an experiment rather than a flagship product. Its value is the separation of sensitive workflow logic from presentation code, plus defensive handling around account privacy and WooCommerce lifecycle behaviour.

## License

GPL-2.0-or-later.
