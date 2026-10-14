<?php

if (!defined('ABSPATH')) {
    exit;
}

class PayPal_Brasil_API_PPlus_Deactivate extends PayPal_Brasil_API_Handler
{

    public function __construct()
    {
        add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
    }

    public function add_handlers($handlers)
    {
        $handlers['pplus_deactivation_update'] = array(
            'callback' => array($this, 'handle'),
            'method' => 'POST',
            'requires_auth' => true,
        );

        return $handlers;
    }

    /**
     * Show or remove PayPal Plus from plugin settings.
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

            if ($active) {
                if (!paypal_brasil_is_pplus_retired()) {
                    $pplus_settings = get_option('woocommerce_paypal-brasil-plus-gateway_settings');
                    $is_enabled = is_array($pplus_settings) && isset($pplus_settings['enabled']) && $pplus_settings['enabled'] === 'yes';

                    $this->send_success_response(
                        __('PayPal Plus - Payment gateway is already available', 'paypal-brasil-para-woocommerce'),
                        array('enabled' => $is_enabled, 'retired' => false)
                    );
                }

                $this->restore_pplus_gateway();

                $this->send_success_response(
                    __('PayPal Plus - Payment gateway restored in settings', 'paypal-brasil-para-woocommerce'),
                    array('enabled' => false, 'retired' => false)
                );
            } else {
                if (paypal_brasil_is_pplus_retired()) {
                    $this->send_success_response(
                        __('PayPal Plus - Payment gateway already removed', 'paypal-brasil-para-woocommerce'),
                        array('enabled' => false, 'retired' => true)
                    );
                }

                $this->remove_pplus_gateway();

                $this->send_success_response(
                    __('PayPal Plus - Payment gateway removed from settings', 'paypal-brasil-para-woocommerce'),
                    array('enabled' => false, 'retired' => true, 'settings_preserved' => true)
                );
            }
        } catch (Exception $ex) {
            $this->send_error_response('PayPal Plus update error: ' . $ex->getMessage());
        }
    }

    /**
     * Restore PayPal Plus gateway in WooCommerce settings.
     */
    private function restore_pplus_gateway()
    {
        delete_option('paypal_brasil_pplus_retired');
        update_option('active_payment_ppp', true);

        $pplus_settings = get_option('woocommerce_paypal-brasil-plus-gateway_settings');

        if (!is_array($pplus_settings) || empty($pplus_settings)) {
            update_option(
                'woocommerce_paypal-brasil-plus-gateway_settings',
                array(
                    'enabled' => 'no',
                )
            );
        }
    }

    /**
     * Hide PayPal Plus from WooCommerce settings while preserving its configuration.
     *
     * Credentials and other settings are kept so BCDC can reuse them via use_plus.
     */
    private function remove_pplus_gateway()
    {
        $pplus_settings = get_option('woocommerce_paypal-brasil-plus-gateway_settings');

        if (!is_array($pplus_settings)) {
            $pplus_settings = array();
        }

        $pplus_settings['enabled'] = 'no';
        update_option('woocommerce_paypal-brasil-plus-gateway_settings', $pplus_settings);

        delete_option('paypal_brasil_webhook_url-paypal-brasil-plus-gateway');
        update_option('active_payment_ppp', false);
        update_option('paypal_brasil_pplus_retired', true);
    }

}

new PayPal_Brasil_API_PPlus_Deactivate();
