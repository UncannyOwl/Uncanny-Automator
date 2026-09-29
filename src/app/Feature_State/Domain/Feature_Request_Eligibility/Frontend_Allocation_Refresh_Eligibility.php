<?php
/**
 * Frontend eligibility for allocation refresh work.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Domain\Feature_Request_Eligibility;

/**
 * Decides whether this frontend request has a reason to refresh allocation facts.
 *
 * The caller supplies permissions and the controls allowed by local settings and
 * request context. It must not use credit-based feature visibility to decide
 * whether a control is available here: an empty cache could hide the control
 * and then prevent the refresh needed to show it again.
 *
 * A true result only allows the caller to check whether a refresh is needed.
 * The existing license, cache, lock, and retry checks still decide whether an
 * HTTP call may start. This policy neither performs work nor grants access.
 */
final class Frontend_Allocation_Refresh_Eligibility {

	/**
	 * Check whether the user can use a relevant control on this frontend request.
	 *
	 * Keep permission and availability paired for each feature. A user allowed to
	 * use Agent must not qualify through a Page Builder control they cannot use.
	 *
	 * Page Builder permissions come from its capability provider, not a check for
	 * an administrator role. Its control availability must also account for existing
	 * pages: disabling new-page creation does not disable editing owned content.
	 *
	 * @param bool $is_logged_in                   Whether the current user is authenticated.
	 * @param bool $can_use_agent                  Whether this user has the required Agent permission.
	 * @param bool $agent_control_available        Whether local settings and context allow an Agent control.
	 * @param bool $can_use_page_builder           Whether this user has the required Page Builder permission.
	 * @param bool $page_builder_control_available Whether local settings and context allow a Page Builder control.
	 *
	 * @return bool Whether this frontend request may check for an allocation refresh.
	 */
	public static function is_eligible(
		bool $is_logged_in,
		bool $can_use_agent,
		bool $agent_control_available,
		bool $can_use_page_builder,
		bool $page_builder_control_available
	): bool {
		// Public visitors only need the page they came to read. Keep this guard
		// even if an integration reports a control or permission as available.
		if ( ! $is_logged_in ) {
			return false;
		}

		// Login by itself is not enough. For example, a member reading a lesson
		// must not pay for a credits check when they cannot use either feature.
		// Conversely, an authorized user still needs no refresh when the current
		// page cannot present any relevant control.
		$needs_agent_facts        = $can_use_agent && $agent_control_available;
		$needs_page_builder_facts = $can_use_page_builder && $page_builder_control_available;

		// Either feature can justify checking the shared allocation cache. Do not
		// require access to both: a Page Builder editor may have no Agent access.
		return $needs_agent_facts || $needs_page_builder_facts;
	}
}
