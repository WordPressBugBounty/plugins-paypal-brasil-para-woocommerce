<?php

use function PHPUnit\Framework\isEmpty;

// Ignore if access directly.
if (!defined('ABSPATH')) {
	exit;
}

if ( ! class_exists( 'WC_PAYPAL_LOGGER' ) ) {
    require_once plugin_dir_path( __FILE__ ) . '../class-wc-paypal-logger.php';
}

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Class Paypal_Brasil_BCDC_Gateway.
 *
 * @property string client_live
 * @property string client_sandbox
 * @property string secret_live
 * @property string secret_sandbox
 * @property string debug
 * @property string invoice_id_prefix
 * @property string title_complement
 */
class Paypal_Brasil_BCDC_Gateway extends PayPal_Brasil_Gateway
{

	private static $instance;
	private static $uuid;
	private string $invoice_id_prefix;
	private string $title_complement;


	/**
	 * PayPal_Brasil_Plus constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		// Store some default gateway settings.
		$this->id = 'paypal-brasil-bcdc-gateway';
		$this->has_fields = true;
		$this->method_title = __('PayPal Brasil', "paypal-brasil-para-woocommerce");
		$this->method_description = __('Add PayPal Transparent Checkout to Your WooCommerce Store.', "paypal-brasil-para-woocommerce");
		$this->supports = array(
			'products',
			'refunds',
		);

		// Load settings fields.
		$this->init_form_fields();
		$this->init_settings();
		// Get options in variable.
		$this->enabled = $this->get_option('enabled');
		$this->title = __('PayPal Brasil - Transparent Checkout', "paypal-brasil-para-woocommerce");
		$this->title_complement = $this->get_option('title_complement');
		$this->mode = $this->get_option('mode');
		$this->client_live = $this->get_option('client_live');
		$this->client_sandbox = $this->get_option('client_sandbox');
		$this->secret_live = $this->get_option('secret_live');
		$this->secret_sandbox = $this->get_option('secret_sandbox');
		$this->invoice_id_prefix = $this->get_option('invoice_id_prefix');
		$this->debug = $this->get_option('debug');

		// Instance the API.
		$this->api = new PayPal_Brasil_Orders_api_V2($this->get_client_id(), $this->get_secret(), $this->mode, $this);


		// Now save with the save hook.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		), 10);

		// Update web experience profile id before actually saving.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'before_process_admin_options'
		), 20);

		// Handler for IPN.
		add_action('woocommerce_api_' . $this->id, array($this, 'webhook_handler'));
		
		// Stop here if is not the first load.
		if (!$this->is_first_load()) {
			return;
		}
		// Enqueue scripts.
		add_action('wp_enqueue_scripts', array($this, 'checkout_scripts'), 0);
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));

		// If it's first load, add a instance of this.
		self::$instance = $this;
		
		//add_action('woocommerce_checkout_show_terms', array($this,'disable_checkout_terms_and_conditions'), 10 );	

	}

	function disable_checkout_terms_and_conditions( ) {
		return false;
	}

	public function before_process_admin_options()
	{
		// Check first if is enabled
		$enabled = $this->get_field_value('enabled', $this->form_fields['enabled']);
		
		if ($enabled !== 'yes') {
			return;
		}

		// update credentials
		$this->update_credentials();

		// validate credentials
		$this->validate_credentials();

		// create webhooks
		$this->create_webhooks();
	}

	/**
	 * Create the webhook or use a existent webhook.
	 */
	public function create_webhooks() {
		// Set by default as not found.
		$webhook     = null;
		$webhook_url = defined( 'PAYPAL_BRASIL_WEBHOOK_URL' ) ? PAYPAL_BRASIL_WEBHOOK_URL : $this->get_webhook_url();

		try {

			// Get a list of webhooks
			$registered_webhooks = $this->api->get_webhooks();

			// Search for registered webhook.
			foreach ( $registered_webhooks['webhooks'] as $registered_webhook ) {
				if ( $registered_webhook['url'] === $webhook_url ) {
					$webhook = $registered_webhook;
					break;
				}
			}

			// If no webhook matched, create a new one.
			if ( ! $webhook ) {
				$events_types = array(
					'CHECKOUT.ORDER.COMPLETED',
					'CHECKOUT.PAYMENT-APPROVAL.REVERSED',
					'PAYMENT.CAPTURE.COMPLETED',
					'PAYMENT.SALE.REFUNDED',
					'PAYMENT.SALE.REVERSED',
					'PAYMENT.ORDER.CANCELLED',
				);

				// Create webhook.
				$webhook_result = $this->api->create_webhook( $webhook_url, $events_types );

				update_option( 'paypal_brasil_webhook_url-' . $this->id, $webhook_result['id'] );

				return;
			}

			// Set the webhook ID
			update_option( 'paypal_brasil_webhook_url-' . $this->id, $webhook['id'] );
		} catch ( Exception $ex ) {
			update_option( 'paypal_brasil_webhook_url-' . $this->id, null );
		}
	}

	/**
	 * Render the payment fields in checkout.
	 */
	public function payment_fields()
	{
		include dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/includes/views/checkout/bcdc-checkout-fields.php';
	}

	public function custom_checkout_field_validation($data, $errors)
	{
		// Adicione uma mensagem de erro ao array de erros
		$errors->add('validation', __('Por favor, preencha o campo personalizado.', 'paypal-brasil-para-woocommerce'));
	}

	/**
	 * Validate data if contain any invalid field.
	 *
	 * @param $data
	 *
	 * @return string|bool
	 */
	private function validate_data($data)
	{
		$states = WC()->countries->get_states($data['country']);
		$errors = array();

		// Check country.
		if ((empty($data['country']) || $states === false)) {
			//$errors['country'] = __('Country', "paypal-brasil-para-woocommerce");
		}

		// Check postcode.
		if (!empty($data['country']) && empty($data['postcode'])) {
			$errors['postcode'] = __('Zip code', "paypal-brasil-para-woocommerce");
		}
		// Check CPF/CNPJ.
		// Only if require CPF/CNPJ.
		if ($data['country'] === 'BR' && paypal_brasil_needs_cpf()) {

			// Check person type.
			if ($data['person_type'] !== '1' && $data['person_type'] !== '2') {
				$errors['person_type'] = __('Person type', "paypal-brasil-para-woocommerce");
			}
			// Check the CPF
			if ($data['person_type'] == '1' && !$this->is_cpf($data['cpf'])) {
				$errors['cpf'] = __('CPF', "paypal-brasil-para-woocommerce");
			}
			// Check the CNPJ
			if ($data['person_type'] == '2' && !$this->is_cnpj($data['cnpj'])) {
				$errors['cnpj'] = __('CNPJ', "paypal-brasil-para-woocommerce");
			}
		}

		if ($errors) {
			$field_errors = array();
			foreach ($errors as $value) {
				$field_errors[] = sprintf(__('The field <strong>%s</strong> is required.', "paypal-brasil-para-woocommerce"), $value);
			}
			$error_string = implode('<br>', $field_errors);

			return $error_string;
		}

		return false;
	}

