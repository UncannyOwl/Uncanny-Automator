<?php
/**
 * Delay-Schedule Wire Mapper.
 *
 * Single boundary between the immutable action-meta wire format
 * (`async_*` post-meta keys) and the canonical internal block vocabulary
 * (`DELAY_*` / `SCHEDULE_*` field names, mode values `delay`/`schedule`/`token`).
 *
 * Per CLAUDE.md Rule 17, this is the ONLY place in `src/app/` that may
 * read `async_mode`, `async_delay_number`, `async_delay_unit`,
 * `async_schedule_date`, `async_schedule_time`, or `async_custom`.
 *
 * Mode-value translation: action-meta uses `custom` for token-driven
 * scheduling; block UI uses `token`. The mapper translates one to the
 * other at the boundary.
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Action\Services\Utilities
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Action\Services\Utilities;

/**
 * Delay-Schedule Wire Mapper Class.
 */
final class Delay_Schedule_Wire_Mapper {

	/**
	 * Translate uo-action `async_*` post-meta into block-vocabulary config.
	 *
	 * Output uses block-side vocabulary: `mode='token'` (NOT `custom`),
	 * `delay_value`/`delay_unit`/`schedule_date`/`schedule_time`/`delay_token`.
	 *
	 * @param array<string,mixed> $meta Raw post-meta from uo-action.
	 * @return array<string,mixed> Config keyed by block-vocabulary names.
	 */
	public function meta_to_config( array $meta ): array {

		if ( empty( $meta['async_mode'] ) ) {
			return array();
		}

		$async_mode = (string) $meta['async_mode'];
		$config     = array(
			'mode' => $this->wire_mode_to_block_mode( $async_mode ),
		);

		if ( 'delay' === $async_mode ) {
			$config['delay_value'] = (int) ( $meta['async_delay_number'] ?? 0 );
			$config['delay_unit']  = (string) ( $meta['async_delay_unit'] ?? 'minutes' );
		}

		if ( 'schedule' === $async_mode ) {
			$config['schedule_date'] = (string) ( $meta['async_schedule_date'] ?? '' );
			$config['schedule_time'] = (string) ( $meta['async_schedule_time'] ?? '' );
		}

		if ( 'custom' === $async_mode ) {
			$config['delay_token'] = (string) ( $meta['async_custom'] ?? '' );
		}

		return $config;
	}

	/**
	 * Translate the wire-format mode value to the block-vocabulary mode value.
	 *
	 * `custom` is the wire-format spelling; `token` is the block-vocabulary
	 * spelling. All other modes round-trip unchanged.
	 *
	 * @param string $wire Wire-format mode (`delay|schedule|custom`).
	 *
	 * @return string Block-vocabulary mode (`delay|schedule|token`).
	 */
	private function wire_mode_to_block_mode( string $wire ): string {
		return 'custom' === $wire ? 'token' : $wire;
	}

	/**
	 * Translate the block-vocabulary mode value back to the wire-format value.
	 *
	 * Inverse of {@see self::wire_mode_to_block_mode()}. Public because
	 * emitters of the legacy `miscellaneous->delay_schedule` shape need the
	 * same translation, and Rule 17 requires this vocabulary to have exactly
	 * one owner.
	 *
	 * @param string $block Block-vocabulary mode (`delay|schedule|token`).
	 *
	 * @return string Wire-format mode (`delay|schedule|custom`).
	 */
	public function block_mode_to_wire_mode( string $block ): string {
		return 'token' === $block ? 'custom' : $block;
	}

	/**
	 * Return every `async_*` post-meta key of the wire format.
	 *
	 * Callers that need to clear delay/schedule state from an action's meta
	 * can use this list rather than hardcoding `async_*` keys (which would
	 * violate Rule 17 if scattered across the codebase).
	 *
	 * @return string[] Flat list of post-meta keys.
	 */
	public function meta_keys(): array {
		return array(
			'async_mode',
			'async_delay_number',
			'async_delay_unit',
			'async_schedule_date',
			'async_schedule_time',
			'async_custom',
			'async_sentence',
		);
	}
}
