<?php

/**
 * Plugin Name: HDWebmobile Currency Switcher
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-currency-switcher/
 * Description: Let customers browse your store in their own currency. The admin-configured currency list and exchange rates can only ever be changed through a capability- and nonce-checked settings form -- never by a bare URL parameter, no matter who visits it.
 * Version: 1.1.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-currency-switcher
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdcs;

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('HDCS_VERSION', '1.1.0');
define('HDCS_PLUGIN_FILE', __FILE__);
define('HDCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDCS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDCS_PLUGIN_DIR . 'includes/class-hdcs-activator.php';

register_activation_hook(HDCS_PLUGIN_FILE, array(HDCS_Activator::class, 'activate'));
add_action('before_woocommerce_init', array(HDCS_Activator::class, 'declare_hpos_compatibility'));

add_action('plugins_loaded', function () {
    require_once HDCS_PLUGIN_DIR . 'includes/class-hdcs-core.php';
    HDCS_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(HDCS_PLUGIN_FILE), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" style="color:#d54e21;font-weight:bold;">' . __('Donate', 'hdwebmobile-currency-switcher') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
