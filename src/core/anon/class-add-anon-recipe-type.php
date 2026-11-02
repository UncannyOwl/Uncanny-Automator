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

		// The Uncanny Automator integration's icon, an SVG, serves every size.
		$icon = Utilities::automator_get_integration_icon( UA_ABSPATH . 'src/integrations/uncanny-automator/img/automator-core-icon.svg' );

		Automator()->register->recipe_type(
			'anonymous',
			array(
				'name'        => 'Anonymous',
				'icon_16'     => $icon,
				'icon_32'     => $icon,
				'icon_64'     => $icon,
				'logo'        => $icon,
				'logo_retina' => $icon,
			)
		);
	}
}
