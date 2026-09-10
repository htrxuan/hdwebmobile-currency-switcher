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
 *
 * The optional "pick a starting currency from the visitor's country" behaviour reuses
 * WooCommerce's OWN geolocation (WC_Geolocation) -- there is no third-party API call and no new
 * dependency. It only ever chooses the DEFAULT currency for a visitor who hasn't picked one
 * yet; a manual switch is always honoured over it and remembered in the cookie.
 */
final class HDCS_Frontend
{
    const COOKIE_NAME       = 'hdcs_currency';
    const OPTION_SHOW_MENU  = 'hdcs_show_in_menu';
    const OPTION_AUTO_GEO   = 'hdcs_auto_by_country';

    private static $instance         = null;
    private static $geo_country      = null;  // memoised per request
    private static $menu_switcher_done = false;

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

        if (get_option(self::OPTION_SHOW_MENU)) {
            add_filter('wp_nav_menu_items', array($this, 'add_to_classic_menu'), 10, 2);
            // Block themes render the header nav as a core/navigation block, so
            // wp_nav_menu_items never fires -- filter the block's own output instead.
            add_filter('render_block', array($this, 'add_to_block_menu'), 10, 2);
        }
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
     * Order of precedence: (1) the visitor's own manual choice, in their cookie; (2) if
     * geolocation is enabled and no cookie is set, the currency mapped to the visitor's
     * country; (3) the store's base currency.
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

        if (get_option(self::OPTION_AUTO_GEO)) {
            $geo_currency = HDCS_Repository::find_currency_for_country(self::detect_country());
            if ($geo_currency && isset($currencies[$geo_currency])) {
                return $geo_currency;
            }
        }

        return $base;
    }

    /**
     * The visitor's country, via WooCommerce's own local GeoLite2 database only. Memoised for
     * the request.
     *
     * geolocate_ip('', false, false): no outbound API call (the third arg) -- WC core itself
     * passes api_fallback=false here, in wc_get_customer_default_location(). An API fallback
     * would add latency to uncached page loads and send the visitor's IP to a third-party
     * service, exactly the kind of hidden dependency this suite avoids. The second arg
     * (settings fallback) is also false: we want a real geolocation hit or nothing, never a
     * guess derived from the store's base country. If the DB can't place the visitor we just
     * fall through to the store's base currency.
     */
    private static function detect_country()
    {
        if (null !== self::$geo_country) {
            return self::$geo_country;
        }
        self::$geo_country = '';

        if (is_admin() || (defined('DOING_CRON') && DOING_CRON) || !class_exists('WC_Geolocation')) {
            return self::$geo_country;
        }

        $geo = \WC_Geolocation::geolocate_ip('', false, false);
        if (!empty($geo['country'])) {
            self::$geo_country = strtoupper($geo['country']);
        }

        return self::$geo_country;
    }

    public function render_switcher()
    {
        echo $this->get_switcher_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_switcher_html() escapes every dynamic value at the point of output; the surrounding markup is static.
    }

    /**
     * @return string The switcher markup, or '' when there's nothing to switch between.
     */
    private function get_switcher_html($wrapper_class = 'hdcs-switcher')
    {
        $currencies = HDCS_Repository::get_currencies();
        if (count($currencies) < 2) {
            return '';
        }

        $current  = self::get_selected_currency();
        $base_url = remove_query_arg('hdcs_currency');

        $html  = '<span class="' . esc_attr($wrapper_class) . '">';
        $html .= '<label for="hdcs-currency-select">' . esc_html__('Currency:', 'hdwebmobile-currency-switcher') . '</label> ';
        $html .= '<select id="hdcs-currency-select" onchange="location.href=this.value">';
        foreach ($currencies as $code => $data) {
            $html .= sprintf(
                '<option value="%s"%s>%s (%s)</option>',
                esc_url(add_query_arg('hdcs_currency', $code, $base_url)),
                selected($code, $current, false),
                esc_html($code),
                esc_html($data['symbol'])
            );
        }
        $html .= '</select></span>';

        return $html;
    }

    /**
     * Classic (wp_nav_menu) themes: append the switcher as one more menu item, once, on the
     * primary location if the theme registers one, otherwise on the first menu rendered.
     */
    public function add_to_classic_menu($items, $args)
    {
        if (self::$menu_switcher_done) {
            return $items;
        }
        $theme_location = is_object($args) && !empty($args->theme_location) ? $args->theme_location : '';
        if ($theme_location && 'primary' !== $theme_location && 'header' !== $theme_location) {
            return $items; // Wait for the primary/header menu if this theme has one.
        }

        $switcher = $this->get_switcher_html('hdcs-switcher hdcs-switcher--menu');
        if ('' === $switcher) {
            return $items;
        }

        self::$menu_switcher_done = true;
        return $items . '<li class="menu-item hdcs-menu-item">' . $switcher . '</li>';
    }

    /**
     * Block themes (Twenty Twenty-Five etc.): the header nav is a core/navigation block, so
     * wp_nav_menu_items never fires. Inject the switcher <li> just before the block's closing
     * </ul>, once, on the first navigation block rendered (typically the header).
     */
    public function add_to_block_menu($block_content, $block)
    {
        if (self::$menu_switcher_done) {
            return $block_content;
        }
        $name = is_array($block) && isset($block['blockName']) ? $block['blockName'] : '';
        if ('core/navigation' !== $name || false === strpos($block_content, '</ul>')) {
            return $block_content;
        }

        $switcher = $this->get_switcher_html('hdcs-switcher hdcs-switcher--menu');
        if ('' === $switcher) {
            return $block_content;
        }

        self::$menu_switcher_done = true;
        $li = '<li class="wp-block-navigation-item hdcs-menu-item">' . $switcher . '</li>';

        // Insert before the last </ul> in the block's markup.
        $pos = strrpos($block_content, '</ul>');
        return substr($block_content, 0, $pos) . $li . substr($block_content, $pos);
    }
}
