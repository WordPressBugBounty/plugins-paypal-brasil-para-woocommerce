<?php

// Ignore if access directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class PayPal_Brasil_Pix_Discount.
 *
 * Percentage discount for Pix payments in the classic checkout.
 *
 * The PayPal API has no per-payment-method discount, so the plugin applies the
 * discount as a negative cart fee. WooCommerce recalculates the fee on every
 * totals calculation (it never stacks) and turns it into an order item, so the
 * total sent to PayPal (`$order->get_total()`) already includes the discount.
 *
 * The chosen payment method is read from the session, which WooCommerce updates
 * on both `update_order_review` and `checkout` before recalculating the cart:
 * the server decides the discount, without trusting the front end.
 */
class PayPal_Brasil_Pix_Discount
{

	const GATEWAY_ID = 'paypal-brasil-pix-gateway';
	const FEE_ID = 'paypal-brasil-pix-discount';
	const META_PERCENTAGE = '_paypal_brasil_pix_discount_percentage';
	const META_AMOUNT = '_paypal_brasil_pix_discount_amount';

	/**
	 * Register hooks.
	 */
	public static function init()
	{
		add_action('woocommerce_cart_calculate_fees', array(__CLASS__, 'add_cart_discount'));
		add_action('woocommerce_checkout_create_order_fee_item', array(__CLASS__, 'tag_order_fee_item'), 10, 3);
		add_action('woocommerce_checkout_create_order', array(__CLASS__, 'save_order_discount_meta'));
		add_action('woocommerce_before_pay_action', array(__CLASS__, 'sync_order_pay_discount'));
		add_action('woocommerce_pay_order_before_submit', array(__CLASS__, 'render_order_pay_notice'));
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_checkout_script'), 20);
	}

	/**
	 * Percentage configured in the Pix gateway (0 when disabled or invalid).
	 *
	 * @return float
	 */
	public static function get_percentage()
	{
		$settings   = get_option('woocommerce_' . self::GATEWAY_ID . '_settings', array());
		$value      = is_array($settings) && isset($settings['discount_percentage']) ? $settings['discount_percentage'] : 0;
		$percentage = PayPal_Brasil_Pix_Discount_Calculator::normalize_percentage($value);

		return null === $percentage ? 0.0 : $percentage;
	}

	/**
	 * Percentage formatted for display (e.g. "5" or "7,5").
	 *
	 * @param float $percentage
	 *
	 * @return string
	 */
	public static function format_percentage($percentage)
	{
		return wc_format_localized_decimal((string) $percentage);
	}

	/**
	 * Discount configured and Pix gateway available.
	 *
	 * @return bool
	 */
	public static function is_active()
	{
		if (self::get_percentage() <= 0) {
			return false;
		}

		$gateway = self::get_gateway();

		return $gateway && $gateway->is_available();
	}

	/**
	 * Pix gateway instance already loaded by WooCommerce.
	 *
	 * @return PayPal_Brasil_PIX_Gateway|null
	 */
	private static function get_gateway()
	{
		if (!function_exists('WC') || !WC()->payment_gateways()) {
			return null;
		}

		$gateways = WC()->payment_gateways()->payment_gateways();

		return isset($gateways[self::GATEWAY_ID]) ? $gateways[self::GATEWAY_ID] : null;
	}

	/**
	 * The discount only applies to the classic checkout: Pix is not registered
	 * in the Checkout Block, and the cart has no chosen payment method yet (the
	 * session may still hold Pix from a previous checkout visit).
	 *
	 * @return bool
	 */
	private static function is_classic_checkout_request()
	{
		// Store API (Cart/Checkout Blocks, mini-cart).
		if (defined('REST_REQUEST') && REST_REQUEST) {
			return false;
		}

		if (!is_checkout() || is_checkout_pay_page()) {
			return false;
		}

		$checkout_page_id = wc_get_page_id('checkout');
		if ($checkout_page_id > 0 && function_exists('has_block') && has_block('woocommerce/checkout', $checkout_page_id)) {
			return false;
		}

		return true;
	}

	/**
	 * Applies the discount as a negative fee when Pix is the chosen method.
	 *
	 * Base: products after coupons, excluding taxes and shipping. When taxes are
	 * enabled, WooCommerce splits the tax of negative fees across the items, so
	 * the effective discount applies to the products including tax.
	 *
	 * @param WC_Cart $cart
	 */
	public static function add_cart_discount($cart)
	{
		if (!$cart instanceof WC_Cart || !self::is_classic_checkout_request()) {
			return;
		}

		if (!WC()->session || self::GATEWAY_ID !== WC()->session->get('chosen_payment_method')) {
			return;
		}

		if (!self::is_active()) {
			return;
		}

		$percentage = self::get_percentage();
		$amount     = PayPal_Brasil_Pix_Discount_Calculator::calculate(
			$cart->get_cart_contents_total(),
			$percentage,
			wc_get_price_decimals()
		);

		if ($amount <= 0) {
			return;
		}

		// The fixed id prevents duplicates: WooCommerce ignores a second fee with the same id.
		$cart->fees_api()->add_fee(
			array(
				'id'      => self::FEE_ID,
				'name'    => sprintf(
					/* translators: %s: discount percentage */
					__('Desconto Pix (%s%%)', 'paypal-brasil-para-woocommerce'),
					self::format_percentage($percentage)
				),
				'amount'  => -$amount,
				'taxable' => true,
			)
		);
	}

	/**
	 * Tags the order fee item created from the cart Pix discount.
	 *
	 * @param WC_Order_Item_Fee $item
	 * @param string            $fee_key
	 * @param object            $fee
	 */
	public static function tag_order_fee_item($item, $fee_key, $fee)
	{
		if (self::FEE_ID !== $fee_key) {
			return;
		}

		$item->add_meta_data(self::META_PERCENTAGE, wc_format_decimal(self::get_percentage()), true);
	}

