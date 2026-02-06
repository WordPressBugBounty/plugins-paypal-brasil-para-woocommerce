<?php
/**
 * Endpoint REST para enviar logs ao Datadog
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Segurança
}

// 1️⃣ Registra o endpoint na API do WordPress
add_action('rest_api_init', function () {
    register_rest_route('bcdc/v1', '/log', [
        'methods'  => 'POST',
        'callback' => 'bcdc_send_log_to_datadog',
        'permission_callback' => '__return_true', // pode ser substituído por autenticação
    ]);
});

// 2️⃣ Função que envia o log ao Datadog
function bcdc_send_log_to_datadog(WP_REST_Request $request) {
    $params = $request->get_json_params();

    $message = $params['message'] ?? '(mensagem não informada)';
    $level   = $params['level'] ?? 'info';
    $context = $params['context'] ?? [];

    // Dados básicos do log
    $payload = [
        'ddsource' => 'paypal-woocommerce',
        'ddtags'   => "site_name:" . get_bloginfo("name") . ",plugin_version:" . PAYPAL_PAYMENTS_VERSION,
        'service'  => 'paypal-woocommerce-frontend',
        'status'   => $level,
        'message'  => $message,
        "hostname" => home_url(),
        "version"  => PAYPAL_PAYMENTS_VERSION,
        'context'  => $context,
        'site'     => get_bloginfo('name'),
        'user_id'  => get_current_user_id(),
        'url'      => $_SERVER['REQUEST_URI'] ?? '',
    ];

    // Chave privada do Datadog
    $api_key = file_get_contents(__DIR__ . '/2892C90D7360927BD664E7506B5DD4607964BBA775550540AE697D26B5B23725.bin');

    // Monta e envia a requisição
    $response = wp_remote_post('https://http-intake.logs.datadoghq.com/api/v2/logs', [
        'headers' => [
            'Content-Type' => 'application/json',
            'DD-API-KEY'   => $api_key,
        ],
        'body'    => wp_json_encode(maskSensitiveData($payload)),
        'timeout' => 5,
    ]);

    // Retorna resposta ao frontend
    if (is_wp_error($response)) {
        return new WP_REST_Response([
            'success' => false,
            'error' => $response->get_error_message(),
        ], 500);
    }

    return new WP_REST_Response(['success' => true], 200);
}

function fieldsToMask ()
{ return [
        "address",
        "address_2",
        "admin_area_1",
        "admin_area_2",
        "city",
        "state",
        "postcode",
        "email_address",
        "number",
        "cpf",
        "cnpj",
        "tax_id",
        "tax_id_type",
        "email",
        "first_name",
        "last_name",
        "neighborhood",
        "phone"
    ];
}

/**
 * Máscara recursiva dos campos sensíveis.
 */
function maskSensitiveData($data)
{
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (in_array($key, fieldsToMask(), true)) {
                $data[$key] = applyMask($key, $value);
            } elseif (is_array($value)) {
                $data[$key] = maskSensitiveData($value);
            }
        }
    }
    return $data;
}

/**
 * Define como mascarar dependendo do tipo do campo.
 */
function applyMask(string $key, $value)
{
    if (!is_string($value)) {
        return $value;
    }

    // Email
    if (stripos($key, "email") !== false) {
        $parts = explode("@", $value);
        if (count($parts) === 2) {
            return substr($parts[0], 0, 1) . "*****@" . $parts[1];
        }
        return "*****";
    }

    // Documento, telefone ou números longos
    if (preg_match("/^[0-9]{6,}$/", $value)) {
        return str_repeat("*", strlen($value) - 3) . substr($value, -3);
    }

    // Nome ou texto livre → só primeiras 2 letras
    if (strlen($value) > 2) {
        return substr($value, 0, 2) . str_repeat("*", strlen($value) - 2);
    }

    return "***";
}
