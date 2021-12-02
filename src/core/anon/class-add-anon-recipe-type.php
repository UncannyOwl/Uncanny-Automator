<?php


namespace Uncanny_Automator;

/**
 * Class Add_Anon_Recipe_Type
 *
 * @package Uncanny_Automator
 */
class Add_Anon_Recipe_Type {
	/**
	 * Add_User_Recipe_Type constructor.
	 */
	public function __construct() {
		add_action( 'automator_add_recipe_type', array( $this, 'add_anon_type_recipe' ) );
	}

	/**
	 *
	 */
	public function add_anon_type_recipe() {

		Automator()->register->recipe_type(
			'anonymous',
			array(
				'name'        => 'Anonymous',
				'icon_16'     => Utilities::automator_get_integration_icon( 'integration-uncannyautomator-icon-16.png' ),
				'icon_32'     => Utilities::automator_get_integration_icon( 'integration-uncannyautomator-icon-32.png' ),
				'icon_64'     => Utilities::automator_get_integration_icon( 'integration-uncannyautomator-icon-64.png' ),
				'logo'        => Utilities::automator_get_integration_icon( 'integration-uncannyautomator.png' ),
				'logo_retina' => Utilities::automator_get_integration_icon( 'integration-uncannyautomator@2x.png' ),
			)
		);
	}
}
