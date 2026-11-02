<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\License;

use Uncanny_Automator\App\Plan\Addons\Value_Objects\License_Summary_Snapshot;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Summary_Scenario;
use Uncanny_Automator\App\Plan\Domain\Plan_Levels;
use Uncanny_Automator\App\Transports\Restful\Addons\Addon_Ctas;

/**
 * Shapes the license summary the AddOns page strict-parses.
 *
 * Every frozen string of that contract lives here: the tier vocabulary, the
 * per-scenario notice and CTA tables, and the two normalizations the frontend
 * schema forces (see the constants below). The snapshot stays the domain
 * truth; this class is presentation.
 */
final class License_Summary_Response {

	/**
	 * The statuses the frontend's LicenseStatus enum accepts. Anything else
	 * — notably EDD's `inactive` — is reported as `invalid`, while
	 * `scenario_id` keeps naming what actually happened.
	 *
	 * @var array<int,string>
	 */
	const WIRE_STATUSES = array(
		'missing',
		'missing_url',
		'license_not_activable',
		'disabled',
		'no_activations_left',
		'expired',
		'key_mismatch',
		'invalid_item_id',
		'item_name_mismatch',
		'site_inactive',
		'invalid',
		'valid',
	);

	/**
	 * Build the summary response body.
	 *
	 * @param License_Summary_Snapshot $snapshot Resolved license state.
	 *
	 * @return array<string,mixed>
	 */
	public static function body( License_Summary_Snapshot $snapshot ): array {
		return array(
			'success' => true,
			'data'    => array(
				'summary' => array(
					'scenario_id'   => $snapshot->scenario()->id(),
					'license_owner' => array(
						'name'  => $snapshot->owner_name(),
						'email' => $snapshot->owner_email(),
					),
					'current_plan'  => self::current_plan( $snapshot->scenario() ),
					'addons'        => array(
						'available' => $snapshot->addons_available(),
						'total'     => $snapshot->addons_total(),
					),
					'status'        => self::status( $snapshot->status() ),
					'notice'        => self::notice( $snapshot ),
					'cta'           => self::ctas( $snapshot->scenario() ),
				),
			),
		);
	}

	/**
	 * The tier the wire reports.
	 *
	 * Driven by the scenario, not by the resolved plan: the frontend schema
	 * cross-checks the two and requires the lite tier for every scenario
	 * that is not an active license — including the license-problem states,
	 * where `get_resolved_plan()` collapses to `basic`.
	 *
	 * @param Summary_Scenario $scenario Resolved scenario.
	 *
	 * @return string
	 */
	private static function current_plan( Summary_Scenario $scenario ): string {

		switch ( $scenario->id() ) {
			case Summary_Scenario::ACTIVE_BASIC:
				return Plan_Levels::PRO_BASIC;
			case Summary_Scenario::ACTIVE_PLUS:
				return Plan_Levels::PRO_PLUS;
			case Summary_Scenario::ACTIVE_ELITE:
				return Plan_Levels::PRO_ELITE;
			default:
				return Plan_Levels::LITE;
		}
	}

	/**
	 * The status the wire reports.
	 *
	 * @param string $status Cross-checked license status.
	 *
	 * @return string
	 */
	private static function status( string $status ): string {
		return in_array( $status, self::WIRE_STATUSES, true ) ? $status : 'invalid';
	}

	/**
	 * The notice for the scenario.
	 *
	 * The `content` key is omitted rather than emitted empty — the frontend
	 * requires a non-empty string when it is present.
	 *
	 * @param License_Summary_Snapshot $snapshot Resolved license state.
	 *
	 * @return array<string,string>
	 */
	private static function notice( License_Summary_Snapshot $snapshot ): array {

		$scenario = $snapshot->scenario();

		if ( $scenario->is_active_license() ) {
			return array(
				'heading' => sprintf(
					/* translators: %s is the plan label, e.g. "Plus", "Plus AI + Automation Monthly", "Elite (Legacy)". */
					esc_html_x( 'Your Pro %s license is active', 'Addons', 'uncanny-automator' ),
					esc_html( $snapshot->plan_display_label() )
				),
				'type'    => 'success',
			);
		}

		switch ( $scenario->id() ) {
			case Summary_Scenario::PRO_NOT_INSTALLED:
				return array(
					'heading' => esc_html_x( "You're currently on Automator Lite", 'Addons', 'uncanny-automator' ),
					'content' => esc_html_x( "It looks like you're using the Lite plan, which **doesn't include addons**. **Upgrade** to Automator Pro to get access to all addon features.", 'Addons', 'uncanny-automator' ),
					'type'    => 'warning',
				);
			case Summary_Scenario::PRO_DEACTIVATED:
				return array(
					'heading' => esc_html_x( 'Automator Pro is installed but inactive', 'Addons', 'uncanny-automator' ),
					'content' => esc_html_x( 'To update your addons and **gain access to the latest released addons**, you need to **activate Automator Pro**. Please activate Automator Pro to unlock these additional features.', 'Addons', 'uncanny-automator' ),
					'type'    => 'warning',
				);
			case Summary_Scenario::INACTIVE:
				return self::license_problem_notice( esc_html_x( 'Your license is inactive', 'Addons', 'uncanny-automator' ) );
			case Summary_Scenario::EXPIRED:
				return self::license_problem_notice( esc_html_x( 'Your license has expired', 'Addons', 'uncanny-automator' ) );
			case Summary_Scenario::DISABLED:
				return self::license_problem_notice( esc_html_x( 'Your license is disabled', 'Addons', 'uncanny-automator' ) );
			case Summary_Scenario::SITE_INACTIVE:
				return self::license_problem_notice( esc_html_x( 'Your license is not active for this site', 'Addons', 'uncanny-automator' ) );
			default:
				return self::license_problem_notice( esc_html_x( 'Your license is invalid', 'Addons', 'uncanny-automator' ) );
		}
	}

	/**
	 * A license-problem notice.
	 *
	 * @param string $heading Notice heading.
	 *
	 * @return array<string,string>
	 */
	private static function license_problem_notice( string $heading ): array {
		return array(
			'heading' => $heading,
			'content' => esc_html_x( 'To update your addons and **gain access to the latest released addons**, you need an **active Pro license**. Please get a Pro license to gain access to these features.', 'Addons', 'uncanny-automator' ),
			'type'    => 'error',
		);
	}

	/**
	 * The CTAs for the scenario.
	 *
	 * @param Summary_Scenario $scenario Resolved scenario.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function ctas( Summary_Scenario $scenario ): array {

		if ( $scenario->needs_license_fix() ) {
			return array( Addon_Ctas::fix_license() );
		}

		switch ( $scenario->id() ) {
			case Summary_Scenario::PRO_NOT_INSTALLED:
				return array( Addon_Ctas::get_pro() );
			case Summary_Scenario::PRO_DEACTIVATED:
				return array( Addon_Ctas::activate_pro() );
			case Summary_Scenario::ACTIVE_BASIC:
			case Summary_Scenario::ACTIVE_PLUS:
				return array( Addon_Ctas::upgrade_plan() );
			default:
				return array();
		}
	}
}
