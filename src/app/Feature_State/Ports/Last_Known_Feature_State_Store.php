<?php
/**
 * Last-known-good feature-state storage port.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Ports;

use Uncanny_Automator\App\Feature_State\Domain\Feature_State;

/**
 * Persists feature states produced by successful policy evaluation.
 */
interface Last_Known_Feature_State_Store {

	/**
	 * Load the most recent valid feature state.
	 *
	 * @return Feature_State|null
	 */
	public function load(): ?Feature_State;

	/**
	 * Persist a successfully evaluated feature state.
	 *
	 * @param Feature_State $state Successfully evaluated feature state.
	 *
	 * @return void
	 */
	public function save( Feature_State $state ): void;
}
