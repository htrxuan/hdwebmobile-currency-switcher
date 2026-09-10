# HDWebmobile Currency Switcher

Let customers browse and check out in their own currency, with admin-configured rates that can never be changed by a bare URL parameter.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-currency-switcher/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

HDWebmobile Currency Switcher adds a simple currency dropdown to your store. Customers pick a currency, prices convert everywhere (shop, product page, cart, checkout) using the rate you set, and their order is recorded in that currency.

## Why this plugin exists

A competing WooCommerce currency-switcher plugin had a serious authorization vulnerability (CVE-2026-4094, CVSS 8.1, CWE-862 Missing Authorization): its entire multi-currency configuration could be wiped out by ANY authenticated user -- Contributor level and above -- simply by visiting any wp-admin page with a `woocs_reset` parameter appended to the URL. The vulnerable code read that parameter directly from an `admin_head` hook with no capability check and no nonce at all. This plugin closes that vulnerability class by construction:

* The currency list and exchange rates are written in exactly one place, and that one place requires `current_user_can('manage_woocommerce')` AND a verified nonce before a single field is read.
* The customer-facing currency switch is a completely separate, harmless mechanism: it only ever selects which already-admin-configured currency to display prices in, stored in the visitor's own cookie. It can never write to the store's configuration.
* Every submitted rate and currency code is independently validated regardless of who is calling.

## Features

* Add as many currencies as you like, each with its own symbol, exchange rate, and decimal places
* A `[hdcs_switcher]` shortcode, plus automatic placement on shop and product pages
* Prices convert consistently across shop, cart, and checkout using WooCommerce's own price pipeline
* Orders are stamped with the currency the customer actually saw and paid in

## Limitations (v1)

* Exchange rates are set manually -- no automatic exchange-rate lookup
* Does not manage multi-currency payment gateway settlement

## Installation

1. Upload the plugin to `/wp-content/plugins/hdwebmobile-currency-switcher`, or install through the WordPress plugins screen.
2. Activate the plugin. WooCommerce must already be installed and active.
3. Under WooCommerce > HDWebmobile > Currency Switcher, add the currencies you want to offer and their rates.

## License

GPLv2 or later. See [LICENSE](LICENSE).
