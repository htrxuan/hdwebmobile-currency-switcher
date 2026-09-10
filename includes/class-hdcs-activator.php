<?php

namespace htrxuan\hdcs;

if (!defined('ABSPATH')) {
    exit;
}

class HDCS_Activator
{

    public static function activate()
    {
        if (!self::is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(HDCS_PLUGIN_FILE));
            set_transient('hdcs_wc_missing_notice', true, 30);
            return;
        }

        // Seed the currency list with the store's own base currency so the switcher
        // always has at least one valid entry -- never an empty, unusable config.
        if (false === get_option('hdcs_currencies', false)) {
            // The raw option, not get_woocommerce_currency() -- see class-hdcs-frontend.php's
            // get_selected_currency() docblock for why calling the filtered getter from this
            // plugin's own code would recurse into our own `woocommerce_currency` filter.
            $base = get_option('woocommerce_currency');
            update_option('hdcs_currencies', array(
                $base => array(
                    'symbol'   => get_woocommerce_currency_symbol($base),
                    'rate'     => 1.0,
                    'decimals' => wc_get_price_decimals(),
                ),
            ));
        }
    }

    public static function is_woocommerce_active()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('woocommerce/woocommerce.php') || class_exists('WooCommerce');
    }

    public static function declare_hpos_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDCS_PLUGIN_FILE, true);
        }
    }
}
