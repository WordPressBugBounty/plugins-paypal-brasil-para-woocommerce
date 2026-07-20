<?php

if (!defined('ABSPATH')) {
    exit;
}

class PayPal_Brasil_API_Banner_Notification extends PayPal_Brasil_API_Handler
{

    public function __construct()
    {
        add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
    }

    public function add_handlers($handlers)
    {
        $handlers['banner_notification_update'] = array(
            'callback' => array($this, 'handle'),
            'method' => 'POST',
        );

        return $handlers;
    }

    /**
     * Enable or disable the BCDC migration banner in admin.
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
            update_option('active_banner_notification_bcdc', $active);

            $message = $active
                ? __('Banner notification enabled', 'paypal-brasil-para-woocommerce')
                : __('Banner notification disabled', 'paypal-brasil-para-woocommerce');

            $this->send_success_response(
                $message,
                array('active' => $active)
            );
        } catch (Exception $ex) {
            $this->send_error_response('Banner notification update error: ' . $ex->getMessage());
        }
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

new PayPal_Brasil_API_Banner_Notification();
