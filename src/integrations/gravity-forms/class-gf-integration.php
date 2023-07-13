<?php

namespace Uncanny_Automator\Integrations\Gravity_Forms;

/**
 * Class Gravity_Forms_Integration
 *
 * @package Uncanny_Automator
 */
class Gravity_Forms_Integration extends \Uncanny_Automator\Integration {

	public $tokens;

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'GF' );
		$this->set_name( 'Gravity Forms' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/gravity-forms-icon.svg' );
	}

	/**
	 * load
	 *
	 * @return void
	 */
	protected function load() {
		$this->tokens = new Gravity_Forms_Tokens();
		new ANON_GF_FORM_ENTRY_UPDATED( $this );
		new ANON_GF_SUBFORM( $this );
		new GF_SUBFORM( $this );
		new GF_SUBFORM_CODES( $this );
		new GF_SUBFORM_GROUPS( $this );
		$this->load_legacy_files();
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'GFFormsModel' );
	}

	/**
	 * load_legacy_files
	 *
	 * @return void
	 */
	public function load_legacy_files() {
		new \Uncanny_Automator\Gf_Tokens();
		new \Uncanny_Automator\Gravity_Forms_Helpers();
		new \Uncanny_Automator\GF_COMMON_TOKENS();
	}

	/**
	 * Retrieves all forms as option fields.
	 *
	 * @return array The list of option fields from Gravity forms.
	 */
	public function get_forms_options() {

		if ( ! class_exists( 'GFAPI' ) ) {

			return array();

		}

		$forms = \GFAPI::get_forms();

		$options = array(
			array(
				'value' => -1,
				'text'  => __( 'Any form', 'uncanny-automator' ),
			),
		);

		foreach ( $forms as $form ) {
			$options[] = array(
				'value' => absint( $form['id'] ),
				'text'  => $form['title'],
			);

		}

		return $options;
	}
}
