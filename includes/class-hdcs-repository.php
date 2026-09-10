<?php

namespace htrxuan\hdcs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Closes CVE-2026-4094 (CVSS 8.1, CWE-862 Missing Authorization) in a competing WooCommerce
 * currency-switcher plugin: its multi-currency configuration could be wiped entirely by ANY
 * authenticated user (Contributor level and above) simply by visiting any wp-admin page with a
 * `woocs_reset` query-string parameter appended -- the vulnerable code read that parameter and
 * acted on it directly from an `admin_head` hook, with no capability check and no nonce.
 *
 * This class is the ONLY place the currency list is ever written, and save_currencies() is
 * called from exactly one place in the entire plugin (class-hdcs-admin.php's settings-save
 * handler), which itself requires current_user_can('manage_woocommerce') AND a verified nonce
 * before this method is ever reached. There is no code path anywhere -- admin_head, admin_init,
 * a bare $_GET read, or otherwise -- that can trigger a config change from an unauthenticated or
 * under-privileged request. The customer-facing currency SWITCH (class-hdcs-frontend.php) never
 * calls this class's write method at all -- it only ever reads the already-validated list and
 * stores which entry the visitor picked, in their own session, so switching currency as a
 * customer can never touch the admin's configuration.
 */
class HDCS_Repository
{
    const OPTION_KEY = 'hdcs_currencies';

    /**
     * @return array<string, array{symbol: string, rate: float, decimals: int}>
     */
    public static function get_currencies()
    {
        $currencies = get_option(self::OPTION_KEY, array());
        return is_array($currencies) ? $currencies : array();
    }

    public static function get_currency($code)
    {
        $currencies = self::get_currencies();
        return $currencies[$code] ?? null;
    }

    /**
     * The only write path for the currency list. Every value is validated here regardless of
     * caller -- the caller's own authorization check (class-hdcs-admin.php) is the access-control
     * boundary, but this method never trusts the shape of its input either.
     *
     * @param array $rows Each row: ['code' => string, 'symbol' => string, 'rate' => mixed, 'decimals' => mixed]
     */
    public static function save_currencies(array $rows)
    {
        // The raw option, not get_woocommerce_currency() -- see class-hdcs-frontend.php's
        // get_selected_currency() docblock for why the filtered getter would recurse here.
        $base       = get_option('woocommerce_currency');
        $currencies = array();

        foreach ($rows as $row) {
            $code = isset($row['code']) ? strtoupper(sanitize_text_field($row['code'])) : '';
            if (!preg_match('/^[A-Z]{3}$/', $code)) {
                continue; // Not a plausible ISO 4217-shaped code -- silently skip, never guess.
            }

            $rate = isset($row['rate']) ? (float) $row['rate'] : 0;
            if ($code === $base) {
                $rate = 1.0; // The base currency's own rate is always exactly 1, never editable.
            }
            if ($rate <= 0) {
                continue; // A zero or negative rate would corrupt every converted price -- reject.
            }

            $decimals = isset($row['decimals']) ? max(0, min(4, (int) $row['decimals'])) : 2;
            $symbol   = isset($row['symbol']) ? sanitize_text_field($row['symbol']) : $code;

            $currencies[$code] = array(
                'symbol'   => $symbol,
                'rate'     => $rate,
                'decimals' => $decimals,
            );
        }

        // The base currency must always be present, at rate 1 -- the switcher can never end up
        // in a state where a customer cannot view prices in the store's own real currency.
        if (!isset($currencies[$base])) {
            $currencies[$base] = array(
                'symbol'   => get_woocommerce_currency_symbol($base),
                'rate'     => 1.0,
                'decimals' => wc_get_price_decimals(),
            );
        }

        update_option(self::OPTION_KEY, $currencies);
        return $currencies;
    }
}
