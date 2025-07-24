<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class ANON_THRIVE_QB_QUIZ_COMPLETED
 *
 * @package Uncanny_Automator
 */
class ANON_THRIVE_QB_QUIZ_COMPLETED extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'TQB_QUIZ_COMPLETED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'TQB_QUIZ';

	/**
	 * Helper instance
	 *
	 * @var Thrive_Quiz_Builder_Helpers
	 */
	protected $helper;

	/**
	 * Setup trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->helper = new Thrive_Quiz_Builder_Helpers();

		$this->set_integration( 'THRIVE_QB' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		$this->add_action( 'thrive_quizbuilder_quiz_completed' );
		$this->set_action_args_count( 3 );

		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Quiz
				esc_html_x( '{{A quiz:%1$s}} is completed', 'Thrive Quiz Builder', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( '{{A quiz}} is completed', 'Thrive Quiz Builder', 'uncanny-automator' )
		);
	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'required'        => true,
				'label'           => esc_html_x( 'Quiz', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => $this->helper->get_dropdown_options_quizzes( true ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool True if validation was successful.
	 */
	public function validate( $trigger, $hook_args ) {
		if ( empty( $hook_args ) ) {
			return false;
		}

		$quiz_data = $hook_args[0];
		if ( ! is_array( $quiz_data ) ) {
			return false;
		}

		// Quiz info
		$quiz_id          = isset( $quiz_data['quiz_id'] ) ? absint( $quiz_data['quiz_id'] ) : 0;
		$selected_quiz_id = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? $trigger['meta'][ $this->get_trigger_meta() ] : 0;

		// Match if any quiz is selected (-1) or if specific quiz matches
		if ( intval( '-1' ) === intval( $selected_quiz_id ) ) {
			return true;
		}

		return $selected_quiz_id === $quiz_id;
	}

	/**
	 * Hydrate tokens with values.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array The token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		if ( empty( $hook_args ) ) {
			return array();
		}

		// The data structure is different for logged-in and anonymous users
		$quiz_data = $hook_args[0];
		$user_data = ! empty( $hook_args[1] ) ? $hook_args[1] : $hook_args[2];

		// Quiz info
		$quiz_id     = isset( $quiz_data['quiz_id'] ) ? absint( $quiz_data['quiz_id'] ) : 0;
		$quiz_title  = isset( $quiz_data['quiz_name'] ) ? $quiz_data['quiz_name'] : '';
		$text_result = isset( $quiz_data['result'] ) ? $quiz_data['result'] : '';
		$quiz_type   = get_post_meta( $quiz_id, 'tqb_quiz_type', true );
		if ( is_array( $quiz_type ) ) {
			$quiz_type = array_shift( $quiz_type );
		}
		$quiz_type = ucfirst( str_replace( '_', ' ', $quiz_type ) );

		// User info
		$user_id = isset( $user_data['user_id'] ) ? absint( $user_data['user_id'] ) : 0;
		$email   = isset( $quiz_data['user_email'] ) ? $quiz_data['user_email'] : '';

		// Get user name based on authentication status.
		$first_name = '';
		$last_name  = '';

		if ( $user_id ) {
			// Logged-in user: retrieve name from WordPress user data.
			$user_info = get_userdata( $user_id );
			if ( $user_info ) {
				$first_name = $user_info->first_name;
				$last_name  = $user_info->last_name;
			}
		} else {
			// Anonymous user: extract name from provided data.
			$full_name = $this->get_anonymous_user_name( $user_data );
			if ( ! empty( $full_name ) ) {
				list( $first_name, $last_name ) = $this->split_full_name( $full_name );
			}
		}

		return array(
			'TQB_QUIZ_ID'         => $quiz_id,
			'TQB_QUIZ_TITLE'      => $quiz_title,
			'TQB_QUIZ_RESULT'     => $text_result,
			'TQB_QUIZ_TYPE'       => $quiz_type,
			'TQB_USER_ID'         => $user_id,
			'TQB_USER_EMAIL'      => $email,
			'TQB_USER_FIRST_NAME' => $first_name,
			'TQB_USER_LAST_NAME'  => $last_name,
		);
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $tokens The existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$trigger_tokens = array(
			'TQB_QUIZ_ID'         => array(
				'name'      => esc_html_x( 'Quiz ID', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'TQB_QUIZ_ID',
				'tokenName' => esc_html_x( 'Quiz ID', 'Thrive Quiz Builder', 'uncanny-automator' ),
			),
			'TQB_QUIZ_TITLE'      => array(
				'name'      => esc_html_x( 'Quiz title', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'TQB_QUIZ_TITLE',
				'tokenName' => esc_html_x( 'Quiz title', 'Thrive Quiz Builder', 'uncanny-automator' ),
			),
			'TQB_QUIZ_RESULT'     => array(
				'name'      => esc_html_x( 'Quiz result', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'TQB_QUIZ_RESULT',
				'tokenName' => esc_html_x( 'Quiz result', 'Thrive Quiz Builder', 'uncanny-automator' ),
			),
			'TQB_QUIZ_TYPE'       => array(
				'name'      => esc_html_x( 'Quiz type', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'TQB_QUIZ_TYPE',
				'tokenName' => esc_html_x( 'Quiz type', 'Thrive Quiz Builder', 'uncanny-automator' ),
			),
			'TQB_USER_ID'         => array(
				'name'      => esc_html_x( 'User ID', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'TQB_USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'Thrive Quiz Builder', 'uncanny-automator' ),
			),
			'TQB_USER_EMAIL'      => array(
				'name'      => esc_html_x( 'User email', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'type'      => 'email',
				'tokenId'   => 'TQB_USER_EMAIL',
				'tokenName' => esc_html_x( 'User email', 'Thrive Quiz Builder', 'uncanny-automator' ),
			),
			'TQB_USER_FIRST_NAME' => array(
				'name'      => esc_html_x( 'User first name', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'TQB_USER_FIRST_NAME',
				'tokenName' => esc_html_x( 'User first name', 'Thrive Quiz Builder', 'uncanny-automator' ),
			),
			'TQB_USER_LAST_NAME'  => array(
				'name'      => esc_html_x( 'User last name', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'TQB_USER_LAST_NAME',
				'tokenName' => esc_html_x( 'User last name', 'Thrive Quiz Builder', 'uncanny-automator' ),
			),
		);

		return $trigger_tokens;
	}

	/**
	 * Get the full name for an anonymous user from the provided data.
	 *
	 * @param array $user_data The user data array.
	 *
	 * @return string The full name or empty string if not found.
	 */
	private function get_anonymous_user_name( $user_data ) {
		if ( isset( $user_data['username'] ) && ! empty( $user_data['username'] ) ) {
			return $user_data['username'];
		}

		if ( isset( $user_data['name'] ) && ! empty( $user_data['name'] ) ) {
			return $user_data['name'];
		}

		return '';
	}

	/**
	 * Split a full name into first name and last name.
	 *
	 * @param string $full_name The full name to split.
	 *
	 * @return array Array with first name and last name.
	 */
	private function split_full_name( $full_name ) {
		$full_name = trim( $full_name );
		if ( empty( $full_name ) ) {
			return array( '', '' );
		}

		$name_parts = explode( ' ', $full_name );
		$first_name = $name_parts[0];
		$last_name  = '';

		if ( count( $name_parts ) > 1 ) {
			$last_name = implode( ' ', array_slice( $name_parts, 1 ) );
		}

		return array( $first_name, $last_name );
	}
}
