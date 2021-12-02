<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Error_Messages
 *
 * @package Uncanny_Automator
 */
class Automator_Error_Messages {

	/**
	 * @var
	 */
	public static $instance;
	/**
	 * Collection of error messages
	 *
	 * @var array
	 */
	private $error_messages = array();

	/**
	 * Automator_Error_Messages constructor.
	 */
	public function __construct() {

		$this->error_messages['email-failed']              = esc_attr__( 'The email was not sent successfully.', 'uncanny-automator' );
		$this->error_messages['email-success']             = esc_attr__( 'The email was sent successfully.', 'uncanny-automator' );
		$this->error_messages['not-logged-in']             = esc_attr__( 'The user is not logged in.', 'uncanny-automator' );
		$this->error_messages['action-not-active']         = esc_attr__( 'The plugin for this action is not active.', 'uncanny-automator' );
		$this->error_messages['action-function-not-exist'] = esc_attr__( 'An error occurred while running this action.', 'uncanny-automator' );
		$this->error_messages['plugin-not-active']         = esc_attr__( 'The plugin for this action is not active.', 'uncanny-automator' );
		apply_filters_deprecated( 'uap_error_messages', array( $this->error_messages ), '3.0', 'automator_error_messages' );
		apply_filters( 'automator_error_messages', $this->error_messages );
	}

	/**
	 * @return Automator_Error_Messages
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
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
		if ( ! isset( $error_messages[ $error_key ] ) ) {
			return esc_html__( 'No message', 'uncanny-automator' );
		}
		$error_message = $error_messages[ $error_key ] . $additional_information;

		/**
		 * Filters the specific error message
		 */
		return apply_filters( 'automator_error_message', $error_message, $error_key, $additional_information );
	}
}
