<?php
namespace Uncanny_Automator;

class Add_Jetform_Builder_Integration {

	use Recipe\Integrations;

	public function __construct() {
		$this->setup();
	}

	protected function setup() {

		$this->set_integration( 'JET_FORM_BUILDER' );

		$this->set_name( 'JetFormBuilder' );

		$this->set_icon( __DIR__ . '/img/jetformbuilder-icon.svg' );

	}

	/**
	 * Method plugin_active
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'jet_form_builder_init' );
	}
}
