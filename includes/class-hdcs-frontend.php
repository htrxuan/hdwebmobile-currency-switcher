<?php

namespace htrxuan\hdcs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the customer-facing currency switch. This is deliberately a read-only preference: the
 * chosen code is validated against the admin's own configured currency list (never trusted
 * blindly) and stored only in the visitor's own cookie -- it is never written to any shared
 * option, never touches HDCS_Repository::save_currencies(), and can never affect any other
 * visitor or the store's actual configuration. See class-hdcs-repository.php's docblock for the
 * CVE this whole separation exists to prevent.
 */
final class HDCS_Frontend
{
    const COOKIE_NAME = 'hdcs_currency';

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
        add_action('init', array($this, 'maybe_handle_switch'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
        add_shortcode('hdcs_switcher', array($this, 'render_switcher'));
        add_action('woocommerce_before_shop_loop', array($this, 'render_switcher'), 5);
        add_action('woocommerce_before_single_product_summary', array($this, 'render_switcher'), 5);
    }

    public function maybe_enqueue_assets()
    {
        wp_enqueue_style('hdcs-frontend', HDCS_PLUGIN_URL . 'assets/css/hdcs-frontend.css', array(), HDCS_VERSION);
    }

    /**
     * Reads the requested currency switch (a plain query var, not a form submission with
     * side effects on anyone else's data) and, ONLY if it exactly matches an entry in the
     * admin's own configured list, stores it in this visitor's cookie. Anything else --
     * an unlisted code, a malformed value -- is silently ignored, never guessed at or
     * partially honored.
     */
    public function maybe_handle_switch()
    {
        if (empty($_GET['hdcs_currency'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only display preference, not a state change to any shared/other-visitor data; validated below against the admin's own whitelist regardless.
            return;
        }

        $requested  = strtoupper(sanitize_text_field(wp_unslash($_GET['hdcs_currency']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
        $currencies = HDCS_Repository::get_currencies();

        if (!isset($currencies[$requested])) {
            return; // Not one of the admin's configured currencies -- never honored.
        }

        setcookie(self::COOKIE_NAME, $requested, time() + MONTH_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[self::COOKIE_NAME] = $requested; // Available immediately for this same request.
    }

    /**
     * The single source of truth for "what currency is this visitor viewing right now" --
     * always validated against the current admin-configured list, so a stale cookie from a
     * currency the admin later removed can never be honored.
     *
     * Reads the store's base currency via the RAW `woocommerce_currency` option, never via
     * get_woocommerce_currency() -- that function applies the `woocommerce_currency` filter,
     * which class-hdcs-price-converter.php hooks straight back into this method. Calling the
     * filtered getter here would recurse infinitely (get_selected_currency() ->
     * get_woocommerce_currency() -> our own filter -> get_selected_currency() -> ...).
     */
    public static function get_selected_currency()
    {
        $currencies = HDCS_Repository::get_currencies();
        $base       = get_option('woocommerce_currency');

        $cookie = isset($_COOKIE[self::COOKIE_NAME]) ? strtoupper(sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_NAME]))) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on this same line; only ever used as an array-key lookup against the admin's own whitelist below, never output or trusted otherwise.

        if ($cookie && isset($currencies[$cookie])) {
            return $cookie;
        }

        return $base;
    }

    public function render_switcher()
    {
        $currencies = HDCS_Repository::get_currencies();
        if (count($currencies) < 2) {
            return; // Nothing to switch between.
        }

        $current = self::get_selected_currency();
        $base_url = remove_query_arg('hdcs_currency');

        echo '<div class="hdcs-switcher"><label for="hdcs-currency-select">' . esc_html__('Currency:', 'hdwebmobile-currency-switcher') . '</label> ';
        echo '<select id="hdcs-currency-select" onchange="location.href=this.value">';
        foreach ($currencies as $code => $data) {
            printf(
                '<option value="%s"%s>%s (%s)</option>',
                esc_url(add_query_arg('hdcs_currency', $code, $base_url)),
                selected($code, $current, false),
                esc_html($code),
                esc_html($data['symbol'])
            );
        }
        echo '</select></div>';
    }
}
