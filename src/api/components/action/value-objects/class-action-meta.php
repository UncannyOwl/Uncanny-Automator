<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

use Uncanny_Automator\Api\Components\Action\Dtos\Async_Delayed;
use Uncanny_Automator\Api\Components\Action\Dtos\Async_Scheduled;
use Uncanny_Automator\Api\Components\Action\Dtos\Async_Custom_Dto;

/**
 * Action Meta Value Object.
 *
 * Represents action configuration and settings.
 * Flexible array structure for action-specific data.
 * Follows the same pattern as Trigger_Configuration.
 *
 * @since 7.0.0
 */
class Action_Meta {

	private array $value;
	private ?Async_Mode $async_mode           = null;
	private ?Async_Delayed $async_delayed     = null;
	private ?Async_Scheduled $async_scheduled = null;
	private ?Async_Custom_Dto $async_custom   = null;

	/**
	 * Constructor.
	 *
	 * @param array $value Action meta array.
	 * @throws \InvalidArgumentException If invalid meta.
	 */
	public function __construct( array $value ) {
		$this->validate( $value );
		$this->value = $value;
		$this->parse_async_configuration( $value );
		$this->calculate_delay_schedule_once();
	}

	/**
	 * Get value.
	 *
	 * @return array
	 */
	public function get_value(): array {
		return $this->value;
	}

	/**
	 * Get specific meta value.
	 *
	 * @param string $key Meta key.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		return $this->value[ $key ] ?? $default_value;
	}

	/**
	 * Check if meta key exists.
	 *
	 * @param string $key Meta key to check.
	 * @return bool
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->value );
	}

	/**
	 * Check if meta is empty.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return empty( $this->value );
	}

	/**
	 * To array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return $this->value;
	}

	/**
	 * Get async mode.
	 *
	 * @return Async_Mode|null
	 */
	public function get_async_mode(): ?Async_Mode {
		return $this->async_mode;
	}

	/**
	 * Get async delayed configuration.
	 *
	 * @return Async_Delayed|null
	 */
	public function get_async_delayed(): ?Async_Delayed {
		return $this->async_delayed;
	}

	/**
	 * Get async scheduled configuration.
	 *
	 * @return Async_Scheduled|null
	 */
	public function get_async_scheduled(): ?Async_Scheduled {
		return $this->async_scheduled;
	}

	/**
	 * Get async custom configuration.
	 *
	 * @return Async_Custom_Dto|null
	 */
	public function get_async_custom(): ?Async_Custom_Dto {
		return $this->async_custom;
	}

	/**
	 * Get async sentence for display.
	 *
	 * @return string
	 */
	public function get_async_sentence(): string {
		if ( null === $this->async_mode ) {
			return '';
		}

		if ( $this->async_mode->is_delay() && null !== $this->async_delayed ) {
			return $this->async_delayed->to_sentence();
		}

		if ( $this->async_mode->is_schedule() && null !== $this->async_scheduled ) {
			return $this->async_scheduled->to_sentence();
		}

		if ( $this->async_mode->is_custom() && null !== $this->async_custom ) {
			return $this->async_custom->to_sentence();
		}

		return '';
	}