	/**
	 * Init the admin form fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', "paypal-brasil-para-woocommerce"),
				'type' => 'checkbox',
				'label' => __('Enable', "paypal-brasil-para-woocommerce"),
				'default' => 'no',
			),
			'title_complement' => array(
				'title' => __('Display name (add-on)', "paypal-brasil-para-woocommerce"),
				'type' => 'text',
			),
			'mode' => array(
				'title' => __('Mode', "paypal-brasil-para-woocommerce"),
				'type' => 'select',
				'options' => array(
					'live' => __('Live', "paypal-brasil-para-woocommerce"),
					'sandbox' => __('Sandbox', "paypal-brasil-para-woocommerce"),
				),
				'description' => __('Use this option to toggle between Sandbox and Production modes. Sandbox is used for testing and Production for actual purchases.', "paypal-brasil-para-woocommerce"),
			),
			'client_live' => array(
				'title' => '',
				'type' => 'text',
				'default' => '',
				'description' => '',
			),
			'secret_live' => array(
				'title' => '',
				'type' => 'text',
				'default' => '',
				'description' => '',
			),
			'client_sandbox' => array(
				'title' => '',
				'type' => 'text',
				'default' => '',
				'description' => '',
			),
			'secret_sandbox' => array(
				'title' => '',
				'type' => 'text',
				'default' => '',
				'description' => '',
			),

			'debug' => array(
				'title' => __('Debug mode', "paypal-brasil-para-woocommerce"),
				'type' => 'checkbox',
				'label' => __('Enable', "paypal-brasil-para-woocommerce"),
				'desc_tip' => __('Enable this mode to debug the application in case of approval or errors.', "paypal-brasil-para-woocommerce"),
				'description' => sprintf(__('Logs will be saved to the path: %s.', "paypal-brasil-para-woocommerce"), $this->get_log_view()),
			),

			'invoice_id_prefix' => array(
				'title' => __('Prefix in the order number', 'paypal-bcdc-brasil'),
				'type' => 'text',
				'default' => '',
				'description' => __('Add a prefix to the order number, this is useful for identifying you when you have more than one store processing through PayPal.', 'paypal-bcdc-brasil'),
			),
		);
	}

	/**
	 * Return the gateway's title.
	 *
	 * @return string
	 */
	public function get_title()
	{
		// A description only for admin section.
		if (is_admin()) {
			global $pagenow;

			return $pagenow === 'post.php' ? __('PayPal - Transparent Checkout', "paypal-brasil-para-woocommerce") : __('Transparent checkout', "paypal-brasil-para-woocommerce");
		}

		$title = $this->get_woocommerce_currency() === "BRL" ? __('Credit card', "paypal-brasil-para-woocommerce") : __('Credit Card', "paypal-brasil-para-woocommerce");
		if (!empty($this->title_complement)) {
			$title .= ' ' . $this->title_complement;
		}

		return apply_filters('woocommerce_gateway_title', $title, $this->id);
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		$is_available = ('yes' === $this->enabled);

		if (WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total()) {
			$is_available = false;
		}

		if (!$this->is_credentials_validated()) {
			$is_available = false;
		}

		return $is_available;
	}

	public function is_credentials_validated()
	{
		return get_option($this->get_option_key() . '_validator') === 'yes';
	}

	function is_only_payment_method_active(){

		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		WC_PAYPAL_LOGGER::log("Payment Gateways:" . json_encode($available_gateways), $this->id, 'info');

		if (isset($available_gateways)) {
			$gateway_count = count($available_gateways);
		}

		// Verificar se apenas o seu método de pagamento está disponível
		if ($gateway_count === 1 && isset($available_gateways[$this->id])) {
			return "true";
		}else{
			return "false";
		}
	}

