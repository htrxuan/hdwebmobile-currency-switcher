<?php

namespace htrxuan\hdcs;

if (!defined('ABSPATH')) {
    exit;
}

final class HDCS_Core
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
        $this->includes();
        $this->init_hooks();
    }

    private function __clone()
    {
    }

    private function includes()
    {
        require_once HDCS_PLUGIN_DIR . 'includes/class-hdcs-repository.php';
        require_once HDCS_PLUGIN_DIR . 'includes/class-hdcs-frontend.php';
        require_once HDCS_PLUGIN_DIR . 'includes/class-hdcs-price-converter.php';
        require_once HDCS_PLUGIN_DIR . 'includes/class-hdcs-admin.php';
    }

    private function init_hooks()
    {
        add_action('admin_notices', array($this, 'render_missing_woocommerce_notice'));

        if (!class_exists('WooCommerce')) {
            return;
        }

        HDCS_Frontend::get_instance();
        HDCS_Price_Converter::get_instance();

        // HDCS_Admin registers admin-menu/settings hooks itself, but this must load
        // unconditionally (not only when is_admin()) since it also owns the
        // hdwebmobile_hub_tabs registration used by the shared hub page.
        HDCS_Admin::get_instance();
    }

    public function render_missing_woocommerce_notice()
    {
        $screen = get_current_screen();
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        if (!get_transient('hdcs_wc_missing_notice')) {
            return;
        }
        delete_transient('hdcs_wc_missing_notice');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e('HDWebmobile Currency Switcher requires WooCommerce to be installed and active. The plugin has been deactivated.', 'hdwebmobile-currency-switcher'); ?>
            </p>
        </div>
        <?php
    }
}
