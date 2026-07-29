<?php

// Ignore if access directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class PayPal_Brasil.
 * @property PayPal_Brasil_Handler handler
 */
class PayPal_Brasil
{

	/**
	 * @var PayPal_Brasil
	 */
	private static $instance;
	private PayPal_Brasil_Handler $handler;

	/**
	 * PayPal_Brasil constructor.
	 */
	private function __construct()
	{
		// Load plugin text domain.
		add_action('init', array($this, 'load_plugin_textdomain'));



		// Include the necessary files and init.
		$this->includes();
		$this->init();
		// Check if Extra Checkout Fields for Brazil is installed.
		if (is_admin()) {
			// Notices for ECFB and WC.
			add_action('admin_notices', array($this, 'ecfb_missing_notice'));
			add_action('admin_notices', array($this, 'woocommerce_wrong_version'));
			add_action( 'admin_notices', array($this, 'paypal_brazil_notice_update_bcdc') );

			// Add custom links to plugins page.
			add_filter('plugin_action_links_' . plugin_basename(PAYPAL_PAYMENTS_MAIN_FILE), array(
				$this,
				'plugin_action_links'
			)
			);
		}

		// Check if WC is compatible.
		if (!self::woocommerce_incompatible()) {
			add_action('plugins_loaded', array($this, 'include_gateways'));
			add_filter('woocommerce_payment_gateways', array($this, 'add_payment_methods'));
			add_action('init', array($this, 'filter_gateways_settings'));
			// After plugin update, ensure Capture refund/reversed webhook events are registered.
			add_action('woocommerce_init', array($this, 'maybe_sync_webhook_event_types'));
		}


	}

	/**
	 * In WC payments section, filter the gateways to be displayed based in a param in URL.
	 */
	public function filter_gateways_settings()
	{
		if (isset($_GET['page']) && isset($_GET['tab']) && $_GET['page'] === 'wc-settings' && $_GET['tab'] === 'checkout' && isset($_REQUEST['paypal-brasil'])) {
			add_filter('woocommerce_payment_gateways', array($this, 'filter_allowed_gateways'));
		}
	}

	/**
	 * Filter gateways that will be displayed in a custom settings page.
	 *
	 * @param $load_gateways
	 *
	 * @return mixed
	 */
	public function filter_allowed_gateways($load_gateways)
	{

		$allowed_gateways = array(
			'PayPal_Brasil_SPB_Gateway',
			'Paypal_Brasil_BCDC_Gateway',
		);

		if ( ! paypal_brasil_is_pplus_retired() ) {
			$allowed_gateways[] = 'PayPal_Brasil_Plus_Gateway';
		}

		foreach ($load_gateways as $key => $gateway) {
			if (!in_array($gateway, $allowed_gateways)) {
				unset($load_gateways[$key]);
			}
		}

		return $load_gateways;
	}

