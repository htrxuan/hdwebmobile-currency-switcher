<?php

namespace htrxuan\hdcs;

if (!defined('ABSPATH')) {
    exit;
}

class HDCS_Admin
{
    const NONCE_ACTION = 'hdcs_save_currencies';

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
        require_once HDCS_PLUGIN_DIR . 'includes/class-hdcs-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_post_hdcs_save_currencies', array($this, 'save_currencies'));
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['currency-switcher'] = array(
            'label'  => __('Currency Switcher', 'hdwebmobile-currency-switcher'),
            'order'  => 145,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    /**
     * The ONLY place HDCS_Repository::save_currencies() is ever called. Both the capability
     * check AND the nonce check happen before a single byte of $_POST is read -- this is the
     * exact gate the vulnerable competing plugin was missing entirely (see class-hdcs-
     * repository.php's docblock for CVE-2026-4094's details).
     */
    public function save_currencies()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-currency-switcher'));
        }
        if (!isset($_POST['hdcs_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hdcs_nonce'])), self::NONCE_ACTION)) {
            wp_die(esc_html__('Security check failed. Please try again.', 'hdwebmobile-currency-switcher'));
        }

        $codes     = isset($_POST['hdcs_code']) ? (array) wp_unslash($_POST['hdcs_code']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is individually sanitized/validated inside HDCS_Repository::save_currencies(), which never trusts caller input regardless of this authorization gate.
        $symbols   = isset($_POST['hdcs_symbol']) ? (array) wp_unslash($_POST['hdcs_symbol']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see above.
        $rates     = isset($_POST['hdcs_rate']) ? (array) wp_unslash($_POST['hdcs_rate']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see above.
        $decimals  = isset($_POST['hdcs_decimals']) ? (array) wp_unslash($_POST['hdcs_decimals']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- see above.

        $rows = array();
        foreach ($codes as $i => $code) {
            $rows[] = array(
                'code'     => $code,
                'symbol'   => $symbols[$i] ?? '',
                'rate'     => $rates[$i] ?? 0,
                'decimals' => $decimals[$i] ?? 2,
            );
        }

        HDCS_Repository::save_currencies($rows);

        wp_safe_redirect(admin_url('admin.php?page=hdwebmobile&tab=currency-switcher&updated=1'));
        exit;
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-currency-switcher'));
        }

        $currencies = HDCS_Repository::get_currencies();
        // The raw option, not get_woocommerce_currency() -- see class-hdcs-frontend.php's
        // get_selected_currency() docblock for why the filtered getter would recurse here.
        $base       = get_option('woocommerce_currency');
        ?>
        <p><?php esc_html_e('Let customers browse your store in a currency of their choice. Checkout charges the amount shown, converted using the rate you set below -- there is no automatic exchange-rate lookup.', 'hdwebmobile-currency-switcher'); ?></p>

        <?php if (!empty($_GET['updated'])) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success flag, no state change. ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Currencies saved.', 'hdwebmobile-currency-switcher'); ?></p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="hdcs_save_currencies" />
            <?php wp_nonce_field(self::NONCE_ACTION, 'hdcs_nonce'); ?>
            <table class="widefat striped" style="max-width:700px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Code', 'hdwebmobile-currency-switcher'); ?></th>
                        <th><?php esc_html_e('Symbol', 'hdwebmobile-currency-switcher'); ?></th>
                        <th><?php
                            /* translators: %s: the store's base currency code */
                            printf(esc_html__('Rate (vs. %s)', 'hdwebmobile-currency-switcher'), esc_html($base));
                        ?></th>
                        <th><?php esc_html_e('Decimals', 'hdwebmobile-currency-switcher'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currencies as $code => $data) : ?>
                        <tr>
                            <td><input type="text" name="hdcs_code[]" value="<?php echo esc_attr($code); ?>" maxlength="3" style="width:5em;text-transform:uppercase;" <?php disabled($code, $base); ?> /></td>
                            <td><input type="text" name="hdcs_symbol[]" value="<?php echo esc_attr($data['symbol']); ?>" style="width:5em;" /></td>
                            <td><input type="number" step="0.0001" min="0.0001" name="hdcs_rate[]" value="<?php echo esc_attr($data['rate']); ?>" style="width:8em;" <?php disabled($code, $base); ?> /></td>
                            <td><input type="number" step="1" min="0" max="4" name="hdcs_decimals[]" value="<?php echo esc_attr($data['decimals']); ?>" style="width:5em;" /></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><input type="text" name="hdcs_code[]" placeholder="EUR" maxlength="3" style="width:5em;text-transform:uppercase;" /></td>
                        <td><input type="text" name="hdcs_symbol[]" placeholder="&euro;" style="width:5em;" /></td>
                        <td><input type="number" step="0.0001" min="0.0001" name="hdcs_rate[]" placeholder="0.92" style="width:8em;" /></td>
                        <td><input type="number" step="1" min="0" max="4" name="hdcs_decimals[]" value="2" style="width:5em;" /></td>
                    </tr>
                </tbody>
            </table>
            <p class="description"><?php esc_html_e('Leave the last row\'s code blank to skip it. The store\'s base currency is always included at rate 1 and cannot be removed.', 'hdwebmobile-currency-switcher'); ?></p>
            <?php submit_button(__('Save Currencies', 'hdwebmobile-currency-switcher')); ?>
        </form>

        <p><code>[hdcs_switcher]</code> <?php esc_html_e('-- shortcode to place the switcher anywhere. It also appears automatically above the shop and single product pages.', 'hdwebmobile-currency-switcher'); ?></p>
        <?php
    }
}
