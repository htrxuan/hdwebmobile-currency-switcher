<?php

namespace htrxuan\hdcs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Applies the visitor's selected currency (HDCS_Frontend::get_selected_currency(), always
 * validated against the admin's own configured list) to WooCommerce's own price-getter filters,
 * so cart, checkout, and order totals all convert consistently through WooCommerce's normal
 * calculation pipeline -- the same approach real currency-switcher plugins use. Every filter
 * here operates only on the raw value WooCommerce itself passes in; nothing here ever re-reads
 * $product->get_price() (which would recursively re-trigger these same filters).
 */
final class HDCS_Price_Converter
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        foreach (array('get_price', 'get_regular_price', 'get_sale_price') as $suffix) {
            add_filter("woocommerce_product_{$suffix}", array($this, 'convert_price'), 20, 2);
            add_filter("woocommerce_product_variation_{$suffix}", array($this, 'convert_price'), 20, 2);
        }

        add_filter('woocommerce_currency', array($this, 'filter_currency'));
        add_filter('woocommerce_currency_symbol', array($this, 'filter_currency_symbol'), 10, 2);
        // The real WC core filter is wc_get_price_decimals() -- there is no
        // "woocommerce_price_num_decimals" hook; confirmed by reading wc-formatting-functions.php
        // directly after this filter silently never fired during live testing.
        add_filter('wc_get_price_decimals', array($this, 'filter_decimals'));

        // Stamp the order with the currency the customer actually saw/paid in, so the order's
        // own record stays consistent with the converted totals it was created with.
        add_action('woocommerce_checkout_create_order', array($this, 'stamp_order_currency'), 5);
    }

    private function current_conversion()
    {
        $code = HDCS_Frontend::get_selected_currency();
        $data = HDCS_Repository::get_currency($code);
        return array($code, $data);
    }

    public function convert_price($price, $product)
    {
        if ('' === $price || null === $price) {
            return $price;
        }
        list(, $data) = $this->current_conversion();
        if (!$data || 1.0 === (float) $data['rate']) {
            return $price;
        }
        return ((float) $price) * (float) $data['rate'];
    }

    public function filter_currency($currency)
    {
        list($code) = $this->current_conversion();
        return $code ?: $currency;
    }

    public function filter_currency_symbol($symbol, $currency)
    {
        $data = HDCS_Repository::get_currency($currency);
        return $data ? $data['symbol'] : $symbol;
    }

    public function filter_decimals($decimals)
    {
        list(, $data) = $this->current_conversion();
        return $data ? (int) $data['decimals'] : $decimals;
    }

    public function stamp_order_currency($order)
    {
        list($code) = $this->current_conversion();
        if ($code) {
            $order->set_currency($code);
        }
    }
}
