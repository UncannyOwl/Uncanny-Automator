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

		$this->set_plugin_file_path( 'jetformbuilder/jet-form-builder.php' );
		$this->set_developer_name( 'Crocoblock' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
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