	/**
	 * Enqueue scripts in checkout.
	 */
	public function checkout_scripts()
	{
		if (!$this->is_available()) {
			return;
		}


		$enqueues = array();
		$localizes = array();
		
		// Enqueue shared.
		$enqueues[] = array('underscore');

		$enqueues[] = array(
			'paypal-brasil-shared',
			plugins_url('assets/dist/js/frontend-shared.js', PAYPAL_PAYMENTS_MAIN_FILE),
			array(),
			PAYPAL_PAYMENTS_VERSION,
			true
		);


		$localizes[] = array(
			'paypal-brasil-shared',
			'paypal_brasil_bcdc_settings',
			array(
				'is_order_pay_page' => is_checkout_pay_page(),
				'nonce' => wp_create_nonce('paypal-brasil-checkout'),
				'current_user_id' => get_current_user_id(),
				'currency' => $this->get_woocommerce_currency(),
				'allowed_currency' => $this->currency_is_allowed(),
				//'currecy' => 'MXN',
				'client_id' => $this->get_client_id(),
				'locale' => get_locale(),
				'paypal_brasil_handler_url' => add_query_arg(
					array(
						'wc-api' => 'paypal_brasil_handler',
						'action' => '{ACTION}'
					), home_url() . '/'),
				'checkout_page_url' => wc_get_checkout_url(),
				'checkout_review_page_url' => add_query_arg(
					array(
						'review-payment' => '1',
						'pay-id' => '{PAY_ID}',
						'payer-id' => '{PAYER_ID}',
					), wc_get_checkout_url()),
				'paypal_brasil_handler_url' => add_query_arg(
					array(
						'wc-api' => 'paypal_brasil_handler',
						'action' => '{ACTION}'
					),
					home_url() . '/'
				)
				//'only_payment_method' => $isOnlyPaymentMethod
				//'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);

		if (is_checkout() && !get_query_var('order-received')) {
			
			$enqueues[] = array(
				'paypal-brasil-bcdc',
				plugins_url('assets/dist/js/frontend-bcdc.js', PAYPAL_PAYMENTS_MAIN_FILE),
				array(),
				PAYPAL_PAYMENTS_VERSION,
				true
			);

			$localizes[] = array(
				'paypal-brasil-bcdc',
				'wc_bcdc_brasil_data',
				array(
					'id' => $this->id,
					'order_pay' => !!get_query_var('order-pay'),
					'mode' => $this->mode === 'sandbox' ? 'sandbox' : 'live',
					'show_payer_tax_id' => paypal_brasil_needs_cpf(),
					'language' => apply_filters('paypal_brasil_plus_language', $this->get_woocommerce_currency() === 'BRL' ? 'pt_BR' : 'en_US'),
					'messages' => array(
						'check_entry' => __('Check the entered data.', "paypal-brasil-para-woocommerce"),
					),
					'debug_mode' => 'yes' === $this->debug,
				)
			);

			ob_start();
			wc_print_notice(__('You canceled the payment.', "paypal-brasil-para-woocommerce"), 'error');
			$cancel_message = ob_get_clean();

			$localizes[] = array(
				'paypal-brasil-bcdc',
				'paypal_brasil_bcdc_messages',
				array(
					'cancel_message' => $cancel_message,
				)
			);

		}

		wp_enqueue_style($this->id . '_style', plugins_url('assets/dist/css/frontend-bcdc.css', PAYPAL_PAYMENTS_MAIN_FILE), array(), PAYPAL_PAYMENTS_VERSION, 'all');

		foreach ($enqueues as $enqueue) {
			call_user_func_array('wp_enqueue_script', $enqueue);
		}

		foreach ($localizes as $localize) {
			call_user_func_array('wp_localize_script', $localize);
		}
	}

	/**
	 * Execute a payment.
	 *
	 * @param $order WC_Order
	 * @param $paypal_order_id
	 * @param $payer_id
	 *
	 * @return array|mixed|object
	 * @throws PayPal_Brasil_API_Exception
	 * @throws PayPal_Brasil_Connection_Exception
	 */
	public function execute_payment($order, $order_id)
	{

		$patch_data = array(
			array(
				'op' => "add",
				'path' => "/purchase_units/@reference_id=='default'/invoice_id",
				'value' => $this->invoice_id_prefix . $order->get_id() . wp_generate_uuid4(),
			),
			array(
				'op' => "add",
				'path' => "/purchase_units/@reference_id=='default'/description",
				'value' => sprintf(__('Order #%s performed in the store %s', "paypal-brasil-para-woocommerce"), $order->get_id(), get_bloginfo('name')),
			),
			array(
				'op' => 'add',
				'path' => "/purchase_units/@reference_id=='default'/custom_id",
				'value' => sprintf(__('Order #%s performed in the store %s', "paypal-brasil-para-woocommerce"), $order->get_id(), get_bloginfo('name')),
			),
		);
		
		try{
			$this->api->update_payment($order_id, $patch_data, array(), 'bcdc');

			$execution_response = $this->api->execute_payment($order_id, array(), 'bcdc');
			WC_PAYPAL_LOGGER::log("Payment capture is completed.",$this->id,'info', $execution_response, array('action'=> 'CAPTURE_ORDER'));

		}catch(PayPal_Brasil_API_Exception $ex){
			$data = $ex->getData();
			WC_PAYPAL_LOGGER::log("Payment capture error.",$this->id,'error',$data);
		}

		return $execution_response;
	}


	/**
	 * Process the payment.
	 *
	 * @param int $order_id
	 *
	 * @param bool $force
	 *
	 * @return null|array
	 * @throws PayPal_Brasil_Connection_Exception
	 */
	public function process_payment($order_id, $force = false)
	{
		$order = wc_get_order($order_id);
		// Check if is a iframe error
		if (isset($_POST['wc-bcdc-brasil-error']) && !empty($_POST['wc-bcdc-brasil-error'])) {
			switch ($_POST['wc-bcdc-brasil-error']) {
				case "CHECK_ENTRY":
					wc_add_notice(__("Check the entered data", "paypal-brasil-para-woocommerce"), "error");
					break;
				case 'CARD_ATTEMPT_INVALID':
					wc_add_notice(__("Payment not approved. Please try again.", "paypal-brasil-para-woocommerce"), "error");
					break;
				case 'INTERNAL_SERVICE_ERROR':
					wc_add_notice(__('Internal error.', "paypal-brasil-para-woocommerce"), 'error');
					break;
				case 'SOCKET_HANG_UP':
				case 'socket hang up':
				case 'connect ECONNREFUSED':
				case 'connect ETIMEDOUT':
				case 'UNKNOWN_INTERNAL_ERROR':
				case 'fiWalletLifecycle_unknown_error':
				case 'Failed to decrypt term info':
					wc_add_notice(__('An unexpected error occurred, please try again. If the error persists, contact. (#23)', "paypal-brasil-para-woocommerce"), 'error');
					break;
				case 'RISK_N_DECLINE':
				case "NO_VALID_FUNDING_SOURCE_OR_RISK_REFUSED":
					wc_add_notice(__("Payment not approved. Please try again.", "paypal-brasil-para-woocommerce"), "error");
					break;
				case "TRY_ANOTHER_CARD":
					wc_add_notice(__("Try another card.", "paypal-brasil-para-woocommerce"), 'error');
					break;
				case 'NO_VALID_FUNDING_INSTRUMENT':
					wc_add_notice(__('Payment not approved. Please try again.', "paypal-brasil-para-woocommerce"), 'error');
					break;
				case 'INVALID_OR_EXPIRED_TOKEN':
					wc_add_notice(__('Session expired. Please try again.', "paypal-brasil-para-woocommerce"), 'error');
					break;
				default:
					wc_add_notice(__('Please review the entered credit card information.', "paypal-brasil-para-woocommerce"), 'error');
					break;
			}
			// Set refresh totals to trigger update_checkout on frontend.
			WC()->session->set('refresh_totals', true);
			do_action('wc_bcdc_brasil_process_payment_error', 'IFRAME_ERROR', $order_id, $_POST['wc-bcdc-brasil-error']);

			$error_type = $_POST['wc-bcdc-brasil-error'];
			$order->add_order_note(__("Payment cannot be processed by PayPal: <b>$error_type</b>"));
			$order->update_status('wc-failed');
			$order->save();

			return null;
		}

		try {

			$iframe_data = isset($_POST['wc-bcdc-brasil-data']) ? json_decode(wp_unslash($_POST['wc-bcdc-brasil-data']), true) : null;
			$paypal_order_id = $iframe_data['payment_id'];
			//throw new PayPal_Brasil_API_Exception("VALIDATION_ERROR", 1);
			
			// Check the payment id
			if (empty($paypal_order_id)) {
				wc_add_notice(__('We were unable to identify the payment ID. Please refresh the page and try again.', "paypal-brasil-para-woocommerce"), 'error');
				// Set refresh totals to trigger update_checkout on frontend.
				WC()->session->set('refresh_totals', true);
				do_action('wc_bcdc_brasil_process_payment_error', 'SESSION_ERROR', $order_id, null);

				$order->add_order_note(__('There was an internal error processing the payment. The payment ID was not identified.', "paypal-brasil-para-woocommerce"));
				$order->update_status('wc-failed');
				$order->save();

				return null;
			}

			// Get the payment from PayPal
			$order_paypal_data = $this->api->get_payment($paypal_order_id,array(),'bcdc');

			// Check if the payment id
			if (empty($order_paypal_data['payer']['payer_id'])) {
				wc_add_notice(__('An unexpected error occurred, please try again. If the error persists, please contact us. (#67)', "paypal-brasil-para-woocommerce"), 'error');
				// Set refresh totals to trigger update_checkout on frontend.
				WC()->session->set('refresh_totals', true);
				do_action('wc_bcdc_brasil_process_payment_error', 'PAYER_ID', $order_id, null);

				$order->add_order_note(__('Payment ID is blank. Please contact support.'));
				$order->update_status('wc-failed');
				$order->save();

				return null;
			}


			// Validate the cart hash
			if ($order_paypal_data['purchase_units'][0]['custom_id'] !== $order->get_cart_hash()) {
				wc_add_notice(__('There was an error validating the request. Please refresh the page and try again.' . $order_paypal_data['purchase_units'][0]['custom_id'], "paypal-brasil-para-woocommerce"), 'error');

				// Set refresh totals to trigger update_checkout on frontend.
				WC()->session->set('refresh_totals', true);

				$order->add_order_note(__('There was an attempt to pay a cart other than approval.', "paypal-brasil-para-woocommerce"));
				$order->update_status('wc-failed');
				$order->save();

				return null;
			}

			// execute the order here.
			$sale = $this->execute_payment($order, $paypal_order_id);
			
			$order_paypal_data = $this->api->get_payment($paypal_order_id,array(),'bcdc');

			$installments_term = intval($order_paypal_data['credit_financing_offer']['term']);
			$installments_monthly_value = $order_paypal_data['credit_financing_offer']['installment_details']['payment_due']['value'];
			$installments_formatted_monthly_value = strip_tags(wc_price($installments_monthly_value));


			if (OrderUtil::custom_orders_table_usage_is_enabled()) {
				$order->update_meta_data('wc_bcdc_brasil_sale_id', $sale['id']);
				$order->update_meta_data('wc_bcdc_brasil_sale', $sale['purchase_units']);
				$order->update_meta_data('wc_bcdc_brasil_sandbox', $this->mode);
				$order->update_meta_data('wc_bcdc_brasil_installments', $installments_term);
				$order->update_meta_data('wc_bcdc_brasil_monthly_value', $installments_monthly_value);
			} else {
				update_post_meta($order_id, 'wc_bcdc_brasil_sale_id', $sale['id']);
				update_post_meta($order_id, 'wc_bcdc_brasil_sale', $sale['purchase_units']);
				update_post_meta($order_id, 'wc_bcdc_brasil_sandbox', $this->mode);
				update_post_meta($order_id, 'wc_bcdc_brasil_installments', $installments_term);
				update_post_meta($order_id, 'wc_bcdc_brasil_monthly_value', $installments_monthly_value);
			}


			$order->set_payment_method_title(
				sprintf(
					$installments_term > 1
					? __('Credit card (%dx de %s) - PayPal', "paypal-brasil-para-woocommerce")
					: __('Credit card - Paypal', "paypal-brasil-para-woocommerce"),
					$installments_term,
					$installments_formatted_monthly_value
				)
			);

			// Add note for installments.
			$installment_note = sprintf(
				$installments_term > 1
				? __('Payment in %d installments of %s on PayPal.', "paypal-brasil-para-woocommerce")
				: __('Credit card payment on PayPal.', "paypal-brasil-para-woocommerce"),
				$installments_term,
				$installments_formatted_monthly_value
			);

			$order->add_order_note($installment_note);

			$result_success = false;
			$payment_completed = false;

			switch ($sale['purchase_units'][0]['payments']['captures'][0]['status']) {
				case 'COMPLETED';
					$sale_id = $sale['purchase_units'][0]['payments']['captures'][0]['id'];
					$order->add_order_note(
						sprintf(
							__('Payment processed by PayPal. Transaction ID: <a href="%s" target="_blank" rel="noopener">%s</a>.', "paypal-brasil-para-woocommerce"),
							$this->mode === 'sandbox' ? "https://www.sandbox.paypal.com/activity/payment/{$sale_id}" : "https://www.paypal.com/activity/payment/{$sale_id}",
							$sale_id
						)
					);
					$result_success = true;
					$payment_completed = true;
					break;
				case 'PENDING':
					wc_reduce_stock_levels($order_id);
					$order->update_status('on-hold', __('Payment is under review by PayPal.', "paypal-brasil-para-woocommerce"));
					$result_success = true;
					break;
			}

			if ($result_success) {
				do_action('wc_bcdc_brasil_process_payment_success', $order_id);

				if ($payment_completed) {
					$order->payment_complete();
				}

				// Return the success URL
				return array(
					'result' => 'success',
					'redirect' => $this->get_return_url($order),
				);
			}
		} catch (PayPal_Brasil_API_Exception $ex) {
			$data = $ex->getData();
			switch ($data['name']) {
				// Repeat the execution
				case 'INTERNAL_SERVER_ERROR':
					if ($force) {
						wc_add_notice(sprintf(__('Internal error.', "paypal-brasil-para-woocommerce")), 'error');
					} else {
						$this->process_payment($order_id, true);
					}
					break;
				case 'INVALID_REQUEST':
					$debug_id = isset($data['debug_id']) ? $data['debug_id'] : ""; 
					wc_add_notice(sprintf(__("An unexpected error occurred, please try again. If the error persists, please contact us ($debug_id).", "paypal-brasil-para-woocommerce")), 'error');
					break;
				case 'RESOURCE_CONFLICT':
					$debug_id = isset($data['debug_id']) ? $data['debug_id'] : ""; 
					wc_add_notice(__("A payment already exists for this order ($debug_id).", "paypal-brasil-para-woocommerce"), 'error');
					break;
				case "UNPROCESSABLE_ENTITY":
					$debug_id = isset($data['debug_id']) ? $data['debug_id'] : "";
					wc_add_notice(__("Payment not approved. Please try again ($debug_id).", "paypal-brasil-para-woocommerce"), "error");
					break;
				default:
					$debug_id = isset($data['debug_id']) ? $data['debug_id'] : "";
					wc_add_notice("Payment not approved. Please try again or select another payment method ($debug_id).", 'error');
					break;
			}

			// Set refresh totals to trigger update_checkout on frontend.
			WC()->session->set('refresh_totals', true);
			do_action('wc_bcdc_brasil_process_payment_error', 'API_EXCEPTION', $order_id, $data['name']);

			$error_type = $data['name'];
			$debug_id = isset($data['debug_id']) ? $data['debug_id'] : "";
			$order->add_order_note(__("There was an error executing the payment through PayPal: <b>EXECUTE_$error_type - DEBUG_ID_$debug_id</b>", "paypal_brasil_para_woocommerce"));
			$order->update_status('wc-failed');
			$order->save();

			return null;
		} catch (Throwable $e) {
		
			$error_data = array("code" => $e->getCode(), "message" => $e->getMessage(), "stackTrace" => $e->getTraceAsString()); 
			// Gera um código único para depuração
			$error_code = uniqid('paypal_brasil_para_woocommerce_err_');

			WC_PAYPAL_LOGGER::log("Payment capture error ($error_code). ",$this->id,'error',$error_data, ["error_code: $error_code"] );

			$order->add_order_note(__("There was an error when trying to process the payment through PayPal. Please contact support. ($error_code)", "paypal-brasil-para-woocommerce"));
			$order->update_status('wc-failed');
			$order->save();
			
			return null;
		}

		$order->add_order_note(__('There was an unknown error when trying to process the payment through PayPal. Please contact support.', "paypal-brasil-para-woocommerce"));
		$order->update_status('wc-failed');
		$order->save();
		
		return null;
	}

	/**
	 * Process the refund for an order.
	 *
	 * @param int $order_id
	 * @param string $reason
	 *
	 * @return WP_Error|bool
	 */
	public function process_refund($order_id, $amount = null, $reason = '')
	{

		if (OrderUtil::custom_orders_table_usage_is_enabled()) {

			$orders = wc_get_orders(
				array(
					'ID' => array($order_id),
					'meta_query' => array(
						array(
							'key' => 'wc_bcdc_brasil_sale_id',
						)
					),
				)
			);

			if (!empty($orders)) {
				$order_paypal_id = $orders[0]->get_meta('wc_bcdc_brasil_sale_id');
			} else {
				$order_paypal_id = '';
			}
		} else {
			$order_paypal_id = get_post_meta($order_id, 'wc_bcdc_brasil_sale_id', true);
		}
		
		// Check if the amount is bigger than zero
		if ($amount <= 0) {
			$min_price = number_format(0, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator());

			return new WP_Error('error', sprintf(__('The refund cannot be less than %s.', "paypal-brasil-para-woocommerce"), html_entity_decode(get_woocommerce_currency_symbol()) . $min_price));
		}
		// Check if we got the sale ID
		if ($order_paypal_id) {
			try {

				$order_paypal_data = $this->api->get_payment($order_paypal_id,array(),'bcdc');

				$capture_id = $order_paypal_data['purchase_units'][0]['payments']['captures'][0]['id'];

				$refund_sale = $this->api->refund_payment($capture_id, paypal_brasil_money_format($amount), $this->get_woocommerce_currency());
				// Check the result success.
				if ($refund_sale['status'] === 'COMPLETED') {
					return true;
				} else {
					return new WP_Error('error', 'error: ' . $refund_sale->getReason());
				}
			} catch (PayPal_Brasil_API_Exception $ex) { // Catch any PayPal error.
				$data = $ex->getData();

				return new WP_Error('error', 'error Message:' . $data['message'] . $capture_id);
			} catch (Exception $ex) {
				return new WP_Error('error', __('There was an error trying to make a refund.', "paypal-brasil-para-woocommerce"));
			}
		} else { // If we don't have the PayPal sale ID.
			return new WP_Error('error', sprintf(__('It looks like you don\'t have a request for a refund.' . $order_paypal_id, "paypal-brasil-para-woocommerce")));
		}
	}

	/**
	 * Get the posted data in the checkout.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function process_posted_data(array $data)
	{

		$checkout = WC()->checkout();
		$order_id = get_query_var('order-pay');
		$order = $order_id ? new WC_Order($order_id) : null;

		// Valores padrão
		$defaults = [
			'first_name' => '',
			'last_name' => '',
			'person_type' => '',
			'cpf' => '',
			'cnpj' => '',
			'phone' => '',
			'email' => '',
			'postcode' => '',
			'address' => '',
			'number' => '',
			'address_2' => '',
			'neighborhood' => '',
			'city' => '',
			'state' => '',
			'country' => '',
			'approval_url' => '',
			'payment_id' => '',
			'payer_id' => '',
			'dummy' => false,
			'invalid' => [],
			'wc-bcdc-brasil-selected' => false
		];

		// Verifica se os dados estão no objeto $order
		if ($order) {
			$billing_data = [
				'postcode' => $order->get_billing_postcode() ?? $order->get_shipping_postcode(),
				'address' => $order->get_billing_address_1() ?? $order->get_shipping_address_1(),
				'address_2' => $order->get_billing_address_2() ?? $order->get_shipping_address_2(),
				'city' => $order->get_billing_city()  ?? $order->get_shipping_city(),
				'state' => $order->get_billing_state() ?? $order->get_shipping_state(),
				'country' => $order->get_billing_country() ?? $order->get_shipping_country(),
				'neighborhood' => $order->get_meta('_billing_neighborhood',true,"view"),
				'number' => $order->get_meta('_billing_number',true,"view"),
				'first_name' => $order->get_billing_first_name()  ?? $order->get_shipping_first_name(),
				'last_name' => $order->get_billing_last_name()  ?? $order->get_shipping_last_name(),
				'person_type' => $order->get_meta('_billing_persontype',true,"view"),
				'cpf' => $order->get_meta('_billing_cpf',true,"view"),
				'cnpj' => $order->get_meta('_billing_cnpj',true,"view"),
				'phone' => $order->get_meta('_billing_cellphone',true,"view") ?: $order->get_billing_phone(),
				'email' => $order->get_billing_email(),
			];
		} else {
			// Se não houver o order, busca dados do checkout ou do $_POST['post_data']
			$post_data = [];
			if (isset($_POST['post_data'])) {
				parse_str($_POST['post_data'], $post_data);
			}

			$get_field = function ($key, $default = '') use ($checkout, $post_data) {
				// Tenta obter o valor do checkout ou de post_data
				return $checkout->get_value($key) ?: ($post_data[$key] ?? $default);
			};

			$billing_data = [
				'first_name' => $get_field('billing_first_name'),
				'last_name' => $get_field('billing_last_name'),
				'person_type' => $get_field('billing_persontype'),
				'cpf' => $get_field('billing_cpf'),
				'cnpj' => $get_field('billing_cnpj'),
				'phone' => $get_field('billing_cellphone', $get_field('billing_phone')),
				'email' => $get_field('billing_email'),
				'postcode' => $get_field('billing_postcode'),
				'address' => $get_field('billing_address_1'),
				'address_2' => $get_field('billing_address_2'),
				'city' => $get_field('billing_city'),
				'state' => $get_field('billing_state'),
				'country' => $get_field('billing_country'),
				'neighborhood' => $get_field('billing_neighborhood'),
				'number' => $get_field('billing_number'),
			];


			WC_PAYPAL_LOGGER::log("Checkout data has been captured", $this->id, 'info');
		}

		// Adiciona dados específicos do plugin "Brazilian Market on WooCommerce"
		if (paypal_brasil_needs_cpf()) {
			$wcbcf_settings = get_option('wcbcf_settings');
			if ($wcbcf_settings && ($wcbcf_settings['person_type'] == '2' || $wcbcf_settings['person_type'] == '3')) {
				$billing_data['person_type'] = $wcbcf_settings['person_type'] == '2' ? '1' : '2';
				$billing_data['person_type_default'] = true;
			}
		}

		// Usa valores padrão e aplica filtros
		$billing_data = wp_parse_args($billing_data, $defaults);
		$billing_data = apply_filters('wc_bcdc_brasil_user_data', $billing_data);

		$required_data = array('first_name', 'last_name', 'person_type');
		$required_data[] = $billing_data['person_type'] == '1' ? 'cpf' : 'cnpj';

		$can_create_payment = "true";
		$missing_fields = [];

		foreach ($required_data as $field) {
			if (!isset($billing_data[$field]) || empty($billing_data[$field])) {
				$missing_fields[] = $field;
			}
		}

		$billing_data['wc-bcdc-brasil-selected'] = isset($post_data) ? filter_var($post_data['wc-bcdc-brasil-selected'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false;
		$billing_data['can_create_payment'] = $can_create_payment;

		if (!empty($missing_fields)) {
			// Create the payment.
			$payment = $order ? $this->create_payment_for_order($billing_data, $order) : $this->create_payment_for_cart_without_payer($billing_data);
			if (isset($payment['id'])) {
				$billing_data['approval_url'] = $payment['links'][1]['href'];
				$billing_data['payment_id'] = $payment['id'];
			}
		} else {
			// Create the payment.
			$payment = $order ? $this->create_payment_for_order($billing_data, $order) : $this->create_payment_for_cart($billing_data);
			if (isset($payment['id'])) {
				$billing_data['approval_url'] = $payment['links'][1]['href'];
				$billing_data['payment_id'] = $payment['id'];
			}
		}
		return $billing_data;

	}

		/**
	 * Get the posted data in the checkout.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_posted_data()
	{

		$checkout = WC()->checkout();
		$order_id = get_query_var('order-pay');
		$order = $order_id ? new WC_Order($order_id) : null;

		// Valores padrão
		$defaults = [
			'first_name' => '',
			'last_name' => '',
			'person_type' => '',
			'cpf' => '',
			'cnpj' => '',
			'phone' => '',
			'email' => '',
			'postcode' => '',
			'address' => '',
			'number' => '',
			'address_2' => '',
			'neighborhood' => '',
			'city' => '',
			'state' => '',
			'country' => '',
			'approval_url' => '',
			'payment_id' => '',
			'payer_id' => '',
			'dummy' => false,
			'invalid' => [],
			'wc-bcdc-brasil-selected' => false
		];

		// Verifica se os dados estão no objeto $order
		if ($order) {
			$billing_data = [
				'postcode' => $order->get_billing_postcode() ?? $order->get_shipping_postcode(),
				'address' => $order->get_billing_address_1() ?? $order->get_shipping_address_1(),
				'address_2' => $order->get_billing_address_2() ?? $order->get_shipping_address_2(),
				'city' => $order->get_billing_city()  ?? $order->get_shipping_city(),
				'state' => $order->get_billing_state() ?? $order->get_shipping_state(),
				'country' => $order->get_billing_country() ?? $order->get_shipping_country(),
				'neighborhood' => $order->get_meta('_billing_neighborhood',true,"view"),
				'number' => $order->get_meta('_billing_number',true,"view"),
				'first_name' => $order->get_billing_first_name()  ?? $order->get_shipping_first_name(),
				'last_name' => $order->get_billing_last_name()  ?? $order->get_shipping_last_name(),
				'person_type' => $order->get_meta('_billing_persontype',true,"view"),
				'cpf' => $order->get_meta('_billing_cpf',true,"view"),
				'cnpj' => $order->get_meta('_billing_cnpj',true,"view"),
				'phone' => $order->get_meta('_billing_cellphone',true,"view") ?: $order->get_billing_phone(),
				'email' => $order->get_billing_email(),
			];
		} else {
			// Se não houver o order, busca dados do checkout ou do $_POST['post_data']
			$post_data = [];
			if (isset($_POST['post_data'])) {
				parse_str($_POST['post_data'], $post_data);
			}

			$get_field = function ($key, $default = '') use ($checkout, $post_data) {
				// Tenta obter o valor do checkout ou de post_data
				return $checkout->get_value($key) ?: ($post_data[$key] ?? $default);
			};

			$billing_data = [
				'first_name' => $get_field('billing_first_name'),
				'last_name' => $get_field('billing_last_name'),
				'person_type' => $get_field('billing_persontype'),
				'cpf' => $get_field('billing_cpf'),
				'cnpj' => $get_field('billing_cnpj'),
				'phone' => $get_field('billing_cellphone', $get_field('billing_phone')),
				'email' => $get_field('billing_email'),
				'postcode' => $get_field('billing_postcode'),
				'address' => $get_field('billing_address_1'),
				'address_2' => $get_field('billing_address_2'),
				'city' => $get_field('billing_city'),
				'state' => $get_field('billing_state'),
				'country' => $get_field('billing_country'),
				'neighborhood' => $get_field('billing_neighborhood'),
				'number' => $get_field('billing_number'),
			];


			WC_PAYPAL_LOGGER::log("Checkout data has been captured", $this->id, 'info');
		}

		// Adiciona dados específicos do plugin "Brazilian Market on WooCommerce"
		if (paypal_brasil_needs_cpf()) {
			$wcbcf_settings = get_option('wcbcf_settings');
			if ($wcbcf_settings && ($wcbcf_settings['person_type'] == '2' || $wcbcf_settings['person_type'] == '3')) {
				$billing_data['person_type'] = $wcbcf_settings['person_type'] == '2' ? '1' : '2';
				$billing_data['person_type_default'] = true;
			}
		}

		// Usa valores padrão e aplica filtros
		$billing_data = wp_parse_args($billing_data, $defaults);
		$billing_data = apply_filters('wc_bcdc_brasil_user_data', $billing_data);

		$required_data = array('first_name', 'last_name', 'person_type');
		$required_data[] = $billing_data['person_type'] == '1' ? 'cpf' : 'cnpj';

		$can_create_payment = "true";
		$missing_fields = [];

		foreach ($required_data as $field) {
			if (!isset($billing_data[$field]) || empty($billing_data[$field])) {
				$missing_fields[] = $field;
			}
		}

		$billing_data['wc-bcdc-brasil-selected'] = isset($post_data) ? filter_var($post_data['wc-bcdc-brasil-selected'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : false;
		$billing_data['can_create_payment'] = $can_create_payment;

		return $billing_data;

	}

		/**
	 * Create the PayPal payment.
	 *
	 * @param $data
	 * @param bool $dummy
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function create_payment_for_cart_without_payer($data, $dummy = false)
	{
		// Don' log if is dummy data.
		if ($dummy) {
			$this->debug = false;
		}

		// Check if is only digital items.
		$only_digital_items = paypal_brasil_is_cart_only_digital();

		// Set the application context
		$payment_data['application_context'] = array(
			'brand_name' => get_bloginfo('name'),
			'locale' => 'pt-BR',
			'user_action' => 'CONTINUE',
			'shipping_preference' => !$only_digital_items ? 'SET_PROVIDED_ADDRESS' : 'NO_SHIPPING',
		);

		$wc_cart = WC()->cart;
		$wc_cart_totals = new WC_Cart_Totals($wc_cart);
		$cart_totals = $wc_cart_totals->get_totals(true);

		$payment_data = array(
			'intent' => 'CAPTURE',
			'purchase_units' => array(
				array(
					'custom_id' => WC()->cart->get_cart_hash(),
					'amount' => array(
						'currency_code' => $this->get_woocommerce_currency(),
						'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['total'])),
						'breakdown' => array(
							'item_total' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['total'] - $cart_totals['shipping_total']))
							),
							'tax_total' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => '0.00'
							),
							'discount' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => '0.00'
							),
							'shipping' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['shipping_total']))
							),
						)
					),
				),
			),
		);

		// Verificar se há métodos de envio no carrinho
		if ($wc_cart->needs_shipping()) {
			// Obter o método de envio selecionado
			$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

			// Certificar-se de que há um método de envio selecionado
			if (!empty($chosen_shipping_methods)) {
				// Obter o nome do método de envio
				$shipping_method = $chosen_shipping_methods[0];
			}
		}

		// Create the address.
		if (!$dummy) {
			// Set shipping only when isn't digital
			if (!$only_digital_items) {

				if (isset($shipping_method) && strpos($shipping_method, 'local_pickup') === 0) {

					$shipping_address = $this->get_payer_address($data);

					$shipping = array(
						'shipping' => array(
							'type' => 'PICKUP_IN_STORE',
							'name' => array(
								'full_name' => $data['first_name'] . " " . $data['last_name'],
							),
						)
					);
					
					if ($this->validate_address($shipping_address)) {
						$shipping['shipping']['address'] = $shipping_address;
					}

					$payment_data['payment_source']['paypal']['experience_context']['shipping_preference'] = 'NO_SHIPPING';
					$payment_data['purchase_units'][0] = array_merge($payment_data['purchase_units'][0], $shipping);

				} else {

					$shipping_address = $this->get_payer_address($data);
					$shipping = array(
						'shipping' => array(
							'type' => 'SHIPPING'
						)
					);

					//If address is invalid, the shipping is not send to create_order by is not permited when shipping reference is SET_PROVIDED_ADDRESS 
					if ($this->validate_address($shipping_address)) {

						$shipping['shipping']['address'] = $shipping_address;

					}

					$payment_data['purchase_units'][0] = array_merge($payment_data['purchase_units'][0], $shipping);

				}

			}

		}

		//Capture item on the cart;
		$items_cart = $wc_cart->get_cart_contents();
		if (!empty($items_cart)) {
			foreach ($items_cart as $key => $item) {
				$item_cart = $wc_cart->get_cart_item($key);

				$items['items'][] = array(
					'name' => $item['data']->get_name(),
					'unit_amount' => array(
						'currency_code' => $this->get_woocommerce_currency(),
						'value' => paypal_format_amount($item_cart['line_total'] / $item_cart['quantity']),
					),
					'quantity' => $item_cart['quantity'],
				);

			}

			$payment_data['purchase_units'][0] = array_merge($payment_data['purchase_units'][0], $items);
		}

		// Check if is order pay
		$exception_data = array();

		try {

			if (!$this->currency_is_allowed()) {
				throw new Exception(__('Payment not allowed in this currency. Contact store support.', "paypal-brasil-para-woocommerce"));
			}

			// Create the payment.
			$result = $this->api->create_payment($payment_data, array(), 'bcdc');

			if (!isset($result['payment_source']['paypal']['address']) || !isset($result['payer']['address'])) {
				WC_PAYPAL_LOGGER::log("Order created without address! \n", $this->id, "warning", $result);
			}

			return $result;
		} catch (PayPal_Brasil_API_Exception $ex) { // Catch any PayPal error.
			$error_data = $ex->getData();
			if ($error_data['name'] === 'VALIDATION_ERROR') {
				$exception_data = $error_data['details'];
			}

		}

		$error_message = str_replace('"', '', $error_data['message']);
		$debug_id = str_replace('"', '', $error_data['debug_id']);
		$exception = new Exception(__("Ocorreu um erro, no CREATE_ORDER, \n
												Mensagem original: {$error_message} \n
												Identificador do erro: {$debug_id}", "paypal-brasil-para-woocommerce"));
		$exception->data = $exception_data;

		throw $exception;
	}

	/**
	 * Create the PayPal payment.
	 *
	 * @param $data
	 * @param bool $dummy
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function create_payment_for_cart($data, $dummy = false)
	{
		// Don' log if is dummy data.
		if ($dummy) {
			$this->debug = false;
		}

		// Check if is only digital items.
		$only_digital_items = paypal_brasil_is_cart_only_digital();

		// Set the application context
		$payment_data['application_context'] = array(
			'brand_name' => get_bloginfo('name'),
			'locale' => 'pt-BR',
			'user_action' => 'CONTINUE',
			'shipping_preference' => !$only_digital_items ? 'SET_PROVIDED_ADDRESS' : 'NO_SHIPPING',
		);

		$wc_cart = WC()->cart;
		$wc_cart_totals = new WC_Cart_Totals($wc_cart);
		$cart_totals = $wc_cart_totals->get_totals(true);

		$payment_data = array(
			'intent' => 'CAPTURE',
			'purchase_units' => array(
				array(
					'custom_id' => WC()->cart->get_cart_hash(),
					'amount' => array(
						'currency_code' => $this->get_woocommerce_currency(),
						'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['total'])),
						'breakdown' => array(
							'item_total' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['total'] - $cart_totals['shipping_total']))
							),
							'tax_total' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => '0.00'
							),
							'discount' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => '0.00'
							),
							'shipping' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['shipping_total']))
							),
						)
					),
				),
			),
		);

		// Verificar se há métodos de envio no carrinho
		if ($wc_cart->needs_shipping()) {
			// Obter o método de envio selecionado
			$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

			// Certificar-se de que há um método de envio selecionado
			if (!empty($chosen_shipping_methods)) {
				// Obter o nome do método de envio
				$shipping_method = $chosen_shipping_methods[0];
			}
		}

		// Create the address.
		if (!$dummy) {
			// Set shipping only when isn't digital
			if (!$only_digital_items) {

				if (isset($shipping_method) && strpos($shipping_method, 'local_pickup') === 0) {

					$shipping_address = $this->get_payer_address($data);

					$shipping = array(
						'shipping' => array(
							'type' => 'PICKUP_IN_STORE',
							'name' => array(
								'full_name' => $data['first_name'] . " " . $data['last_name'],
							),
						)
					);

					if ($this->validate_address($shipping_address)) {
						$shipping['shipping']['address'] = $shipping_address;
						$payment_data['payment_source']['paypal']['address'] = $shipping_address;
					}

					$payment_data['payment_source']['paypal']['experience_context']['shipping_preference'] = 'NO_SHIPPING';
					$payment_data['purchase_units'][0] = array_merge($payment_data['purchase_units'][0], $shipping);

				} else {

					$shipping_address = $this->get_payer_address($data);
					$shipping = array(
						'shipping' => array(
							'type' => 'SHIPPING',
							'name' => array(
								'full_name' => $data['first_name'] . " " . $data['last_name'],
							),
							//'address' => $shipping_address
						)
					);

					//If address is invalid, the shipping is not send to create_order by is not permited when shipping reference is SET_PROVIDED_ADDRESS 
					if ($this->validate_address($shipping_address)) {

						$shipping['shipping']['address'] = $shipping_address;
						$payment_data['payment_source']['paypal']['address'] = $shipping_address;
						$payment_data['payment_source']['paypal']['experience_context']['shipping_preference'] = 'SET_PROVIDED_ADDRESS';

					}

					$payment_data['purchase_units'][0] = array_merge($payment_data['purchase_units'][0], $shipping);

				}

			}

		}

		if (!isset($payment_data['payment_source']['paypal']) || !is_array($payment_data['payment_source']['paypal'])) {
			$payment_data['payment_source']['paypal'] = [];
		}

		// Mesclar os dados do pagador
		$payment_data['payment_source']['paypal'] = array_merge(
			$payment_data['payment_source']['paypal'],
			$this->get_payer_info($data)
		);

		//Capture item on the cart;
		$items_cart = $wc_cart->get_cart_contents();
		if (!empty($items_cart)) {
			foreach ($items_cart as $key => $item) {
				$item_cart = $wc_cart->get_cart_item($key);

				$items['items'][] = array(
					'name' => $item['data']->get_name(),
					'unit_amount' => array(
						'currency_code' => $this->get_woocommerce_currency(),
						'value' => paypal_format_amount($item_cart['line_total'] / $item_cart['quantity']),
					),
					'quantity' => $item_cart['quantity'],
				);

			}

			$payment_data['purchase_units'][0] = array_merge($payment_data['purchase_units'][0], $items);
		}

		// Check if is order pay
		$exception_data = array();

		try {

			if (!$this->currency_is_allowed()) {
				throw new Exception(__('Payment not allowed in this currency. Contact store support.', "paypal-brasil-para-woocommerce"));
				;
			}

			// Create the payment.
			$result = $this->api->create_payment($payment_data, array(), 'bcdc');

			if (!isset($result['payment_source']['paypal']['address']) || !isset($result['payer']['address'])) {
				WC_PAYPAL_LOGGER::log("Order created without address.", $this->id, "warning", $result);
			} else {
				WC_PAYPAL_LOGGER::log("Order body", $this->id, "info", $result);
			}

			return $result;
		} catch (PayPal_Brasil_API_Exception $ex) { // Catch any PayPal error.
			$error_data = !empty($ex->getData()) ? $ex->getData() : array("code" => $ex->getCode(), "message" => $ex->getMessage()); 
			if ((isset($error_data['name'])) && $error_data['name'] === 'VALIDATION_ERROR') {
				$exception_data = $error_data['details'];
			}

			WC_PAYPAL_LOGGER::log("Error on create order.", $this->id, "error", $error_data);

		}

		$error_message = str_replace('"', '', $error_data['message']);
		$debug_id = str_replace('"', '', $error_data['debug_id']);
		$exception = new Exception(__("Ocorreu um erro, no CREATE_ORDER, \n
												Mensagem original: {$error_message} \n
												Identificador do erro: {$debug_id}", "paypal-brasil-para-woocommerce"));
		$exception->data = $exception_data;

		if ((isset($data['wc-bcdc-brasil-selected'])) && $data['wc-bcdc-brasil-selected']) {
			throw $exception;
		}
	}

	/**
	 * @param $data
	 * @param $order WC_Order
	 * @param bool $dummy
	 *
	 * @return mixed
	 * @throws PayPal_Brasil_Connection_Exception
	 */
	public function create_payment_for_order($data, $order, $dummy = false)
	{
		// Get the order if was given order ID.
		if (!is_a($order, 'WC_Order')) {
			$order = wc_get_order($order);
		}

		// Don' log if is dummy data.
		if ($dummy) {
			$this->debug = false;
		}

		$order_total = $order->get_total();
		$order_shipping_total = $order->get_shipping_total();

		wp_localize_script( 'paypal-brasil-shared',
		'paypal_brasil_bcdc_order', array("order_pay_total" => $order_total));

		$payment_data = array(
			'intent' => 'CAPTURE',
			'purchase_units' => array(
				array(
					'custom_id' => $order->get_cart_hash(),
					'amount' => array(
						'currency_code' => $this->get_woocommerce_currency(),
						'value' => paypal_format_amount($order_total),
						'breakdown' => array(
							'item_total' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => paypal_format_amount($order_total - $order_shipping_total)
							),
							'tax_total' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => '0.00'
							),
							'discount' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => '0.00'
							),
							'shipping' => array(
								'currency_code' => $this->get_woocommerce_currency(),
								'value' => paypal_format_amount($order_shipping_total)
							),
						)
					),
				),
			),
		);