	/**
	 * Get plugin instance.
	 *
	 * @return PayPal_Brasil
	 */
	public static function get_instance()
	{
		// Init a instance if not created.
		if (!self::$instance) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Include files.
	 */
	private function includes()
	{
		include dirname(__FILE__) . '/includes/helpers.php';
		include dirname(__FILE__) . '/includes/api/class-paypal-brasil-api.php';
		include dirname(__FILE__) . '/includes/api/class-paypal-orders-api-v2.php';
		include dirname(__FILE__) . '/includes/api/class-paypal-brasil-api-exception.php';
		include dirname(__FILE__) . '/includes/api/class-paypal-brasil-connection-exception.php';
		include dirname(__FILE__) . '/includes/handlers/class-paypal-brasil-handler.php';
	}

	/**
	 * Init necessary classes.
	 */
	private function init()
	{
		$this->handler = new PayPal_Brasil_Handler();
	}

	/**
	 * Add plugin payment methods to WooCommerce methods.
	 *
	 * @param $methods
	 *
	 * @return array
	 */
	public function add_payment_methods($methods)
	{
		$methods[] = 'PayPal_Brasil_SPB_Gateway';
		$methods[] = 'Paypal_Brasil_BCDC_Gateway';

		if ( ! paypal_brasil_is_pplus_retired() ) {
			$methods[] = 'PayPal_Brasil_Plus_Gateway';
		}

		return $methods;
	}

	/**
	 * Include payment gateways.
	 */
	public function include_gateways()
	{
		if (class_exists('WC_Payment_Gateway')) {
			include_once dirname(__FILE__) . '/includes/payment-methods/abstract-class-paypal-brasil-gateway.php';
			include_once dirname(__FILE__) . '/includes/payment-methods/class-paypal-brasil-spb-gateway.php';
			include_once dirname(__FILE__) . '/includes/payment-methods/class-paypal-brasil-plus-gateway.php';
			include_once dirname(__FILE__) . '/includes/payment-methods/class-paypal-brasil-orders-gateway.php';
			include_once dirname(__FILE__) . '/includes/payment-methods/class-paypal-brasil-bcdc-gateway.php';
			if (!in_array(get_woocommerce_currency(), self::get_allowed_currencies())) {
				add_action('admin_notices', array($this, 'woocommerce_unavailable_currency'));
			}
		} else {
			add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
		}
	}

	/**
	 * After update (or first boot on new schema), sync Orders v2 webhook event types
	 * for enabled BCDC/SPB gateways that already have credentials saved.
	 */
	public function maybe_sync_webhook_event_types()
	{
		if (!defined('PAYPAL_BRASIL_WEBHOOK_EVENTS_VERSION')) {
			return;
		}

		if (get_option('paypal_brasil_webhook_events_version') === PAYPAL_BRASIL_WEBHOOK_EVENTS_VERSION) {
			return;
		}

		if (get_transient('paypal_brasil_webhook_sync_backoff') || get_transient('paypal_brasil_webhook_sync_lock')) {
			return;
		}

		if (!function_exists('WC') || !WC()->payment_gateways()) {
			return;
		}

		set_transient('paypal_brasil_webhook_sync_lock', 1, 5 * MINUTE_IN_SECONDS);

		$synced = $this->sync_orders_v2_webhook_events();

		delete_transient('paypal_brasil_webhook_sync_lock');

		if ($synced) {
			update_option('paypal_brasil_webhook_events_version', PAYPAL_BRASIL_WEBHOOK_EVENTS_VERSION);
			return;
		}

		// Retry later if PayPal API was unavailable.
		set_transient('paypal_brasil_webhook_sync_backoff', 1, HOUR_IN_SECONDS);
	}

	/**
	 * Create/update webhooks for enabled BCDC and SPB using saved credentials.
	 *
	 * @return bool True when every eligible gateway synced successfully (or none eligible).
	 */
	private function sync_orders_v2_webhook_events()
	{
		$gateway_ids = array(
			'paypal-brasil-bcdc-gateway',
			'paypal-brasil-spb-gateway',
		);

		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		$attempted        = 0;
		$failed           = 0;

		foreach ($gateway_ids as $gateway_id) {
			if (empty($payment_gateways[$gateway_id]) || !is_object($payment_gateways[$gateway_id])) {
				continue;
			}

			$gateway = $payment_gateways[$gateway_id];

			if ($gateway->get_option('enabled') !== 'yes') {
				continue;
			}

			$client_id = $gateway->get_client_id();
			$secret    = $gateway->get_secret();

			if (empty($client_id) || empty($secret)) {
				continue;
			}

			$attempted++;

			if (isset($gateway->api) && is_object($gateway->api) && method_exists($gateway->api, 'update_credentials')) {
				$gateway->api->update_credentials($client_id, $secret, $gateway->mode);
			}

			$result = $gateway->create_webhooks();
			if ($result === false) {
				$failed++;
				if (class_exists('WC_PAYPAL_LOGGER')) {
					WC_PAYPAL_LOGGER::log(
						'Automatic webhook event sync failed after plugin update.',
						$gateway_id,
						'error'
					);
				}
			}
		}

		if ($attempted === 0) {
			return true;
		}

		return $failed === 0;
	}

	/**
	 * Action links.
	 *
	 * @param array $links Action links.
	 *
	 * @return array
	 */
	public function plugin_action_links($links)
	{
		$plugin_links = array(
			'<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&paypal-brasil')) . '">' . __('Settings', "paypal-brasil-para-woocommerce") . '</a>',
		);

		return array_merge($plugin_links, $links);
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain()
	{
		load_plugin_textdomain("paypal-brasil-para-woocommerce", false, dirname(plugin_basename(PAYPAL_PAYMENTS_MAIN_FILE)) . '/languages');
	}


	/**
	 * Return if WooCommerce is compatible or not.
	 * @return mixed
	 */
	public static function woocommerce_incompatible()
	{
		$version = get_option('woocommerce_version');

		return version_compare($version, '3.6.0', "<");
	}

	/**
	 * WooCommerce Extra Checkout Fields for Brazil notice.
	 */
	public function ecfb_missing_notice()
	{
		// Check if Extra Checkout Fields for Brazil is installed, but check if it's BRL.
		if (paypal_brasil_needs_cpf() && !class_exists('Extra_Checkout_Fields_For_Brazil')) {
			include dirname(__FILE__) . '/includes/views/notices/html-notice-missing-ecfb.php';
		}
	}

	/**
	 * WooCommerce wrong version notice.
	 */
	public function woocommerce_wrong_version()
	{
		if (self::woocommerce_incompatible()) {
			include dirname(__FILE__) . '/includes/views/notices/html-notice-wrong-version-woocommerce.php';
		}
	}

	/**
	 * WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice()
	{
		include dirname(__FILE__) . '/includes/views/notices/html-notice-missing-woocommerce.php';
	}

	/**
	 * Add notice for update PPP to BCDC
	 */
	public function paypal_brazil_notice_update_bcdc()
	{
		if ( paypal_brasil_is_banner_notification_active() ) {
			include dirname(__FILE__) . '/includes/views/notices/banner-notification.php';
			wp_enqueue_style('banner_paypal_brasil_gateway_style', plugins_url('assets/dist/css/banner-notice.css', PAYPAL_PAYMENTS_MAIN_FILE), array(), PAYPAL_PAYMENTS_VERSION, 'all');
		}

	}

	/**
	 * WooCommerce unavailable currency notice.
	 */
	public function woocommerce_unavailable_currency()
	{
		include dirname(__FILE__) . '/includes/views/notices/html-notice-woocommerce-unavailable-currency.php';
	}

	/**
	 * Get allowed currencies for this gateway.
	 * @return array
	 */
	public static function get_allowed_currencies()
	{
		return array('BRL', 'USD', 'MXN');
	}



}