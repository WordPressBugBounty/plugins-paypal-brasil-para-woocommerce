<?php

// Exit if not in WordPress.
if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('WC_PAYPAL_LOGGER')) {
	require_once plugin_dir_path(__FILE__) . '../class-wc-paypal-logger.php';
}
// Check if class already exists before create.
if (!class_exists('PayPal_Brasil_Webhooks_Handler')) {

	/**
	 * Class WC_PPP_Brasil_Webhooks_Handler.
	 */
	class PayPal_Brasil_Webhooks_Handler
	{

		private $gateway_id;

		/**
		 * @var PayPal_Brasil_Gateway
		 */
		private $gateway;

		/**
		 * WC_PPP_Brasil_Webhooks_Handler constructor.
		 *
		 * @param $gateway_id string
		 * @param $gateway PayPal_Brasil_Gateway
		 */
		public function __construct($gateway_id, $gateway)
		{
			$this->gateway_id = $gateway_id;
			$this->gateway = $gateway;
		}

		private function log($data)
		{
			if ($this->gateway) {
				WC_PAYPAL_LOGGER::log($data, $this->gateway_id);
			}
		}

		/**
		 * Find order IDs by PayPal sale / capture meta (simple queries — nested meta_query can fail on HPOS).
		 *
		 * @param array  $lookup_ids          PayPal identifiers from the webhook.
		 * @param string $resource_meta_key   Primary gateway meta key (e.g. wc_bcdc_brasil_sale_id).
		 * @param string|null $capture_meta_key Optional capture meta key (BCDC).
		 * @return int[]
		 */
		private function find_order_ids_by_paypal_lookup( $lookup_ids, $resource_meta_key, $capture_meta_key ) {
			foreach ( $lookup_ids as $lookup_id ) {
				$found = wc_get_orders(
					array(
						'limit'      => 1,
						'return'     => 'ids',
						'status'     => 'any',
						'meta_query' => array(
							array(
								'key'     => $resource_meta_key,
								'value'   => $lookup_id,
								'compare' => '=',
							),
						),
					)
				);
				if ( ! empty( $found ) ) {
					return $found;
				}
				if ( $capture_meta_key ) {
					$found = wc_get_orders(
						array(
							'limit'      => 1,
							'return'     => 'ids',
							'status'     => 'any',
							'meta_query' => array(
								array(
									'key'     => $capture_meta_key,
									'value'   => $lookup_id,
									'compare' => '=',
								),
							),
						)
					);
					if ( ! empty( $found ) ) {
						return $found;
					}
				}
			}
			return array();
		}

		/**
		 * Resolve WooCommerce order id from PAYMENT.CAPTURE.* resource (custom_id or invoice_id).
		 *
		 * @param array $event Webhook payload.
		 * @return int 0 if not resolved.
		 */
		private function try_resolve_wc_order_id_from_capture_resource( $event ) {
			$resource = isset( $event['resource'] ) && is_array( $event['resource'] ) ? $event['resource'] : null;
			if ( ! $resource ) {
				return 0;
			}
			$custom_id = isset( $resource['custom_id'] ) ? $resource['custom_id'] : '';
			if ( is_string( $custom_id ) && preg_match( '/#(\d+)/u', $custom_id, $m ) ) {
				return (int) $m[1];
			}
			if ( ! is_object( $this->gateway ) || ! is_callable( array( $this->gateway, 'get_option' ) ) ) {
				return 0;
			}
			$invoice_id = isset( $resource['invoice_id'] ) ? $resource['invoice_id'] : '';
			$prefix       = (string) $this->gateway->get_option( 'invoice_id_prefix', '' );
			if ( $invoice_id === '' || $prefix === '' || strpos( $invoice_id, $prefix ) !== 0 ) {
				return 0;
			}
			$rest = substr( $invoice_id, strlen( $prefix ) );
			// invoice_id = prefix + WC order id + UUID (36 chars from wp_generate_uuid4()).
			if ( strlen( $rest ) <= 36 ) {
				return 0;
			}
			$maybe_order_id = substr( $rest, 0, -36 );
			if ( ctype_digit( $maybe_order_id ) ) {
				return (int) $maybe_order_id;
			}
			return 0;
		}

		/**
		 * Extract capture id from refund/capture resource links (rel=up → /captures/{id}).
		 * PAYMENT.CAPTURE.REFUNDED uses resource.id as the refund id, not the capture id.
		 *
		 * @param array $event Webhook payload.
		 * @return string Empty string if not found.
		 */
		private function try_extract_capture_id_from_resource( $event ) {
			$resource = isset( $event['resource'] ) && is_array( $event['resource'] ) ? $event['resource'] : null;
			if ( ! $resource || empty( $resource['links'] ) || ! is_array( $resource['links'] ) ) {
				return '';
			}
			foreach ( $resource['links'] as $link ) {
				if ( empty( $link['rel'] ) || empty( $link['href'] ) || $link['rel'] !== 'up' ) {
					continue;
				}
				if ( preg_match( '#/captures/([A-Za-z0-9_-]+)#', $link['href'], $m ) ) {
					return $m[1];
				}
			}
			return '';
		}

		/**
		 * Refund amount from Sale (amount.total) or Capture/Orders v2 (amount.value).
		 *
		 * @param array $event Webhook payload.
		 * @return string|null
		 */
		private function get_refund_amount_from_event( $event ) {
			if ( isset( $event['resource']['amount']['value'] ) && $event['resource']['amount']['value'] !== '' ) {
				return $event['resource']['amount']['value'];
			}
			if ( isset( $event['resource']['amount']['total'] ) && $event['resource']['amount']['total'] !== '' ) {
				return $event['resource']['amount']['total'];
			}
			return null;
		}

		/**
		 * Handle the event.
		 *
		 * @param $event
		 *
		 * @throws Exception
		 */
		public function handle($event)
		{
			$gateway_meta_keys = [
				"paypal-brasil-plus-gateway" => 'wc_ppp_brasil_sale_id',
				"paypal-brasil-spb-gateway" => 'paypal_brasil_sale_id',
				"paypal-brasil-bcdc-gateway" => 'wc_bcdc_brasil_sale_id'
			];

			$gateway_capture_meta_keys = array(
				'paypal-brasil-bcdc-gateway' => 'wc_bcdc_brasil_capture_id',
			);


			$method_name = 'handle_process_' . str_replace('.', '_', strtolower($event['event_type']));
			$method_name = str_replace('-', '_', strtolower($method_name));
			$this->log('Handling process method: ' . $method_name);
			if (method_exists($this, $method_name)) {
				$this->log('Method name: ' . $method_name);
				// PAYMENT.CAPTURE.* sends resource.id = capture id; BCDC stores PayPal order id in wc_bcdc_brasil_sale_id.
				// supplementary_data.related_ids.order_id links capture -> order for lookup.
				$resource_id = isset($event['resource']['sale_id']) ? $event['resource']['sale_id'] : $event['resource']['id'];
				$lookup_ids = array();
				if ($resource_id !== null && $resource_id !== '') {
					$lookup_ids[] = $resource_id;
				}
				$related_paypal_order_id = isset($event['resource']['supplementary_data']['related_ids']['order_id'])
					? $event['resource']['supplementary_data']['related_ids']['order_id']
					: '';
				if ($related_paypal_order_id !== '' && ! in_array($related_paypal_order_id, $lookup_ids, true)) {
					$lookup_ids[] = $related_paypal_order_id;
				}
				// CAPTURE.REFUNDED: resource.id is the refund id; capture lives on links[rel=up].
				$capture_id_from_links = $this->try_extract_capture_id_from_resource( $event );
				if ( $capture_id_from_links !== '' && ! in_array( $capture_id_from_links, $lookup_ids, true ) ) {
					$lookup_ids[] = $capture_id_from_links;
				}
				$this->log(
					'Resource ID: ' . $resource_id
					. ( $related_paypal_order_id !== '' ? ' | related PayPal order id: ' . $related_paypal_order_id : '' )
					. ( $capture_id_from_links !== '' ? ' | capture id from links: ' . $capture_id_from_links : '' )
				);

				if ( ! isset( $gateway_meta_keys[ $this->gateway_id ] ) ) {
					$this->log( 'Invalid gateway ID: ' . $this->gateway_id );
					throw new Exception( 'Error on webhook handling' );
				}

				$resource_meta_key = $gateway_meta_keys[ $this->gateway_id ];
				$capture_meta_key  = isset( $gateway_capture_meta_keys[ $this->gateway_id ] )
					? $gateway_capture_meta_keys[ $this->gateway_id ]
					: null;

				if ( empty( $lookup_ids ) ) {
					$this->log( 'Order not found with this resource_id (empty lookup)' );
					throw new Exception( 'Order not found' );
				}

				$order_ids             = $this->find_order_ids_by_paypal_lookup( $lookup_ids, $resource_meta_key, $capture_meta_key );
				$resolved_via_fallback = false;

				if ( empty( $order_ids ) ) {
					$fallback_wc_order_id = $this->try_resolve_wc_order_id_from_capture_resource( $event );
					if ( $fallback_wc_order_id > 0 ) {
						$order_ids             = array( $fallback_wc_order_id );
						$resolved_via_fallback = true;
						$this->log( 'Order candidate from custom_id/invoice_id: ' . $fallback_wc_order_id );
					}
				}

				if ( ! empty( $order_ids ) ) {
					$order_id = $order_ids[0];
					$this->log( 'Order ID: ' . $order_id );
					$order    = wc_get_order( $order_id );

					$payment_method = ! empty( $order ) ? $order->get_payment_method() : '';
					$this->log( 'Payment method: ' . $payment_method );

					$stored_sale    = $order ? $order->get_meta( $resource_meta_key ) : '';
					$stored_capture = ( $order && $capture_meta_key ) ? $order->get_meta( $capture_meta_key ) : '';
					$id_matches     = in_array( $stored_sale, $lookup_ids, true )
						|| ( $stored_capture !== '' && in_array( $stored_capture, $lookup_ids, true ) );
					// Refund payloads may only match via custom_id/invoice_id (resource.id = refund id).
					if ( ! $id_matches && ! $resolved_via_fallback ) {
						$this->log( 'Resource ID mismatch' );
						return;
					}

					if ( $payment_method === $this->gateway_id ) {
						$this->log( 'Processing webhook for payment method: ' . $payment_method );
						$this->{$method_name}( $order, $event );
					} else {
						$this->log( 'Payment method not found: ' . $payment_method );
					}
				} else {
					$this->log( 'Order not found with this resource_id' );
					throw new Exception( 'Order not found' );
				}

			} else {
				throw new Exception('Invalid method to handle.');
			}
		}

		/**
		 * When payment is marked as completed.
		 *
		 * @param $order WC_Order
		 */
		public function handle_process_payment_sale_completed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing completed was not initiated because there is no order.');

				return;
			}

			$resource_id = isset($event['resource']['sale_id']) ? $event['resource']['sale_id'] : $event['resource']['id'];

			$this->log('Processing completed initiated.');
			// Check if the current status isn't processing or completed.
			if (
				!in_array($order->get_status(), array(
					'processing',
					'completed',
					'refunded',
					'cancelled'
				), true)
			) {
				$order->add_order_note(__('PayPal: Paid transaction.', "paypal-brasil-para-woocommerce"));
				$order->add_order_note(
					sprintf(
						__('Payment processed by PayPal. Transaction ID: <a href="%s" target="_blank" rel="noopener">%s</a>.', "paypal-brasil-para-woocommerce"),
						$this->gateway->mode === 'sandbox' ? "https://www.sandbox.paypal.com/activity/payment/{$resource_id}" : "https://www.paypal.com/activity/payment/{$resource_id}",
						$resource_id
					)
				);
				$order->payment_complete();
				$this->log('Processing completed finished.');
			}
		}

		/**
		 * When payment is denied.
		 *
		 * @param $order WC_Order
		 */
		public function handle_process_payment_sale_denied($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing denied was not initiated because there is no order.');

				return;
			}

			$this->log('Processing denied initiated.');
			// Check if the current status isn't failed.
			if (!in_array($order->get_status(), array('failed', 'completed', 'processing'), true)) {
				$order->update_status('failed', __('PayPal: The transaction was rejected by the card company or for fraud.', "paypal-brasil-para-woocommerce"));
				$this->log('Processing denied finished.');
			} else {
				$this->log('Processing denied did not change anything.');
			}
		}

		/**
		 * When payment is refunded.
		 *
		 * @param $order WC_Order
		 *
		 * @throws Exception
		 */
		public function handle_process_payment_sale_refunded($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing refunded was not initiated because there is no order.');

				return;
			}

			$this->log('Processing refunded initiated.');

			$refund_amount = $this->get_refund_amount_from_event( $event );
			if ( $refund_amount === null ) {
				$this->log( 'Processing refunded failed: missing amount in webhook resource.' );
				throw new Exception( __( 'There was an error trying to make a refund: missing amount.', "paypal-brasil-para-woocommerce" ) );
			}

			// Check if is partial refund.
			$partial_refund = paypal_brasil_money_format($order->get_total() - $order->get_total_refunded()) !== paypal_brasil_money_format($refund_amount);

			// Check if the current status isn't refunded.
			if (!in_array($order->get_status(), array('refunded'), true)) {
				// Check if is total refund
				if ($partial_refund) {
					$order->add_order_note(__('PayPal: The transaction was partially refunded.', "paypal-brasil-para-woocommerce"));
				} else {
					$order->update_status('refunded', __('PayPal: The transaction has been refunded in full.', "paypal-brasil-para-woocommerce"));
				}

				// Create the refund.
				$refund = wc_create_refund(
					array(
						'amount' => wc_format_decimal($refund_amount),
						'reason' => $partial_refund ? __('PayPal: The transaction was partially refunded.', "paypal-brasil-para-woocommerce") : __('PayPal: transaction refunded in full.', "paypal-brasil-para-woocommerce"),
						'order_id' => $order->get_id(),
						'refund_payment' => false,
					)
				);

				if (is_wp_error($refund)) {
					$this->log('There was some error refunding.');
					throw new Exception(sprintf(__('There was an error trying to make a refund: %s', "paypal-brasil-para-woocommerce"), $refund->get_error_message()));
				}

				$this->log('Processing refunded finished.');
			} else {
				$this->log('Processing refunded did not change anything.');

				throw new Exception(__('This order has already been refunded.', "paypal-brasil-para-woocommerce"));
			}
		}

		/**
		 * When payment is reversed.
		 *
		 * @param $order WC_Order
		 *
		 * @throws Exception
		 */
		public function handle_process_payment_sale_reversed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing reversed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing reversed initiated.');

			// Check if the current status isn't refunded.
			if (!in_array($order->get_status(), array('refunded'), true)) {
				$order->update_status('refunded', __('PayPal: The transaction has been rolled back.', "paypal-brasil-para-woocommerce"));

				$refund = wc_create_refund(
					array(
						'amount' => wc_format_decimal($order->get_total() - $order->get_total_refunded()),
						'reason' => __('PayPal: reversed transaction.', "paypal-brasil-para-woocommerce"),
						'order_id' => $order->get_id(),
						'refund_payment' => false,
					)
				);

				if (is_wp_error($refund)) {
					$this->log('There was some error reversing.');

					throw new Exception(sprintf(__('There was an error trying to make a refund: %s', "paypal-brasil-para-woocommerce"), $refund->get_error_message()));
				}

				$this->log('Processing reversed finished.');

			} else {
				$this->log('Processing reversed did not change anything.');

				throw new Exception(__('This order has already been refunded.', "paypal-brasil-para-woocommerce"));
			}
		}

		/**
		 * When payment is marked as completed.
		 *
		 * @param $order WC_Order
		 */
		public function handle_process_checkout_order_completed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing completed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing completed initiated.');
			// Check if the current status isn't processing or completed.
			if (
				!in_array($order->get_status(), array(
					'completed',
					'refunded',
					'cancelled'
				), true)
			) {
				$order->add_order_note(__('PayPal: Paid transaction.', "paypal-brasil-para-woocommerce"));
				$order->payment_complete();
				$this->log('Processing completed finished.');
			}
		}

		/**
		 * When payment is reversed.
		 *
		 * @param $order WC_Order
		 *
		 * @throws Exception
		 */
		public function handle_process_checkout_payment_approval_reversed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing reversed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing reversed initiated.');

			// Check if the current status isn't refunded.
			if (!in_array($order->get_status(), array('refunded'), true)) {
				$order->update_status('refunded', __('PayPal: The transaction has been rolled back.', "paypal-brasil-para-woocommerce"));

				$refund = wc_create_refund(
					array(
						'amount' => wc_format_decimal($order->get_total() - $order->get_total_refunded()),
						'reason' => __('PayPal: reversed transaction.', "paypal-brasil-para-woocommerce"),
						'order_id' => $order->get_id(),
						'refund_payment' => false,
					)
				);

				if (is_wp_error($refund)) {
					$this->log('There was some error reversing.');

					throw new Exception(sprintf(__('There was an error trying to make a refund: %s', "paypal-brasil-para-woocommerce"), $refund->get_error_message()));
				}

				$this->log('Processing reversed finished.');

			} else {
				$this->log('Processing reversed did not change anything.');

				throw new Exception(__('This order has already been refunded.', "paypal-brasil-para-woocommerce"));
			}
		}

		/**
		 * When payment is cancelled.
		 *
		 * @param $order WC_Order
		 *
		 * @throws Exception
		 */
		public function handle_process_payment_order_cancelled($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing reversed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing reversed initiated.');

			// Check if the current status isn't refunded.
			if (!in_array($order->get_status(), array('refunded'), true)) {
				$order->update_status('refunded', __('PayPal: The transaction has been rolled back.', "paypal-brasil-para-woocommerce"));

				$refund = wc_create_refund(
					array(
						'amount' => wc_format_decimal($order->get_total() - $order->get_total_refunded()),
						'reason' => __('PayPal: reversed transaction.', "paypal-brasil-para-woocommerce"),
						'order_id' => $order->get_id(),
						'refund_payment' => false,
					)
				);

				if (is_wp_error($refund)) {
					$this->log('There was some error reversing.');

					throw new Exception(sprintf(__('There was an error trying to make a refund: %s', "paypal-brasil-para-woocommerce"), $refund->get_error_message()));
				}

				$this->log('Processing reversed finished.');

			} else {
				$this->log('Processing reversed did not change anything.');

				throw new Exception(__('This order has already been refunded.', "paypal-brasil-para-woocommerce"));
			}
		}


		/**
		 * When payment is marked as completed.
		 *
		 * @param $order WC_Order
		 */
		public function handle_process_payment_capture_completed($order, $event)
		{
			// Check if order exists.
			if (!$order) {
				$this->log('Processing completed was not initiated because there is no order.');

				return;
			}

			$this->log('Processing completed initiated.');
			$resource_id = isset($event['resource']['sale_id']) ? $event['resource']['sale_id'] : $event['resource']['id'];
			// Check if the current status isn't processing or completed.
			if (
				!in_array($order->get_status(), array(
					'completed',
					'refunded',
					'cancelled'
				), true)
			) {
				$order->add_order_note(__('PayPal: Paid transaction.', "paypal-brasil-para-woocommerce"));
				$order->add_order_note(
					sprintf(
						__('Payment processed by PayPal. Transaction ID: <a href="%s" target="_blank" rel="noopener">%s</a>.', "paypal-brasil-para-woocommerce"),
						$this->gateway->mode === 'sandbox' ? "https://www.sandbox.paypal.com/activity/payment/{$resource_id}" : "https://www.paypal.com/activity/payment/{$resource_id}",
						$resource_id
					)
				);
				$order->payment_complete();
				$this->log('Processing completed finished.');
			}
		}

		/**
		 * When a Capture (Orders API v2) payment is refunded.
		 *
		 * @param WC_Order $order Order.
		 * @param array    $event Webhook payload.
		 *
		 * @throws Exception
		 */
		public function handle_process_payment_capture_refunded( $order, $event ) {
			$this->handle_process_payment_sale_refunded( $order, $event );
		}

		/**
		 * When a Capture (Orders API v2) payment is reversed.
		 *
		 * @param WC_Order $order Order.
		 * @param array    $event Webhook payload.
		 *
		 * @throws Exception
		 */
		public function handle_process_payment_capture_reversed( $order, $event ) {
			$this->handle_process_payment_sale_reversed( $order, $event );
		}



	}




}