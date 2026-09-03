<?php
/**
 * Automator feature state policy.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Domain;

/**
 * Projects one already-classified Axis row into its six visibility decisions.
 *
 * This class stays focused on the final mapping: it does not read licenses,
 * inspect allocations, or infer entitlement. Those facts have already been
 * translated into Policy_State by Policy_State_Resolver. That separation keeps
 * the Release 7.5.1.1 table easy to review as a state-to-visibility mapping.
 * The approved table lives in POLICY.md beside this class.
 */
final class Feature_State_Policy {

	/**
	 * Snapshot compatibility revision.
	 *
	 * Bump this value whenever evaluate() changes the policy-to-visibility mapping.
	 * Stored snapshots from another revision are rejected instead of carrying an old
	 * policy decision across the change.
	 */
	public const REVISION = '7.5.1.1';

	/**
	 * Resolve the Release 7.5.1.1 feature state.
	 *
	 * The later "Ideal state" table is intentionally absent. It introduces an
	 * education state that this release's visible/hidden vocabulary cannot express.
	 *
	 * @param Policy_State $policy_state License, connection, and allocation state.
	 *
	 * @return Feature_State
	 */
	public static function evaluate( Policy_State $policy_state ): Feature_State {
		$state = $policy_state->value();

		// Axis row: a disconnected Lite site exposes only its connection path.
		if ( Policy_State::LITE_ONLY_NOT_CONNECTED === $state ) {
			return Feature_State::from_visible_features(
				array( Feature_State::SETUP_WIZARD )
			);
		}

		// These seven Axis rows intentionally expose every product surface. In
		// particular, Lite fully-used and every valid AI state are visible in 7.5.1.1.
		if (
			in_array(
				$state,
				array(
					Policy_State::LITE_ONLY_CONNECTED_100_PERCENT_USED_ALLOCATION,
					Policy_State::LITE_ONLY_CONNECTED_VALID_ALLOCATION,
					Policy_State::PRO_LEGACY_LICENSE_VALID_HEAD_START_ALLOCATION,
					Policy_State::PRO_LEGACY_LICENSE_100_PERCENT_USED_OR_EXPIRED_HEAD_START_ALLOCATION,
					Policy_State::PRO_AI_LICENSE_VALID_ALLOCATION,
					Policy_State::PRO_AI_LICENSE_100_PERCENT_USED_OR_EXPIRED_ALLOCATIONS,
					Policy_State::PRO_AI_LICENSE_NO_ALLOCATION_HISTORY,
				),
				true
			)
		) {
			return Feature_State::from_visible_features( self::product_features() );
		}

		// Pro no-key, invalid, and expired rows retain both settings tabs and the
		// setup wizard, while Page Builder and Agent launch surfaces remain hidden.
		if (
			in_array(
				$state,
				array(
					Policy_State::PRO_NO_LICENSE_ENTERED,
					Policy_State::PRO_INVALID_LICENSE_ENTERED,
					Policy_State::PRO_EXPIRED_LICENSE_ENTERED,
				),
				true
			)
		) {
			return Feature_State::from_visible_features(
				array_merge(
					self::settings_features(),
					array( Feature_State::SETUP_WIZARD )
				)
			);
		}

		if ( Policy_State::PRO_LEGACY_LICENSE_NEVER_HAD_ALLOCATION === $state ) {
			// Unlike the three local Pro-error rows above, this row does not expose
			// Setup Wizard. The Axis grants settings access only.
			return Feature_State::from_visible_features( self::settings_features() );
		}

		// Exactly three supported Axis rows land here: Lite-connected-never and
		// both lifetime rows. They are intentionally all hidden in 7.5.1.1 even
		// though the later Ideal table differs. A future unhandled state also fails
		// closed instead of inheriting visibility from a superficially similar row.
		return Feature_State::from_visible_features( array() );
	}

	/**
	 * Get the five product surfaces used by the Axis.
	 *
	 * Setup Wizard is an onboarding/recovery surface, not product access, and is
	 * therefore deliberately excluded from this group.
	 *
	 * @return string[]
	 */
	private static function product_features(): array {
		return array(
			Feature_State::AGENT_SETTINGS_TAB,
			Feature_State::PAGE_BUILDER_SETTINGS_TAB,
			Feature_State::PAGE_BUILDER_MENU,
			Feature_State::AGENT_LAUNCHER_TAB,
			Feature_State::AGENT_LAUNCHER_TOP_BAR_LINK,
		);
	}

	/**
	 * Get the settings features.
	 *
	 * @return string[]
	 */
	private static function settings_features(): array {
		return array(
			Feature_State::AGENT_SETTINGS_TAB,
			Feature_State::PAGE_BUILDER_SETTINGS_TAB,
		);
	}
}
