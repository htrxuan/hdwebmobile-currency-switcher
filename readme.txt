=== HDWebmobile Currency Switcher ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, currency switcher, multi-currency, exchange rate
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers browse and check out in their own currency, with admin-configured rates that can never be changed by a bare URL parameter.

== Description ==

HDWebmobile Currency Switcher adds a simple currency dropdown to your store. Customers pick a currency, prices convert everywhere (shop, product page, cart, checkout) using the rate you set, and their order is recorded in that currency.

= Why this plugin exists =
A competing WooCommerce currency-switcher plugin had a serious authorization vulnerability (CVE-2026-4094, CVSS 8.1, CWE-862 Missing Authorization): its entire multi-currency configuration could be wiped out by ANY authenticated user -- Contributor level and above -- simply by visiting any wp-admin page with a `woocs_reset` parameter appended to the URL. The vulnerable code read that parameter directly from an `admin_head` hook with no capability check and no nonce at all. This plugin closes that vulnerability class by construction:

* The currency list and exchange rates are written in exactly one place, and that one place requires `current_user_can('manage_woocommerce')` AND a verified nonce before a single field is read -- there is no `admin_head`/`admin_init` code path that acts on a bare URL parameter.
* The customer-facing currency switch is a completely separate, harmless mechanism: it only ever selects which already-admin-configured currency to display prices in, stored in the visitor's own cookie. It can never write to the store's configuration, and an unlisted or tampered currency code is always silently ignored.
* Every submitted rate and currency code is independently validated (a plausible 3-letter code, a positive rate) regardless of who is calling -- the authorization check is not the only safety net.

= Key Features =
* Add as many currencies as you like, each with its own symbol, exchange rate, and decimal places
* A simple `[hdcs_switcher]` shortcode, plus automatic placement on shop and product pages
* Optionally show the switcher right in your site navigation menu (works with both classic and block themes)
* Optionally pick a visitor's starting currency from their country, using WooCommerce's own local geolocation -- no third-party API is called, and a visitor who picks a currency always keeps their choice
* Prices convert consistently across shop, cart, and checkout using WooCommerce's own price pipeline
* Orders are stamped with the currency the customer actually saw and paid in

= Limitations (please read before installing) =
* Exchange rates are set manually by the store admin -- there is no automatic exchange-rate lookup or daily update
* This does not manage multi-currency payment gateway settlement; whether your payment processor can actually settle in a given currency is between you and your gateway
* Automatic currency-by-country relies on WooCommerce's own geolocation database being set up (a free MaxMind license key under WooCommerce > Settings > Integration). If it isn't, or the visitor's country can't be determined, the store's base currency is used
* Automatic selection only chooses the *starting* currency on a visitor's first look; it never overrides a currency the visitor has picked

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-currency-switcher` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Under WooCommerce > HDWebmobile > Currency Switcher, add the currencies you want to offer and their rates.

== How to Use ==

= 1. Add currencies =
On the Currency Switcher settings tab, add a currency code, symbol, and rate relative to your store's base currency.

= 2. Customers switch =
The switcher dropdown appears automatically on shop and product pages, and via the `[hdcs_switcher]` shortcode anywhere else. Tick "Show the currency switcher in the site navigation menu" to also add it to your header menu.

= 3. (Optional) Start from the visitor's country =
Fill in the Countries column for each currency (e.g. `US, CA` for USD) and tick "Pick a starting currency from the visitor's country". On a visitor's first look, WooCommerce's own geolocation chooses which currency to show; once they pick one themselves, that choice is remembered and always wins.

== Screenshots ==

1. The Currency Switcher settings tab under WooCommerce > HDWebmobile.
2. The currency dropdown on a shop page.

== Changelog ==

= 1.1.0 =
* New: option to show the currency switcher in the site navigation menu (classic and block themes).
* New: option to pick a visitor's starting currency from their country via WooCommerce's own local geolocation (no third-party API). Each currency takes an optional list of country codes; a manually chosen currency always wins.

= 1.0.0 =
* Initial release: capability- and nonce-gated currency configuration, cookie-based customer switch, full price-pipeline conversion.
