<?php

namespace Uncanny_Automator\Services\Addons\Data;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\Services\Plugin\Info;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * License Summary
 *
 * - Generates a license summary for the user.
 * - Generates the call to action for the license summary.
 *
 * @package Uncanny_Automator\Services\Addons
 */
class License_Summary {

	/**
	 * Addons plan resolver instance.
	 *
	 * @var Plan_Resolver
	 */
	private $plan_resolver;

	/**
	 * License manager (src/app source of truth).
	 *
	 * @var License_Manager
	 */
	private $license_manager;

	/**
	 * Connected user results.
	 *
	 * @var array
	 */
	private $connected_user;

	/**
	 * The current plan slug (lite/basic/plus/elite).
	 *
	 * @var string
	 */
	private $plan;

	/**
	 * The current plan display name from the storefront.
	 *
	 * @var string
	 */
	private $plan_name;

	/**
	 * Is Pro active.
	 *
	 * @var bool
	 */
	private $is_pro_active;

	/**
	 * The license status.
	 *
	 * @var string
	 */
	private $license_status;

	/**
	 * The scenario ID.
	 *
	 * @var string
	 */
	private $scenario_id;

	/**
	 * The number of addons available for the license.
	 *
	 * @var int
	 */
	private $addons_available_for_license = 0;