	/**
	 * Stores the discount percentage and amount on the order (audit/support).
	 *
	 * Also clears the metadata when a pending order is resumed without Pix.
	 *
	 * @param WC_Order $order
	 */
	public static function save_order_discount_meta($order)
	{
		$items = self::get_order_discount_items($order);

		if (empty($items)) {
			$order->delete_meta_data(self::META_PERCENTAGE);
			$order->delete_meta_data(self::META_AMOUNT);
			return;
		}

		$item = reset($items);
		$order->update_meta_data(self::META_PERCENTAGE, $item->get_meta(self::META_PERCENTAGE));
		$order->update_meta_data(self::META_AMOUNT, wc_format_decimal(self::get_order_discount_amount($order), wc_get_price_decimals()));
	}

	/**
	 * Order fee items that belong to the Pix discount.
	 *
	 * @param WC_Order $order
	 *
	 * @return WC_Order_Item_Fee[]
	 */
	private static function get_order_discount_items($order)
	{
		$items = array();

		foreach ($order->get_fees() as $item_id => $item) {
			if ('' !== (string) $item->get_meta(self::META_PERCENTAGE)) {
				$items[$item_id] = $item;
			}
		}

		return $items;
	}

	/**
	 * Total (positive, tax included) Pix discount amount on the order.
	 *
	 * @param WC_Order $order
	 *
	 * @return float
	 */
	private static function get_order_discount_amount($order)
	{
		$amount = 0.0;

		foreach (self::get_order_discount_items($order) as $item) {
			$amount -= (float) $item->get_total() + (float) $item->get_total_tax();
		}

		return $amount;
	}

	/**
	 * "Pay for order" page: the order already exists and switching the payment
	 * method does not recalculate the totals. If the customer pays an order that
	 * received the Pix discount with another method (e.g. expired QR Code), the
	 * discount is removed before payment. The discount is never added here, so
	 * the charged amount stays equal to the total shown on the page.
	 *
	 * WooCommerce has already validated the "woocommerce-pay" nonce before this hook.
	 *
	 * @param WC_Order $order
	 */
	public static function sync_order_pay_discount($order)
	{
		$payment_method = isset($_POST['payment_method']) ? wc_clean(wp_unslash($_POST['payment_method'])) : '';

		if (self::GATEWAY_ID === $payment_method) {
			return;
		}

		$items = self::get_order_discount_items($order);
		if (empty($items)) {
			return;
		}

		$removed = self::get_order_discount_amount($order);

		foreach (array_keys($items) as $item_id) {
			$order->remove_item($item_id);
		}

		$order->delete_meta_data(self::META_PERCENTAGE);
		$order->delete_meta_data(self::META_AMOUNT);
		$order->add_order_note(
			sprintf(
				__('Desconto Pix de %s removido: o pedido será pago com outro método de pagamento.', 'paypal-brasil-para-woocommerce'),
				wc_price($removed, array('currency' => $order->get_currency()))
			)
		);

		$order->calculate_totals(wc_tax_enabled());
	}

	/**
	 * Notice on the "pay for order" page, shown via JS when another method is
	 * selected, with the order total without the Pix discount.
	 */
	public static function render_order_pay_notice()
	{
		$order = wc_get_order(absint(get_query_var('order-pay')));
		if (!$order) {
			return;
		}

		$discount = self::get_order_discount_amount($order);
		if ($discount <= 0) {
			return;
		}

		$currency = array('currency' => $order->get_currency());

		echo '<div class="woocommerce-info paypal-brasil-pix-discount-notice" style="display:none">';
		echo wp_kses_post(
			sprintf(
				/* translators: 1: discount amount, 2: order total without the discount */
				__('O desconto Pix de %1$s vale apenas para pagamento via Pix. Com outro método, o total será %2$s.', 'paypal-brasil-para-woocommerce'),
				wc_price($discount, $currency),
				wc_price((float) $order->get_total() + $discount, $currency)
			)
		);
		echo '</div>';
	}

	/**
	 * Message shown in the Pix payment box at checkout.
	 *
	 * @return string
	 */
	public static function get_checkout_message()
	{
		if (!self::is_classic_checkout_request() || self::get_percentage() <= 0) {
			return '';
		}

		return sprintf(
			__('Pague com Pix e ganhe %s%% de desconto nos produtos.', 'paypal-brasil-para-woocommerce'),
			self::format_percentage(self::get_percentage())
		);
	}

	/**
	 * The classic checkout does not recalculate the totals when the payment
	 * method changes; the script triggers the recalculation and toggles the
	 * "pay for order" notice.
	 */
	public static function enqueue_checkout_script()
	{
		if (!is_checkout() || !wp_script_is('wc-checkout', 'registered')) {
			return;
		}

		if (!is_checkout_pay_page() && !self::is_active()) {
			return;
		}

		$gateway_id = wp_json_encode(self::GATEWAY_ID);

		$script = <<<JS
jQuery(function ($) {
	var gatewayId = {$gateway_id};

	$(document.body).on('payment_method_selected', function () {
		if ($('form.checkout').length) {
			$(document.body).trigger('update_checkout');
		}
	});

	var \$notice = $('.paypal-brasil-pix-discount-notice');
	if (\$notice.length) {
		var toggleNotice = function () {
			\$notice.toggle($('#order_review input[name="payment_method"]:checked').val() !== gatewayId);
		};
		$('#order_review').on('change', 'input[name="payment_method"]', toggleNotice);
		toggleNotice();
	}
});
JS;

		wp_add_inline_script('wc-checkout', $script);
	}
}
