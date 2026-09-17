<div class="admin-options-container">

	<?php if ( ( empty( $_POST ) && $this->enabled === 'yes' ) || ( isset( $_POST ) && $this->get_updated_values()['enabled'] === 'yes' ) ) : ?>

		<!-- CREDENTIALS ERROR -->
		<?php if ( get_option( $this->get_option_key() . '_validator' ) === 'no' ) : ?>
			<div id="message" class="error inline">
				<p>
					<strong>
						<?php _e( 'Suas credenciais não são válidas. Verifique as informações fornecidas.', 'paypal-brasil-para-woocommerce' ); ?>
					</strong>
				</p>
			</div>
		<?php endif; ?>

		<!-- WEBHOOK -->
		<?php if ( ! $this->get_webhook_id() ) : ?>
			<div id="paypal-brasil-message-webhook" class="error inline">
				<p>
					<strong>
						<?php _e( 'Não foi possível criar as configurações de webhook. Tente salvar novamente.', 'paypal-brasil-para-woocommerce' ); ?>
					</strong>
				</p>
			</div>
		<?php endif; ?>

	<?php endif; ?>

	<?php echo wp_kses_post( wpautop( $this->get_method_description() ) ); ?>

	<table class="form-table">
		<tbody>

			<!-- HABILITAR -->
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'enabled' ) ); ?>">
						<?php _e( 'Ativar/Desativar', 'paypal-brasil-para-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>
								<?php _e( 'Ativar/Desativar', 'paypal-brasil-para-woocommerce' ); ?>
							</span></legend>
						<label for="<?php echo esc_attr( $this->get_field_key( 'enabled' ) ); ?>">
							<input type="checkbox"
								name="<?php echo esc_attr( $this->get_field_key( 'enabled' ) ); ?>"
								id="<?php echo esc_attr( $this->get_field_key( 'enabled' ) ); ?>"
								value="<?php echo esc_attr( $this->enabled ); ?>" v-model="enabled" true-value="yes"
								false-value="">
							<?php _e( 'Ativar Apple Pay', 'paypal-brasil-para-woocommerce' ); ?>
						</label><br>
					</fieldset>
				</td>
			</tr>

			<!-- TÍTULO (COMPLEMENTO) -->
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'title_complement' ) ); ?>">
						<?php _e( 'Nome de exibição (complemento)', 'paypal-brasil-para-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<input class="input-text regular-input" type="text"
							id="<?php echo esc_attr( $this->get_field_key( 'title_complement' ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_key( 'title_complement' ) ); ?>"
							v-model="title_complement">
					</fieldset>
				</td>
			</tr>

			<!-- CONFIGURAÇÃO DE CREDENCIAIS -->
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'credential_configuration' ) ); ?>">
						<?php _e( 'Credential Configuration', 'paypal-brasil-para-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>
								<?php _e( 'Credential Configuration', 'paypal-brasil-para-woocommerce' ); ?>
							</span></legend>
						<select class="select"
							id="<?php echo esc_attr( $this->get_field_key( 'credential_configuration' ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_key( 'credential_configuration' ) ); ?>"
							v-model="credentialConfiguration">
							<option value="none">
								<?php _e( 'Do not use credentials', 'paypal-brasil-para-woocommerce' ); ?>
							</option>
							<option value="use_bcdc">
								<?php _e( 'Use BCDC Credentials', 'paypal-brasil-para-woocommerce' ); ?>
							</option>
							<option value="use_spb">
								<?php _e( 'Use SPB Credentials', 'paypal-brasil-para-woocommerce' ); ?>
							</option>
							<option value="new_credentials">
								<?php _e( 'Configure New Credentials', 'paypal-brasil-para-woocommerce' ); ?>
							</option>
						</select>
						<p class="description">
							<?php _e( 'Escolha como configurar as credenciais do Apple Pay. Você pode reutilizar credenciais de outros gateways ou configurar novas.', 'paypal-brasil-para-woocommerce' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<!-- MODO -->
			<tr valign="top" :class="{hidden: credentialConfiguration === 'none'}">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'mode' ) ); ?>">
						<?php echo esc_html( $this->get_form_fields()['mode']['title'] ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>
								<?php echo esc_html( $this->get_form_fields()['mode']['title'] ); ?>
							</span></legend>
						<select class="select" id="<?php echo esc_attr( $this->get_field_key( 'mode' ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_key( 'mode' ) ); ?>" v-model="mode">
							<option value="live">
								<?php _e( 'Produção', 'paypal-brasil-para-woocommerce' ); ?>
							</option>
							<option value="sandbox" selected="selected">
								<?php _e( 'Sandbox', 'paypal-brasil-para-woocommerce' ); ?>
							</option>
						</select>
						<p class="description">
							<?php _e( 'Use esta opção para alternar entre Sandbox e Produção. Sandbox é usado para testes e Produção para compras reais.', 'paypal-brasil-para-woocommerce' ); ?>
						</p>
					</fieldset>
				</td>
			</tr>

			<!-- CLIENT ID LIVE -->
			<tr valign="top" :class="{hidden: !isLive() || credentialConfiguration !== 'new_credentials'}">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'client_live' ) ); ?>">
						<?php _e( 'Client ID (produção)', 'paypal-brasil-para-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<input class="input-text regular-input" type="text"
							id="<?php echo esc_attr( $this->get_field_key( 'client_live' ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_key( 'client_live' ) ); ?>" v-model="client.live">
					</fieldset>
				</td>
			</tr>

			<!-- CLIENT ID SANDBOX -->
			<tr valign="top" :class="{hidden: isLive() || credentialConfiguration !== 'new_credentials'}">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'client_sandbox' ) ); ?>">
						<?php _e( 'Client ID (sandbox)', 'paypal-brasil-para-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<input class="input-text regular-input" type="text"
							id="<?php echo esc_attr( $this->get_field_key( 'client_sandbox' ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_key( 'client_sandbox' ) ); ?>"
							v-model="client.sandbox">
					</fieldset>
				</td>
			</tr>

			<!-- SECRET LIVE -->
			<tr valign="top" :class="{hidden: !isLive() || credentialConfiguration !== 'new_credentials'}">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'secret_live' ) ); ?>">
						<?php _e( 'Secret (produção)', 'paypal-brasil-para-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<input class="input-text regular-input" type="text"
							id="<?php echo esc_attr( $this->get_field_key( 'secret_live' ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_key( 'secret_live' ) ); ?>" v-model="secret.live">
					</fieldset>
				</td>
			</tr>

			<!-- SECRET SANDBOX -->
			<tr valign="top" :class="{hidden: isLive() || credentialConfiguration !== 'new_credentials'}">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'secret_sandbox' ) ); ?>">
						<?php _e( 'Secret (sandbox)', 'paypal-brasil-para-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<input class="input-text regular-input" type="text"
							id="<?php echo esc_attr( $this->get_field_key( 'secret_sandbox' ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_key( 'secret_sandbox' ) ); ?>"
							v-model="secret.sandbox">
					</fieldset>
				</td>
			</tr>

			<h2>
				<?php _e( 'Configurações avançadas', 'paypal-brasil-para-woocommerce' ); ?>
			</h2>

			<!-- PREFIXO DO NÚMERO DO PEDIDO -->
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'invoice_id_prefix' ) ); ?>">
						<?php _e( 'Prefixo no número do pedido', 'paypal-brasil-para-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<input class="input-text regular-input" type="text"
							id="<?php echo esc_attr( $this->get_field_key( 'invoice_id_prefix' ) ); ?>"
							name="<?php echo esc_attr( $this->get_field_key( 'invoice_id_prefix' ) ); ?>"
							v-model="invoice_id_prefix">
					</fieldset>
				</td>
			</tr>

			<!-- MODO DEPURAÇÃO -->
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $this->get_field_key( 'debug' ) ); ?>">
						<?php esc_html_e( 'Log de depuração', 'paypal-brasil-para-woocommerce' ); ?>
					</label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span>
								<?php esc_html_e( 'Log de depuração', 'paypal-brasil-para-woocommerce' ); ?>
							</span></legend>
						<label for="<?php echo esc_attr( $this->get_field_key( 'debug' ) ); ?>">
							<input type="checkbox" id="<?php echo esc_attr( $this->get_field_key( 'debug' ) ); ?>"
								name="<?php echo esc_attr( $this->get_field_key( 'debug' ) ); ?>" v-model="debugMode"
								true-value="yes" false-value="">
							<?php _e( 'Ativar logs', 'paypal-brasil-para-woocommerce' ); ?>
						</label><br>
					</fieldset>
				</td>
			</tr>

		</tbody>
	</table>

</div>
