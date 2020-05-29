<?php

namespace Uncanny_Automator;

/**
 * Class Add_User_Recipe_Type
 * @package Uncanny_Automator
 */
class Add_User_Recipe_Type {
	public function __construct() {
		add_action( 'uncanny_automator_add_recipe_type', [ $this, 'add_user_type_recipe' ] );
	}

	public function add_user_type_recipe() {

		global $uncanny_automator;

		$uncanny_automator->register->recipe_type( 'user', array(
			'name'        => 'User',
			'icon_16'     => Utilities::get_integration_icon( 'integration-uncannyautomator-icon-16.png' ),
			'icon_32'     => Utilities::get_integration_icon( 'integration-uncannyautomator-icon-32.png' ),
			'icon_64'     => Utilities::get_integration_icon( 'integration-uncannyautomator-icon-64.png' ),
			'logo'        => Utilities::get_integration_icon( 'integration-uncannyautomator.png' ),
			'logo_retina' => Utilities::get_integration_icon( 'integration-uncannyautomator@2x.png' ),
		) );


	}

}