		// Check if is only digital items.
		$only_digital_items = paypal_brasil_is_order_only_digital($order);

		// Set the application context
		$payment_data['application_context'] = array(
			'brand_name' => get_bloginfo('name'),
			'locale' => 'pt-BR',
			'user_action' => 'CONTINUE',
		);

		if ($order->needs_shipping_address()) {

			$shipping_items = $order->get_items('shipping');

			if (!empty($shipping_items)) {
				$first_shipping_item = reset($shipping_items);

				$shipping_method = $first_shipping_item->get_method_title();
			}
		}

		if (!$dummy) {
			// Set shipping only when isn't digital
			if (!$only_digital_items) {

				if (isset($shipping_method) && strpos($shipping_method, 'local_pickup') === 0) {

					$shipping = array(
						'shipping' => array(
							'type' => 'PICKUP_IN_STORE',
							'name' => array(
								'full_name' => $data['first_name'] . " " . $data['last_name'],
							)
						)
					);

					$payment_data['payment_source']['paypal']['experience_context']['shipping_preference'] = 'NO_SHIPPING';

					$payment_data['purchase_units'][0] = array_merge($payment_data['purchase_units'][0], $shipping);

				} else {


					$shipping_address = $this->get_payer_address($data);

					if ($this->validate_address($shipping_address)) {

						$shipping = array(
							'shipping' => array(
								'type' => 'SHIPPING',
								'name' => array(
									'full_name' => $data['first_name'] . " " . $data['last_name'],
								),
								'address' => $shipping_address
							)
						);

						$payment_data['purchase_units'][0] = array_merge($payment_data['purchase_units'][0], $shipping);
					}

					$payment_data['payment_source']['paypal']['experience_context']['shipping_preference'] = 'SET_PROVIDED_ADDRESS';

				}

				
			}

		}

