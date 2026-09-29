<?php
/**
 * Local request facts needed before allocation facts are refreshed.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Ports;

/**
 * Describes the current request without making a feature-visibility decision.
 *
 * The application owns this contract. WordPress implements it from local user,
 * setting, and request state. A test can supply the same facts without loading
 * WordPress. Neither implementation may fetch allocation facts or resolve
 * credit-based visibility: that would make eligibility depend on the work it
 * is supposed to precede.
 */
interface Frontend_Feature_Request_Port {

	/**
	 * Whether this is a frontend page request rather than admin or background work.
	 *
	 * @return bool
	 */
	public function is_frontend_request(): bool;

	/**
	 * Whether the current user is authenticated.
	 *
	 * @return bool
	 */
	public function is_logged_in(): bool;

	/**
	 * Whether the user has the existing Agent access capability.
	 *
	 * @return bool
	 */
	public function can_use_agent(): bool;

	/**
	 * Whether local frontend integration rules allow an Agent control.
	 *
	 * @return bool
	 */
	public function agent_control_available(): bool;

	/**
	 * Whether the user has a capability accepted by Page Builder.
	 *
	 * @return bool
	 */
	public function can_use_page_builder(): bool;

	/**
	 * Whether a Page Builder control can appear before credit-based visibility.
	 *
	 * @return bool
	 */
	public function page_builder_control_available(): bool;
}
