<?php
$migration_url  = admin_url('admin.php?page=wc-settings&tab=checkout&section=paypal-brasil-bcdc-gateway');
$learn_more_url = 'https://www.paypal.com/br/business/accept-payments/checkout';
$terms_url      = 'https://www.paypal.com/br/webapps/mpp/ua/legalhub-full';
?>

<div class="notice is-dismissible paypal-brasil-gateway-notice">
	<div class="container">
		<div class="paypal-brasil-banner">
			<div class="paypal-brasil-banner__header">
				<span class="paypal-brasil-banner__header-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="24" height="24" fill="none">
						<rect x="3" y="6.5" width="18" height="11" rx="2.2" stroke="currentColor" stroke-width="1.8"></rect>
						<path d="M3.8 10.5h16.4" stroke="currentColor" stroke-width="1.8"></path>
						<circle cx="8" cy="14" r="1.2" fill="currentColor"></circle>
					</svg>
				</span>
				<div>
					<h2><?php esc_html_e('Atualize para o Cartão de Crédito e Débito do PayPal', 'paypal-brasil-para-woocommerce'); ?></h2>
					<p><?php esc_html_e('Migre do '); ?>
                    <strong> <?php esc_html_e('PayPal Plus', 'paypal-brasil-para-woocommerce'); ?> </strong>
                    <?php esc_html_e('para o ', 'paypal-brasil-para-woocommerce'); ?>
                    <strong> <?php esc_html_e('Cartão de Crédito e Débito (BCDC) ', 'paypal-brasil-para-woocommerce'); ?> </strong>
                    <?php esc_html_e('— mais moderno e com mais opções de pagamento para seus clientes.', 'paypal-brasil-para-woocommerce'); ?></p>
				</div>
			</div>

			<div class="paypal-brasil-banner__reasons">
				<h3><?php esc_html_e('Por que migrar agora', 'paypal-brasil-para-woocommerce'); ?></h3>

				<ul class="paypal-brasil-banner__list">
					<li>
						<span class="paypal-brasil-banner__list-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="20" height="20" fill="none">
								<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"></circle>
								<path d="M8.6 12.1l2.1 2.2 4.8-4.9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
							</svg>
						</span>
						<span><strong><?php esc_html_e('Aceitação de débito', 'paypal-brasil-para-woocommerce'); ?></strong> — <?php esc_html_e('além do crédito, seus clientes podem pagar com cartão de débito das principais bandeiras', 'paypal-brasil-para-woocommerce'); ?></span>
					</li>
					<li>
						<span class="paypal-brasil-banner__list-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-lock"><path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6" /><path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" /><path d="M8 11v-4a4 4 0 1 1 8 0v4" /></svg>
						</span>
						<span><strong><?php esc_html_e('Proteção ao comprador e ao vendedor', 'paypal-brasil-para-woocommerce'); ?></strong> — <?php esc_html_e('com a confiança do PayPal em cada transação', 'paypal-brasil-para-woocommerce'); ?></span>
					</li>
					<li>
						<span class="paypal-brasil-banner__list-icon" aria-hidden="true">
							<svg viewBox="0 0 24 24" width="21" height="21" fill="none">
								<path d="M13 3L6.8 13h4.7L11 21l6.2-10h-4.7L13 3z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"></path>
							</svg>
						</span>
						<span><strong><?php esc_html_e('Checkout rápido e simples', 'paypal-brasil-para-woocommerce'); ?></strong> — <?php esc_html_e('experiência otimizada que pode reduzir o abandono de carrinho', 'paypal-brasil-para-woocommerce'); ?></span>
					</li>
					<li>
						<span class="paypal-brasil-banner__list-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>

						</span>
						<span><strong><?php esc_html_e('Integração simples', 'paypal-brasil-para-woocommerce'); ?></strong> — <?php esc_html_e('configuração diretamente no painel do WooCommerce', 'paypal-brasil-para-woocommerce'); ?></span>
					</li>
				</ul>
			</div>

			<div class="paypal-brasil-banner__actions">
				<a class="button paypal-brasil-banner__button paypal-brasil-banner__button--primary" href="<?php echo esc_url($migration_url); ?>">
					<span aria-hidden="true" class="paypal-brasil-banner__button-arrow">
						<svg viewBox="0 0 20 20" width="16" height="16" fill="none">
							<path d="M4 10h11M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>
						</svg>
					</span>
					<?php esc_html_e('Migrar agora', 'paypal-brasil-para-woocommerce'); ?>
				</a>
				<a class="button paypal-brasil-banner__button paypal-brasil-banner__button--outline" href="<?php echo esc_url($learn_more_url); ?>" target="_blank" rel="noopener noreferrer">
					<u><?php esc_html_e('Saiba mais sobre o BCDC', 'paypal-brasil-para-woocommerce'); ?></u>
				</a>
			</div>

			<p class="paypal-brasil-banner__footnote">
				<?php esc_html_e('A utilização do Cartão de Crédito e Débito do PayPal está sujeita à aprovação de cadastro e aos termos e condições aplicáveis. As funcionalidades descritas podem variar conforme o perfil do estabelecimento. Consulte os', 'paypal-brasil-para-woocommerce'); ?>
				<a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e('termos e políticas do PayPal', 'paypal-brasil-para-woocommerce'); ?>
				</a>
				<?php esc_html_e('antes de prosseguir.', 'paypal-brasil-para-woocommerce'); ?>
			</p>
		</div>
	</div>
</div>