<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Error_Messages
 * @package Uncanny_Automator
 */
class Automator_Error_Messages {

	/**
	 * Collection of error messages
	 * @var array
	 */
	private $error_messages = array();

	public function __construct() {

		$this->error_messages['email-failed']                = __( 'The email was not sent successfully.', 'uncanny-automator' );
		$this->error_messages['email-success']               = __( 'The email was sent successfully.', 'uncanny-automator' );
		$this->error_messages['not-logged-in']               = __( 'The user is not logged in.', 'uncanny-automator' );
		$this->error_messages['action-not-active']           = __( 'The plugin for this action is not active.', 'uncanny-automator' );
		$this->error_messages['action-function-not-exist']   = __( 'An error occurred while running this action.', 'uncanny-automator' );
		$this->error_messages['plugin-not-active']           = __( 'The plugin for this action is not active.', 'uncanny-automator' );
		apply_filters( 'uap_error_messages', $this->error_messages );
	}

	/**
	 * Get the message associated with the error key
	 *
	 * @param null|string $additional_information
	 * @param null|string $error_key
	 *
	 * @return null|string
	 */
	public function get( $error_key = null, $additional_information = '' ) {

		/**
		 * Filters all error messages before a specific error message is set
		 */
		$error_messages = apply_filters( 'automator_error_messages', $this->error_messages );
		$error_message  = null;

		if ( isset( $error_messages[ $error_key ] ) ) {
			$error_message = $error_messages[ $error_key ] . $additional_information;
		} else {
			return 'No message';
		}

		/**
		 * Filters the specific error message
		 */
		$error_message = apply_filters( 'automator_error_message', $error_message, $error_key, $additional_information );

		return $error_message;
	}
}