		//Set payer_info on payment_source.paypal data.
		$payment_data['payment_source']['paypal'] = $this->get_payer_info($data);
		$payment_data['payment_source']['paypal']['address'] = $shipping_address;

		//capture items on the order.
		$items_order = $order->get_items();
		foreach ($items_order as $item) {
			$items['items'][] = array(
				'name' => $item->get_name(),
				'unit_amount' => array(
					'currency_code' => $this->get_woocommerce_currency(),
					'value' => paypal_format_amount($item->get_total() / $item->get_quantity()),
				),
				'quantity' => $item->get_quantity(),
			);
		}

		$payment_data['purchase_units'][0] = array_merge($payment_data['purchase_units'][0], $items);

		// Check if is order pay
		$exception_data = array();

		try {

			if(!$this->currency_is_allowed()){
				$exception = new Exception(__('Payment not allowed in this currency. Contact store support.', "paypal-brasil-para-woocommerce"));
				$exception->data = $exception_data;
				throw $exception;
			}

			// Create the payment.
			$result = $this->api->create_payment($payment_data, array(), 'bcdc');

			if (!isset($result['payment_source']['paypal']['address']) || !isset($result['payer']['address'])) {
				WC_PAYPAL_LOGGER::log("Order created without address.", $this->id, "warning", $result);
			} else {
				WC_PAYPAL_LOGGER::log("Order body", $this->id, "info", $result);
			}
			
			return $result;
			// Catch any PayPal error.
		} catch (PayPal_Brasil_API_Exception $ex) {
			$error_data = $ex->getData();
			if ($error_data['name'] === 'VALIDATION_ERROR') {
				$exception_data = $error_data['details'];
			}
		}

