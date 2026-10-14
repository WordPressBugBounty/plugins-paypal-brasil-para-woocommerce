<?php

if (!defined('ABSPATH')) {
    exit;
}

class PayPal_Brasil_API_PPP_Activate extends PayPal_Brasil_API_Handler
{

    public function __construct()
    {
        add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
    }

    public function add_handlers($handlers)
    {
        $handlers['ppp_activation_update'] = array(
            'callback' => array($this, 'handle'),
            'method' => 'POST',
            'requires_auth' => true,
        );

        return $handlers;
    }

    /**
     * Handle the request.
     */
    public function handle()
    {
        try {
            if (paypal_brasil_is_pplus_retired()) {
                $this->send_error_response('Error: PayPal Plus is hidden. Use pplus_deactivation_update with active true to restore it.');
                return;
            }

            $json_data = file_get_contents('php://input');
            $post_data = json_decode($json_data, true);
            $activeGateway = sanitize_text_field($post_data['active']);

            if ($activeGateway) {

                update_option('active_payment_ppp', $activeGateway);

                $this->send_success_response(__('PPP - Payment gateway updated', "paypal-brasil-para-woocommerce"), array("enabled" => boolval($activeGateway)));
            } else {
                update_option('active_payment_ppp', $activeGateway);

                $pplus_settings = get_option('woocommerce_paypal-brasil-plus-gateway_settings');
                $pplus_settings['enabled'] = false;
                update_option('woocommerce_paypal-brasil-plus-gateway_settings', $pplus_settings);

                $this->send_success_response(__('PPP - Payment gateway updated', "paypal-brasil-para-woocommerce"), array("enabled" => boolval($activeGateway)));
            }
        } catch (Exception $ex) {
            $this->send_error_response('Payment gateway not activated Error: ' . $ex->getMessage());
        }
    }

}

new PayPal_Brasil_API_PPP_Activate();
