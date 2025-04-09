<?php

if (!defined('ABSPATH')) {
	exit;
}

class PayPal_Brasil_API_Bcdc_Checkout_Handler extends PayPal_Brasil_API_Handler
{

	public function __construct()
	{
		add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
	}

	public function add_handlers($handlers)
	{
		$handlers['checkout_bcdc'] = array(
			'callback' => array($this, 'handle'),
			'method' => 'POST',
		);

		return $handlers;
	}

	/**
	 * Add validators and input fields.
	 *
	 * @return array
	 */
	public function get_fields()
	{
		return array(
			array(
				'name' => __('name', "paypal-brasil-para-woocommerce"),
				'key' => 'first_name',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('surname', "paypal-brasil-para-woocommerce"),
				'key' => 'last_name',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('city', "paypal-brasil-para-woocommerce"),
				'key' => 'city',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('country', "paypal-brasil-para-woocommerce"),
				'key' => 'country',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_country'),
			),
			array(
				'name' => __('zip code', "paypal-brasil-para-woocommerce"),
				'key' => 'postcode',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_postcode'),
			),
			array(
				'name' => __('state', "paypal-brasil-para-woocommerce"),
				'key' => 'state',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_state'),
			),
			array(
				'name' => __('address', "paypal-brasil-para-woocommerce"),
				'key' => 'address_line_1',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('number', "paypal-brasil-para-woocommerce"),
				'key' => 'number',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('complement', "paypal-brasil-para-woocommerce"),
				'key' => 'address_line_2',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'name' => __('neighborhood', "paypal-brasil-para-woocommerce"),
				'key' => 'neighborhood',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'name' => __('phone', "paypal-brasil-para-woocommerce"),
				'key' => 'phone',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('email', "paypal-brasil-para-woocommerce"),
				'key' => 'email',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('person_type', "paypal-brasil-para-woocommerce"),
				'key' => 'person_type',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('cpf', "paypal-brasil-para-woocommerce"),
				'key' => 'cpf',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('cnpj', "paypal-brasil-para-woocommerce"),
				'key' => 'cnpj',
				'sanitize' => 'sanitize_text_field',
				//'validation' => array($this, 'required_text'),
			),
		);
	}

	/**
	 * Handle the request.
	 */
	public function handle()
	{
		try {
			$dummy = false;
			// Don' log if is dummy data.
			if ($dummy) {
				$this->debug = false;
			}

			$validation = $this->validate_input_data();

			if (!$validation['success']) {
				$this->send_error_response(
					__('Some fields are missing to initiate the payment.', 'paypal-brasil'),
					array(
						'errors' => $validation['errors']
					)
				);
			}

			$posted_data = $validation['data'];

			// Get the wanted gateway.
			$gateway = $this->get_paypal_gateway('paypal-brasil-bcdc-gateway');
			$response_data = $gateway->process_posted_data($posted_data);

			// Send success response with data.
			$this->send_success_response(__('Payment created successfully.', "paypal-brasil-para-woocommerce"), $response_data);
		} catch (Exception $ex) {
			$this->send_error_response($ex->getMessage());
		}
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

			WC_PAYPAL_LOGGER::log("Tax_info validated from data checkout to create order.", $this->get_paypal_gateway('paypal-brasil-bcdc-gateway')->id,'info');
		}

		if (isset($data['email']) && !empty($data['email'])) {
			$payer_info['email_address'] = $data['email'];
			WC_PAYPAL_LOGGER::log("Email validated from data checkout to create order.", $this->get_paypal_gateway('paypal-brasil-bcdc-gateway')->id,'info');
		}


		if (isset($data['phone']) && !empty($data['phone'])) {
			//remove special characters
			$data['phone'] = preg_replace('/\D/', '', $data['phone']);
			$payer_info['phone'] = array(
				'phone_number' => array(
					'national_number' => "55" . $data['phone']
				)
			);
			WC_PAYPAL_LOGGER::log("Phone validated from data checkout to create order.", $this->get_paypal_gateway('paypal-brasil-bcdc-gateway')->id,'info');
		}

		if((isset($data['first_name']) && !empty($data['first_name'])) && (isset($data['last_name']) && !empty($data['last_name']))){
			$payer_info['name'] = array(
				'given_name' => $data['first_name'],
				'surname' => $data['last_name']
			);
			WC_PAYPAL_LOGGER::log("First name and last name validated from data checkout to create order.", $this->get_paypal_gateway('paypal-brasil-bcdc-gateway')->id,'info');
		}

		return $payer_info;
	}

	public function currency_is_allowed()
	{
		$alloweds_currency = PayPal_Brasil::get_allowed_currencies();

		if (!in_array(get_woocommerce_currency(), $alloweds_currency)) {
			return false;
		}

		return true;
	}

	public function required_text($data, $key, $name)
	{
		if (!empty($data)) {
			return true;
		}

		return sprintf(__('The field <strong>%s</strong> is required.', "paypal-brasil-para-woocommerce"), $name);
	}

	public function required_person_type($data,$key,$name)
	{
		if (!empty($data)) {
			return true;
		}

		return sprintf(__('The field <strong>%s</strong> is required.', "paypal-brasil-para-woocommerce"), $name);
		$required_data = array('first_name', 'last_name', 'person_type');
		$required_data[] = $billing_data['person_type'] == '1' ? 'cpf' : 'cnpj';
	}

	public function required_nonce($data, $key, $name)
	{
		if (wp_verify_nonce($data, 'paypal-brasil-checkout')) {
			return true;
		}

		return sprintf(__('The %s is invalid.', "paypal-brasil-para-woocommerce"),/* */ $name);
	}

}

new PayPal_Brasil_API_Bcdc_Checkout_Handler();