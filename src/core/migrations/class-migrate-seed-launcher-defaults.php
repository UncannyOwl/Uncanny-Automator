<?php

namespace Uncanny_Automator\Migrations;

use Uncanny_Automator\Admin_Settings_Uncanny_Agent_General;
use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;
use Uncanny_Automator\Automator_Load;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Seed_Launcher_Defaults.
 *
 * 8.0 turns the Uncanny Agent launchers and Uncanny Page Builder off by
 * default: the setup wizard switches them on when the customer chooses them.
 * Before 8.0 both were on by default and most sites never saved the options,
 * so an absent option meant "on". This migration writes that "on" explicitly
 * for sites that existed before the change, so an upgrade does not switch
 * anything off. A fresh install gets nothing written and reads the new
 * defaults.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator
 */
class Seed_Launcher_Defaults extends Migration {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( '80_seed_launcher_defaults' );
	}

	/**
	 * migrate
	 *
	 * @return void
	 */
	public function migrate() {

		if ( ! Automator_Load::is_fresh_install() ) {
			$this->seed_existing_install();
		}

		$this->complete();
	}

	/**
	 * Write the pre-8.0 "on" for each switch the site never saved.
	 *
	 * @return void
	 */
	private function seed_existing_install() {

		// Per key, not per option: a site that saved Agent settings on 7.1 to 7.4
		// stored only `enabled`, and its missing top-bar switch meant "on".
		$this->seed_missing_keys(
			Admin_Settings_Uncanny_Agent_General::OPTION_NAME,
			array(
				Admin_Settings_Uncanny_Agent_General::ENABLED_KEY,
				Admin_Settings_Uncanny_Agent_General::TOP_BAR_BUTTON_ENABLED_KEY,
			)
		);

		$this->seed_missing_keys( Page_Builder_Settings::OPTION_NAME, array( Page_Builder_Settings::ENABLED_KEY ) );
	}

	/**
	 * Store "on" for each key the option does not hold, keeping saved values.
	 *
	 * @param string   $option_name The settings option.
	 * @param string[] $keys        The switches it holds.
	 *
	 * @return void
	 */
	private function seed_missing_keys( $option_name, array $keys ) {

		$saved    = automator_get_option( $option_name, null );
		$settings = is_array( $saved ) ? $saved : array();

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = true;
			}
		}

		if ( $settings !== $saved ) {
			automator_update_option( $option_name, $settings );
		}
	}
}

new Seed_Launcher_Defaults();
