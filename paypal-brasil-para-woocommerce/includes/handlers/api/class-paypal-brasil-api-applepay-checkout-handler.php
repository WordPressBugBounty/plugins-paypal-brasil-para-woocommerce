<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handlers do checkout do Apple Pay (Basic).
 *
 * - `applepay_create_order`: monta o payload mínimo (padrão SPB, sem payer/endereço)
 *   e cria o Order via Orders v2 API.
 * - `applepay_capture`: recebe o `order_id` (Order já aprovado no Apple Pay) e
 *   executa a captura via Orders v2 API.
 */
class PayPal_Brasil_API_ApplePay_Checkout_Handler extends PayPal_Brasil_API_Handler {

	public function __construct() {
		add_filter( 'paypal_brasil_handlers', array( $this, 'add_handlers' ) );
	}

	public function add_handlers( $handlers ) {
		$handlers['applepay_create_order'] = array(
			'callback' => array( $this, 'handle_create_order' ),
			'method'   => 'POST',
		);

		$handlers['applepay_capture'] = array(
			'callback' => array( $this, 'handle_capture' ),
			'method'   => 'POST',
		);

		$handlers['applepay_get_order'] = array(
			'callback' => array( $this, 'handle_get_order' ),
			'method'   => 'POST',
		);

		return $handlers;
	}

	/**
	 * Handle create order (payload mínimo, padrão SPB).
	 */
	public function handle_create_order() {
		try {
			// Obtém o gateway Apple Pay.
			$gateway = $this->get_paypal_gateway( 'paypal-brasil-applepay-gateway' );

			// Força o cálculo dos totais do carrinho.
			WC()->cart->calculate_totals();

			// Verifica se o carrinho não está vazio.
			if ( ! WC()->cart->get_totals()['total'] ) {
				$this->send_error_response( __( 'You cannot pay for an empty order.', 'paypal-brasil-para-woocommerce' ) );
			}

			$wc_cart_totals = new WC_Cart_Totals( WC()->cart );
			$cart_totals    = $wc_cart_totals->get_totals( true );

			$only_digital_items = paypal_brasil_is_cart_only_digital();

			// Payload mínimo do Apple Pay (fluxo hospedado: a folha do Apple Pay
			// coleta os dados de pagamento e endereço, então não enviamos payer/address).
			$data = array(
				'intent'         => 'CAPTURE',
				'purchase_units' => array(
					array(
						'amount' => array(
							'currency_code' => get_woocommerce_currency(),
							'value'         => paypal_format_amount( wc_remove_number_precision_deep( $cart_totals['total'] ) ),
							'breakdown'     => array(
								'item_total' => array(
									'currency_code' => get_woocommerce_currency(),
									'value'         => paypal_format_amount( wc_remove_number_precision_deep( $cart_totals['total'] - $cart_totals['shipping_total'] ) ),
								),
								'tax_total'  => array(
									'currency_code' => get_woocommerce_currency(),
									'value'         => '0.00',
								),
								'discount'   => array(
									'currency_code' => get_woocommerce_currency(),
									'value'         => '0.00',
								),
								'shipping'   => array(
									'currency_code' => get_woocommerce_currency(),
									'value'         => paypal_format_amount( wc_remove_number_precision_deep( $cart_totals['shipping_total'] ) ),
								),
							),
						),
						'items'  => array(),
					),
				),
				'application_context' => array(
					'brand_name'          => get_bloginfo( 'name' ),
					'locale'              => 'pt-BR',
					'shipping_preference' => $only_digital_items ? 'NO_SHIPPING' : 'SET_PROVIDED_ADDRESS',
				),
			);

			WC_PAYPAL_LOGGER::log( 'Payload create order on Apple Pay from checkout', $gateway->id, 'info', $data );

			// Cria o Order na API.
			$create_payment = $gateway->api->create_payment( $data, array(), 'applepay' );

			WC_PAYPAL_LOGGER::log( 'Create order on Apple Pay from checkout', $gateway->id, 'info', $create_payment );

			$this->send_success_response(
				__( 'Order created successfully.', 'paypal-brasil-para-woocommerce' ),
				array(
					'id' => $create_payment['id'],
				)
			);
		} catch ( PayPal_Brasil_API_Exception $ex ) {
			$error_data = $ex->getData();
			WC_PAYPAL_LOGGER::log( 'Error on create order on Apple Pay from checkout', 'paypal-brasil-applepay-gateway', 'error', $error_data );
			$this->send_error_response( __( 'Unable to create order.', 'paypal-brasil-para-woocommerce' ), $error_data );
		} catch ( Exception $ex ) {
			$this->send_error_response( $ex->getMessage() );
		}
	}

