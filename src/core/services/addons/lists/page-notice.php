<?php

namespace Uncanny_Automator\Services\Addons\Lists;

use Uncanny_Automator\Services\Addons\Data\Calls_To_Action;

/**
 * Class Page_Notice
 *
 * - Generates the page notice for the addons list.
 * - Message
 *
 * @package Uncanny_Automator\Services\Addons\Lists
 */
class Page_Notice {

	/**
	 * The scenario ID.
	 *
	 * @var string
	 */
	private $scenario_id;

	/**
	 * The plan ( elite, plus, basic ).
	 *
	 * @var string
	 */
	private $plan;

	/**
	 * The message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * The type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The CTA.
	 *
	 * @var string
	 */
	private $cta;

	/**
	 * The requires license fix flag.
	 *
	 * @var bool
	 */
	private $requires_license_fix = false;

	/**
	 * Constructor.
	 *
	 * @param string $scenario_id  The scenario ID.
	 * @param string $plan         The plan code.
	 *
	 * @return void
	 */
	public function __construct( $scenario_id, $plan ) {
		$this->scenario_id = $scenario_id;
		$this->plan        = $plan;
		$this->set_notice();
	}

	/**
	 * Get the page notice.
	 *
	 * @return array
	 */
	public function get_page_notice() {
		return array(
			'message' => $this->message,
			'type'    => $this->type,
			'cta'     => $this->cta,
		);
	}

	/**
	 * Set the notice.
	 *
	 * @return void
	 */
	private function set_notice() {
		$this->set_requires_license_fix();
		$this->set_message();
		$this->set_type();
		$this->set_cta();
	}

	/**
	 * Set the message for the page notice.
	 *
	 * @return void
	 */
	private function set_message() {

		// Requires license fix.
		if ( $this->requires_license_fix ) {
			switch ( $this->plan ) {
				case 'basic':
					$this->message = esc_html_x( 'Get access to these addons with Automator Pro with a valid Basic, Plus or Elite license', 'Addons', 'uncanny-automator' );
					break;
				case 'plus':
					$this->message = esc_html_x( 'Get access to these addons with Automator Pro with a valid Plus or Elite license', 'Addons', 'uncanny-automator' );
					break;
				case 'elite':
					$this->message = esc_html_x( 'Get access to these addons with Automator Pro with a valid Elite license', 'Addons', 'uncanny-automator' );
					break;
			}
			return;
		}

		// Determine message for all other issues.
		switch ( $this->scenario_id ) {
			// Basic plan - Pro not installed or is deactivated.
			case 'pro-not-installed-requires-pro-basic':
			case 'pro-installed-but-deactivated-requires-pro-basic':
				$this->message = esc_html_x( 'Get access to these addons with Automator Pro with a Basic, Plus or Elite license', 'Addons', 'uncanny-automator' );
				break;
			// Plus plan - Pro not installed or is deactivated.
			case 'pro-not-installed-requires-pro-plus':
			case 'pro-installed-but-deactivated-requires-pro-plus':
				$this->message = esc_html_x( 'Get access to these addons with Automator Pro with a Plus or Elite license', 'Addons', 'uncanny-automator' );
				break;
			// Elite plan - Pro not installed or is deactivated.
			case 'pro-not-installed-requires-elite':
			case 'pro-installed-but-deactivated-requires-elite':
				$this->message = esc_html_x( 'Get access to these addons with Automator Pro with an Elite license', 'Addons', 'uncanny-automator' );
				break;
			// License requirements vs current plan ( Requires Plus or Elite )
			case 'pro-installed-license-active-pro-basic-requires-pro-plus':
				$this->message = esc_html_x( 'Get access to these with a Pro Plus or Pro Elite license', 'Addons', 'uncanny-automator' );
				break;
			// License requirements vs current plan ( Requires Elite )
			case 'pro-installed-license-active-pro-basic-requires-elite':
			case 'pro-installed-license-active-pro-plus-requires-elite':
				$this->message = esc_html_x( 'Get access to these with a Pro Elite license', 'Addons', 'uncanny-automator' );
				break;
			default:
				break;
		}
	}

	/**
	 * Set the type for the page notice.
	 *
	 * @return void
	 */
	private function set_type() {

		// Map to info else error.
		$type_map = array(
			'pro-installed-license-active-pro-basic-requires-pro-plus' => 'info',
			'pro-installed-license-active-pro-basic-requires-elite'    => 'info',
			'pro-installed-license-active-pro-plus-requires-elite'     => 'info',
		);

		$this->type = $type_map[ $this->scenario_id ] ?? 'error';
	}

	/**
	 * Set the CTA for the page notice.
	 *
	 * @return void
	 */
	private function set_cta() {
		// License fix.
		if ( $this->requires_license_fix ) {
			$this->cta = Calls_To_Action::get_fix_license( 'error' );
			return;
		}

		// Determine CTA.
		switch ( $this->scenario_id ) {
			// Pro not installed.
			case 'pro-not-installed-requires-pro-basic':
			case 'pro-not-installed-requires-pro-plus':
			case 'pro-not-installed-requires-elite':
				// Set Automator Pro pricing CTA.
				$this->cta = Calls_To_Action::get_pro_plugin();
				break;
			// Pro installed but deactivated.
			case 'pro-installed-but-deactivated-requires-pro-basic':
			case 'pro-installed-but-deactivated-requires-pro-plus':
			case 'pro-installed-but-deactivated-requires-elite':
				// Set Automator Pro activate CTA.
				$this->cta = Calls_To_Action::get_activate_pro( 'error' );
				break;
			// Requires a plan upgrade.
			case 'pro-installed-license-active-pro-basic-requires-pro-plus':
			case 'pro-installed-license-active-pro-basic-requires-elite':
			case 'pro-installed-license-active-pro-plus-requires-elite':
				// Set Automator Pro pricing upgrade CTA.
				$this->cta = Calls_To_Action::get_upgrade_plan( 'error' );
				break;
			default:
				break;
		}
	}

	/**
	 * Set the requires license fix flag.
	 *
	 * @return void
	 */
	private function set_requires_license_fix() {
		switch ( $this->scenario_id ) {
			case "pro-installed-license-inactive-requires-pro-{$this->plan}":
			case "pro-installed-license-expired-requires-pro-{$this->plan}":
			case "pro-installed-license-disabled-requires-pro-{$this->plan}":
			case "pro-installed-license-site-inactive-requires-pro-{$this->plan}":
			case "pro-installed-license-invalid-requires-pro-{$this->plan}":
				$this->requires_license_fix = true;
				break;
			default:
				$this->requires_license_fix = false;
				break;
		}
	}
}