	/**
	 * The generated license summary.
	 *
	 * @var array
	 */
	private $summary;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
	}

	/**
	 * License information.
	 *
	 * @return array {
	 *     @property string $scenario_id The scenario ID.
	 *     @property array $license_owner {
	 *         @property string $name   The full name of the license owner.
	 *         @property string $email  The email address of the license owner.
	 *     },
	 *     @property string $current_plan The current plan of the license. Possible values are "lite", "basic", "plus", and "elite".
	 *     @property array  $addons {
	 *         @property int $available The number of addons available to download to the license owner.
	 *         @property int $total     The total number of addons available when having the higher plan.
	 *     },
	 *     @property string $status The license status. Possible values are "valid", "invalid", "expired", "inactive", "disabled", "site_inactive".
	 *     @property array[] $cta An array of call-to-action buttons to manage the license. Each element is an associative array with:
	 *     @property array[] $cta {
	 *         @property string      $label The label of the button.
	 *         @property string      $url   The URL of the button.
	 *         @property string|null $icon  (Optional) An icon for the button.
	 *     }
	 *     @property array $notice {
	 *         @property string $heading The heading of the notice.
	 *         @property string $type    The type of the notice. Possible values are "success", "warning", "error".
	 *     }
	 * }
	 */

	/**
	 * Get license summary.
	 *
	 * @return array
	 */
	public function get_license_summary() {

		// Set all class properties.
		$this->set_class_properties();

		// Generate the license summary.
		$this->summary = $this->generate_license_summary();

		return $this->summary;
	}

	/**
	 * Set class properties.
	 *
	 * @return void
	 */
	private function set_class_properties() {
		$this->license_manager = automator_license_manager();
		$this->plan_resolver   = new Plan_Resolver();

		$this->plan          = $this->license_manager->get_resolved_plan();
		$this->plan_name     = $this->license_manager->get_resolved_plan_name();
		$this->is_pro_active = $this->license_manager->is_pro_active();

		$license              = $this->license_manager->get_license_data();
		$this->connected_user = is_array( $license ) ? $license : null;

		$this->addons_available_for_license = $this->connected_user
			? $this->plan_resolver->get_number_of_addons_for_license()
			: 0;

		if ( ! $this->connected_user ) {
			$this->connected_user = array(
				'license'        => 'invalid',
				'customer_name'  => esc_html_x( 'Not connected', 'Addons', 'uncanny-automator' ),
				'customer_email' => '',
				'license_id'     => '',
				'payment_id'     => '',
			);
		}

		$this->set_license_status();
		$this->scenario_id = $this->get_scenario_id();
	}

	/**
	 * Set license status.
	 *
	 * @return void
	 */
	private function set_license_status() {
		$this->license_status = $this->validate_status_by_plan(
			$this->plan,
			$this->connected_user['license']
		);
	}

	/**
	 * Validate the status by plan.
	 *
	 * @param string $plan The plan.
	 * @param string $status The status.
	 * @return string
	 */
	private function validate_status_by_plan( $plan, $status ) {
		// Cross-check the API "valid" with the locally stored EDD Pro status.
		// Guards against a stale cached payload that still reports valid after
		// Pro was deactivated or its license key removed locally.
		if ( 'valid' === $status && 'lite' !== $plan ) {
			if ( 'pro' !== $this->license_manager->get_type() ) {
				$status = 'invalid';
			}
		}
		return $status;
	}

	/**
	 * Generate the license summary.
	 *
	 * @return array
	 */
	private function generate_license_summary() {
		return array(
			'scenario_id'       => $this->scenario_id,
			'license_owner'     => array(
				'name'  => $this->connected_user['customer_name'],
				'email' => $this->connected_user['customer_email'],
			),
			'current_plan'      => $this->plan,
			'current_plan_name' => $this->plan_name,
			'addons'            => array(
				'available' => $this->addons_available_for_license,
				'total'     => $this->plan_resolver->get_total_number_of_available_addons(),
			),
			'status'            => $this->license_status,
			'notice'            => $this->get_notice(),
			'cta'               => $this->get_call_to_action(),
		);
	}

	/**
	 * Get the scenario ID.
	 *
	 * @return string
	 */
	private function get_scenario_id() {

		// Pro not installed.
		if ( ! Info::is_pro_plugin_installed() ) {
			return 'pro-not-installed';
		}

		// Pro not active.
		if ( ! $this->is_pro_active ) {
			return 'pro-installed-but-deactivated';
		}

		// Is license valid.
		if ( 'valid' === $this->license_status ) {

			// License is elite.
			if ( $this->plan_resolver->has_access_to_plan( 'elite' ) ) {
				return 'pro-installed-license-active-pro-elite';
			}

			// License is plus.
			if ( $this->plan_resolver->has_access_to_plan( 'plus' ) ) {
				return 'pro-installed-license-active-pro-plus';
			}

			// License is basic.
			return 'pro-installed-license-active-pro-basic';
		}

		// All other license statuses.
		switch ( $this->license_status ) {
			case 'inactive':
				return 'pro-installed-license-inactive';
			case 'expired':
				return 'pro-installed-license-expired';
			case 'disabled':
				return 'pro-installed-license-disabled';
			case 'site_inactive':
				return 'pro-installed-license-site-inactive';
			case 'invalid':
				return 'pro-installed-license-invalid';
			default:
				return 'pro-installed-license-invalid';
		}
	}

	/**
	 * Resolve the display label for the active plan.
	 *
	 * Prefers the storefront plan_name (e.g. "Plus AI + Automation Monthly").
	 * Falls back to the capitalised tier slug ("Basic", "Plus", "Elite") when
	 * plan_name is empty or simply echoes the slug — i.e. the storefront has
	 * not yet synced plan_name on this license.
	 *
	 * @return string
	 */
	private function get_plan_display_label() {
		// Keep aligned with the JS tierLabels map in
		// src/assets/src/shared/components/license-summary/index.js — both
		// sides treat these slugs as echo-backs so plan_name falls through
		// to the localised tier label.
		$tier_slugs = array( 'lite', 'basic', 'plus', 'elite' );

		if ( ! empty( $this->plan_name ) && ! in_array( strtolower( $this->plan_name ), $tier_slugs, true ) ) {
			return $this->plan_name;
		}

		return ucfirst( $this->plan );
	}

	/**
	 * Get the notice.
	 *
	 * @return array
	 */
	private function get_notice() {
		switch ( $this->scenario_id ) {
			case 'pro-not-installed':
				return array(
					'heading' => esc_html_x( "You're currently on Automator Lite", 'Addons', 'uncanny-automator' ),
					'type'    => 'success',
				);
			case 'pro-installed-but-deactivated':
				return array(
					'heading' => esc_html_x( 'Automator Pro is installed but inactive', 'Addons', 'uncanny-automator' ),
					'type'    => 'warning',
				);
			case 'pro-installed-license-active-pro-basic':
			case 'pro-installed-license-active-pro-plus':
			case 'pro-installed-license-active-pro-elite':
				return array(
					'heading' => sprintf(
						/* translators: %s is the plan label, e.g. "Plus", "Plus AI + Automation Monthly", "Elite (Legacy)". */
						esc_html_x( 'Your Pro %s license is active', 'Addons', 'uncanny-automator' ),
						esc_html( $this->get_plan_display_label() )
					),
					'type'    => 'success',
				);
			case 'pro-installed-license-inactive':
				return array(
					'heading' => esc_html_x( 'Your license is inactive', 'Addons', 'uncanny-automator' ),
					'type'    => 'error',
				);
			case 'pro-installed-license-expired':
				return array(
					'heading' => esc_html_x( 'Your license has expired', 'Addons', 'uncanny-automator' ),
					'type'    => 'error',
				);
			case 'pro-installed-license-disabled':
				return array(
					'heading' => esc_html_x( 'Your license is disabled', 'Addons', 'uncanny-automator' ),
					'type'    => 'error',
				);
			case 'pro-installed-license-site-inactive':
				return array(
					'heading' => esc_html_x( 'Your license is not active for this site', 'Addons', 'uncanny-automator' ),
					'type'    => 'error',
				);
			// case 'pro-installed-license-invalid':
			default:
				return array(
					'heading' => esc_html_x( 'Your license is invalid', 'Addons', 'uncanny-automator' ),
					'type'    => 'error',
				);
		}
	}

	/**
	 * Get the call to action.
	 *
	 * @return array
	 */
	private function get_call_to_action() {

		switch ( $this->scenario_id ) {
			// Upgrade CTA - Goes to external pricing page.
			case 'pro-not-installed':
			case 'pro-installed-license-active-pro-basic':
			case 'pro-installed-license-active-pro-plus':
				return 'pro-not-installed' === $this->scenario_id
					? array( Calls_To_Action::get_pro_plugin() )
					: array( Calls_To_Action::get_upgrade_plan() );
			// Activate CTA - action.
			case 'pro-installed-but-deactivated':
				return array( Calls_To_Action::get_activate_pro( 'warning' ) );
			// Fix license CTA - Goes to manage license settings page.
			case 'pro-installed-license-inactive':
			case 'pro-installed-license-expired':
			case 'pro-installed-license-disabled':
			case 'pro-installed-license-site-inactive':
			case 'pro-installed-license-invalid':
				return array( Calls_To_Action::get_fix_license() );
			// Return empty array no CTA is needed.
			case 'pro-installed-license-active-pro-elite':
			default:
				return array();
		}
	}
}
