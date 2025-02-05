<?php

if (!defined('ABSPATH')) {
	exit;
}

class PayPal_Brasil_API_Logger_Handler extends PayPal_Brasil_API_Handler
{

	public function __construct()
	{
		add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
	}

	public function add_handlers($handlers)
	{
		$handlers['api_logger_handler'] = array(
			'callback' => array($this, 'handle'),
			'method' => 'POST',
		);

		return $handlers;
	}

	public function validate_input_data(): array	
	{

		// Get input data in body.
		$input = $this->get_raw_data_as_json();

		// Get fields.
		$fields = $this->get_fields();

		// Loop each item and validate.
		foreach ($fields as $item) {
			// Get the data if exists.
			$input_data = isset($input[$item['key']]) ? $input[$item['key']] : '';

			if (isset($item['sanitize'])) {
				$sanitized_data = call_user_func($item['sanitize'], $input_data, $item['key']);
			} else {
				$sanitized_data = $input_data;
			}

			// Check first is there is any validation for this field.
			if (isset($item['validation'])) {
				// Call for validation method.
				$validation = call_user_func($item['validation'], $sanitized_data, $item['key'], $item['name'], $input);

				// If there is any validation error, add to error items.
				if ($validation !== true) {
					$errors[$item['key']] = $validation;
				}
			}

			// Add the sanitized item to data.
			$data[$item['key']] = $sanitized_data;
		}

		return array(
			'success' => !$errors,
			'errors' => $errors,
			'data' => $data,
		);
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
				'name' => __('nonce', "paypal-brasil-para-woocommerce"),
				'key' => 'nonce',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_nonce'),
			),
			array(
				'name' => __('gateway_id', "paypal-brasil-para-woocommerce"),
				'key' => 'gateway_id',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('message', "paypal-brasil-para-woocommerce"),
				'key' => 'message',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('level', "paypal-brasil-para-woocommerce"),
				'key' => 'level',
				'sanitize' => 'sanitize_text_field',
				'validation' => array($this, 'required_text'),
			),
			array(
				'name' => __('tags', "paypal-brasil-para-woocommerce"),
				'key' => 'tags'
			),
			array(
				'name' => __('extra', "paypal-brasil-para-woocommerce"),
				'key' => 'extra'
			)
		);
	}

	/**
	 * Handle the request.
	 */
	public function handle()
	{
		try {
			$validation = $this->validate_input_data();
			if (!$validation['success']) {
				$this->send_error_response(
					__('Some fields are missing to initiate the payment.', 'paypal-brasil'),
					array(
						'errors' => $validation['errors']
					)
				);
			}

			$data = $validation['data'];
			$response = WC_PAYPAL_LOGGER::log($data["message"], $data["gateway_id"], $data["level"], $data["extra"], $data["tags"]);
			// Send success response with data.
			$this->send_success_response(__('Log created successfully.', "paypal-brasil-para-woocommerce"));
		} catch (Exception $ex) {
			$this->send_error_response($ex->getMessage());
		}
	}

	public function required_text($data, $key, $name)
	{
		if (!empty($data)) {
			return true;
		}

		return sprintf(__('The field <strong>%s</strong> is required.', "paypal-brasil-para-woocommerce"), $name);
	}

	public function required_nonce($data, $key, $name)
	{
		if (wp_verify_nonce($data, 'paypal-brasil-checkout')) {
			return true;
		}

		return sprintf(__('The %s is invalid.', "paypal-brasil-para-woocommerce"), $name);
	}


}

new PayPal_Brasil_API_Logger_Handler();