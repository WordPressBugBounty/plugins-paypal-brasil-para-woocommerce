<?php

// Ignore if access directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class PayPal_Brasil_Pix_Discount_Calculator.
 *
 * Pure Pix discount rules (no WordPress/WooCommerce dependency), isolated so
 * they can be unit tested.
 */
class PayPal_Brasil_Pix_Discount_Calculator
{

	/**
	 * Highest accepted percentage. 100% would zero the order total, and PayPal
	 * does not create a zero-amount Pix charge.
	 */
	const MAX_PERCENTAGE = 99.99;

	/**
	 * Normalizes the percentage entered by the merchant.
	 *
	 * Accepts a comma or a dot as the decimal separator. An empty value means 0
	 * (discount disabled).
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return float|null Percentage with up to 2 decimals, or null when invalid.
	 */
	public static function normalize_percentage($value)
	{
		if (is_string($value)) {
			$value = str_replace(',', '.', trim($value));
		}

		if (null === $value || '' === $value) {
			return 0.0;
		}

		if (!is_numeric($value)) {
			return null;
		}

		$value = round((float) $value, 2);

		if ($value < 0 || $value > self::MAX_PERCENTAGE) {
			return null;
		}

		return $value;
	}

	/**
	 * Calculates the (positive) discount amount for the given base.
	 *
	 * @param float|string $base       Calculation base (products after coupons, excluding taxes).
	 * @param float|string $percentage Already normalized percentage.
	 * @param int          $decimals   Store price decimals.
	 *
	 * @return float
	 */
	public static function calculate($base, $percentage, $decimals = 2)
	{
		$base       = (float) $base;
		$percentage = (float) $percentage;

		if ($base <= 0 || $percentage <= 0) {
			return 0.0;
		}

		return round($base * $percentage / 100, (int) $decimals);
	}
}
