<?php

// Ignore if access directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_PAYPAL_LOGGER' ) ) {
	require_once plugin_dir_path( __FILE__ ) . '../class-wc-paypal-logger.php';
}

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Class PayPal_Brasil_ApplePay_Gateway.
 *
 * Implementa o Basic Apple Pay (fluxo hospedado pelo PayPal) via PayPal JS SDK v6.
 * Segue o mesmo padrão dos gateways PIX/BCDC: credenciais compartilháveis
 * (credential_configuration), webhooks próprios e captura via Orders v2.
 *
 * @property string client_live
 * @property string client_sandbox
 * @property string secret_live
 * @property string secret_sandbox
 * @property string debug
 * @property string invoice_id_prefix
 * @property string title_complement
 * @property string credential_configuration
 */
class PayPal_Brasil_ApplePay_Gateway extends PayPal_Brasil_Gateway {

	private static $instance;

	public string $title_complement;
	public $credential_configuration;
	public string $invoice_id_prefix;

	/**
	 * PayPal_Brasil_ApplePay_Gateway constructor.
	 */
	public function __construct() {
		parent::__construct();

		// Store some default gateway settings.
		$this->id                 = 'paypal-brasil-applepay-gateway';
		$this->has_fields         = true;
		$this->method_title       = __( 'PayPal Brasil - Apple Pay', 'paypal-brasil-para-woocommerce' );
		$this->icon               = plugins_url( 'assets/images/paypal-logo.png', PAYPAL_PAYMENTS_MAIN_FILE );
		$this->method_description = __(
			'Aceite pagamentos via Apple Pay (Basic) através do PayPal.',
			'paypal-brasil-para-woocommerce'
		);
		// Evita aviso de depreciação no get_description() (WooCommerce >= 9) que
		// chama wp_kses_post() sobre uma descrição null quando o campo não existe.
		$this->description        = '';
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load settings fields.
		$this->init_form_fields();
		$this->init_settings();

		// Get available options.
		$this->enabled                 = $this->get_option( 'enabled' );
		$this->title                   = __( 'Apple Pay', 'paypal-brasil-para-woocommerce' );
		$this->title_complement        = $this->get_option( 'title_complement' );
		$this->mode                    = $this->get_option( 'mode' );
		$this->credential_configuration = $this->get_option( 'credential_configuration', 'none' );
		$this->client_live             = $this->get_option( 'client_live' );
		$this->client_sandbox          = $this->get_option( 'client_sandbox' );
		$this->secret_live             = $this->get_option( 'secret_live' );
		$this->secret_sandbox          = $this->get_option( 'secret_sandbox' );
		$this->invoice_id_prefix       = $this->get_option( 'invoice_id_prefix' );
		$this->debug                   = $this->get_option( 'debug' );

		// Instance the API.
		$this->api = new PayPal_Brasil_Orders_api_V2( $this->get_client_id(), $this->get_secret(), $this->mode, $this );

		// Save settings.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options',
		), 10 );

		// Save custom settings (credenciais + webhooks) antes do save padrão.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'before_process_admin_options',
		), 20 );

		// Handler para webhooks (IPN).
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'webhook_handler' ) );

		// Stop here if is not the first load.
		if ( ! $this->is_first_load() ) {
			return;
		}

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'checkout_scripts' ), 0 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		// If it's first load, add an instance of this.
		self::$instance = $this;
	}

	/**
	 * Check if is first load of this class.
	 * This should prevent add double hooks.
	 *
	 * @return bool
	 */
	private function is_first_load() {
		return ! self::$instance;
	}

	/**
	 * Return the gateway's title.
	 *
	 * @return string
	 */
	public function get_title() {
		// A description only for admin section.
		if ( is_admin() ) {
			global $pagenow;

			return $pagenow === 'post.php' ? __( 'PayPal - Apple Pay', 'paypal-brasil-para-woocommerce' ) : __( 'Apple Pay', 'paypal-brasil-para-woocommerce' );
		}

		// Title for frontend.
		$title = __( 'Apple Pay', 'paypal-brasil-para-woocommerce' );
		if ( ! empty( $this->title_complement ) ) {
			$title .= ' (' . $this->title_complement . ')';
		}

		return apply_filters( 'woocommerce_gateway_title', $title, $this->id );
	}

	/**
	 * Define gateway form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'                 => array(
				'title'   => __( 'Ativar/Desativar', 'paypal-brasil-para-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar Apple Pay', 'paypal-brasil-para-woocommerce' ),
				'default' => 'no',
			),
			'title_complement'        => array(
				'title'       => __( 'Nome de exibição (complemento)', 'paypal-brasil-para-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Texto adicional que aparecerá no título do método de pagamento.', 'paypal-brasil-para-woocommerce' ),
			),
			'mode'                    => array(
				'title'   => __( 'Modo', 'paypal-brasil-para-woocommerce' ),
				'type'    => 'select',
				'options' => array(
					'live'    => __( 'Produção', 'paypal-brasil-para-woocommerce' ),
					'sandbox' => __( 'Sandbox', 'paypal-brasil-para-woocommerce' ),
				),
			),
			'credential_configuration' => array(
				'title'       => __( 'Credential Configuration', 'paypal-brasil-para-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'none'            => __( 'Do not use credentials', 'paypal-brasil-para-woocommerce' ),
					'use_bcdc'        => __( 'Use BCDC Credentials', 'paypal-brasil-para-woocommerce' ),
					'use_spb'         => __( 'Use SPB Credentials', 'paypal-brasil-para-woocommerce' ),
					'new_credentials' => __( 'Configure New Credentials', 'paypal-brasil-para-woocommerce' ),
				),
				'description' => __( 'Escolha como configurar as credenciais do Apple Pay. Você pode reutilizar credenciais de outros gateways ou configurar novas.', 'paypal-brasil-para-woocommerce' ),
				'default'     => 'none',
			),
			'client_live'             => array(
				'title' => __( 'Client ID (produção)', 'paypal-brasil-para-woocommerce' ),
				'type'  => 'text',
			),
			'client_sandbox'          => array(
				'title'   => __( 'Client ID (sandbox)', 'paypal-brasil-para-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'secret_live'             => array(
				'title' => __( 'Secret (produção)', 'paypal-brasil-para-woocommerce' ),
				'type'  => 'text',
			),
			'secret_sandbox'          => array(
				'title'   => __( 'Secret (sandbox)', 'paypal-brasil-para-woocommerce' ),
				'type'    => 'text',
				'default' => '',
			),
			'debug'                   => array(
				'title'       => __( 'Log de depuração', 'paypal-brasil-para-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Ativar logs', 'paypal-brasil-para-woocommerce' ),
				'default'     => 'no',
				'description' => __( 'Ativar para registrar eventos do Apple Pay para depuração.', 'paypal-brasil-para-woocommerce' ),
			),
			'invoice_id_prefix'       => array(
				'title'       => __( 'Prefixo no número do pedido', 'paypal-brasil-para-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Adicione um prefixo ao número do pedido, útil para identificação quando você tem mais de uma loja processando pelo PayPal.', 'paypal-brasil-para-woocommerce' ),
			),
		);
	}

	/**
	 * Executado antes do save padrão das opções do gateway.
	 * Valida credenciais e cria webhooks quando o gateway está habilitado.
	 */
	public function before_process_admin_options() {
		// Check first if is enabled.
		$enabled = $this->get_field_value( 'enabled', $this->form_fields['enabled'] );
		if ( $enabled !== 'yes' ) {
			return;
		}

		// update credentials.
		$this->update_credentials();

		// validate credentials.
		$this->validate_credentials();

		// create webhooks.
		$this->create_webhooks();
	}

	/**
	 * Atualiza as credenciais do gateway de acordo com o credential_configuration.
	 */
	public function update_credentials() {
		$mode               = $this->get_field_value( 'mode', $this->form_fields['mode'] );
		$credential_config  = $this->get_field_value( 'credential_configuration', $this->form_fields['credential_configuration'] );

		$client = '';
		$secret = '';

		switch ( $credential_config ) {
			case 'use_bcdc':
				$credentials = $this->get_bcdc_credentials( $mode );
				$client      = $credentials['client'];
				$secret      = $credentials['secret'];
				break;

			case 'use_spb':
				$credentials = $this->get_spb_credentials( $mode );
				$client      = $credentials['client'];
				$secret      = $credentials['secret'];
				break;

			case 'new_credentials':
				$client_type = $mode === 'sandbox' ? 'client_sandbox' : 'client_live';
				$secret_type = $mode === 'sandbox' ? 'secret_sandbox' : 'secret_live';
				$client      = $this->get_field_value( $client_type, $this->form_fields[ $client_type ] );
				$secret      = $this->get_field_value( $secret_type, $this->form_fields[ $secret_type ] );
				break;

			case 'none':
			default:
				// Não usa credenciais.
				return;
		}

		// Salva as credenciais nas opções do gateway atual.
		if ( ! empty( $client ) && ! empty( $secret ) ) {
			$client_key = $mode === 'sandbox' ? 'client_sandbox' : 'client_live';
			$secret_key = $mode === 'sandbox' ? 'secret_sandbox' : 'secret_live';

			$this->update_option( $client_key, $client );
			$this->update_option( $secret_key, $secret );

			if ( $mode === 'sandbox' ) {
				$this->client_sandbox = $client;
				$this->secret_sandbox = $secret;
			} else {
				$this->client_live = $client;
				$this->secret_live = $secret;
			}
		}

		$this->api->update_credentials( $client, $secret, $mode );
	}

	/**
	 * Get BCDC gateway credentials.
	 */
	public function get_bcdc_credentials( $mode ) {
		$bcdc_settings = get_option( 'woocommerce_paypal-brasil-bcdc-gateway_settings' );

		if ( ! $bcdc_settings ) {
			return array( 'client' => '', 'secret' => '' );
		}

		$client_key = $mode === 'sandbox' ? 'client_sandbox' : 'client_live';
		$secret_key = $mode === 'sandbox' ? 'secret_sandbox' : 'secret_live';

		return array(
			'client' => isset( $bcdc_settings[ $client_key ] ) ? $bcdc_settings[ $client_key ] : '',
			'secret' => isset( $bcdc_settings[ $secret_key ] ) ? $bcdc_settings[ $secret_key ] : '',
		);
	}

	/**
	 * Get SPB gateway credentials.
	 */
	public function get_spb_credentials( $mode ) {
		$spb_settings = get_option( 'woocommerce_paypal-brasil-spb-gateway_settings' );

		if ( ! $spb_settings ) {
			return array( 'client' => '', 'secret' => '' );
		}

		$client_key = $mode === 'sandbox' ? 'client_sandbox' : 'client_live';
		$secret_key = $mode === 'sandbox' ? 'secret_sandbox' : 'secret_live';

		return array(
			'client' => isset( $spb_settings[ $client_key ] ) ? $spb_settings[ $client_key ] : '',
			'secret' => isset( $spb_settings[ $secret_key ] ) ? $spb_settings[ $secret_key ] : '',
		);
	}

	/**
	 * Override get_client_id to use configured credentials.
	 */
	public function get_client_id() {
		$credential_config = $this->get_option( 'credential_configuration' );

		switch ( $credential_config ) {
			case 'use_bcdc':
				$credentials = $this->get_bcdc_credentials( $this->mode );
				return $credentials['client'];

			case 'use_spb':
				$credentials = $this->get_spb_credentials( $this->mode );
				return $credentials['client'];

			case 'new_credentials':
				return parent::get_client_id();

			case 'none':
			default:
				return '';
		}
	}

	/**
	 * Override get_secret to use configured credentials.
	 */
	public function get_secret() {
		$credential_config = $this->get_option( 'credential_configuration' );

		switch ( $credential_config ) {
			case 'use_bcdc':
				$credentials = $this->get_bcdc_credentials( $this->mode );
				return $credentials['secret'];

			case 'use_spb':
				$credentials = $this->get_spb_credentials( $this->mode );
				return $credentials['secret'];

			case 'new_credentials':
				return parent::get_secret();

			case 'none':
			default:
				return '';
		}
	}

	/**
	 * Create the webhook or use an existent webhook.
	 *
	 * Eventos Apple Pay (SDD 4.4.5).
	 *
	 * @return bool True when webhook is ready (created/updated/already ok).
	 */
	public function create_webhooks() {
		$webhook     = null;
		$webhook_url = defined( 'PAYPAL_BRASIL_WEBHOOK_URL' ) ? PAYPAL_BRASIL_WEBHOOK_URL : $this->get_webhook_url();

		$events_types = array(
			'CHECKOUT.ORDER.APPROVED',
			'PAYMENT.CAPTURE.COMPLETED',
			'PAYMENT.CAPTURE.DENIED',
			'PAYMENT.CAPTURE.PENDING',
			'PAYMENT.CAPTURE.REFUNDED',
			'PAYMENT.CAPTURE.REVERSED',
			'PAYMENT.CAPTURE.DECLINED',
		);

		try {
			// Get a list of webhooks.
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
				$webhook_result = $this->api->create_webhook( $webhook_url, $events_types );
				update_option( 'paypal_brasil_webhook_url-' . $this->id, $webhook_result['id'] );
				return true;
			}

			$registered_event_names = array();
			if ( ! empty( $webhook['event_types'] ) && is_array( $webhook['event_types'] ) ) {
				foreach ( $webhook['event_types'] as $event_type ) {
					if ( ! empty( $event_type['name'] ) ) {
						$registered_event_names[] = $event_type['name'];
					}
				}
			}

			$missing_events = array_diff( $events_types, $registered_event_names );
			if ( ! empty( $missing_events ) ) {
				try {
					$this->api->update_webhook_event_types( $webhook['id'], $events_types );
				} catch ( Exception $update_ex ) {
					WC_PAYPAL_LOGGER::log(
						'Failed to update webhook event types: ' . $update_ex->getMessage(),
						$this->id,
						'error',
						array( 'missing_events' => array_values( $missing_events ) )
					);
					update_option( 'paypal_brasil_webhook_url-' . $this->id, $webhook['id'] );
					return false;
				}
			}

			// Set the webhook ID.
			update_option( 'paypal_brasil_webhook_url-' . $this->id, $webhook['id'] );
			return true;
		} catch ( Exception $ex ) {
			update_option( 'paypal_brasil_webhook_url-' . $this->id, null );
			return false;
		}
	}

	/**
	 * Check if the credentials were validated.
	 *
	 * @return bool
	 */
	public function is_credentials_validated() {
		return get_option( $this->get_option_key() . '_validator' ) === 'yes';
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = ( 'yes' === $this->enabled );

		// Verificar se é moeda brasileira.
		if ( get_woocommerce_currency() !== 'BRL' ) {
			$is_available = false;
		}

		if ( WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total() ) {
			$is_available = false;
		}

		if ( ! $this->is_credentials_validated() ) {
			$is_available = false;
		}

		return $is_available;
	}

	/**
	 * Render the payment fields in checkout.
	 */
	public function payment_fields() {
		include dirname( PAYPAL_PAYMENTS_MAIN_FILE ) . '/includes/views/checkout/applepay-checkout-fields.php';
	}

	/**
	 * Process gateway payment for a given order ID.
	 *
	 * No fluxo Apple Pay, o pedido já foi criado e aprovado no SDK v6
	 * (onApprove). Aqui apenas capturamos o Order e marcamos o pedido.
	 *
	 * @param int $order_id
	 *
	 * @return array|null
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		try {
			// Order id do PayPal enviado pelo frontend (hidden field).
			$paypal_order_id = isset( $_POST['paypal-brasil-applepay-order-id'] ) ? sanitize_text_field( wp_unslash( $_POST['paypal-brasil-applepay-order-id'] ) ) : '';

			if ( empty( $paypal_order_id ) ) {
				wc_add_notice( __( 'Não foi possível identificar o pagamento. Atualize a página e tente novamente.', 'paypal-brasil-para-woocommerce' ), 'error' );
				return null;
			}

			// Verificação síncrona de status antes da captura (Get Order).
			// Confirma que o Order está em um estado capturável antes de executar a captura.
			$order_details = $this->api->get_payment( $paypal_order_id, array(), 'applepay' );
			$order_status  = isset( $order_details['status'] ) ? $order_details['status'] : '';

			if ( 'APPROVED' !== $order_status ) {
				wc_add_notice( __( 'O pagamento ainda não foi aprovado. Tente novamente.', 'paypal-brasil-para-woocommerce' ), 'error' );
				return null;
			}

			// Captura o Order já aprovado no Apple Pay.
			$response = $this->api->execute_payment( $paypal_order_id, array(), 'applepay' );

			$capture = isset( $response['purchase_units'][0]['payments']['captures'][0] ) ? $response['purchase_units'][0]['payments']['captures'][0] : null;

			if ( ! $capture ) {
				wc_add_notice( __( 'Não foi possível confirmar a captura do pagamento.', 'paypal-brasil-para-woocommerce' ), 'error' );
				return null;
			}

			$capture_id     = isset( $capture['id'] ) ? $capture['id'] : '';
			$capture_status = isset( $capture['status'] ) ? $capture['status'] : '';

			// Salva os dados da captura no pedido.
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				$order->update_meta_data( 'wc_applepay_brasil_sale_id', $response['id'] );
				$order->update_meta_data( 'wc_applepay_brasil_capture_id', $capture_id );
				$order->update_meta_data( 'wc_applepay_brasil_sale', $response['purchase_units'] );
				$order->update_meta_data( 'wc_applepay_brasil_sandbox', $this->mode );
			} else {
				update_post_meta( $order_id, 'wc_applepay_brasil_sale_id', $response['id'] );
				update_post_meta( $order_id, 'wc_applepay_brasil_capture_id', $capture_id );
				update_post_meta( $order_id, 'wc_applepay_brasil_sale', $response['purchase_units'] );
				update_post_meta( $order_id, 'wc_applepay_brasil_sandbox', $this->mode );
			}

			$order->set_payment_method_title( __( 'Apple Pay', 'paypal-brasil-para-woocommerce' ) );

			switch ( $capture_status ) {
				case 'COMPLETED':
					$order->add_order_note(
						sprintf(
							__( 'Pagamento processado pelo PayPal. ID da transação: <a href="%s" target="_blank" rel="noopener">%s</a>.', 'paypal-brasil-para-woocommerce' ),
							$this->mode === 'sandbox' ? "https://www.sandbox.paypal.com/activity/payment/{$capture_id}" : "https://www.paypal.com/activity/payment/{$capture_id}",
							$capture_id
						)
					);
					$order->payment_complete();
					break;

				case 'PENDING':
					wc_reduce_stock_levels( $order_id );
					$order->update_status( 'on-hold', __( 'Pagamento em análise pelo PayPal.', 'paypal-brasil-para-woocommerce' ) );
					break;

				default:
					wc_add_notice( __( 'Pagamento não aprovado. Tente novamente.', 'paypal-brasil-para-woocommerce' ), 'error' );
					return null;
			}

			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( PayPal_Brasil_API_Exception $ex ) {
			$data = $ex->getData();
			WC_PAYPAL_LOGGER::log( 'Error to capture order on Apple Pay.', $this->id, 'error', $data );
			wc_add_notice( __( 'Não foi possível processar o pagamento com Apple Pay. Tente novamente.', 'paypal-brasil-para-woocommerce' ), 'error' );
			return null;
		} catch ( Exception $ex ) {
			WC_PAYPAL_LOGGER::log( 'Error to capture order on Apple Pay: ' . $ex->getMessage(), $this->id, 'error' );
			wc_add_notice( __( 'Não foi possível processar o pagamento com Apple Pay. Tente novamente.', 'paypal-brasil-para-woocommerce' ), 'error' );
			return null;
		}
	}

	/**
	 * Process the refund for an order.
	 *
	 * @param int    $order_id
	 * @param float  $amount
	 * @param string $reason
	 *
	 * @return WP_Error|bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders = wc_get_orders(
				array(
					'ID'         => array( $order_id ),
					'meta_query' => array(
						array(
							'key' => 'wc_applepay_brasil_capture_id',
						),
					),
				)
			);

			$capture_id = ! empty( $orders ) ? $orders[0]->get_meta( 'wc_applepay_brasil_capture_id' ) : '';
		} else {
			$capture_id = get_post_meta( $order_id, 'wc_applepay_brasil_capture_id', true );
		}

		// Check if the amount is bigger than zero.
		if ( $amount <= 0 ) {
			$min_price = number_format( 0, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );

			return new WP_Error( 'error', sprintf( __( 'O reembolso não pode ser menor que %s.', 'paypal-brasil-para-woocommerce' ), html_entity_decode( get_woocommerce_currency_symbol() ) . $min_price ) );
		}

		// Check if we got the capture ID.
		if ( $capture_id ) {
			try {
				$refund_sale = $this->api->refund_payment( $capture_id, paypal_brasil_money_format( $amount ), get_woocommerce_currency() );

				if ( $refund_sale['status'] === 'COMPLETED' ) {
					return true;
				}

				return new WP_Error( 'error', 'error: ' . ( isset( $refund_sale->getReason ) ? $refund_sale->getReason() : '' ) );
			} catch ( PayPal_Brasil_API_Exception $ex ) {
				$data = $ex->getData();
				WC_PAYPAL_LOGGER::log( 'Error on refund from Apple Pay.', $this->id, 'error', $data );
				return new WP_Error( 'error', $data['message'] );
			} catch ( Exception $ex ) {
				WC_PAYPAL_LOGGER::log( 'Error on refund from Apple Pay: ' . $ex->getMessage(), $this->id, 'error' );
				return new WP_Error( 'error', __( 'Houve um erro ao tentar fazer o reembolso.', 'paypal-brasil-para-woocommerce' ) );
			}
		}

		return new WP_Error( 'error', __( 'Não foi possível localizar a transação para reembolso.', 'paypal-brasil-para-woocommerce' ) );
	}

	/**
	 * Backend view for admin options.
	 */
	public function admin_options() {
		include dirname( PAYPAL_PAYMENTS_MAIN_FILE ) . '/includes/views/admin-options/admin-options-applepay/admin-options-applepay.php';
	}

	/**
	 * Get the admin options template to render by Vue.
	 */
	private function get_admin_options_template() {
		ob_start();
		include dirname( PAYPAL_PAYMENTS_MAIN_FILE ) . '/includes/views/admin-options/admin-options-applepay/admin-options-applepay-template.php';

		return ob_get_clean();
	}

	/**
	 * Enqueue admin scripts for gateway settings page.
	 */
	public function admin_scripts() {
		$screen         = get_current_screen();
		$screen_id      = $screen ? $screen->id : '';
		$wc_screen_id   = sanitize_title( __( 'WooCommerce', 'paypal-brasil-para-woocommerce' ) );
		$wc_settings_id = $wc_screen_id . '_page_wc-settings';

		// Check if we are on the gateway settings page.
		if ( $wc_settings_id === $screen_id && isset( $_GET['section'] ) && $_GET['section'] === $this->id ) {

			// Add shared file if exists.
			if ( file_exists( dirname( PAYPAL_PAYMENTS_MAIN_FILE ) . '/assets/dist/js/shared.js' ) ) {
				wp_enqueue_script( 'paypal_brasil_admin_options_shared', plugins_url( 'assets/dist/js/shared.js', PAYPAL_PAYMENTS_MAIN_FILE ), array(), PAYPAL_PAYMENTS_VERSION, true );
			}

			wp_enqueue_style( $this->id . '_style', plugins_url( 'assets/dist/css/admin-options-applepay.css', PAYPAL_PAYMENTS_MAIN_FILE ), array(), PAYPAL_PAYMENTS_VERSION, 'all' );

			wp_enqueue_script( $this->id . '_script', plugins_url( 'assets/dist/js/admin-options-applepay.js', PAYPAL_PAYMENTS_MAIN_FILE ), array(), PAYPAL_PAYMENTS_VERSION, true );

			wp_localize_script(
				$this->id . '_script',
				'paypal_brasil_admin_options_applepay',
				array(
					'template'                => $this->get_admin_options_template(),
					'enabled'                 => $this->enabled,
					'mode'                    => $this->mode,
					'credential_configuration' => $this->credential_configuration,
					'client'                  => array(
						'live'    => $this->client_live,
						'sandbox' => $this->client_sandbox,
					),
					'secret'                  => array(
						'live'    => $this->secret_live,
						'sandbox' => $this->secret_sandbox,
					),
					'title'                   => $this->title,
					'title_complement'        => $this->title_complement,
					'invoice_id_prefix'       => $this->invoice_id_prefix,
					'debug'                   => $this->debug,
					'images_path'             => plugins_url( 'assets/images/buttons', PAYPAL_PAYMENTS_MAIN_FILE ),
				)
			);
		}
	}

	/**
	 * Enqueue scripts in checkout.
	 */
	public function checkout_scripts() {
		if ( ! $this->is_available() ) {
			return;
		}

		if ( is_checkout() || is_checkout_pay_page() ) {
			// Carrega os bundles do PayPal JS SDK v6 (core + brand) exigidos pelo Basic Apple Pay.
			// O componente `applepay-payments` e o `<apple-pay-mark>` vêm do bundle `brand`.
			// Em sandbox o SDK deve ser carregado do host sandbox; do contrário, o client token
			// de sandbox é rejeitado pelo SDK de produção ("invalid_client").
			$sdk_host = 'sandbox' === $this->mode ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';

			wp_enqueue_script(
				'paypal-web-sdk-v6-core',
				$sdk_host . '/web-sdk/v6/core',
				array(),
				null,
				true
			);
			wp_enqueue_script(
				'paypal-web-sdk-v6-brand',
				$sdk_host . '/web-sdk/v6/brand',
				array(),
				null,
				true
			);

			wp_enqueue_script(
				'paypal-brasil-applepay',
				plugins_url( 'assets/dist/js/frontend-applepay.js', PAYPAL_PAYMENTS_MAIN_FILE ),
				array( 'jquery' ),
				PAYPAL_PAYMENTS_VERSION,
				true
			);

			wp_enqueue_style(
				'paypal-brasil-applepay',
				plugins_url( 'assets/dist/css/frontend-applepay.css', PAYPAL_PAYMENTS_MAIN_FILE ),
				array(),
				PAYPAL_PAYMENTS_VERSION,
				'all'
			);

			wp_localize_script(
				'paypal-brasil-applepay',
				'paypal_brasil_applepay_settings',
				array(
					'id'                        => $this->id,
					'mode'                      => $this->mode === 'sandbox' ? 'sandbox' : 'live',
					'currency'                  => get_woocommerce_currency(),
					'country_code'              => 'BR',
					'locale'                    => get_locale(),
					'debug_mode'                => 'yes' === $this->debug,
					'paypal_brasil_handler_url' => add_query_arg(
						array(
							'wc-api' => 'paypal_brasil_handler',
							'action' => '{ACTION}',
						),
						home_url() . '/'
					),
				)
			);
		}
	}

	/**
	 * Get the WooCommerce currency.
	 *
	 * @return string
	 */
	private function get_woocommerce_currency() {
		return get_woocommerce_currency();
	}

	/**
	 * Get the admin options data to render by Vue.
	 *
	 * @return array
	 */
	private function get_fields_values() {
		return array(
			'enabled'                 => $this->enabled,
			'mode'                    => $this->mode,
			'credential_configuration' => $this->credential_configuration,
			'client'                  => array(
				'live'    => $this->client_live,
				'sandbox' => $this->client_sandbox,
			),
			'secret'                  => array(
				'live'    => $this->secret_live,
				'sandbox' => $this->secret_sandbox,
			),
			'title'                   => $this->title,
			'title_complement'        => $this->title_complement,
			'invoice_id_prefix'       => $this->invoice_id_prefix,
			'debug'                   => $this->debug,
		);
	}
}
