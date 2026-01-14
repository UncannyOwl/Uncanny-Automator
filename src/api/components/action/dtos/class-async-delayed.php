<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Dtos;

use Uncanny_Automator\Api\Components\Action\Value_Objects\Async_Delay_Number;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Async_Delay_Unit;

/**
 * Async Delayed DTO.
 *
 * Data Transfer Object for delay mode async configuration.
 *
 * @since 7.0.0
 */
class Async_Delayed {

	private ?Async_Delay_Number $delay_number;
	private ?Async_Delay_Unit $delay_unit;

	/**
	 * Constructor.
	 *
	 * @param int|null    $delay_number Delay number value.
	 * @param string|null $delay_unit   Delay unit value.
	 */
	public function __construct( ?int $delay_number = null, ?string $delay_unit = null ) {
		$this->delay_number = null !== $delay_number ? new Async_Delay_Number( $delay_number ) : null;
		$this->delay_unit   = null !== $delay_unit ? new Async_Delay_Unit( $delay_unit ) : null;
	}

	/**
	 * Get delay number.
	 *
	 * @return Async_Delay_Number|null
	 */
	public function get_delay_number(): ?Async_Delay_Number {
		return $this->delay_number;
	}

	/**
	 * Get delay unit.
	 *
	 * @return Async_Delay_Unit|null
	 */
	public function get_delay_unit(): ?Async_Delay_Unit {
		return $this->delay_unit;
	}

	/**
	 * Check if DTO has all required data.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return null !== $this->delay_number && null !== $this->delay_unit;
	}

	/**
	 * Generate human-readable sentence.
	 *
	 * @return string
	 */
	public function to_sentence(): string {
		if ( ! $this->is_complete() ) {
			return '';
		}

		$number = $this->delay_number->get_value();
		$unit   = $this->delay_unit->get_value();

		// Handle singular/plural
		if ( 1 === $number ) {
			$unit = rtrim( $unit, 's' ); // Remove 's' for singular
		}

		return sprintf( '%d %s', $number, $unit );
	}

	/**
	 * Convert to array for storage.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array();

		if ( null !== $this->delay_number ) {
			$data['delay_number'] = $this->delay_number->get_value();
		}

		if ( null !== $this->delay_unit ) {
			$data['delay_unit'] = $this->delay_unit->get_value();
		}

		return $data;
	}
}
