<?php
/**
 * Remoção de dados ao excluir o plugin (Painel → Plugins → Excluir).
 *
 * Não remove metadados de pedidos (histórico de pagamentos) por padrão.
 *
 * Filtros:
 * - paypal_brasil_uninstall_remove_data (bool, default true) — desliga toda a limpeza.
 * - paypal_brasil_uninstall_remove_remote_registration (bool, default true) — apaga plugin_id e token_authentication_hash (nomes genéricos; desative se outro plugin reutilizar essas chaves).
 * - paypal_brasil_uninstall_delete_order_payment_meta (bool, default false) — remove meta de pagamento PayPal dos pedidos (irreversível).
 *
 * @package PayPal_Brasil_para_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! apply_filters( 'paypal_brasil_uninstall_remove_data', true ) ) {
	return;
}

global $wpdb;

/**
 * Executa limpeza no site atual.
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 */
function paypal_brasil_run_uninstall_cleanup() {
	global $wpdb;

	$remove_remote = (bool) apply_filters( 'paypal_brasil_uninstall_remove_remote_registration', true );

	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce_paypal-brasil%'"
	);
	
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'paypal_brasil_webhook_url-%'"
	);

	$extra_options = array(
		'active_payment_ppp',
		'active_payment_bcdc',
		'active_banner_notification_bcdc',
		'message_banner_notification_bcdc',
		'paypal_brasil_pplus_retired',
	);

	if ( $remove_remote ) {
		$extra_options[] = 'plugin_id';
		$extra_options[] = 'token_authentication_hash';
	}

	foreach ( $extra_options as $option_name ) {
		delete_option( $option_name );
	}

	// Tokens de API em cache (class-paypal-brasil-api.php / class-paypal-orders-api-v2.php).
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_paypal_brasil_%' OR option_name LIKE '_transient_timeout_paypal_brasil_%'"
	);

}

if ( is_multisite() ) {
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
	if ( is_array( $blog_ids ) ) {
		foreach ( $blog_ids as $blog_id ) {
			switch_to_blog( (int) $blog_id );
			paypal_brasil_run_uninstall_cleanup();
			restore_current_blog();
		}
	}
} else {
	paypal_brasil_run_uninstall_cleanup();
}
