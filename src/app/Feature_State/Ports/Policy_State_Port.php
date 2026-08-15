<?php
/**
 * Automator feature policy state port.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Ports;

use Uncanny_Automator\App\Feature_State\Domain\Policy_State;

/**
 * Supplies one Domain policy state without leaking its evidence source.
 *
 * Implementations may read WordPress options and cached Platform responses,
 * but callers receive the Domain value object rather than arrays or transport
 * fields. Unavailable evidence stays an exception because the release Axis has
 * no technical-failure row for it.
 */
interface Policy_State_Port {

	/**
	 * Get the current feature policy state.
	 *
	 * @return Policy_State
	 *
	 * @throws \RuntimeException When authoritative facts cannot resolve a policy state.
	 */
	public function get_state(): Policy_State;
}
