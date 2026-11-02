<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Addons;

use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\License_Summary_Snapshot;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Summary_Scenario;

/**
 * Shapes one addon group the AddOns page strict-parses.
 *
 * Owns the composite list scenario grammar and the page-notice tables. The
 * `page_notice` key is omitted entirely for an entitled list, because the
 * frontend schema types it as optional rather than empty.
 */
final class Addons_List_Response {

	/**
	 * Build the inner list payload.
	 *
	 * @param string                   $list_code  Either `plus` or `elite`.
	 * @param bool                     $has_access Whether the license entitles this group.
	 * @param array<int,array>         $cards      Shaped addon cards.
	 * @param License_Summary_Snapshot $snapshot   Resolved license state.
	 *
	 * @return array<string,mixed>
	 */
	public static function body( string $list_code, bool $has_access, array $cards, License_Summary_Snapshot $snapshot ): array {

		$scenario_id = self::scenario_id( $list_code, $has_access, $snapshot );

		$body = array(
			'scenario_id' => $scenario_id,
			'has_access'  => $has_access,
		);

		if ( ! $has_access ) {
			$body['page_notice'] = self::page_notice( $list_code, $snapshot );
		}

		$body['addons'] = $cards;

		return $body;
	}

	/**
	 * The composite scenario describing why this group is or is not
	 * reachable.
	 *
	 * @param string                   $list_code  List code.
	 * @param bool                     $has_access Whether the license entitles this group.
	 * @param License_Summary_Snapshot $snapshot   Resolved license state.
	 *
	 * @return string
	 */
	private static function scenario_id( string $list_code, bool $has_access, License_Summary_Snapshot $snapshot ): string {

		if ( $has_access ) {
			return 'has-access';
		}

		// Summary_Scenario owns this derivation; rebuilding it from the status
		// here would disagree with the notice below for any status the VO
		// folds into `invalid` (EDD also returns key_mismatch,
		// item_name_mismatch, no_activations_left).
		return $snapshot->scenario()->id() . '-' . self::requires_suffix( $list_code );
	}

	/**
	 * The tier this group requires.
	 *
	 * @param string $list_code List code.
	 *
	 * @return string
	 */
	private static function requires_suffix( string $list_code ): string {
		return Addon::TIER_ELITE === $list_code ? 'requires-elite' : 'requires-pro-' . $list_code;
	}

	/**
	 * The notice shown above an unreachable group.
	 *
	 * @param string                   $list_code List code.
	 * @param License_Summary_Snapshot $snapshot  Resolved license state.
	 *
	 * @return array<string,mixed>
	 */
	private static function page_notice( string $list_code, License_Summary_Snapshot $snapshot ): array {

		$scenario = $snapshot->scenario();

		// A broken license is keyed on the license state, not on the list code:
		// the elite list never produces a `requires-pro-{plan}` suffix to match.
		if ( $scenario->needs_license_fix() ) {
			return array(
				'message' => self::valid_license_message( $list_code ),
				'type'    => 'error',
				'cta'     => Addon_Ctas::fix_license(),
			);
		}

		if ( Summary_Scenario::PRO_NOT_INSTALLED === $scenario->id() ) {
			return array(
				'message' => self::needs_pro_message( $list_code ),
				'type'    => 'error',
				'cta'     => Addon_Ctas::get_pro(),
			);
		}

		if ( Summary_Scenario::PRO_DEACTIVATED === $scenario->id() ) {
			return array(
				'message' => self::needs_pro_message( $list_code ),
				'type'    => 'error',
				'cta'     => Addon_Ctas::activate_pro(),
			);
		}

		return array(
			'message' => self::needs_upgrade_message( $list_code ),
			'type'    => 'info',
			'cta'     => Addon_Ctas::upgrade_plan(),
		);
	}

	/**
	 * Message for a site whose license needs repair.
	 *
	 * @param string $list_code List code.
	 *
	 * @return string
	 */
	private static function valid_license_message( string $list_code ): string {
		return Addon::TIER_ELITE === $list_code
			? esc_html_x( 'Get access to these addons with Automator Pro with a valid Elite license', 'Addons', 'uncanny-automator' )
			: esc_html_x( 'Get access to these addons with Automator Pro with a valid Plus or Elite license', 'Addons', 'uncanny-automator' );
	}

	/**
	 * Message for a site without a usable Pro plugin.
	 *
	 * @param string $list_code List code.
	 *
	 * @return string
	 */
	private static function needs_pro_message( string $list_code ): string {
		return Addon::TIER_ELITE === $list_code
			? esc_html_x( 'Get access to these addons with Automator Pro with an Elite license', 'Addons', 'uncanny-automator' )
			: esc_html_x( 'Get access to these addons with Automator Pro with a Plus or Elite license', 'Addons', 'uncanny-automator' );
	}

	/**
	 * Message for a valid license on too low a tier.
	 *
	 * @param string $list_code List code.
	 *
	 * @return string
	 */
	private static function needs_upgrade_message( string $list_code ): string {
		return Addon::TIER_ELITE === $list_code
			? esc_html_x( 'Get access to these with an Elite license', 'Addons', 'uncanny-automator' )
			: esc_html_x( 'Get access to these with a Plus or Elite license', 'Addons', 'uncanny-automator' );
	}
}
