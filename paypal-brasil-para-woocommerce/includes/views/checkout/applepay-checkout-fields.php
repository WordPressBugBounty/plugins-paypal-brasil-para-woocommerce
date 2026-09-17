<?php
/**
 * Apple Pay checkout fields template (Basic Apple Pay).
 *
 * Mantém apenas o campo oculto com o ID do Order criado no backend. O mark
 * (<apple-pay-mark>) e o botão "Pagar com Apple Pay" são renderizados pelo
 * frontend (frontend-applepay.js) dentro do container de botões do checkout
 * (#paypal-brasil-button-container), seguindo o padrão dos gateways SPB/BCDC.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="paypal-applepay-fields">
	<!-- Campo oculto preenchido pelo frontend com o ID do Order criado no backend. -->
	<input type="hidden" id="paypal-brasil-applepay-order-id" name="paypal-brasil-applepay-order-id" value="">
</div>
