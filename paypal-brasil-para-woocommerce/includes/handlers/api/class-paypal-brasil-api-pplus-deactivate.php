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
        );

        return $handlers;
    }

    /**
     * Show or remove PayPal Plus from plugin settings.
     */
    public function handle()
    {
        try {
            if (!$this->verify_authenticate()) {
                $this->send_error_response('Not authorized', array(), 403);
            }

            $json_data = file_get_contents('php://input');
            $post_data = json_decode($json_data, true);

            if (!isset($post_data['active'])) {
                $this->send_error_response('Missing required param: active', array(), 400);
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

    public function verify_authenticate()
    {
        $data = array(
            'uuid' => get_option('plugin_id'),
            'store_url' => home_url(),
            'token_authentication_hash' => get_option('token_authentication_hash')
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, PAYPAL_BRASIL_PCP_API_VERIFY_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->send_error_response('Not authorized', array(), 403);
        }

        $response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($response_code == 200) {
            return true;
        }

        return false;
    }

}

new PayPal_Brasil_API_PPlus_Deactivate();
