<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mescla o JSON do createOrder BCDC sobre os dados de cobrança do checkout.
 */
class BCDC_Billing_Data_Merger {

	/**
	 * @param array $billing_data Dados do checkout/sessão WooCommerce.
	 * @param array $request_data Corpo JSON validado do endpoint checkout_bcdc.
	 * @return array
	 */
	public static function merge_from_api_request( array $billing_data, array $request_data ) {
		if ( empty( $request_data ) ) {
			return $billing_data;
		}

		if ( array_key_exists( 'wc-bcdc-brasil-selected', $request_data ) ) {
			$billing_data['wc-bcdc-brasil-selected'] = filter_var( $request_data['wc-bcdc-brasil-selected'], FILTER_VALIDATE_BOOLEAN );
		}

		$map = array(
			'first_name'       => 'first_name',
			'last_name'        => 'last_name',
			'person_type'      => 'person_type',
			'cpf'              => 'cpf',
			'cnpj'             => 'cnpj',
			'phone'            => 'phone',
			'email'            => 'email',
			'postcode'         => 'postcode',
			'city'             => 'city',
			'state'            => 'state',
			'country'          => 'country',
			'number'           => 'number',
			'neighborhood'     => 'neighborhood',
			'address'          => 'address',
			'address_2'        => 'address_2',
			'address_line_1'   => 'address',
			'address_line_2'   => 'address_2',
		);

		foreach ( $map as $from => $to ) {
			if ( ! array_key_exists( $from, $request_data ) ) {
				continue;
			}

			$val = $request_data[ $from ];
			if ( null === $val ) {
				continue;
			}

			$billing_data[ $to ] = is_string( $val ) ? trim( $val ) : $val;
		}

		return $billing_data;
	}
}
