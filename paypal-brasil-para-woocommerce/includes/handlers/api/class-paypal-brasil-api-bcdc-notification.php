<?php

if (!defined('ABSPATH')) {
    exit;
}

class PayPal_Brasil_API_Bcdc_Notification extends PayPal_Brasil_API_Handler
{

    public function __construct()
    {
        add_filter('paypal_brasil_handlers', array($this, 'add_handlers'));
    }

    public function add_handlers($handlers)
    {
        $handlers['bcdc_notification_update'] = array(
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
            $json_data = file_get_contents('php://input');
            $post_data = json_decode($json_data, true);

            if (!is_array($post_data) || !isset($post_data['active'])) {
                $this->send_error_response('Missing required param: active', array(), 400);
                return;
            }

            $active = wc_string_to_bool($post_data['active']);
            update_option('active_banner_notification_bcdc', $active);

            if (isset($post_data['message'])) {
                update_option('message_banner_notification_bcdc', sanitize_text_field($post_data['message']));
            }

            $message = $active
                ? __('Banner updated', 'paypal-brasil-para-woocommerce')
                : __('Banner updated', 'paypal-brasil-para-woocommerce');

            $this->send_success_response($message, array('active' => $active));
        } catch (Exception $ex) {
            $this->send_error_response('Banner not activated Error: ' . $ex->getMessage());
        }
    }

}

new PayPal_Brasil_API_Bcdc_Notification();