	/**
	 * Convert async configuration to flat array for storage.
	 *
	 * @return array
	 */
	public function get_async_as_flat_array(): array {
		$data = array();

		if ( null === $this->async_mode ) {
			return $data;
		}

		$data['async_mode'] = $this->async_mode->get_value();

		if ( $this->async_mode->is_delay() && null !== $this->async_delayed ) {
			$delay_data = $this->async_delayed->to_array();
			if ( isset( $delay_data['delay_number'] ) ) {
				$data['async_delay_number'] = $delay_data['delay_number'];
			}
			if ( isset( $delay_data['delay_unit'] ) ) {
				$data['async_delay_unit'] = $delay_data['delay_unit'];
			}

			// Use pre-calculated schedule values (calculated once in constructor)
			if ( isset( $this->value['async_schedule_date'] ) ) {
				$data['async_schedule_date'] = $this->value['async_schedule_date'];
			}
			if ( isset( $this->value['async_schedule_time'] ) ) {
				$data['async_schedule_time'] = $this->value['async_schedule_time'];
			}

			$data['async_sentence'] = $this->get_async_sentence();
		}

		if ( $this->async_mode->is_schedule() && null !== $this->async_scheduled ) {
			$schedule_data = $this->async_scheduled->to_array();
			if ( isset( $schedule_data['schedule_date'] ) ) {
				$data['async_schedule_date'] = $schedule_data['schedule_date'];
			}
			if ( isset( $schedule_data['schedule_time'] ) ) {
				$data['async_schedule_time'] = $schedule_data['schedule_time'];
			}
			$data['async_sentence'] = $this->get_async_sentence();
		}

		if ( $this->async_mode->is_custom() && null !== $this->async_custom ) {
			$custom_data = $this->async_custom->to_array();
			if ( isset( $custom_data['custom'] ) ) {
				$data['async_custom'] = $custom_data['custom'];
			}
		}

		return $data;
	}

	/**
	 * Parse async configuration from meta array.
	 *
	 * @param array $value Meta array.
	 */
	private function parse_async_configuration( array $value ): void {
		// Check for async mode
		if ( ! isset( $value['async_mode'] ) ) {
			return; // No async configuration
		}

		try {
			$this->async_mode = new Async_Mode( $value['async_mode'] );

			if ( $this->async_mode->is_delay() ) {
				$delay_number = isset( $value['async_delay_number'] ) ? (int) $value['async_delay_number'] : null;
				$delay_unit   = $value['async_delay_unit'] ?? null;
				if ( null !== $delay_number && null !== $delay_unit ) {
					$this->async_delayed = new Async_Delayed( $delay_number, $delay_unit );
				}
			}

			if ( $this->async_mode->is_schedule() ) {
				$schedule_date = $value['async_schedule_date'] ?? null;
				$schedule_time = $value['async_schedule_time'] ?? null;
				if ( null !== $schedule_date && null !== $schedule_time ) {
					$this->async_scheduled = new Async_Scheduled( $schedule_date, $schedule_time );
				}
			}

			if ( $this->async_mode->is_custom() ) {
				$custom = $value['async_custom'] ?? null;
				if ( null !== $custom ) {
					$this->async_custom = new Async_Custom_Dto( $custom );
				}
			}
		} catch ( \InvalidArgumentException $e ) {
			// Skip invalid async configuration silently.
			return;
		}
	}

	/**
	 * Calculate and store scheduled timestamp for delay-based async actions.
	 *
	 * Ensures immutability by calculating the scheduled time once during construction.
	 * Only calculates if it's a delay-based action without pre-stored schedule values.
	 *
	 * @since 7.0.0
	 */
	private function calculate_delay_schedule_once(): void {
		// Only process delay-based async actions
		if ( null === $this->async_mode || ! $this->async_mode->is_delay() || null === $this->async_delayed ) {
			return;
		}

		// Only calculate if not already stored (prevents recalculation and drift)
		if ( isset( $this->value['async_schedule_date'] ) && isset( $this->value['async_schedule_time'] ) ) {
			return;
		}

		// Calculate the scheduled timestamp once and store it
		if ( $this->async_delayed->is_complete() ) {
			$delay_seconds       = $this->async_delayed->get_delay_unit()->to_seconds(
				$this->async_delayed->get_delay_number()->get_value()
			);
			$scheduled_timestamp = time() + $delay_seconds;

			// Store in value array to maintain immutability
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- Using local timezone for user-facing schedule display
			$this->value['async_schedule_date'] = date( 'Y-m-d', $scheduled_timestamp );
			// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- Using local timezone for user-facing schedule display
			$this->value['async_schedule_time'] = date( 'h:i A', $scheduled_timestamp );
		}
	}

	/**
	 * Validate action meta.
	 *
	 * @param array $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( array $value ): void {
		// Meta can be empty array - no additional validation needed
		// Individual actions will validate their specific requirements
	}
}
