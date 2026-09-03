<?php
/**
 * License transient keys.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transient\Domain;

/**
 * Lists the cached evidence that belongs to the current Automator license.
 *
 * These keys must move together when the license or connected Free account
 * changes. Other Automator transients are deliberately excluded because they
 * cache unrelated recipe, integration, dashboard, or diagnostic data.
 */
class License_Transient_Keys {

	public const LICENSE               = 'automator_api_license';
	public const LICENSE_CHECK_FAILED  = 'automator_license_check_failed';
	public const CREDIT_DATA           = 'automator_api_credit_data';
	public const CREDITS               = 'automator_api_credits';
	public const LLM_ALLOCATION_FACTS  = 'automator_llm_allocation_facts';
	public const LLM_ALLOCATION_FAILED = 'automator_llm_allocation_facts_failed';

	public const FEATURE_STATE_LAST_KNOWN_GOOD = 'automator_feature_state_last_known_good';

	/**
	 * Get all license-derived transient keys.
	 *
	 * The failure marker is part of the group because it short-circuits license
	 * reads. Keeping it after a successful identity change would leave the new
	 * license fail-closed until that marker expires.
	 *
	 * @return string[]
	 */
	public static function get_all(): array {
		return array(
			self::LICENSE,
			self::LICENSE_CHECK_FAILED,
			self::CREDIT_DATA,
			self::CREDITS,
			self::LLM_ALLOCATION_FACTS,
			self::LLM_ALLOCATION_FAILED,
			self::FEATURE_STATE_LAST_KNOWN_GOOD,
		);
	}
}
