<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout field mapping helpers for BCDC (testable, WP-agnostic logic).
 */
class BCDC_Checkout_Field_Mapper {

	/**
	 * @return array<string, array{label: string, default: string}>
	 */
	public static function get_definitions() {
		return array(
			'first_name'   => array(
				'label'   => 'Nome',
				'default' => 'billing_first_name',
			),
			'last_name'    => array(
				'label'   => 'Sobrenome',
				'default' => 'billing_last_name',
			),
			'person_type'  => array(
				'label'   => 'Tipo de pessoa',
				'default' => 'billing_persontype',
			),
			'cpf'          => array(
				'label'   => 'CPF',
				'default' => 'billing_cpf',
			),
			'cnpj'         => array(
				'label'   => 'CNPJ',
				'default' => 'billing_cnpj',
			),
			'phone'        => array(
				'label'   => 'Telefone',
				'default' => 'billing_phone',
			),
			'email'        => array(
				'label'   => 'E-mail',
				'default' => 'billing_email',
			),
			'postcode'     => array(
				'label'   => 'CEP',
				'default' => 'billing_postcode',
			),
			'address'      => array(
				'label'   => 'Endereço',
				'default' => 'billing_address_1',
			),
			'number'       => array(
				'label'   => 'Número',
				'default' => 'billing_number',
			),
			'address_2'    => array(
				'label'   => 'Complemento',
				'default' => 'billing_address_2',
			),
			'neighborhood' => array(
				'label'   => 'Bairro',
				'default' => 'billing_neighborhood',
			),
			'city'         => array(
				'label'   => 'Cidade',
				'default' => 'billing_city',
			),
			'state'        => array(
				'label'   => 'Estado',
				'default' => 'billing_state',
			),
			'country'      => array(
				'label'   => 'País',
				'default' => 'billing_country',
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function get_default_mapping() {
		$defaults = array();

		foreach ( self::get_definitions() as $key => $definition ) {
			$defaults[ $key ] = $definition['default'];
		}

		return $defaults;
	}

	/**
	 * Merge saved JSON overrides with defaults.
	 *
	 * @param string $saved_json Saved option value.
	 * @return array<string, string>
	 */
	public static function resolve_mapping( $saved_json ) {
		$mapping = self::get_default_mapping();

		if ( empty( $saved_json ) ) {
			return $mapping;
		}

		$decoded = json_decode( $saved_json, true );
		if ( ! is_array( $decoded ) ) {
			return $mapping;
		}

		foreach ( $decoded as $key => $value ) {
			if ( ! isset( $mapping[ $key ] ) || $value === '' || $value === null ) {
				continue;
			}

			if ( self::is_valid_field_name( (string) $value ) ) {
				$mapping[ $key ] = self::sanitize_field_name( (string) $value );
			}
		}

		return $mapping;
	}

	/**
	 * Sanitize mapping JSON for persistence.
	 *
	 * @param string $value Raw POST value.
	 * @return string JSON string or empty.
	 */
	public static function sanitize_saved_mapping( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}

		$sanitized = array();

		foreach ( self::get_default_mapping() as $internal_key => $default_field ) {
			if ( ! isset( $decoded[ $internal_key ] ) || $decoded[ $internal_key ] === '' ) {
				continue;
			}

			$field_name = (string) $decoded[ $internal_key ];
			if ( self::is_valid_field_name( $field_name ) ) {
				$sanitized[ $internal_key ] = self::sanitize_field_name( $field_name );
			}
		}

		return empty( $sanitized ) ? '' : wp_json_encode( $sanitized );
	}

	/**
	 * @param string $field_name Checkout field name.
	 * @return bool
	 */
	public static function is_valid_field_name( $field_name ) {
		return (bool) preg_match( '/^[a-z0-9_\-]+$/i', $field_name );
	}

	/**
	 * @param string $field_name Checkout field name.
	 * @return string
	 */
	public static function sanitize_field_name( $field_name ) {
		$field_name = (string) $field_name;

		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $field_name );
		}

		return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $field_name ) );
	}

	/**
	 * Build payload for hidden admin input (only non-default overrides).
	 *
	 * @param array<string, string> $form_values Values from admin form.
	 * @return string
	 */
	public static function build_admin_payload( array $form_values ) {
		$payload = array();

		foreach ( self::get_definitions() as $key => $definition ) {
			$value = isset( $form_values[ $key ] ) ? trim( (string) $form_values[ $key ] ) : '';
			if ( $value && $value !== $definition['default'] ) {
				$payload[ $key ] = $value;
			}
		}

		return empty( $payload ) ? '{}' : wp_json_encode( $payload );
	}
}
