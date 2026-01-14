<?php
/**
 * Async Config Converter.
 *
 * Handles conversion of async configuration between MCP format and meta format.
 * Extracts async configuration logic from Action_Instance_Service for better separation of concerns.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services\Action\Helpers
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Action\Utilities;

/**
 * Async Config Converter Class.
 *
 * Converts async configuration from MCP tool format to internal meta format
 * and handles time calculations for delayed executions.
 */
class Async_Config_Converter {

	/**
	 * Convert async configuration from MCP format to meta format.
	 *
	 * @param array $async_config Async configuration from MCP tool.
	 * @return array Flat async meta for storage.
	 */
	public function convert_to_meta( array $async_config ): array {
		$meta = array();

		if ( empty( $async_config['mode'] ) ) {
			return $meta;
		}

		$meta['async_mode'] = $async_config['mode'];

		switch ( $async_config['mode'] ) {
			case 'delay':
				if ( isset( $async_config['delay_number'] ) ) {
					$meta['async_delay_number'] = (int) $async_config['delay_number'];
				}
				if ( isset( $async_config['delay_unit'] ) ) {
					$meta['async_delay_unit'] = $async_config['delay_unit'];
				}

				// Calculate schedule date/time based on delay
				if ( isset( $meta['async_delay_number'], $meta['async_delay_unit'] ) ) {
					$delay_seconds               = $this->calculate_delay_seconds( $meta['async_delay_number'], $meta['async_delay_unit'] );
					$scheduled_timestamp         = time() + $delay_seconds;
					$meta['async_schedule_date'] = gmdate( 'Y-m-d', $scheduled_timestamp );
					$meta['async_schedule_time'] = gmdate( 'h:i A', $scheduled_timestamp );
					$meta['async_sentence']      = sprintf( '%d %s', $meta['async_delay_number'], $meta['async_delay_unit'] );
				}
				break;

			case 'schedule':
				if ( isset( $async_config['schedule_date'] ) ) {
					$meta['async_schedule_date'] = $async_config['schedule_date'];
				}
				if ( isset( $async_config['schedule_time'] ) ) {
					$meta['async_schedule_time'] = $async_config['schedule_time'];
				}

				// Generate sentence for schedule
				if ( isset( $meta['async_schedule_date'], $meta['async_schedule_time'] ) ) {
					$meta['async_sentence'] = sprintf( '%s @ %s', $meta['async_schedule_date'], $meta['async_schedule_time'] );
				}
				break;

			case 'custom':
				if ( isset( $async_config['custom'] ) ) {
					$meta['async_custom'] = $async_config['custom'];
				}
				break;
		}

		return $meta;
	}

	/**
	 * Calculate delay in seconds.
	 *
	 * @param int    $number Number of units.
	 * @param string $unit   Time unit.
	 * @return int Total seconds.
	 */
	private function calculate_delay_seconds( int $number, string $unit ): int {
		$multipliers = array(
			'seconds' => 1,
			'minutes' => 60,
			'hours'   => 3600,
			'days'    => 86400,
			'years'   => 31536000, // 365 days
		);

		return $number * ( $multipliers[ $unit ] ?? 1 );
	}
}
