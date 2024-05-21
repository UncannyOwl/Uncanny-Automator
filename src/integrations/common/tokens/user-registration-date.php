<?php

namespace Uncanny_Automator\Integrations\Common\Tokens;

use Uncanny_Automator\Tokens\Token;

/**
 * Class User_Registration_Date
 */
class User_Registration_Date extends Token {
	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'COMMON';
		$this->id            = 'user_registered';
		$this->name          = esc_attr_x( 'User registration date', 'Token', 'uncanny-automator' );
		$this->requires_user = true;
		$this->type          = 'date';
		$this->cacheable     = true;
	}

	/**
	 * display_in_recipe_ui
	 *
	 * @return bool
	 */
	public function display_in_recipe_ui() {

		if ( 'anonymous' === $this->get_recipe_type() ) {
			$this->remove_supported_item( 'trigger' );
			$this->remove_supported_item( 'user-selector' );
		}

		return true;
	}

	/**
	 * parse
	 *
	 * @param mixed $replaceable
	 * @param mixed $field_text
	 * @param mixed $match
	 * @param mixed $current_user
	 *
	 * @return string
	 */
	public function parse( $replaceable, $field_text, $match, $current_user ) {
		$user = get_user_by( 'id', $current_user );

		// Get the registration date of the user
		$registration_date = strtotime( $user->user_registered );

		// Format the date according to WordPress settings
		return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $registration_date );
	}
}
