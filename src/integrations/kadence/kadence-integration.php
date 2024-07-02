<?php

namespace Uncanny_Automator\Integrations\Kadence;

use Uncanny_Automator\Integration;

/**
 * Class Kadence_Integration
 *
 * @pacakge Uncanny_Automator
 */
class Kadence_Integration extends Integration {

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->helpers = new Kadence_Helpers();
		$this->set_integration( 'KADENCE' );
		$this->set_name( 'Kadence' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/kadence-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new KADENCE_FORM_SUBMITTED( $this->helpers );
		new KADENCE_ANON_FORM_SUBMITTED( $this->helpers );

		add_action(
			'kadence_blocks_form_submission',
			array(
				$this->helpers,
				'automator_kadence_form_submitted_function',
			),
			4,
			99
		);
		add_action(
			'kadence_blocks_advanced_form_submission',
			array(
				$this->helpers,
				'automator_kadence_form_submitted_function',
			),
			3,
			99
		);
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		// get the current theme
		$theme = wp_get_theme();
		if ( ( 'Kadence' == $theme->name || 'Kadence' == $theme->parent_theme ) || defined( 'KADENCE_BLOCKS_VERSION' ) ) {
			return true;
		}

		return false;
	}
}
