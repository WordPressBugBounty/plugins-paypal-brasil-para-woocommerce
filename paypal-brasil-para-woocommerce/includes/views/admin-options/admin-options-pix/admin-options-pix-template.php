<div class="admin-options-container">

    <?php if ((empty($_POST) && $this->enabled === 'yes') || (isset($_POST) && $this->get_updated_values()['enabled'] === 'yes')): ?>

        <!-- CREDENTIALS ERROR -->
        <?php if (get_option($this->get_option_key() . '_validator') === 'no'): ?>
            <div id="message" class="error inline">
                <p>
                    <strong>
                        <?php _e(
                            "Your credentials are not valid. Please check the information provided.",
                            "paypal-brasil-para-woocommerce"
                        ); ?>
                    </strong>
                </p>
            </div>
        <?php endif; ?>

        <!-- WEBHOOK -->
        <?php if (!$this->get_webhook_id()): ?>
            <div id="paypal-brasil-message-webhook" class="error inline">
                <p>
                    <strong>
                        <?php _e(
                            "Unable to create webhook configurations. Try to save again.",
                            "paypal-brasil-para-woocommerce"
                        ); ?>
                    </strong>
                </p>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <?php echo wp_kses_post(wpautop($this->get_method_description())); ?>

    <table class="form-table">

        <tbody>

            <!-- HABILITAR -->

            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($this->get_field_key('enabled')); ?>">
                        <?php _e("Enable/Disable", "paypal-brasil-para-woocommerce"); ?>
                    </label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>
                                <?php _e("Enable/Disable", "paypal-brasil-para-woocommerce"); ?>
                            </span></legend>
                        <label for="<?php echo esc_attr($this->get_field_key('enabled')); ?>">
                            <input type="checkbox" class="test"
                                name="<?php echo esc_attr($this->get_field_key('enabled')); ?>"
                                id="<?php echo esc_attr($this->get_field_key('enabled')); ?>"
                                value="<?php echo esc_attr($this->enabled); ?>" v-model="enabled" true-value="yes"
                                false-value="">
                            <?php _e("Enable", "paypal-brasil-para-woocommerce"); ?>
                        </label><br>
                    </fieldset>
                </td>
            </tr>

            <!-- CONFIGURAÇÃO DE CREDENCIAIS -->

            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($this->get_field_key('credential_configuration')); ?>">
                        <?php _e("Credential Configuration", "paypal-brasil-para-woocommerce"); ?>
                    </label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span>
                                <?php _e("Credential Configuration", "paypal-brasil-para-woocommerce"); ?>
                            </span>
                        </legend>
                        <select class="select" id="<?php echo esc_attr($this->get_field_key('credential_configuration')); ?>"
                            name="<?php echo esc_attr($this->get_field_key('credential_configuration')); ?>" v-model="credentialConfiguration">
                            <option value="none">
                                <?php _e("Do not use credentials", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                            <option value="use_bcdc">
                                <?php _e("Use BCDC Credentials", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                            <option value="use_spb">
                                <?php _e("Use SPB Credentials", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                            <option value="new_credentials">
                                <?php _e("Configure New Credentials", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e("Choose how you want to configure your Invoice credentials. You can use existing credentials from other gateways or configure new ones.", "paypal-brasil-para-woocommerce"); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <!-- MODO -->

            <tr valign="top" :class="{hidden: credentialConfiguration === 'none'}">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($this->get_field_key('mode')); ?>">
                        <?php echo esc_html($this->get_form_fields()['mode']['title']); ?>
                    </label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span>
                                <?php echo esc_html($this->get_form_fields()['mode']['title']); ?>
                            </span>
                        </legend>
                        <select class="select" id="<?php echo esc_attr($this->get_field_key('mode')); ?>"
                            name="<?php echo esc_attr($this->get_field_key('mode')); ?>" v-model="mode">
                            <option value="live">
                                <?php _e("Production", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                            <option value="sandbox" selected="selected">
                                <?php _e("Sandbox", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e("Use this option to toggle between Sandbox and Production modes. Sandbox is
                        used for testing and production for actual purchases.", "paypal-brasil-para-woocommerce"); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <!-- CLIENT ID LIVE -->

            <tr valign="top" :class="{hidden: !isLive() || credentialConfiguration !== 'new_credentials'}">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($this->get_field_key('client_live')); ?>">
                        <?php _e("Client ID
                    (production)", "paypal-brasil-para-woocommerce"); ?>
                    </label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>
                                <?php _e("Client ID", "paypal-brasil-para-woocommerce"); ?>
                            </span></legend>
                        <input class="input-text regular-input" type="text"
                            id="<?php echo esc_attr($this->get_field_key('client_live')); ?>"
                            name="<?php echo esc_attr($this->get_field_key('client_live')); ?>" v-model="client.live">
                        <p class="description">
                            <?php _e("To generate the Client ID go to", "paypal-brasil-para-woocommerce"); ?> <a
                                href="https://developer.paypal.com/docs/multiparty/get-started/" target="_blank">
                                <?php _e("here", "paypal-brasil-para-woocommerce"); ?>
                            </a>
                            <?php _e('and get it from the “REST API APPS” section.', "paypal-brasil-para-woocommerce"); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <!-- CLIENT ID SANDBOX -->

            <tr valign="top" :class="{hidden: isLive() || credentialConfiguration !== 'new_credentials'}">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($this->get_field_key('client_sandbox')); ?>">
                        <?php _e(" Client ID
                    (sandbox)", "paypal-brasil-para-woocommerce"); ?>
                    </label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>
                                <?php _e(" Client ID", "paypal-brasil-para-woocommerce"); ?>
                            </span></legend>
                        <input class="input-text regular-input" type="text"
                            id="<?php echo esc_attr($this->get_field_key('client_sandbox')); ?>"
                            name="<?php echo esc_attr($this->get_field_key('client_sandbox')); ?>"
                            v-model="client.sandbox">
                        <p class="description">
                            <?php _e("To generate the Client ID go to", "paypal-brasil-para-woocommerce"); ?> <a
                                href="https://developer.paypal.com/docs/multiparty/get-started/" target="_blank">
                                <?php _e(" here ", "paypal-brasil-para-woocommerce"); ?>
                            </a>
                            <?php _e("and get it from the “REST API APPS” section.", "paypal-brasil-para-woocommerce"); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <!-- SECRET LIVE -->

            <tr valign="top" :class="{hidden: !isLive() || credentialConfiguration !== 'new_credentials'}">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($this->get_field_key('secret_live')); ?>">
                        <?php _e("Secret (production)", "paypal-brasil-para-woocommerce"); ?>
                    </label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>
                                <?php _e("Client secret", "paypal-brasil-para-woocommerce"); ?>
                            </span></legend>
                        <input class="input-text regular-input" type="text"
                            id="<?php echo esc_attr($this->get_field_key('secret_live')); ?>"
                            name="<?php echo esc_attr($this->get_field_key('secret_live')); ?>" v-model="secret.live">
                        <p class="description">
                            <?php _e("To generate the Secret go to ", "paypal-brasil-para-woocommerce"); ?> <a
                                href="https://developer.paypal.com/docs/multiparty/get-started/" target="_blank">
                                <?php _e("here", "paypal-brasil-para-woocommerce"); ?>
                            </a>
                            <?php _e("and get it from the “REST API APPS” section.", "paypal-brasil-para-woocommerce"); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <!-- SECRET SANDBOX -->

            <tr valign="top" :class="{hidden: isLive() || credentialConfiguration !== 'new_credentials'}">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($this->get_field_key('secret_sandbox')); ?>">
                        <?php _e("Secret (sandbox)", "paypal-brasil-para-woocommerce"); ?>
                    </label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>
                                <?php _e(" Client secret (sandbox)", "paypal-brasil-para-woocommerce"); ?>
                            </span></legend>
                        <input class="input-text regular-input" type="text"
                            id="<?php echo esc_attr($this->get_field_key('secret_sandbox')); ?>"
                            name="<?php echo esc_attr($this->get_field_key('secret_sandbox')); ?>"
                            v-model="secret.sandbox">
                        <p class="description">
                            <?php _e("To generate the Secret go to ", "paypal-brasil-para-woocommerce"); ?> <a
                                href="https://developer.paypal.com/docs/multiparty/get-started/" target="_blank">
                                <?php _e("here", "paypal-brasil-para-woocommerce"); ?>
                            </a>
                            <?php _e("and get it from the “REST API APPS” section.", "paypal-brasil-para-woocommerce"); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <h2>
                <?php _e("Advanced Settings", "paypal-brasil-para-woocommerce"); ?>
            </h2>

            <!-- QR_CODE EXPIRATION -->

            <tr valign="top">

                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($this->get_field_key('qr_expiry')); ?>">
                        <?php esc_html_e('Tempo de expiração do QR code', "paypal-brasil-para-woocommerce"); ?>
                    </label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span>
                                <?php echo esc_html($this->get_form_fields()['qr_expiry']); ?>
                            </span>
                        </legend>
                        <!--TODO - Implementar tempo de expiração-->
                        <select class="select" id="<?php echo esc_attr($this->get_field_key('qr_expiry')); ?>"
                            name="<?php echo esc_attr($this->get_field_key('qr_expiry')); ?>"
                            v-model="paymentExpiration">
                            <option value="5H" selected="selected">
                                <?php _e("5 horas", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                            <option value="1H">
                                <?php _e("1 hora", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                            <option value="30M">
                                <?php _e("30 minutos", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                            <option value="20M">
                                <?php _e("20 minutos", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                            <option value="10M">
                                <?php _e("10 minutos", "paypal-brasil-para-woocommerce"); ?>
                            </option>
                            <option value="5M">
                                <?php _e("5 minutos", "paypal-brasil-para-woocommerce"); ?>
                            </option>

                        </select>
                        <p class="description">
                            <?php _e("Use esta opção para definir o tempo de expiração da fatura.", "paypal-brasil-para-woocommerce"); ?>
                        </p>
                    </fieldset>
                </td>
            </tr>

            <!-- MODO DEPURAÇÃO -->

            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label for="<?php echo esc_attr($this->get_field_key('debug')); ?>">
                        <?php esc_html_e('Debug mode', "paypal-brasil-para-woocommerce"); ?>
                    </label>
                </th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>
                                <?php esc_html_e('Debug mode', "paypal-brasil-para-woocommerce"); ?>
                            </span></legend>
                        <label for="<?php echo esc_attr($this->get_field_key('debug')); ?>">
                            <input type="checkbox" id="<?php echo esc_attr($this->get_field_key('debug')); ?>"
                                name="<?php echo esc_attr($this->get_field_key('debug')); ?>" v-model="debugMode"
                                true-value="yes" false-value="">
                            <?php _e("Enable", "paypal-brasil-para-woocommerce"); ?>
                        </label><br>
                        <p class="description">
                            <?php esc_html_e('The logs will be saved in the path:', "paypal-brasil-para-woocommerce"); ?>
                            <a target="_blank" href="<?php echo esc_url(admin_url(
                                sprintf(
                                    'admin.php?page=wc-status&tab=logs&log_file=%s',
                                    paypal_brasil_get_log_file($this->id)
                                )
                            )); ?>">
                                <?php esc_html_e('Status
                                                                                   of the system &gt; Logs', "paypal-brasil-para-woocommerce"); ?>
                            </a>.
                        </p>
                    </fieldset>
                </td>
            </tr>

        </tbody>

    </table>

</div>