	/**
	 * Handle capture order (Order já aprovado no Apple Pay).
	 */
	public function handle_capture() {
		try {
			$input = $this->get_raw_data_as_json();

			if ( empty( $input['order_id'] ) ) {
				$this->send_error_response( __( 'Missing required param: order_id.', 'paypal-brasil-para-woocommerce' ), array(), 400 );
			}

			$order_id = sanitize_text_field( wp_unslash( $input['order_id'] ) );

			// Obtém o gateway Apple Pay.
			$gateway = $this->get_paypal_gateway( 'paypal-brasil-applepay-gateway' );

			// Executa a captura.
			$capture = $gateway->api->execute_payment( $order_id, array(), 'applepay' );

			WC_PAYPAL_LOGGER::log( 'Capture order on Apple Pay from checkout', $gateway->id, 'info', $capture );

			$this->send_success_response(
				__( 'Order captured successfully.', 'paypal-brasil-para-woocommerce' ),
				$capture
			);
		} catch ( PayPal_Brasil_API_Exception $ex ) {
			$error_data = $ex->getData();
			WC_PAYPAL_LOGGER::log( 'Error on capture order on Apple Pay from checkout', 'paypal-brasil-applepay-gateway', 'error', $error_data );
			$this->send_error_response( __( 'Unable to capture order.', 'paypal-brasil-para-woocommerce' ), $error_data );
		} catch ( Exception $ex ) {
			$this->send_error_response( $ex->getMessage() );
		}
	}

	/**
	 * Handle get order (consulta síncrona de status, usado em conciliação).
	 */
	public function handle_get_order() {
		try {
			$input = $this->get_raw_data_as_json();

			if ( empty( $input['order_id'] ) ) {
				$this->send_error_response( __( 'Missing required param: order_id.', 'paypal-brasil-para-woocommerce' ), array(), 400 );
			}

			$order_id = sanitize_text_field( wp_unslash( $input['order_id'] ) );

			// Obtém o gateway Apple Pay.
			$gateway = $this->get_paypal_gateway( 'paypal-brasil-applepay-gateway' );

			// Consulta os detalhes do Order via Orders v2 API (Get Order).
			$order_details = $gateway->api->get_payment( $order_id, array(), 'applepay' );

			WC_PAYPAL_LOGGER::log( 'Get order on Apple Pay from checkout', $gateway->id, 'info', $order_details );

			$this->send_success_response(
				__( 'Order details retrieved successfully.', 'paypal-brasil-para-woocommerce' ),
				$order_details
			);
		} catch ( PayPal_Brasil_API_Exception $ex ) {
			$error_data = $ex->getData();
			WC_PAYPAL_LOGGER::log( 'Error on get order on Apple Pay from checkout', 'paypal-brasil-applepay-gateway', 'error', $error_data );
			$this->send_error_response( __( 'Unable to get order.', 'paypal-brasil-para-woocommerce' ), $error_data );
		} catch ( Exception $ex ) {
			$this->send_error_response( $ex->getMessage() );
		}
	}
}

new PayPal_Brasil_API_ApplePay_Checkout_Handler();
