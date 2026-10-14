<?php

if (!defined('ABSPATH')) {
    exit;
}

class PayPal_Brasil_API_Bcdc_Checkout_Activate extends PayPal_Brasil_API_Handler
{

    public function __construct()
    {
        add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
    }

    public function add_handlers($handlers)
    {
        $handlers['bcdc_checkout_activation'] = array(
            'callback' => array($this, 'handle'),
            'method' => 'POST',
            'requires_auth' => true,
        );

        return $handlers;
    }

    /**
     * Update BCDC checkout status. Activation only when currently disabled.
     */
    public function handle()
    {
        try {
            $json_data = file_get_contents('php://input');
            $post_data = json_decode($json_data, true);

            if (!isset($post_data['active'])) {
                $this->send_error_response('Missing required param: active', array(), 400);
                return;
            }

            $active = wc_string_to_bool($post_data['active']);
            $bcdc_settings = get_option('woocommerce_paypal-brasil-bcdc-gateway_settings');
            $bcdc_is_active = get_option('active_payment_bcdc');

            if (!is_array($bcdc_settings)) {
                $bcdc_settings = array();
            }

            $is_enabled_at_checkout = isset($bcdc_settings['enabled']) && $bcdc_settings['enabled'] === 'yes';

            if ($active) {
                if ($is_enabled_at_checkout || $bcdc_is_active) {
                    $this->send_error_response('Error: BCDC is already active at checkout');
                    return;
                }

                $gateways = WC()->payment_gateways()->payment_gateways();

                if (!isset($gateways['paypal-brasil-bcdc-gateway'])) {
                    $this->send_error_response('BCDC gateway not found', array(), 500);
                    return;
                }

                /** @var Paypal_Brasil_BCDC_Gateway $bcdc_gateway */
                $bcdc_gateway = $gateways['paypal-brasil-bcdc-gateway'];

                $bcdc_settings = $bcdc_gateway->apply_settings_from_plus($bcdc_settings);
                $bcdc_settings['enabled'] = 'yes';
                update_option('woocommerce_paypal-brasil-bcdc-gateway_settings', $bcdc_settings);

                $bcdc_gateway->setup_gateway_after_activation();

                update_option('active_payment_bcdc', true);
                update_option('active_banner_notification_bcdc', false);

                $this->send_success_response(
                    __('BCDC - Payment gateway enabled at checkout', 'paypal-brasil-para-woocommerce'),
                    array('enabled' => true)
                );
            } else {
                $bcdc_settings['enabled'] = 'no';
                update_option('woocommerce_paypal-brasil-bcdc-gateway_settings', $bcdc_settings);
                update_option('active_payment_bcdc', false);

                $this->send_success_response(
                    __('BCDC - Payment gateway disabled at checkout', 'paypal-brasil-para-woocommerce'),
                    array('enabled' => false)
                );
            }
        } catch (Exception $ex) {
            $this->send_error_response('BCDC checkout update error: ' . $ex->getMessage());
        }
    }

}

new PayPal_Brasil_API_Bcdc_Checkout_Activate();
