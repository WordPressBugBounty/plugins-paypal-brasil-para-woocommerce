<?php
/**
 * Script de Automação para Testes PIX
 * 
 * Este script automatiza parte dos testes do PIX para facilitar a validação.
 * Execute via WP-CLI ou inclua em um plugin de teste.
 * 
 * @package PayPal_Brasil
 * @subpackage Tests
 */

// Prevenir execução direta
if (!defined('ABSPATH')) {
    exit;
}

class PayPal_Brasil_PIX_Test_Automation {
    
    private $results = [];
    private $errors = [];
    
    public function __construct() {
        $this->log('=== INICIANDO TESTES AUTOMATIZADOS PIX ===');
    }
    
    /**
     * Executa todos os testes automatizados
     */
    public function run_all_tests() {
        $this->test_environment_setup();
        $this->test_gateway_registration();
        $this->test_api_methods();
        $this->test_validation_functions();
        $this->test_webhook_endpoints();
        $this->test_assets_compilation();
        
        $this->generate_report();
    }
    
    /**
     * Testa configuração do ambiente
     */
    public function test_environment_setup() {
        $this->log('--- Testando Configuração do Ambiente ---');
        
        // Verificar WordPress
        $wp_version = get_bloginfo('version');
        $this->assert_version_requirement($wp_version, '5.0', 'WordPress');
        
        // Verificar WooCommerce
        if (class_exists('WooCommerce')) {
            $wc_version = WC()->version;
            $this->assert_version_requirement($wc_version, '3.0', 'WooCommerce');
        } else {
            $this->add_error('WooCommerce não está instalado');
        }
        
        // Verificar PHP
        $php_version = PHP_VERSION;
        $this->assert_version_requirement($php_version, '7.4', 'PHP');
        
        // Verificar extensões PHP
        $required_extensions = ['curl', 'json', 'openssl'];
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $this->add_error("Extensão PHP '$ext' não está carregada");
            } else {
                $this->add_success("Extensão PHP '$ext' OK");
            }
        }
        
        // Verificar SSL
        if (is_ssl()) {
            $this->add_success('SSL está ativo');
        } else {
            $this->add_error('SSL não está ativo (necessário para webhooks)');
        }
        
        // Verificar moeda
        $currency = get_woocommerce_currency();
        if ($currency === 'BRL') {
            $this->add_success('Moeda configurada como BRL');
        } else {
            $this->add_error("Moeda configurada como '$currency', deve ser BRL");
        }
    }
    
    /**
     * Testa registro do gateway
     */
    public function test_gateway_registration() {
        $this->log('--- Testando Registro do Gateway ---');
        
        // Verificar se a classe existe
        if (class_exists('PayPal_Brasil_PIX_Gateway')) {
            $this->add_success('Classe PayPal_Brasil_PIX_Gateway existe');
        } else {
            $this->add_error('Classe PayPal_Brasil_PIX_Gateway não encontrada');
            return;
        }
        
        // Verificar se o gateway está registrado
        $gateways = WC()->payment_gateways->payment_gateways();
        if (isset($gateways['paypal-brasil-pix-gateway'])) {
            $this->add_success('Gateway PIX está registrado');
            
            $gateway = $gateways['paypal-brasil-pix-gateway'];
            
            // Verificar propriedades essenciais
            $this->assert_property($gateway, 'id', 'paypal-brasil-pix-gateway');
            $this->assert_property($gateway, 'method_title', 'PayPal Brasil PIX');
            $this->assert_property($gateway, 'supports', ['products']);
            
        } else {
            $this->add_error('Gateway PIX não está registrado');
        }
    }
    
    /**
     * Testa métodos da API
     */
    public function test_api_methods() {
        $this->log('--- Testando Métodos da API ---');
        
        // Verificar se a classe API existe
        if (class_exists('PayPal_Brasil_Orders_API_V2')) {
            $this->add_success('Classe PayPal_Brasil_Orders_API_V2 existe');
            
            $api = new PayPal_Brasil_Orders_API_V2();
            
            // Verificar métodos PIX
            if (method_exists($api, 'create_payment_pix')) {
                $this->add_success('Método create_payment_pix existe');
            } else {
                $this->add_error('Método create_payment_pix não encontrado');
            }
            
            if (method_exists($api, 'get_pix_payment_status')) {
                $this->add_success('Método get_pix_payment_status existe');
            } else {
                $this->add_error('Método get_pix_payment_status não encontrado');
            }
            
            // Verificar BN codes
            $bn_codes = $api->get_bn_codes();
            if (isset($bn_codes['pix']) && $bn_codes['pix'] === 'WooCommerceBrazil_Ecom_PIX') {
                $this->add_success('BN Code PIX configurado corretamente');
            } else {
                $this->add_error('BN Code PIX não configurado');
            }
            
        } else {
            $this->add_error('Classe PayPal_Brasil_Orders_API_V2 não encontrada');
        }
    }
    
    /**
     * Testa funções de validação
     */
    public function test_validation_functions() {
        $this->log('--- Testando Funções de Validação ---');
        
        // Testar validação de CPF
        $valid_cpf = '123.456.789-09';
        $invalid_cpf = '111.111.111-11';
        
        if (class_exists('PayPal_Brasil_PIX_Gateway')) {
            $gateway = new PayPal_Brasil_PIX_Gateway();
            
            if (method_exists($gateway, 'validate_cpf')) {
                if ($gateway->validate_cpf($valid_cpf)) {
                    $this->add_success('Validação de CPF válido OK');
                } else {
                    $this->add_error('Validação de CPF válido falhou');
                }
                
                if (!$gateway->validate_cpf($invalid_cpf)) {
                    $this->add_success('Validação de CPF inválido OK');
                } else {
                    $this->add_error('Validação de CPF inválido falhou');
                }
            } else {
                $this->add_error('Método validate_cpf não encontrado');
            }
            
            // Testar validação de CNPJ
            $valid_cnpj = '11.222.333/0001-81';
            $invalid_cnpj = '11.111.111/1111-11';
            
            if (method_exists($gateway, 'validate_cnpj')) {
                if ($gateway->validate_cnpj($valid_cnpj)) {
                    $this->add_success('Validação de CNPJ válido OK');
                } else {
                    $this->add_error('Validação de CNPJ válido falhou');
                }
                
                if (!$gateway->validate_cnpj($invalid_cnpj)) {
                    $this->add_success('Validação de CNPJ inválido OK');
                } else {
                    $this->add_error('Validação de CNPJ inválido falhou');
                }
            } else {
                $this->add_error('Método validate_cnpj não encontrado');
            }
        }
    }
    
    /**
     * Testa endpoints de webhook
     */
    public function test_webhook_endpoints() {
        $this->log('--- Testando Endpoints de Webhook ---');
        
        // Verificar se o endpoint está registrado
        $webhook_url = home_url('/wc-api/paypal_brasil_pix_webhook');
        
        // Fazer requisição de teste
        $response = wp_remote_get($webhook_url, [
            'timeout' => 10,
            'sslverify' => false
        ]);
        
        if (!is_wp_error($response)) {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200 || $status_code === 405) {
                $this->add_success('Endpoint de webhook está acessível');
            } else {
                $this->add_error("Endpoint de webhook retornou status $status_code");
            }
        } else {
            $this->add_error('Erro ao acessar endpoint de webhook: ' . $response->get_error_message());
        }
    }
    
    /**
     * Testa compilação de assets
     */
    public function test_assets_compilation() {
        $this->log('--- Testando Assets Compilados ---');
        
        $assets_path = PAYPAL_PAYMENTS_MAIN_FILE ? dirname(PAYPAL_PAYMENTS_MAIN_FILE) . '/assets/dist/' : '';
        
        if (empty($assets_path)) {
            $this->add_error('Caminho dos assets não encontrado');
            return;
        }
        
        // Verificar assets frontend
        $frontend_js = $assets_path . 'frontend-pix.js';
        $frontend_css = $assets_path . 'frontend-pix.css';
        
        if (file_exists($frontend_js)) {
            $this->add_success('Asset frontend-pix.js existe');
            $size = filesize($frontend_js);
            $this->log("Tamanho: " . round($size/1024, 2) . "KB");
        } else {
            $this->add_error('Asset frontend-pix.js não encontrado');
        }
        
        if (file_exists($frontend_css)) {
            $this->add_success('Asset frontend-pix.css existe');
            $size = filesize($frontend_css);
            $this->log("Tamanho: " . round($size/1024, 2) . "KB");
        } else {
            $this->add_error('Asset frontend-pix.css não encontrado');
        }
        
        // Verificar assets admin
        $admin_js = $assets_path . 'admin-options-pix.js';
        $admin_css = $assets_path . 'admin-options-pix.css';
        
        if (file_exists($admin_js)) {
            $this->add_success('Asset admin-options-pix.js existe');
        } else {
            $this->add_error('Asset admin-options-pix.js não encontrado');
        }
        
        if (file_exists($admin_css)) {
            $this->add_success('Asset admin-options-pix.css existe');
        } else {
            $this->add_error('Asset admin-options-pix.css não encontrado');
        }
    }
    
    /**
     * Cria um pedido de teste
     */
    public function create_test_order() {
        $this->log('--- Criando Pedido de Teste ---');
        
        // Criar produto de teste
        $product = new WC_Product_Simple();
        $product->set_name('Produto Teste PIX');
        $product->set_regular_price(10.00);
        $product->set_status('publish');
        $product_id = $product->save();
        
        if ($product_id) {
            $this->add_success("Produto de teste criado (ID: $product_id)");
        } else {
            $this->add_error('Falha ao criar produto de teste');
            return false;
        }
        
        // Criar pedido de teste
        $order = wc_create_order();
        $order->add_product($product, 1);
        $order->set_address([
            'first_name' => 'João',
            'last_name' => 'Silva',
            'email' => 'joao@teste.com.br',
            'phone' => '11999999999',
            'address_1' => 'Rua Teste, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postcode' => '01234-567',
            'country' => 'BR'
        ], 'billing');
        
        $order->set_payment_method('paypal-brasil-pix-gateway');
        $order->set_currency('BRL');
        $order->calculate_totals();
        $order_id = $order->save();
        
        if ($order_id) {
            $this->add_success("Pedido de teste criado (ID: $order_id)");
            return $order_id;
        } else {
            $this->add_error('Falha ao criar pedido de teste');
            return false;
        }
    }
    
    /**
     * Gera relatório final
     */
    public function generate_report() {
        $this->log('=== RELATÓRIO FINAL ===');
        
        $total_tests = count($this->results);
        $passed_tests = count(array_filter($this->results, function($result) {
            return $result['status'] === 'success';
        }));
        $failed_tests = $total_tests - $passed_tests;
        
        $this->log("Total de testes: $total_tests");
        $this->log("Testes aprovados: $passed_tests");
        $this->log("Testes falharam: $failed_tests");
        
        if ($failed_tests === 0) {
            $this->log('✅ TODOS OS TESTES PASSARAM - PIX PRONTO PARA PRODUÇÃO');
        } else {
            $this->log('❌ ALGUNS TESTES FALHARAM - REVISAR ANTES DE PRODUÇÃO');
        }
        
        // Listar erros
        if (!empty($this->errors)) {
            $this->log('--- ERROS ENCONTRADOS ---');
            foreach ($this->errors as $error) {
                $this->log("❌ $error");
            }
        }
        
        // Salvar relatório em arquivo
        $this->save_report_to_file();
    }
    
    /**
     * Salva relatório em arquivo
     */
    private function save_report_to_file() {
        $upload_dir = wp_upload_dir();
        $report_file = $upload_dir['basedir'] . '/pix-test-report-' . date('Y-m-d-H-i-s') . '.txt';
        
        $content = "=== RELATÓRIO DE TESTES PIX ===\n";
        $content .= "Data: " . date('d/m/Y H:i:s') . "\n";
        $content .= "WordPress: " . get_bloginfo('version') . "\n";
        $content .= "WooCommerce: " . (class_exists('WooCommerce') ? WC()->version : 'N/A') . "\n";
        $content .= "PHP: " . PHP_VERSION . "\n\n";
        
        foreach ($this->results as $result) {
            $status = $result['status'] === 'success' ? '✅' : '❌';
            $content .= "$status {$result['message']}\n";
        }
        
        if (!empty($this->errors)) {
            $content .= "\n=== ERROS ===\n";
            foreach ($this->errors as $error) {
                $content .= "❌ $error\n";
            }
        }
        
        file_put_contents($report_file, $content);
        $this->log("Relatório salvo em: $report_file");
    }
    
    // Métodos auxiliares
    private function assert_version_requirement($current, $required, $component) {
        if (version_compare($current, $required, '>=')) {
            $this->add_success("$component $current >= $required");
        } else {
            $this->add_error("$component $current < $required (mínimo requerido)");
        }
    }
    
    private function assert_property($object, $property, $expected) {
        if (isset($object->$property) && $object->$property === $expected) {
            $this->add_success("Propriedade '$property' = '$expected'");
        } else {
            $actual = isset($object->$property) ? $object->$property : 'undefined';
            $this->add_error("Propriedade '$property' = '$actual', esperado '$expected'");
        }
    }
    
    private function add_success($message) {
        $this->results[] = ['status' => 'success', 'message' => $message];
        $this->log("✅ $message");
    }
    
    private function add_error($message) {
        $this->results[] = ['status' => 'error', 'message' => $message];
        $this->errors[] = $message;
        $this->log("❌ $message");
    }
    
    private function log($message) {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::log($message);
        } else {
            error_log("[PIX Test] $message");
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo $message . "\n";
            }
        }
    }
}

// Executar testes se chamado via WP-CLI
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('pix test', function() {
        $tester = new PayPal_Brasil_PIX_Test_Automation();
        $tester->run_all_tests();
    });
}

// Função para executar testes manualmente
function run_pix_tests() {
    $tester = new PayPal_Brasil_PIX_Test_Automation();
    $tester->run_all_tests();
}