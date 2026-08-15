<?php
/**
 * Check Uncanny Agent settings access use case.
 *
 * @package Uncanny_Automator
 * @since 7.6
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Uncanny_Agent\Application;

use Uncanny_Automator\App\Feature_State\Application\Get_Feature_State;
use Uncanny_Automator\App\Feature_State\Domain\Feature_State;

/**
 * Checks access to the Uncanny Agent settings page.
 */
final class Check_Uncanny_Agent_Settings_Access {

	/**
	 * Request-scoped feature-state query.
	 *
	 * @var Get_Feature_State
	 */
	private Get_Feature_State $feature_state;

	/**
	 * Create the use case.
	 *
	 * @param Get_Feature_State $feature_state Request-scoped feature-state query.
	 */
	public function __construct( Get_Feature_State $feature_state ) {
		$this->feature_state = $feature_state;
	}

	/**
	 * Check the current settings access.
	 *
	 * @return bool
	 */
	public function execute(): bool {
		return $this->feature_state->execute()->is_visible( Feature_State::AGENT_SETTINGS_TAB );
	}
}
