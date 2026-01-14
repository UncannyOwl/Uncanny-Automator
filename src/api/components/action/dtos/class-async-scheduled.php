<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action\Dtos;

use Uncanny_Automator\Api\Components\Action\Value_Objects\Async_Schedule_Date;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Async_Schedule_Time;

/**
 * Async Scheduled DTO.
 *
 * Data Transfer Object for schedule mode async configuration.
 *
 * @since 7.0.0
 */
class Async_Scheduled {

	private ?Async_Schedule_Date $schedule_date;
	private ?Async_Schedule_Time $schedule_time;

	/**
	 * Constructor.
	 *
	 * @param string|null $schedule_date Schedule date value.
	 * @param string|null $schedule_time Schedule time value.
	 */
	public function __construct( ?string $schedule_date = null, ?string $schedule_time = null ) {
		$this->schedule_date = null !== $schedule_date ? new Async_Schedule_Date( $schedule_date ) : null;
		$this->schedule_time = null !== $schedule_time ? new Async_Schedule_Time( $schedule_time ) : null;
	}

	/**
	 * Get schedule date.
	 *
	 * @return Async_Schedule_Date|null
	 */
	public function get_schedule_date(): ?Async_Schedule_Date {
		return $this->schedule_date;
	}

	/**
	 * Get schedule time.
	 *
	 * @return Async_Schedule_Time|null
	 */
	public function get_schedule_time(): ?Async_Schedule_Time {
		return $this->schedule_time;
	}

	/**
	 * Check if DTO has all required data.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return null !== $this->schedule_date && null !== $this->schedule_time;
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

		$date = $this->schedule_date->get_value();
		$time = $this->schedule_time->get_value();

		return sprintf( '%s @ %s', $date, $time );
	}

	/**
	 * Convert to array for storage.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array();

		if ( null !== $this->schedule_date ) {
			$data['schedule_date'] = $this->schedule_date->get_value();
		}

		if ( null !== $this->schedule_time ) {
			$data['schedule_time'] = $this->schedule_time->get_value();
		}

		return $data;
	}
}