		$error_message = str_replace('"', '', $error_data['message']);
		$debug_id   = str_replace('"', '', $error_data['debug_id']);
		$exception = new Exception(__("Ocorreu um erro, no CREATE_ORDER, \n
												Mensagem original: {$error_message} \n
												Identificador do erro: {$debug_id}", "paypal-brasil-para-woocommerce"));
		$exception->data = $exception_data;

		throw $exception;
	}

	/**
	 * Check if is first load of this class.
	 * This should prevent add double hooks.
	 *
	 * @return bool
	 */
	private function is_first_load()
	{
		return !self::$instance;
	}


	public function get_payer_address($data)
	{

		// Prepare empty address_line_1
		$address_line_1 = array();
		// Add the address
		if ($data['address']) {
			$address_line_1[] = $data['address'];
		}
		// Add the number
		if ($data['number']) {
			$address_line_1[] = $data['number'];
		}
		// Prepare empty line 2.
		$address_line_2 = array();
		// Add neighborhood to line 2
		if ($data['neighborhood']) {
			$address_line_2[] = $data['neighborhood'];
		}
		// Add shipping address line 2
		if ($data['address_2']) {
			$address_line_2[] = $data['address_2'];
		}

		$shipping_address = array(
			'address_line_1' => mb_substr(implode(', ', $address_line_1), 0, 100),
			'admin_area_1' => $data['state'],
			'admin_area_2' => $data['city'],
			'postal_code' => $data['postcode'],
			'country_code' => $data['country']
		);
		// If is anything on address line 2, add to shipping address.
		if ($address_line_2) {
			$shipping_address['address_line_2'] = mb_substr(implode(', ', $address_line_2), 0, 100);
		}

		return $shipping_address;
	}

	public function validate_address(array $data): bool{
		$adressFields = ['address_line_1','admin_area_1', 'admin_area_2','postal_code', 'country_code'];
		$isValid = true; 
		foreach ($adressFields as $value) {
			if (!isset($data[$value]) || empty($data[$value])) {
				$isValid = false;
			}
		}

		return $isValid;
	}

	public function get_payer_info($data = null)
	{

		if (isset($data['person_type']) && (isset($data['cpf']) || isset($data['cnpj']))) {
			$payer_info['tax_info'] = array(
				'tax_id_type' => $data['person_type'] == '1' ? 'BR_CPF' : 'BR_CNPJ',
				'tax_id' => $data['person_type'] == '1' ? $data['cpf'] : $data['cnpj']
			);

			WC_PAYPAL_LOGGER::log("Tax_info validated from data checkout to create order.", $this->id,'info');
		}

		if (isset($data['email']) && !empty($data['email'])) {
			$payer_info['email_address'] = $data['email'];
			WC_PAYPAL_LOGGER::log("Email validated from data checkout to create order.", $this->id,'info');
		}


		if (isset($data['phone']) && !empty($data['phone'])) {
			//remove special characters
			$data['phone'] = preg_replace('/\D/', '', $data['phone']);
			$payer_info['phone'] = array(
				'phone_number' => array(
					'national_number' => "55" . $data['phone']
				)
			);
			WC_PAYPAL_LOGGER::log("Phone validated from data checkout to create order.", $this->id,'info');
		}

		if((isset($data['first_name']) && !empty($data['first_name'])) && (isset($data['last_name']) && !empty($data['last_name']))){
			$payer_info['name'] = array(
				'given_name' => $data['first_name'],
				'surname' => $data['last_name']
			);
			WC_PAYPAL_LOGGER::log("First name and last name validated from data checkout to create order.", $this->id,'info');
		}

		return $payer_info;
	}

	/**
	 * Render HTML in admin options.
	 */
	public function admin_options()
	{
		include dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/includes/views/admin-options/admin-options-bcdc/admin-options-bcdc.php';
	}

	/**
	 * Get the WooCommerce currency.
	 *
	 * @return string
	 */
	private function get_woocommerce_currency()
	{
		return get_woocommerce_currency();
	}

	/**
	 * Get the WooCommerce country.
	 *
	 * @return string
	 */
	private function get_woocommerce_country()
	{
		return get_woocommerce_currency() === 'BRL' ? 'BR' : 'US';
	}

	/**
	 * Enqueue admin scripts.
	 */
	public function admin_scripts()
	{
		$screen = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		$wc_screen_id = sanitize_title(__('WooCommerce', "paypal-brasil-para-woocommerce"));
		$wc_settings_id = $wc_screen_id . '_page_wc-settings';
		if ($wc_settings_id === $screen_id && isset($_GET['section']) && $_GET['section'] === $this->id) {
			wp_enqueue_style('wc-bcdc-brasil-admin-style', plugins_url('assets/dist/css/admin-options-bcdc.css', PAYPAL_PAYMENTS_MAIN_FILE), array(), PAYPAL_PAYMENTS_VERSION, 'all');

			// Add shared file if exists.
			if (file_exists(dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/assets/dist/js/shared.js')) {
				wp_enqueue_script('paypal_brasil_admin_options_shared', plugins_url('assets/dist/js/shared.js', PAYPAL_PAYMENTS_MAIN_FILE), array(), PAYPAL_PAYMENTS_VERSION, true);
			}

			wp_enqueue_script($this->id . '_script', plugins_url('assets/dist/js/admin-options-bcdc.js', PAYPAL_PAYMENTS_MAIN_FILE), array(), PAYPAL_PAYMENTS_VERSION, true);
			wp_localize_script($this->id . '_script', 'paypal_brasil_admin_options_bcdc', array(
				'template' => $this->get_admin_options_template(),
				'enabled' => $this->enabled,
				'mode' => $this->mode,
				'client' => array(
					'live' => $this->client_live,
					'sandbox' => $this->client_sandbox,
				),
				'secret' => array(
					'live' => $this->secret_live,
					'sandbox' => $this->secret_sandbox,
				),
				'title' => $this->title,
				'title_complement' => $this->title_complement,
				'invoice_id_prefix' => $this->invoice_id_prefix,
				'debug' => $this->debug,
				'images_path' => plugins_url('assets/images/buttons_bcdc', PAYPAL_PAYMENTS_MAIN_FILE)
			)
			);
		}
	}

	/**
	 * Get the admin options template to render by Vue.
	 */
	private function get_admin_options_template()
	{
		ob_start();
		include dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/includes/views/admin-options/admin-options-bcdc/admin-options-bcdc-template.php';

		return ob_get_clean();
	}

	/**
	 * Get log.
	 *
	 * @return string
	 */
	protected function get_log_view()
	{
		return '<a target="_blank" href="' . esc_url(admin_url('admin.php?page=wc-status&tab=logs&log_file=' . esc_attr($this->id) . '-' . sanitize_file_name(wp_hash($this->id)) . '.log')) . '">' . __('Status do Sistema &gt; Logs', "paypal-brasil-para-woocommerce") . '</a>';
	}

	private function get_fields_values()
	{
		return array(
			'enabled' => $this->enabled,
			'mode' => $this->mode,
			'client' => array(
				'live' => $this->client_live,
				'sandbox' => $this->client_sandbox,
			),
			'secret' => array(
				'live' => $this->secret_live,
				'sandbox' => $this->secret_sandbox,
			),
			'title' => $this->title,
			'title_complement' => $this->title_complement,
			'invoice_id_prefix' => $this->invoice_id_prefix,
			'debug' => $this->debug,
		);
	}

	/**
	 * Clear all user session. This should be used after process payment.
	 * Will clean every session for all integrations, as we don't need that anymore.
	 */
	public function clear_all_sessions()
	{
		$sessions = array(
			'paypal-brasil-bcdc-data',
		);

		// Each session will be destroyed.
		foreach ($sessions as $session) {
			unset(WC()->session->{$session});
		}
	}

	public function currency_is_allowed()
	{
		$alloweds_currency = PayPal_Brasil::get_allowed_currencies();

		if (!in_array(get_woocommerce_currency(), $alloweds_currency)) {
			return false;
		}

		return true;
	}


}