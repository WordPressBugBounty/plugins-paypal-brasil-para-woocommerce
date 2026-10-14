<?php

if (!defined('ABSPATH')) {
    exit;
}

class PayPal_Brasil_API_Bcdc_Activate extends PayPal_Brasil_API_Handler
{

    public function __construct()
    {
        add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
    }

    public function add_handlers($handlers)
    {
        $handlers['bcdc_activation_update'] = array(
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
            $bcdc_is_activate = get_option('active_payment_bcdc');
            $json_data = file_get_contents('php://input');
            $post_data = json_decode($json_data, true);
            $activeGateway = sanitize_text_field($post_data['active']);

            if ($activeGateway) {

                if ($bcdc_is_activate) {
                    $this->send_error_response('Error: BCDC is already active');
                    return;
                }

                update_option('active_payment_bcdc', $activeGateway);
                update_option('active_banner_notification_bcdc', false);

                $this->send_success_response(__('BCDC - Payment gateway updated', "paypal-brasil-para-woocommerce"), array("enabled" => boolval($activeGateway)));
            } else {
                update_option('active_payment_bcdc', $activeGateway);

                $bcdc_settings = get_option('woocommerce_paypal-brasil-bcdc-gateway_settings');
                $bcdc_settings['enabled'] = false;
                update_option('woocommerce_paypal-brasil-bcdc-gateway_settings', $bcdc_settings);

                $this->send_success_response(__('BCDC - Payment gateway updated', "paypal-brasil-para-woocommerce"), array("enabled" => boolval($activeGateway)));
            }
        } catch (Exception $ex) {
            $this->send_error_response('Payment gateway not activated Error: ' . $ex->getMessage());
        }
    }

}

new PayPal_Brasil_API_Bcdc_Activate();
