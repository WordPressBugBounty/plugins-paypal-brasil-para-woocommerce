<?php

// Exit if run outside WP.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PayPal_Brasil_Handler.
 */
class PayPal_Brasil_Handler {

	/**
	 * PayPal_Brasil_Handler constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_paypal_brasil_handler', array( $this, 'handler' ) );

		// Include API files.
		$this->includes();
	}

	/**
	 * Include all API files.
	 */
	private function includes() {
		include_once dirname( __FILE__ ) . '/abstratct-class-paypal-brasil-api-handler.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-checkout-handler.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-bcdc-checkout-handler.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-webhook-handler.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-shortcut-cart-handler.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-shortcut-mini-cart-handler.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-billing-agreement-token.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-save-billing-agreement.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-bcdc-notification.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-banner-notification.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-bcdc-activate.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-ppp-activate.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-pplus-deactivate.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-bcdc-checkout-activate.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-logger-handler.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-applepay-client-token-handler.php';
		include_once dirname( __FILE__ ) . '/api/class-paypal-brasil-api-applepay-checkout-handler.php';
	}

	/**
	 * Get all the setup handlers.
	 * @return array
	 */
	public function get_handlers() {
		return apply_filters( 'paypal_brasil_handlers', array() );
	}

	/**
	 * Execute the request.
	 */
	public function handler() {
		$handlers = $this->get_handlers();

		// Return an error in case action doesn't exists .
		if ( ! isset( $_GET['action'] ) || empty( $_GET['action'] ) || ! array_key_exists( $_GET['action'], $handlers ) ) {
			wp_send_json( array(
				'result'  => 'error',
				'message' => "Invalid request. Invalid param 'action'.",
			), 500 );
		}

		$action = $_GET['action'];

		// Enforce the HTTP method declared for the handler.
		if ( isset( $handlers[ $action ]['method'] ) ) {
			$declared_method = strtoupper( $handlers[ $action ]['method'] );
			$request_method  = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
			if ( $request_method !== $declared_method ) {
				wp_send_json( array(
					'result'  => 'error',
					'message' => 'Method not allowed.',
				), 405 );
			}
		}

		// Enforce authorization for handlers that require it.
		if ( ! empty( $handlers[ $action ]['requires_auth'] ) ) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_send_json( array(
					'result'  => 'error',
					'message' => 'Not authorized.',
				), 403 );
			}

			$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( $_SERVER['HTTP_X_WP_NONCE'] ) : '';
			if ( ! wp_verify_nonce( $nonce, 'paypal_brasil_handler' ) ) {
				wp_send_json( array(
					'result'  => 'error',
					'message' => 'Not authorized.',
				), 403 );
			}

			$content_type = isset( $_SERVER['CONTENT_TYPE'] ) ? $_SERVER['CONTENT_TYPE'] : '';
			if ( stripos( $content_type, 'application/json' ) === false ) {
				wp_send_json( array(
					'result'  => 'error',
					'message' => 'Content-Type must be application/json.',
				), 415 );
			}
		}

		call_user_func( $handlers[ $action ]['callback'] );
	}

}
