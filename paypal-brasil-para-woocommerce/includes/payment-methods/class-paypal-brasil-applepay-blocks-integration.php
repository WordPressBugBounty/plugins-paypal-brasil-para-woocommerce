<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Integração do Apple Pay com o Checkout em Blocos do WooCommerce.
 *
 * Registra o gateway Apple Pay como um método de pagamento compatível com a
 * API de Cart & Checkout Blocks (WooCommerce Blocks). Reaproveita a mesma
 * lógica de elegibilidade/credenciais do gateway clássico, expondo os dados
 * necessários ao frontend via wcSettings (paymentMethodData).
 */
final class PayPal_Brasil_ApplePay_Blocks_Integration extends AbstractPaymentMethodType {

	/**
	 * Nome do método. Deve corresponder ao id do gateway Apple Pay.
	 *
	 * @var string
	 */
	protected $name = 'paypal-brasil-applepay-gateway';

	/**
	 * Carrega as configurações do gateway a partir das opções do WordPress.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_paypal-brasil-applepay-gateway_settings', array() );

		// Estilos do botão/mark no Checkout em Blocos.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enfileira o CSS do bloco apenas na página de checkout.
	 */
	public function enqueue_styles() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'paypal-brasil-applepay-blocks',
			plugins_url( 'assets/dist/css/frontend-applepay-blocks.css', PAYPAL_PAYMENTS_MAIN_FILE ),
			array(),
			PAYPAL_PAYMENTS_VERSION
		);
	}

	/**
	 * Define se o método deve estar ativo no Checkout em Blocos.
	 *
	 * Quando false, o script não é enfileirado e o método não aparece na lista.
	 *
	 * @return bool
	 */
	public function is_active() {
		$gateway = $this->get_gateway();

		return $gateway && $gateway->is_available();
	}

	/**
	 * Registra e retorna os handles de script deste método (frontend).
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		$script_path = 'assets/dist/js/frontend-applepay-blocks.js';
		$script_url  = plugins_url( $script_path, PAYPAL_PAYMENTS_MAIN_FILE );

		wp_register_script(
			'paypal-brasil-applepay-blocks',
			$script_url,
			array( 'wc-blocks-registry' ),
			PAYPAL_PAYMENTS_VERSION,
			true
		);

		return array( 'paypal-brasil-applepay-blocks' );
	}

	/**
	 * Dados expostos ao script do bloco via wcSettings.paymentMethodData.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateway = $this->get_gateway();
		if ( ! $gateway ) {
			return array();
		}

		return array(
			'title'                     => $gateway->get_title(),
			'description'               => $gateway->get_description(),
			'supports'                  => $gateway->supports,
			'id'                        => $this->name,
			'mode'                      => 'sandbox' === $gateway->mode ? 'sandbox' : 'live',
			'currency'                  => get_woocommerce_currency(),
			'country_code'              => 'BR',
			'locale'                    => get_locale(),
			'debug_mode'                => 'yes' === $gateway->get_option( 'debug' ),
			'paypal_brasil_handler_url' => add_query_arg(
				array(
					'wc-api' => 'paypal_brasil_handler',
					'action' => '{ACTION}',
				),
				home_url() . '/'
			),
		);
	}

	/**
	 * Retorna a instância do gateway Apple Pay.
	 *
	 * @return PayPal_Brasil_ApplePay_Gateway|null
	 */
	private function get_gateway() {
		$gateways = WC()->payment_gateways()->payment_gateways();

		return isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
	}
}
