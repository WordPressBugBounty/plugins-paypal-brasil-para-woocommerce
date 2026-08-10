<?php

// Ignore if access directly.
if (!defined('ABSPATH')) {
	exit;
}

use Automattic\WooCommerce\Utilities\OrderUtil;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Class PayPal_Brasil_PIX_Gateway.
 *
 * @property string client_live
 * @property string client_sandbox
 * @property string secret_live
 * @property string secret_sandbox
 * @property string debug
 * @property string invoice_id_prefix
 * @property string title_complement
 * @property string qr_expiry
 */
class PayPal_Brasil_PIX_Gateway extends PayPal_Brasil_Gateway
{

	private static $instance;

	public string $title_complement;
	public $credential_configuration;
	public string $invoice_id_prefix;
	public string $qr_expiry;


	/**
	 * PayPal_Brasil_PIX_Gateway constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		// Store some default gateway settings.
		$this->id = 'paypal-brasil-pix-gateway';
		$this->has_fields = true;
		$this->method_title = __('PayPal Brasil PIX', "paypal-brasil-para-woocommerce");
		$this->icon = plugins_url('assets/images/pix_br_logo.png', PAYPAL_PAYMENTS_MAIN_FILE);
		$this->method_description = __(
			'Aceite pagamentos instantâneos via PIX através do PayPal.',
			"paypal-brasil-para-woocommerce"
		);
		$this->supports = array(
			'products',
			'refunds',
		);

		// Load settings fields.
		$this->init_form_fields();
		$this->init_settings();

		// Get available options.
		$this->enabled = $this->get_option('enabled');
		$this->title = __('PIX', "paypal-brasil-para-woocommerce");
		$this->title_complement = $this->get_option('title_complement');
		$this->mode = $this->get_option('mode');
		$this->credential_configuration = $this->get_option('credential_configuration', 'none');
		$this->client_live = $this->get_option('client_live');
		$this->client_sandbox = $this->get_option('client_sandbox');
		$this->secret_live = $this->get_option('secret_live');
		$this->secret_sandbox = $this->get_option('secret_sandbox');
		$this->qr_expiry = $this->get_option('qr_expiry', '30M');

		$this->invoice_id_prefix = $this->get_option('invoice_id_prefix');
		$this->debug = $this->get_option('debug');

		// Instance the API.
		$this->api = new PayPal_Brasil_Orders_api_V2($this->get_client_id(), $this->get_secret(), $this->mode, $this);

		// Save settings.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		), 10);

		// Save custom settings.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'before_process_admin_options'
		), 20);

		// Stop here if is not the first load.
		if (!$this->is_first_load()) {
			return;
		}

		// Handler for IPN.
		add_action('woocommerce_api_' . $this->id, array($this, 'webhook_handler'));
		// Add hook to display QR code on thank you page
		$this->add_thankyou_hook();

		// Enqueue scripts.
		add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
		add_action('wp_enqueue_scripts', array($this, 'checkout_scripts'), 0);

		// Hooks AJAX para verificação de status
		add_action('wp_ajax_check_pix_status', array($this, 'ajax_check_pix_status'));
		add_action('wp_ajax_nopriv_check_pix_status', array($this, 'ajax_check_pix_status'));
		$this->init_ajax_request();

		// If it's first load, add a instance of this.
		self::$instance = $this;


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

			return $pagenow === 'post.php' ? __(
				'PayPal - PIX',
				"paypal-brasil-para-woocommerce"
			) : __('PIX', "paypal-brasil-para-woocommerce");
		}

		// Title for frontend.
		$title = __('PIX', "paypal-brasil-para-woocommerce");
		if (!empty($this->title_complement)) {
			$title .= ' (' . $this->title_complement . ')';
		}

		return apply_filters('woocommerce_gateway_title', $title, $this->id);
	}

	/**
	 * Define gateway form fields.
	 */
	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Ativar/Desativar', "paypal-brasil-para-woocommerce"),
				'type' => 'checkbox',
				'label' => __('Ativar PIX', "paypal-brasil-para-woocommerce"),
				'default' => 'no',
			),
			'title_complement' => array(
				'title' => __('Nome de exibição (complemento)', "paypal-brasil-para-woocommerce"),
				'type' => 'text',
				'description' => __('Texto adicional que aparecerá no título do método de pagamento.', "paypal-brasil-para-woocommerce"),
			),
			'mode' => array(
				'title' => __('Modo', "paypal-brasil-para-woocommerce"),
				'type' => 'select',
				'options' => array(
					'live' => __('Produção', "paypal-brasil-para-woocommerce"),
					'sandbox' => __('Sandbox', "paypal-brasil-para-woocommerce"),
				),
			),
			'credential_configuration' => array(
                'title' => __('Credential Configuration', "paypal-brasil-para-woocommerce"),
                'type' => 'select',
                'options' => array(
                    'none' => __('Do not use credentials', "paypal-brasil-para-woocommerce"),
                    'use_bcdc' => __('Use BCDC Credentials', "paypal-brasil-para-woocommerce"),
                    'use_spb' => __('Use SPB Credentials', "paypal-brasil-para-woocommerce"),
                    'new_credentials' => __('Configure New Credentials', "paypal-brasil-para-woocommerce"),
                ),
                'description' => __('Choose how you want to configure your Invoice credentials. You can use existing credentials from other gateways or configure new ones.', "paypal-brasil-para-woocommerce"),
                'default' => 'none',
            ),
			'client_live' => array(
				'title' => __('Client ID (produção)', "paypal-brasil-para-woocommerce"),
				'type' => 'text',
				
			),
			'client_sandbox' => array(
				'title' => __('Client ID (sandbox)', "paypal-brasil-para-woocommerce"),
				'type' => 'text',
				'default' => ''
			),
			'secret_live' => array(
				'title' => __('Secret (produção)', "paypal-brasil-para-woocommerce"),
				'type' => 'text',
			),
			'secret_sandbox' => array(
				'title' => __('Secret (sandbox)', "paypal-brasil-para-woocommerce"),
				'type' => 'text',
				'default' => ''
			),
			'qr_expiry' => array(
				'title'       => __('Tempo de expiração do QR Code', 'paypal-brasil-para-woocommerce'),
				'type'        => 'select',
				'description' => __('Tempo máximo que o cliente terá para escanear o QR Code e concluir o pagamento.', 'paypal-brasil-para-woocommerce'),
				'default'     => '5M',
				'options'     => array(
					'5M' => __('15 minutos', 'paypal-brasil-para-woocommerce'),
					'10M' => __('30 minutos', 'paypal-brasil-para-woocommerce'),
					'20M' => __('30 minutos', 'paypal-brasil-para-woocommerce'),
					'30M' => __('30 minutos', 'paypal-brasil-para-woocommerce'),
					'1H'  => __('1 hora',     'paypal-brasil-para-woocommerce'),
					'5H'  => __('4 horas',    'paypal-brasil-para-woocommerce'),
				),
			),
			'debug' => array(
				'title' => __('Log de depuração', "paypal-brasil-para-woocommerce"),
				'type' => 'checkbox',
				'label' => __('Ativar logs', "paypal-brasil-para-woocommerce"),
				'default' => 'no',
				'description' => __('Ativar para registrar eventos do PIX para depuração.', "paypal-brasil-para-woocommerce"),
			),
			'invoice_id_prefix' => array(
				'title' => __('Prefixo no número do pedido', "paypal-brasil-para-woocommerce"),
				'type' => 'text',
				'default' => '',
				'description' => __(
					'Adicione um prefixo ao número do pedido, útil para identificação quando você tem mais de uma loja processando pelo PayPal.',
					"paypal-brasil-para-woocommerce"
				),
			),
		);
	}

	function update_credentials()
    {
        $mode = $this->get_field_value('mode', $this->form_fields['mode']);
        $credential_config = $this->get_field_value('credential_configuration', $this->form_fields['credential_configuration']);

        $client = '';
        $secret = '';

        switch ($credential_config) {
            case 'use_bcdc':
                $credentials = $this->get_bcdc_credentials($mode);
                $client = $credentials['client'];
                $secret = $credentials['secret'];
                break;

            case 'use_spb':
                $credentials = $this->get_spb_credentials($mode);
                $client = $credentials['client'];
                $secret = $credentials['secret'];
                break;

            case 'new_credentials':
                $client_type = $mode === 'sandbox' ? 'client_sandbox' : 'client_live';
                $secret_type = $mode === 'sandbox' ? 'secret_sandbox' : 'secret_live';
                $client = $this->get_field_value($client_type, $this->form_fields[$client_type]);
                $secret = $this->get_field_value($secret_type, $this->form_fields[$secret_type]);
                break;

            case 'none':
            default:
                // Don't use credentials
                return;
        }

		    // Salva as credenciais nas opções do gateway atual
			if (!empty($client) && !empty($secret)) {
				$client_key = $mode === 'sandbox' ? 'client_sandbox' : 'client_live';
				$secret_key = $mode === 'sandbox' ? 'secret_sandbox' : 'secret_live';
				
				$this->update_option($client_key, $client);
				$this->update_option($secret_key, $secret);
				
				// Atualizar propriedades da classe
				if ($mode === 'sandbox') {
					$this->client_sandbox = $client;
					$this->secret_sandbox = $secret;
				} else {
					$this->client_live = $client;
					$this->secret_live = $secret;
				}
			}

        $this->api->update_credentials($client, $secret, $mode);
    }


    /**
     * Get BCDC gateway credentials
     */
    function get_bcdc_credentials($mode)
    {
        $bcdc_settings = get_option('woocommerce_paypal-brasil-bcdc-gateway_settings');

        if (!$bcdc_settings) {
            return array('client' => '', 'secret' => '');
        }

        $client_key = $mode === 'sandbox' ? 'client_sandbox' : 'client_live';
        $secret_key = $mode === 'sandbox' ? 'secret_sandbox' : 'secret_live';

        return array(
            'client' => isset($bcdc_settings[$client_key]) ? $bcdc_settings[$client_key] : '',
            'secret' => isset($bcdc_settings[$secret_key]) ? $bcdc_settings[$secret_key] : ''
        );
    }

    /**
     * Get SPB gateway credentials
     */
    function get_spb_credentials($mode)
    {
        $spb_settings = get_option('woocommerce_paypal-brasil-spb-gateway_settings');

        if (!$spb_settings) {
            return array('client' => '', 'secret' => '');
        }

        $client_key = $mode === 'sandbox' ? 'client_sandbox' : 'client_live';
        $secret_key = $mode === 'sandbox' ? 'secret_sandbox' : 'secret_live';

        return array(
            'client' => isset($spb_settings[$client_key]) ? $spb_settings[$client_key] : '',
            'secret' => isset($spb_settings[$secret_key]) ? $spb_settings[$secret_key] : ''
        );
    }

    /**
     * Override get_client_id to use configured credentials
     */
    function get_client_id()
    {
        $credential_config = $this->get_option('credential_configuration');

        switch ($credential_config) {
            case 'use_bcdc':
                $credentials = $this->get_bcdc_credentials($this->mode);
                return $credentials['client'];

            case 'use_spb':
                $credentials = $this->get_spb_credentials($this->mode);
                return $credentials['client'];

            case 'new_credentials':
                return parent::get_client_id();

            case 'none':
            default:
                return '';
        }
    }

    /**
     * Override get_secret to use configured credentials
     */
    function get_secret()
    {
        $credential_config = $this->get_option('credential_configuration');

        switch ($credential_config) {
            case 'use_bcdc':
                $credentials = $this->get_bcdc_credentials($this->mode);
                return $credentials['secret'];

            case 'use_spb':
                $credentials = $this->get_spb_credentials($this->mode);
                return $credentials['secret'];

            case 'new_credentials':
                return parent::get_secret();

            case 'none':
            default:
                return '';
        }
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
	public function create_webhooks()
	{
		$webhook = null;
		$webhook_url = defined('PAYPAL_BRASIL_WEBHOOK_URL') ? PAYPAL_BRASIL_WEBHOOK_URL : $this->get_webhook_url();

		try {
			$registered_webhooks = $this->api->get_webhooks();

			foreach ($registered_webhooks['webhooks'] as $registered_webhook) {
				if ($registered_webhook['url'] === $webhook_url) {
					$webhook = $registered_webhook;
					break;
				}
			}

			if (!$webhook) {
				$events_types = array(
					'PAYMENT.CAPTURE.COMPLETED',
					'PAYMENT.CAPTURE.DECLINED', 
					'PAYMENT.CAPTURE.PENDING',
					'PAYMENT.CAPTURE.REFUNDED',
					'PAYMENT.CAPTURE.REVERSED',
				);

				$webhook_result = $this->api->create_webhook($webhook_url, $events_types);
				update_option('paypal_brasil_webhook_url-' . $this->id, $webhook_result['id']);
				return;
			}

			update_option('paypal_brasil_webhook_url-' . $this->id, $webhook['id']);
		} catch (Exception $ex) {
			update_option('paypal_brasil_webhook_url-' . $this->id, null);
		}
	}

	public function is_credentials_validated()
	{
		return get_option($this->get_option_key() . '_validator') === 'yes';
	}

	/**
	 * Get if gateway is available.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		$is_available = ('yes' === $this->enabled);

		// Verificar se é moeda brasileira
		if (get_woocommerce_currency() !== 'BRL') {
			$is_available = false;
		}

		if (WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total()) {
			$is_available = false;
		}

		if (!$this->is_credentials_validated()) {
			$is_available = false;
		}

		return $is_available;
	}

	/**
	 * Validate PIX requirements
	 */
	private function validate_pix_requirements()
	{
		// Verificar se é BRL
		if (get_woocommerce_currency() !== 'BRL') {
			throw new Exception(__('PIX só funciona com moeda brasileira (BRL).', 'paypal-brasil-para-woocommerce'));
		}
		
		// Verificar credenciais
		if (empty($this->get_client_id()) || empty($this->get_secret())) {
			throw new Exception(__('Credenciais PIX não configuradas.', 'paypal-brasil-para-woocommerce'));
		}
	}

	/**
	 * Handle API exceptions for PIX
	 */
	private function handle_api_exception($exception)
	{
		$data = $exception->getData();
		
		//TODO - Revisar possíveis erros retornados pela API
		switch ($data['name']) {
			case 'VALIDATION_ERROR':
				wc_add_notice(
					__('Dados inválidos para pagamento PIX. Verifique as informações.', 'paypal-brasil-para-woocommerce'),
					'error'
				);
				break;
				
			case 'INTERNAL_SERVICE_ERROR':
				wc_add_notice(
					__('Erro temporário. Tente novamente em alguns minutos.', 'paypal-brasil-para-woocommerce'),
					'error'
				);
				break;
				
			case 'AUTHORIZATION_ERROR':
				wc_add_notice(
					__('Erro de autorização. Verifique suas credenciais PIX.', 'paypal-brasil-para-woocommerce'),
					'error'
				);
				break;
				
			default:
				wc_add_notice(
					__('Não foi possível processar o pagamento PIX.', 'paypal-brasil-para-woocommerce'),
					'error'
				);
				break;
		}
		
		$this->log('Erro PIX: ' . json_encode($data));
	}

	/**
	 * Process gateway payment for a given order ID.
	 *
	 * @param $order_id
	 *
	 * @return array
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		try {
			// Validar requisitos PIX
			$this->validate_pix_requirements();

			// Processar pagamento PIX
			$result = $this->process_payment_pix($order);
			
			return $result;

		} catch (PayPal_Brasil_API_Exception $ex) {
			$this->handle_api_exception($ex);
			WC()->session->set('refresh_totals', true);
		} catch (Exception $ex) {
			wc_add_notice($ex->getMessage(), 'error');
			WC()->session->set('refresh_totals', true);
		}
	}

	/**
	 * Process PIX payment
	 */
	private function process_payment_pix($order)
	{
		// Verificar se tem CPF/CNPJ
		$cpf_cnpj = $this->get_customer_cpf_cnpj($order);
		if (!$cpf_cnpj) {
			throw new Exception(__('CPF ou CNPJ é obrigatório para pagamentos PIX.', 'paypal-brasil-para-woocommerce'));
		}

		$order_total      = wc_add_number_precision($order->get_total());
		$qr_expiry_secs   = $this->qr_expiry_to_seconds($this->qr_expiry);
		$expiry_timestamp = time() + $qr_expiry_secs;

		// Dados do pagamento PIX
		$data = array(
			'intent' => 'CAPTURE',
			'purchase_units' => array(
				array(
					'reference_id' => 'default',
					'amount' => array(
						'currency_code' => $order->get_currency(),
						'value' => paypal_format_amount(wc_remove_number_precision($order_total)),
					),
					'description' => sprintf(
						__('Pagamento PIX - Pedido #%s', 'paypal-brasil-para-woocommerce'),
						$order->get_id()
					),
					'invoice_id' => sprintf('%s%s', $this->invoice_id_prefix, $order->get_id()),
					'custom_id' => $order->get_id(),
					'soft_descriptor' => 'Internal Pix tests',
				),
			),
			'payment_source' => array(
				'pix' => array(
					'name'          => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					'country_code'  => $order->get_billing_country() ?: $order->get_shipping_country(),
					'currency_code' => $this->get_woocommerce_currency(),
					'phone'         => array(
						'national_number' => $this->format_phone_for_paypal(
							$order->get_meta('_billing_cellphone', true, 'view') ?: $order->get_billing_phone()
						),
					),
					'email'         => $order->get_billing_email(),
					'tax_info'      => array(
						'tax_id'      => $cpf_cnpj,
						'tax_id_type' => strlen($cpf_cnpj) === 11 ? 'BR_CPF' : 'BR_CNPJ',
					),
					'qr_details'    => array(
						'qr_expiry' => $qr_expiry_secs,
					),
					'experience_context' => array(
						'shipping_preference' => 'GET_FROM_FILE',
						'return_url'          => $this->get_return_url($order),
						'cancel_url'          => wc_get_checkout_url(),
					),
				),
			),
			'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL',
			'application_context' => array(
				'brand_name' => get_bloginfo('name'),
				'locale' => 'pt-BR',
			),
		);

		try {

			// Criar pagamento PIX na API
			$response = $this->api->create_payment_pix($data,['PayPal-Request-Id' => wp_generate_uuid4()]);

			// Extrair dados do PIX
			$pix_data = $this->extract_pix_data($response);

			// Salvar dados PIX no pedido
			$this->save_pix_data_to_order($order, $response, $pix_data, $expiry_timestamp);

			// Atualizar status do pedido
			$order->update_status(
				'on-hold',
				__('Aguardando pagamento PIX.', 'paypal-brasil-para-woocommerce')
			);

			// Reduzir estoque
			wc_reduce_stock_levels($order->get_id());

			//TODO - Refatorar Get order id
			if (OrderUtil::custom_orders_table_usage_is_enabled()) {
				$order->update_meta_data('wc_pix_brasil_sale_id', $response['id']);
				$order->update_meta_data('wc_pix_brasil_sandbox', $this->mode);
				$order->update_meta_data('wc_pix_brasil_pix_code', $response['payment_source']['pix']['qr_payload']);
			} else {
				update_post_meta($order->get_id(), 'wc_pix_brasil_sale_id', $response['id']);
				update_post_meta($order->get_id(), 'wc_pix_brasil_sandbox', $this->mode);
				update_post_meta($order->get_id(), 'wc_pix_brasil_pix_code', $response['payment_source']['pix']['qr_payload']);
			}

			$order->set_payment_method_title(__('Pix', "paypal-brasil-para-woocommerce"));

			// Add note for installments.
			$installment_note = sprintf(__('Pix payment on PayPal.', "paypal-brasil-para-woocommerce"));

			$order->add_order_note($installment_note);

			$order->save();

			return array(
				'result' => 'success',
				'redirect' => add_query_arg(
					array(
						'pix-payment' => '1',
						'order-id' => $order->get_id(),
						'pix-order-id' => $response['id'],
					),
					$this->get_return_url($order)
				),
			);

		} catch (PayPal_Brasil_API_Exception $ex) {
			$this->handle_api_exception($ex);
			throw $ex;
		}
	}

	/**
	 * Get customer CPF/CNPJ from order
	 */
	private function get_customer_cpf_cnpj($order)
	{
		// Tentar pegar CPF primeiro
		$cpf = $order->get_meta('_billing_cpf');
		if (!empty($cpf) && $this->is_cpf($cpf)) {
			return preg_replace('/[^0-9]/', '', $cpf);
		}

		// Tentar pegar CNPJ
		$cnpj = $order->get_meta('_billing_cnpj');
		if (!empty($cnpj) && $this->is_cnpj($cnpj)) {
			return preg_replace('/[^0-9]/', '', $cnpj);
		}

		// Verificar se tem persontype para decidir qual campo usar
		$persontype = $order->get_meta('_billing_persontype');
		if ($persontype === '1') {
			// Pessoa física - usar CPF
			return $cpf ? preg_replace('/[^0-9]/', '', $cpf) : null;
		} elseif ($persontype === '2') {
			// Pessoa jurídica - usar CNPJ
			return $cnpj ? preg_replace('/[^0-9]/', '', $cnpj) : null;
		}

		return null;
	}

	/**
	 * Extract PIX data from PayPal response
	 */
	private function extract_pix_data($response)
	{
		$pix_data = array();

		// Procurar pelos dados PIX na resposta
		if (isset($response['payment_source']['pix'])) {
			$pix_source = $response['payment_source']['pix']['qr_details'];

			$pix_data['qr_payload'] = isset($pix_source['qr_payload']) ? $pix_source['qr_payload'] : '';
			$pix_data['qr_image'] = isset($pix_source['qr_image']) ? $pix_source['qr_image'] : '';
			$pix_data['qr_expiry'] = isset($pix_source['qr_expiry']) ? $pix_source['qr_expiry'] : '';
		}

		if (empty($pix_data['qr_payload']) && isset($response['links'])) {
			foreach ($response['links'] as $link) {
				if ($link['rel'] === 'payer-action' && strpos($link['href'], 'pix') !== false) {
					// Extrair dados do link se necessário
					$pix_data['payer_action_url'] = $link['href'];
				}
			}
		}

		return $pix_data;
	}

	/**
	 * Save PIX data to order
	 */
	private function save_pix_data_to_order($order, $response, $pix_data, $expiry_timestamp)
	{
		$order_id = $order->get_id();

		// Salvar dados principais
		if (OrderUtil::custom_orders_table_usage_is_enabled()) {
			$order->update_meta_data('paypal_brasil_pix_order_id', $response['id']);
			$order->update_meta_data('paypal_brasil_pix_status', $response['status']);
			$order->update_meta_data('paypal_brasil_pix_data', $response);
			$order->update_meta_data('paypal_brasil_pix_qr_code', $pix_data['qr_code']);
			$order->update_meta_data('paypal_brasil_pix_code', $pix_data['pix_code']);
			$order->update_meta_data('paypal_brasil_pix_expiry', $expiry_timestamp);
		} else {
			update_post_meta($order_id, 'paypal_brasil_pix_order_id', $response['id']);
			update_post_meta($order_id, 'paypal_brasil_pix_status', $response['status']);
			update_post_meta($order_id, 'paypal_brasil_pix_data', $response);
			update_post_meta($order_id, 'paypal_brasil_pix_qr_code', $pix_data['qr_code']);
			update_post_meta($order_id, 'paypal_brasil_pix_code', $pix_data['pix_code']);
			update_post_meta($order_id, 'paypal_brasil_pix_expiry', $expiry_timestamp);
		}

		$order->add_order_note(
			sprintf(
				__('Paypal - Pagamento PIX criado. ID: %s. Expira em: %s', 'paypal-brasil-para-woocommerce'),
				$response['id'],
				date('d/m/Y H:i:s', $expiry_timestamp)
			)
		);

		$order->save();
	}

	/**
	 * Frontend Payment Fields.
	 */
	public function payment_fields()
	{
		include dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/includes/views/checkout/pix-checkout-fields.php';
	}

	/**
	 * Backend view for admin options.
	 */
	public function admin_options()
	{
		include dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/includes/views/admin-options/admin-options-pix/admin-options-pix.php';
	}

	/**
	 * Get the admin options template to render by Vue.
	 */
	private function get_admin_options_template()
	{
		ob_start();
		include dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/includes/views/admin-options/admin-options-pix/admin-options-pix-template.php';
		return ob_get_clean();
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
	 * Format a raw phone number for the PayPal E.164 national_number field.
	 *
	 * PayPal requires that the combined length of the country calling code (CC)
	 * and the national number does not exceed 15 digits (ITU-T E.164).
	 *
	 * Brazil CC = 55 (2 digits), so the national part can have at most 13 digits.
	 * Brazilian numbers: CC(2) + NDC/area(2) + subscriber(8-9) = 12-13 digits total.
	 *
	 * Steps:
	 *   1. Strip every non-digit character.
	 *   2. If the caller already included the Brazil CC (55) prefix, remove it so
	 *      we don't double-prefix.
	 *   3. Prepend the CC "55".
	 *   4. Trim to 15 digits max to satisfy the E.164 constraint.
	 *
	 * @param  string $raw_phone  Phone number as stored in WooCommerce (any format).
	 * @return string             Digits-only string, CC-prefixed, max 15 chars.
	 */
	private function format_phone_for_paypal( $raw_phone ) {
		// 1. Keep digits only.
		$digits = preg_replace( '/\D/', '', (string) $raw_phone );

		// 2. Strip a leading "55" country code when the number already contains it.
		//    A Brazilian mobile with CC is 13 digits (55 + 2 NDC + 9 SN).
		//    A Brazilian landline with CC is 12 digits (55 + 2 NDC + 8 SN).
		if ( in_array( strlen( $digits ), array( 12, 13 ), true ) && substr( $digits, 0, 2 ) === '55' ) {
			$digits = substr( $digits, 2 );
		}

		// 3. Prepend Brazil country code.
		$e164 = '55' . $digits;

		// 4. Enforce E.164 maximum of 15 digits.
		return substr( $e164, 0, 15 );
	}

	/**
	 * Convert a qr_expiry option value (e.g. "30M", "1H") to seconds.
	 *
	 * @param string $value
	 * @return int
	 */
	private function qr_expiry_to_seconds($value)
	{
		$map = array(
			'15M' => 900,
			'30M' => 1800,
			'1H'  => 3600,
			'2H'  => 7200,
			'4H'  => 14400,
			'8H'  => 28800,
			'12H' => 43200,
			'24H' => 86400,
		);

		return isset($map[$value]) ? $map[$value] : 1800;
	}

	/**
	 * Get admin options data for JavaScript
	 */
	private function get_admin_options_data()
	{
		//TODO - REvisitar função.
		return array(
			'enabled' => $this->enabled,
			'title_complement' => $this->title_complement,
			'mode' => $this->mode,
			'client_live' => $this->client_live,
			'client_sandbox' => $this->client_sandbox,
			'secret_live' => $this->secret_live,
			'secret_sandbox' => $this->secret_sandbox,
			'qr_expiry' => $this->qr_expiry,
			'invoice_id_prefix' => $this->invoice_id_prefix,
			'debug' => $this->debug,
			'currency' => get_woocommerce_currency(),
			'is_credentials_validated' => $this->is_credentials_validated(),
			'cpf_plugin_active' => class_exists('Extra_Checkout_Fields_For_Brazil'),
		);
	}

	/**
	 * Enqueue admin scripts for gateway settings page.
	 */
	public function admin_scripts()
	{
		$screen = get_current_screen();
		$screen_id = $screen ? $screen->id : '';
		$wc_screen_id = sanitize_title(__('WooCommerce', "paypal-brasil-para-woocommerce"));
		$wc_settings_id = $wc_screen_id . '_page_wc-settings';

		// Check if we are on the gateway settings page.
		if ($wc_settings_id === $screen_id && isset($_GET['section']) && $_GET['section'] === $this->id) {
			
			// Add shared file if exists.
			if (file_exists(dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/assets/dist/js/shared.js')) {
				wp_enqueue_script(
					'paypal_brasil_admin_options_shared',
					plugins_url('assets/dist/js/shared.js', PAYPAL_PAYMENTS_MAIN_FILE),
					array(),
					PAYPAL_PAYMENTS_VERSION,
					true
				);
			}

			wp_enqueue_style(
				$this->id . '_style',
				plugins_url('assets/dist/css/admin-options-pix.css', PAYPAL_PAYMENTS_MAIN_FILE),
				array(),
				PAYPAL_PAYMENTS_VERSION,
				'all'
			);

			// Enqueue admin options and localize settings.
			wp_enqueue_script(
				$this->id . '_script',
				plugins_url('assets/dist/js/admin-options-pix.js', PAYPAL_PAYMENTS_MAIN_FILE),
				array(),
				PAYPAL_PAYMENTS_VERSION,
				true
			);

			wp_localize_script(
				$this->id . '_script',
				'paypal_brasil_admin_options_pix',
				array(
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
					'qr_expiry' => $this->qr_expiry,
					'title_complement' => $this->title_complement,
					'invoice_id_prefix' => $this->invoice_id_prefix,
					'debug' => $this->debug,
					'images_path' => plugins_url('assets/images/buttons_bcdc', PAYPAL_PAYMENTS_MAIN_FILE)
				)
			);

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

		if (is_checkout() || is_checkout_pay_page()) {
			wp_enqueue_script(
				'paypal-brasil-pix',
				plugins_url('assets/dist/js/frontend-pix.js', PAYPAL_PAYMENTS_MAIN_FILE),
				array('jquery'),
				PAYPAL_PAYMENTS_VERSION,
				true
			);

			wp_enqueue_style(
				'paypal-brasil-pix',
				plugins_url('assets/dist/css/frontend-pix.css', PAYPAL_PAYMENTS_MAIN_FILE),
				array(),
				PAYPAL_PAYMENTS_VERSION,
				'all'
			);
		}
	}

	/**
	 * Process refund
	 */
	public function process_refund($order_id, $amount = null, $reason = '')
	{
		//TODO - Implementar refund.
		return new WP_Error('error', __('Reembolso PIX em desenvolvimento.', 'paypal-brasil-para-woocommerce'));
	}

	private function get_fields_values()
	{
		return array(
			'enabled' => $this->enabled,
			'mode' => $this->mode,
			'credential_configuration' => $this->credential_configuration,
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
			'qr_expiry' => $this->qr_expiry,
			'debug' => $this->debug,
		);
	}

	/**
	 * Gera um QR Code a partir de uma string PIX e retorna como um Data URI.
	 *
	 * @param string $pixString A string completa do PIX "Copia e Cola" (começando com "000201...").
	 * @return string O Data URI da imagem PNG do QR Code.
	 */
	function gerar_qr_code_pix_data_uri($pixString)
	{
		try {
			$result = Builder::create()
				->writer(new PngWriter())
				->writerOptions([])
				->data($pixString) // A string do PIX Copia e Cola vai aqui
				->encoding(new Encoding('UTF-8'))
				->errorCorrectionLevel(ErrorCorrectionLevel::High)
				->size(300) // Tamanho da imagem em pixels
				->margin(10)
				->roundBlockSizeMode(RoundBlockSizeMode::Margin)
				->build();

			return $result->getDataUri();

		} catch (Exception $e) {
			return __('Erro ao gerar QR code.', 'paypal-brasil-para-woocommerce');
		}
	}

	/**
	 * Display PIX QR code on the order received (thank you) page.
	 *
	 * @param int $order_id
	 */
	public function display_pix_qr_code_on_thankyou($order_id)
	{
		include dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/includes/views/checkout/pix-success-page.php';
	}

	/**
	 * Add hooks for displaying QR code on thank you page
	 */
	public function add_thankyou_hook()
	{
		add_action('woocommerce_before_thankyou', array($this, 'display_pix_qr_code_on_thankyou'));
	}


	/**
	 * Init ajax request
	 *
	 * @return void
	 */
	public function init_ajax_request() {
		add_action( 'wp_ajax_paypal_check_pix_payment_status', array( $this, 'ajax_paypal_check_pix_payment_status' ) );
		add_action( 'wp_ajax_nopriv_paypal_check_pix_payment_status', array( $this, 'ajax_paypal_check_pix_payment_status' ) );
	}

	/**
	 *  AJAX handler for PIX status checking
	 *
	 * @return void
	 */
	public function ajax_paypal_check_pix_payment_status() {
		try {
			if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'paypal_check_pix_payment_status' ) ) {
				wp_send_json_error( array( 'error' => 'Request not authorized' ), 403 );
			}
			if ( empty( $_REQUEST['order_id'] ) ) {
				wp_send_json_error( array( 'error' => 'Missing order ID' ), 400 );
			}

			$order_id = absint( $_REQUEST['order_id'] );
			$order = wc_get_order($order_id);
			if ( ! $order ) {
				wp_send_json_error( array( 'error' => 'Invalid order ID' ), 400 );
			}
	
			$paypal_order_id = $order->get_meta('paypal_brasil_pix_order_id');
			$response = $this->api->get_payment($paypal_order_id,array(),'pix');
			$paypal_order_status = isset( $response['status'] ) ? $response['status'] : '';
	
			//FIXME - Revisar status que podem ser retornados.
			if(isset($response['purchase_units'][0]['payments']['captures']['0']['status'])){
				$capture_status = $response['purchase_units'][0]['payments']['captures'][0]['status'];
				if ($capture_status == 'COMPLETED') {
					do_action('wc_bcdc_brasil_process_payment_success', $order_id);
					if ($order && $order->get_status() === 'on-hold') {
						$capture_id = $response['purchase_units'][0]['payments']['captures'][0]['id'];
						$order->payment_complete($capture_id);
						$order->add_order_note(
							sprintf(
								__('Payment processed by PayPal. Transaction ID: <a href="%s" target="_blank" rel="noopener">%s</a>.', "paypal-brasil-para-woocommerce"),
								$this->mode === 'sandbox' ? "https://www.sandbox.paypal.com/activity/payment/{$capture_id}" : "https://www.paypal.com/activity/payment/{$capture_id}",
								$capture_id
							)
						);
					}

					wp_send_json_success(array('status' => 'COMPLETED'), 200);
				}
		
				if($capture_status == 'EXPIRED'){
					$order->add_order_note(__("Pagamento PIX expirado."));
					$order->update_status('wc-failed');
					$order->save();
					wp_send_json_error(array('status' => 'EXPIRED'), 200);
				}
		
				if($capture_status == 'WAITING'){
					wp_send_json_error(array('status' => 'WAITING'), 200);
				}
			}

			// PIX can stay pending before capture is created.
			if ( in_array( $paypal_order_status, array( 'PAYER_ACTION_REQUIRED', 'CREATED', 'APPROVED', 'SAVED' ), true ) ) {
				wp_send_json_error( array( 'status' => 'WAITING', 'paypal_status' => $paypal_order_status ), 200 );
			}

			if ( in_array( $paypal_order_status, array( 'VOIDED', 'CANCELLED' ), true ) ) {
				wp_send_json_error( array( 'status' => 'EXPIRED', 'paypal_status' => $paypal_order_status ), 200 );
			}

			wp_send_json_error( array( 'status' => 'UNKNOWN', 'error' => 'Unable to read capture status' ), 200 );
		} catch (\Throwable $th) {
			//TODO - INCLUDE LOGS
			wp_send_json_error(array('status' => 'Internal error'), 500);
		}

		
	}




}