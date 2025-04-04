<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayPal_Brasil_API_Checkout_Handler extends PayPal_Brasil_API_Handler {

	public function __construct() {
		add_filter( 'paypal_brasil_handlers', array( $this, 'add_handlers' ) );
	}

	public function add_handlers( $handlers ) {
		$handlers['checkout'] = array(
			'callback' => array( $this, 'handle' ),
			'method'   => 'POST',
		);

		return $handlers;
	}

	/**
	 * Add validators and input fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		return array(
			array(
				'name'     => __( 'nonce', "paypal-brasil-para-woocommerce" ),
				'key'      => 'nonce',
				'sanitize' => 'sanitize_text_field',
//				'validation' => array( $this, 'required_nonce' ),
			),
			array(
				'name'       => __( 'name', "paypal-brasil-para-woocommerce" ),
				'key'        => 'first_name',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'       => __( 'surname', "paypal-brasil-para-woocommerce" ),
				'key'        => 'last_name',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'       => __( 'city', "paypal-brasil-para-woocommerce" ),
				'key'        => 'city',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'       => __( 'country', "paypal-brasil-para-woocommerce" ),
				'key'        => 'country',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_country' ),
			),
			array(
				'name'       => __( 'zip code', "paypal-brasil-para-woocommerce" ),
				'key'        => 'postcode',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_postcode' ),
			),
			array(
				'name'       => __( 'state', "paypal-brasil-para-woocommerce" ),
				'key'        => 'state',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_state' ),
			),
			array(
				'name'       => __( 'address', "paypal-brasil-para-woocommerce" ),
				'key'        => 'address_line_1',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'       => __( 'number', "paypal-brasil-para-woocommerce" ),
				'key'        => 'number',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
			array(
				'name'     => __( 'complement', "paypal-brasil-para-woocommerce" ),
				'key'      => 'address_line_2',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'name'     => __( 'neighborhood', "paypal-brasil-para-woocommerce" ),
				'key'      => 'neighborhood',
				'sanitize' => 'sanitize_text_field',
			),
			array(
				'name'       => __( 'phone', "paypal-brasil-para-woocommerce" ),
				'key'        => 'phone',
				'sanitize'   => 'sanitize_text_field',
				'validation' => array( $this, 'required_text' ),
			),
		);
	}

	/**
	 * Handle the request.
	 */
	public function handle() {
		try {

			$validation = $this->validate_input_data();

			if ( ! $validation['success'] ) {
				$this->send_error_response(
					__( 'Some fields are missing to initiate the payment.', 'paypal-brasil' ),
					array(
						'errors' => $validation['errors']
					)
				);
			}

			$posted_data = $validation['data'];

			// Get the wanted gateway.
			$gateway = $this->get_paypal_gateway( 'paypal-brasil-spb-gateway' );

			// Force to calculate cart.
			WC()->cart->calculate_totals();

			// Store cart.
			$cart = WC()->cart;

			// Check if there is anything on cart.
			if ( ! $cart->get_totals()['total'] ) {
				$this->send_error_response( __( 'You cannot pay for an empty order.', "paypal-brasil-para-woocommerce" ) );
			}

			$wc_cart = WC()->cart;
			$wc_cart_totals = new WC_Cart_Totals($wc_cart);
			$cart_totals = $wc_cart_totals->get_totals(true);

			$only_digital_items = paypal_brasil_is_cart_only_digital();

			$data = array (
				'purchase_units' => array(
					array(
							'items'      => array(
								array(
									'name'     => sprintf( __( 'Store order %s', "paypal-brasil-para-woocommerce" ),
										get_bloginfo( 'name' ) ),
									'unit_amount' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => paypal_format_amount( wc_remove_number_precision_deep( $cart_totals['total'] - $cart_totals['shipping_total'] ) ),
									),
									'quantity' => 1,
									'sku'      => 'order-items',
								),
							),	
							'amount' => array(
								'currency_code' => get_woocommerce_currency(),
								'value' => paypal_format_amount( wc_remove_number_precision_deep( $cart_totals['total'] ) ),
								'breakdown' => array(
									'item_total' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => paypal_format_amount( wc_remove_number_precision_deep( $cart_totals['total'] - $cart_totals['shipping_total'] ) )
									),
									'tax_total' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => '0.00'
									),
									'discount' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => '0.00'
									),
									'shipping' => array(
										'currency_code' => get_woocommerce_currency(),
										'value' => paypal_format_amount(wc_remove_number_precision_deep($cart_totals['shipping_total']))
									),
								)
							),
						),
				),
				'intent' => 'CAPTURE',
				'payment_source'               => array(
					'paypal'      => array(
						'experience_context' => array(
							'return_url' => home_url(),
							'cancel_url' => home_url(),
						),
						'user_action' => 'CONTINUE'
					),
				),
				'application_context' => array(
					'brand_name'          => get_bloginfo( 'name' ),
					'user_action'         => 'CONTINUE',
				),
			);

			WC_PAYPAL_LOGGER::log("Payload create order on SPB from checkout", $gateway->id, "info", $data);

			try {
				// Create the payment in API.
				$create_payment = $gateway->api->create_payment($data, array(), 'ec');
				WC_PAYPAL_LOGGER::log("Create order on SPB from checkout", $gateway->id, "info", $create_payment);
			} catch (PayPal_Brasil_API_Exception $ex) { // Catch any PayPal error.
				$error_data = $ex->getData();
				if ($error_data['name'] === 'VALIDATION_ERROR') {
					$exception_data = $error_data['details'];
				}

				WC_PAYPAL_LOGGER::log("Error on create order on SPB from checkout", $gateway->id, "error", $error_data);
			}

			// Get the response links.
			$links = $gateway->api->parse_links( $create_payment['links'] );

			// Extract EC token from response.
			//preg_match( '/(EC-\w+)/', $links['approval_url'], $ec_token );

			// Parse a URL para obter os parâmetros
			$urlParse = parse_url($links['payer-action']);

			// Obtenha os parâmetros da consulta
			parse_str($urlParse['query'], $param);

			// Obtenha o valor do parâmetro 'token'
			$token = isset($param['token']) ? $param['token'] : null;

			// Separate data.
			$data = array(
				'pay_id' => $create_payment['id'],
				'ec'     => $token,
			);

			// Store the requested data in session.
			WC()->session->set( 'paypal_brasil_spb_data', $data );

			// Send success response with data.
			$this->send_success_response( __( 'Payment created successfully.', "paypal-brasil-para-woocommerce" ), $data );
		} catch ( Exception $ex ) {
			$this->send_error_response( $ex->getMessage() );
		}
	}

	// CUSTOM VALIDATORS

	public function required_text( $data, $key, $name ) {
		if ( ! empty( $data ) ) {
			return true;
		}

		return sprintf( __( 'The field <strong>%s</strong> is required.', "paypal-brasil-para-woocommerce" ), $name );
	}

	public function required_country( $data, $key, $name ) {
		return $this->required_text( $data, $key, $name );
	}

	public function required_state($data, $key, $name, $input) {
		$country = isset( $input['country'] ) && !empty( $input['country'] ) ? $input['country'] : '';
		$states = WC()->countries->get_states($country);

		if ( ! $states ) {
			return true;
		}

		if ( empty( $data ) ) {
			return sprintf( __( 'The field <strong>%s</strong> is required.', "paypal-brasil-para-woocommerce" ), $name );
		} else if ( ! isset( $states[ $data ] ) ) {
			return sprintf( __( 'The field <strong>%s</strong> is invalid.', "paypal-brasil-para-woocommerce" ), $name );
		}

		return true;
	}

	public function required_postcode( $data, $key, $name ) {
		return $this->required_text( $data, $key, $name );
	}

	public function required_nonce( $data, $key, $name ) {
		if ( wp_verify_nonce( $data, 'paypal-brasil-checkout' ) ) {
			return true;
		}

		return sprintf( __( 'The %s is invalid.', "paypal-brasil-para-woocommerce" ), $name );
	}

}

new PayPal_Brasil_API_Checkout_Handler();