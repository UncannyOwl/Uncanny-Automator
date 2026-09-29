<?php
/**
 * Operator explanations for feature-state status values.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Presentation;

use Uncanny_Automator\App\Feature_State\Application\Get_Feature_State;
use Uncanny_Automator\App\Feature_State\Domain\Pending_First_Use_Allocation;

/**
 * Keeps diagnostic wording separate from the facts and policy decisions.
 */
final class Feature_State_Report_Help {

	/** @var array<string,array<string,string>> */
	private array $messages;

	/**
	 * Keep diagnostic descriptions in English so operators and support see the
	 * same wording alongside the original field identifiers. Translation lookups
	 * are intentionally omitted for this diagnostic report.
	 */
	public function __construct() {
		$this->messages = array(
			'pro_plugin_active' => array(
				'true' => 'Automator Pro is active. The feature policy uses the Pro license rules.',
				'false' => 'Automator Pro is inactive. The feature policy uses the Lite connection rules.',
			),
			'pro_license_key_present' => array(
				'true' => 'A Pro license key is saved on this site. This alone does not mean the license is valid.',
				'false' => 'No Pro license key is saved on this site.',
			),
			'lite_license_key_present' => array(
				'true' => 'A Lite connection key is saved on this site. This alone does not mean the connection is valid.',
				'false' => 'No Lite connection key is saved on this site.',
			),
			'license_cache_present' => array(
				'true' => 'A cached license response is available. Check its status and whether it matches the current license.',
				'false' => 'No usable cached license response is available. License details cannot be read from the cache.',
			),
			'license_cache_key_matches' => array(
				'true' => 'The cached license response belongs to the currently selected license key.',
				'false' => 'The cached response does not match the selected license, or no license key is selected. It cannot be used to check a valid Pro license.',
			),
			'is_lifetime' => array(
				'true' => 'The cached license response identifies this as a lifetime license. Separate lifetime policy rules apply.',
				'false' => 'The cached license response identifies this as a non-lifetime license.',
			),
			'allocation_cache_present' => array(
				'true' => 'A credit allocation response is saved on this site. It still needs to match the selected license and have a valid format.',
				'false' => 'No allocation response is cached. Missing data is not the same as zero allocations.',
			),
			'allocation_cache_key_matches' => array(
				'true' => 'The cached allocation response belongs to the currently selected license key.',
				'false' => 'The cached allocation response cannot be tied to the selected license. The policy cannot use it.',
			),
			'success' => array(
				'true' => 'The allocation service reported a successful lookup. Zero counts are valid results, not a connection failure.',
				'false' => 'The allocation service did not report a successful lookup. These facts cannot be used for a new feature visibility check.',
			),
			'source' => array(
				Get_Feature_State::SOURCE_RESOLVED => 'Visibility was calculated successfully for this page request.',
				Get_Feature_State::SOURCE_LAST_KNOWN_GOOD => 'The current feature visibility check failed. This page is using the last valid saved result.',
				Get_Feature_State::SOURCE_ALL_HIDDEN_FALLBACK => 'The current feature visibility check failed and no valid saved result was available. All six listed features are hidden.',
				'unavailable' => 'The feature visibility check is unavailable. No result could be inspected.',
			),
			'resolution_error' => array(
				'null' => 'No resolution error was recorded.',
				'*' => 'The feature visibility check failed with this message. Check Source to see how visibility was determined for this page.',
			),
			'error' => array(
				'*' => 'This section could not be read. Other sections are collected separately and may still be available.',
			),
			'extended_support_license' => array(
				'*' => 'The license API does not provide extended-support status.',
			),
			'allocation_schema_version' => array(
				'*' => 'The format version of the cached allocation data. It must match the version supported by this plugin.',
			),
			'active_allocations' => array(
				'0' => 'The service reported no active credit allocations. Check pending first-use allocation for an eligible grant.',
				'*' => 'The number of active credit allocations reported by the service. This is a count of allocations, not the remaining credit balance.',
			),
			'used_allocations' => array(
				'0' => 'The service reported no fully used allocations.',
				'*' => 'The number of credit allocations that have been fully used. These show that credits were allocated before.',
			),
			'expired_allocations' => array(
				'0' => 'The service reported no expired allocations.',
				'*' => 'The number of expired credit allocations reported by the service. These show that credits were allocated before.',
			),
			'pending_first_use_allocation' => array(
				'null' => 'The service reported no credit grant waiting to be created on first use.',
				Pending_First_Use_Allocation::PHASE_1_LITE => 'The cached response reports a Phase 1 Lite credit grant available on first use.',
				Pending_First_Use_Allocation::LEGACY_HEAD_START => 'The cached response reports a Legacy Head Start credit grant available on first use.',
			),
			'observed_at' => array(
				'*' => 'When the allocation service checked these facts. This is the age of the information, not the date credits were granted.',
			),
			'pro_license_status' => array(
				'valid' => 'The stored license status is valid. Allocation and other policy checks still determine feature visibility.',
				'expired' => 'The stored Pro license status is expired.',
				'(empty)' => 'No license status is stored locally.',
				'*' => 'The displayed license status is not valid. Check the license connection or activation.',
			),
			'lite_license_status' => array(
				'valid' => 'The stored Lite connection status is valid. A saved key is also required; allocation facts determine product access.',
				'(empty)' => 'No Lite connection status is stored locally.',
				'*' => 'The Lite connection is not marked valid. The site may need to reconnect.',
			),
			'policy_state' => array(
				'null' => 'The current feature visibility check did not produce a policy state. Check Resolution error and Source.',
				'lite_only_not_connected' => 'This Lite site is not connected. Only the setup wizard is allowed by the visibility rules.',
				'lite_only_connected_never_had_allocation' => 'This Lite site is connected but has no allocation history or pending first-use grant. Product features are hidden.',
				'lite_only_connected_100_percent_used_allocation' => 'This Lite site has fully used its allocation. Product features remain visible; chat access still depends on credits.',
				'lite_only_connected_valid_allocation' => 'This Lite site has an active allocation or an eligible first-use grant. Product features are visible.',
				'pro_no_license_entered' => 'Pro is active but no license key is entered. Settings and the setup wizard remain available; product launchers are hidden.',
				'pro_invalid_license_entered' => 'The entered Pro license is not valid. Settings and the setup wizard remain available; product launchers are hidden.',
				'pro_expired_license_entered' => 'The entered Pro license is expired. Settings and the setup wizard remain available; product launchers are hidden.',
				'pro_legacy_license_valid_head_start_allocation' => 'This Legacy Pro license has an active allocation or an eligible Head Start grant. Product features are visible.',
				'pro_legacy_license_100_percent_used_or_expired_head_start_allocation' => 'This Legacy Pro license has fully used or expired allocation history. Product features remain visible; chat access still depends on credits.',
				'pro_legacy_license_never_had_allocation' => 'This Legacy Pro license has no allocation history or pending Head Start grant. Settings remain available, but launchers and the Page Builder menu are hidden.',
				'pro_ai_license_valid_allocation' => 'This AI plan has an active allocation. Product features are visible.',
				'pro_ai_license_100_percent_used_or_expired_allocations' => 'This AI plan has fully used or expired allocations. Product features remain visible; chat access still depends on credits.',
				'pro_ai_license_no_allocation_history' => 'This valid AI plan has no allocation history. Product features remain visible; this does not guarantee credits are available.',
				'lifetime_license_no_extended_support' => 'This lifetime license has no active extended support. The current policy hides all six features.',
				'lifetime_license_active_extended_support_license' => 'This lifetime license has active extended support. The current policy still hides all six features.',
			),
		);
	}

	/**
	 * Explain the displayed observation without treating missing facts as false.
	 *
	 * @param string $field Original diagnostic field name.
	 * @param string $value Displayed value from the report.
	 * @return string
	 */
	public function explain( string $field, string $value ): string {
		if ( ! isset( $this->messages[ $field ] ) ) {
			return '';
		}

		if ( 'Unavailable' === $value ) {
			return 'This fact is missing or cannot be read from the available data. It does not mean false or zero.';
		}
		if ( 'Invalid value' === $value ) {
			return 'The stored fact has an unexpected format and cannot be interpreted reliably.';
		}
		$messages = $this->messages[ $field ];
		if ( isset( $messages[ $value ] ) ) {
			return $messages[ $value ];
		}
		if ( 'null' === $value && ! in_array( $field, array( 'pro_license_status', 'lite_license_status' ), true ) ) {
			return 'This fact is unknown or the check could not be performed with the available data. It does not mean false or zero.';
		}
		return $messages['*'] ?? 'This value is not recognized for this field. Check the cached data and policy revision.';
	}
}
