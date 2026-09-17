<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handler que retorna o Client Token (browser-safe) para inicializar o
 * PayPal JS SDK v6 no frontend do Basic Apple Pay.
 *
 * O Client Token é diferente do Access Token (backend): é obtido com
 * `response_type=client_token&intent=sdk_init` e possui escopo reduzido,
 * seguro para uso no navegador.
 */
class PayPal_Brasil_API_ApplePay_Client_Token_Handler extends PayPal_Brasil_API_Handler {

	public function __construct() {
		add_filter( 'paypal_brasil_handlers', array( $this, 'add_handlers' ) );
	}

	public function add_handlers( $handlers ) {
		$handlers['applepay_client_token'] = array(
			'callback' => array( $this, 'handle' ),
			'method'   => 'POST',
		);

		return $handlers;
	}

	/**
	 * Handle the request.
	 */
	public function handle() {
		try {
			// Obtém o gateway Apple Pay.
			$gateway = $this->get_paypal_gateway( 'paypal-brasil-applepay-gateway' );

			// Solicita o client token browser-safe.
			$response = $gateway->api->get_client_token();

			if ( empty( $response['access_token'] ) ) {
				$this->send_error_response( __( 'Unable to get client token.', 'paypal-brasil-para-woocommerce' ), array(), 400 );
			}

			// Retorna apenas o token para o frontend (nunca o secret).
			$this->send_success_response(
				__( 'Client token generated successfully.', 'paypal-brasil-para-woocommerce' ),
				array(
					'access_token' => $response['access_token'],
					'expires_in'   => isset( $response['expires_in'] ) ? $response['expires_in'] : null,
				)
			);
		} catch ( Exception $ex ) {
			$this->send_error_response( $ex->getMessage() );
		}
	}
}

new PayPal_Brasil_API_ApplePay_Client_Token_Handler();
