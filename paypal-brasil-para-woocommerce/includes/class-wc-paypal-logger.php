<?php

if (!defined('ABSPATH')) {
	exit;
}

use GuzzleHttp\Client;

/**
 * Utilize WC logger class
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class WC_PAYPAL_LOGGER
{
	/**
	 * Add a log entry.
	 *
	 * @param string $message Log message.
	 */
	public static function log($message, $gateway_id, string $level = 'info', array $extra = array(), array $tags = array())
	{
		if (!class_exists('WC_Logger')) {
			return;
		}

		$options = get_option("woocommerce_{$gateway_id}_settings");
		
		$wc_logger = wc_get_logger();
		$context = array('source' => $gateway_id);
		
		$log_message = PHP_EOL . '==== Paypal Brasil para woocommerce Version: ' . PAYPAL_PAYMENTS_VERSION . ' ====' . PHP_EOL;
		$log_message .= PHP_EOL;
		$log_message .= '=== Start Log ===' . PHP_EOL;
		$log_message .= $message . PHP_EOL;
		$log_message .= '=== End Log ===' . PHP_EOL;
		$log_message .= PHP_EOL;
		
		if (!empty($options) && isset($options['debug']) && $options['debug'] === 'yes') {
			$wc_logger->debug($log_message, $context);
		}

		// Enviar para o Datadog somente se for um log de erro ou critico, ou contiver palavras específicas
		$datadog_api_key = self::getDatadogApiKey();

			try {
				$client = new Client([
					'base_uri' => 'https://http-intake.logs.datadoghq.com/',
				]);

				$obj = new self();

				// Dados do log em JSON
				$logData = [
					"ddsource" => "paypal-woocommerce",
					"ddtags" => "site_name:" . get_bloginfo("name") . "," . "plugin_version:" . PAYPAL_PAYMENTS_VERSION,
					"gateway" => $gateway_id,
					"message" => $message,
					"service" => "paypal-woocommerce",
					"status" => $level,
					"hostname" => home_url(),
					"version" => PAYPAL_PAYMENTS_VERSION,
					"body" => array($obj->filterData($extra))
				];

				if(isset($tags)){
					foreach ($tags as $tag) {
						$logData['ddtags'] = $logData['ddtags'] .',' . $tag;
					}
				}

				$log = $logData;


				$client->post("api/v2/logs", [
					'headers' => [
						'Content-Type' => 'application/json',
						'Accept' => 'application/json',
						'DD-API-KEY' => $datadog_api_key
					],
					'json' => $logData,
				]);
			} catch (\Throwable $th) {
				return;
			}

	}


	/**
	 * Filter and hide sensitive data fields.
	 *
	 * @param array $data
	 * @param array $fieldsToMask
	 * @return array Array with data filter result.
	 */
	function filterData(array $data): array
	{
		$fieldsToMask = ["address_line_1", "address_line_2", "admin_area_1", "admin_area_2", "country_code", "postal_code", "email_address", "surname", "national_number", "tax_id", "tax_id_type", "email", "full_name", "document", "documentType", "phone"];
		foreach ($data as $key => &$value) {
			// Se a chave estiver na lista de campos para mascarar e o valor for uma string, aplique a máscara
			if (in_array($key, $fieldsToMask) && is_string($value)) {
				$value = $this->maskString($value);
			}

			// Se o valor for um array, aplique a função recursivamente
			if (is_array($value)) {
				$value = $this->filterData($value);
			}
		}

		return $data;
	}


	/**
	 * Replaces the value of the string with asterisks
	 *
	 * @param string $string
	 * @return string 
	 */
	function maskString($string): string
	{
		$length = strlen($string);

		if ($length <= 4) {
			return str_repeat('*', $length);
		}

		return substr($string, 0, 2) . str_repeat('*', $length - 4) . substr($string, -2);
	}

	private static function getDatadogApiKey()
	{
		return file_get_contents(__DIR__ . '/2892C90D7360927BD664E7506B5DD4607964BBA775550540AE697D26B5B23725.bin');
	}
}
