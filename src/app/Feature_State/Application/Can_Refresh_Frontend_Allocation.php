<?php
/**
 * Ask whether this frontend request needs allocation refresh work.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Application;

use Uncanny_Automator\App\Feature_State\Domain\Feature_Request_Eligibility\Frontend_Allocation_Refresh_Eligibility;
use Uncanny_Automator\App\Feature_State\Ports\Frontend_Feature_Request_Port;

/**
 * Reads local request facts through a port and asks the domain for its decision.
 *
 * The use case knows neither WordPress functions nor the concrete adapter.
 * Its caller still owns the cache, license, retry, and refresh-lock checks.
 */
final class Can_Refresh_Frontend_Allocation {

	private Frontend_Feature_Request_Port $request;

	/**
	 * Store the dependency without reading request state during construction.
	 *
	 * @param Frontend_Feature_Request_Port $request Local request facts.
	 */
	public function __construct( Frontend_Feature_Request_Port $request ) {
		$this->request = $request;
	}

	/**
	 * Check eligibility without performing a refresh or changing feature state.
	 *
	 * @return bool
	 */
	public function execute(): bool {
		try {
			// This query only owns frontend eligibility. Admin menus, launcher REST,
			// and scheduled refreshes retain their own entry points and rules.
			if ( ! $this->request->is_frontend_request() || ! $this->request->is_logged_in() ) {
				return false;
			}

			// A user without permission cannot use that feature's control. Avoid its
			// settings reads and integration filters on ordinary member page loads.
			$can_use_agent        = $this->request->can_use_agent();
			$can_use_page_builder = $this->request->can_use_page_builder();

			return Frontend_Allocation_Refresh_Eligibility::is_eligible(
				true,
				$can_use_agent,
				$can_use_agent && $this->request->agent_control_available(),
				$can_use_page_builder,
				$can_use_page_builder && $this->request->page_builder_control_available()
			);
		} catch ( \Throwable $error ) {
			// A failing local read or third-party filter must not break the visitor's
			// page or start optional remote work. This does not decide visibility:
			// the existing feature-state query still owns its saved-state fallback.
			return false;
		}
	}
}
