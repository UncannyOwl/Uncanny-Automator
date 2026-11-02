<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\License;

/**
 * What the customer is shown for a `402` from the platform — the one copy of
 * that rule, for `/v2` and `/v3` alike.
 *
 * Both surfaces run the same license and credit check, in the same order,
 * with the same messages (platform answers contract 4.4), so they show the
 * same thing: the free account's credit-limit refusal becomes the upgrade
 * message (the logs page turns `{{automator_upgrade_link}}` into a link), and
 * every other 402 — a license that is expired, disabled, not valid — is shown
 * as the platform words it.
 *
 * The contract gives both refusals one type (`402 INSUFFICIENT_PRIVILEGES`),
 * so this rule is the `/v2` one: the credit-limit refusal is the one whose
 * text names the upgrade. Api_Server (`/v2`) and Platform_Failure_Message
 * (`/v3`) both read is_credit_limit() and CREDIT_LIMIT, so the two surfaces
 * can never drift apart.
 *
 * @since 8.0.0
 */
final class Payment_Required_Message {

	/**
	 * Shown for the free account's credit-limit refusal. Frozen: `/v2` has
	 * thrown these bytes since before this class existed.
	 */
	public const CREDIT_LIMIT = 'Credit required for action/trigger. Current credits: 0. {{automator_upgrade_link}}.';

	/**
	 * The words that mark the platform's credit-limit refusal.
	 */
	private const CREDIT_LIMIT_MARKER = 'Upgrade to Uncanny Automator Pro';

	/**
	 * Whether a 402's description is the free account's credit-limit refusal.
	 *
	 * @param string $description The platform's description.
	 *
	 * @return bool
	 */
	public static function is_credit_limit( string $description ): bool {
		return false !== strpos( $description, self::CREDIT_LIMIT_MARKER );
	}
}
