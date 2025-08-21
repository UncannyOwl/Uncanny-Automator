<?php

namespace Uncanny_Automator\Integrations\Thrive_Quiz_Builder;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class ANON_THRIVE_QB_QUIZ_COMPLETED
 *
 * @package Uncanny_Automator
 */
class ANON_THRIVE_QB_QUIZ_COMPLETED extends Trigger {

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'THRIVE_QB' );
		$this->set_trigger_code( 'TQB_QUIZ_COMPLETED' );
		$this->set_trigger_meta( 'TQB_QUIZ' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		$this->add_action( 'thrive_quizbuilder_quiz_completed', 20, 3 );

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

		$helper = new Thrive_Quiz_Builder_Helpers();

		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'required'        => true,
				'label'           => esc_html_x( 'Quiz', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => $helper->get_dropdown_options_quizzes( true ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		if ( empty( $hook_args ) ) {
			return false;
		}

		$quiz_data = $hook_args[0];
		if ( ! is_array( $quiz_data ) ) {
			return false;
		}

		// Quiz info
		$quiz_id          = isset( $quiz_data['quiz_id'] ) ? $quiz_data['quiz_id'] : 0;
		$selected_quiz_id = $trigger['meta'][ $this->get_trigger_meta() ];

		if ( intval( '-1' ) === intval( $selected_quiz_id ) || absint( $quiz_id ) === absint( $selected_quiz_id ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		if ( empty( $hook_args ) ) {
			return array();
		}

		// The data structure is different for logged-in and anonymous users
		$quiz_data = $hook_args[0];
		$user_data = $hook_args[1];
		$form_data = $hook_args[2];
		$post_id   = empty( $_REQUEST['tqb-post-id'] ) ? 0 : (int) sanitize_text_field( wp_unslash( $_REQUEST['tqb-post-id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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
		$user_id = isset( $user_data['user_id'] ) ? absint( $user_data['user_id'] ) : get_current_user_id();
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
			// The name is in $form_data (hook_args[2]), not in $user_data
			$full_name = $this->get_anonymous_user_name( $form_data );

			if ( ! empty( $full_name ) ) {
				list( $first_name, $last_name ) = $this->split_full_name( $full_name );
			}
		}

		$tokens = array(
			'TQB_QUIZ_ID'         => $quiz_id,
			'TQB_QUIZ_TITLE'      => $quiz_title,
			'TQB_QUIZ_RESULT'     => $text_result,
			'TQB_QUIZ_TYPE'       => $quiz_type,
			'TQB_USER_ID'         => $user_id,
			'TQB_USER_EMAIL'      => $email,
			'TQB_USER_FIRST_NAME' => $first_name,
			'TQB_USER_LAST_NAME'  => $last_name,
			'TQB_POST_ID'         => $post_id,
		);

		return $tokens;
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
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
			'TQB_POST_ID'         => array(
				'name'      => esc_html_x( 'Post ID', 'Thrive Quiz Builder', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'TQB_POST_ID',
				'tokenName' => esc_html_x( 'Post ID', 'Thrive Quiz Builder', 'uncanny-automator' ),
			),
		);

		return array_merge( $tokens, $trigger_tokens );
	}

	/**
	 * Get the full name for an anonymous user from the provided data.
	 *
	 * @param array $user_data The user data array.
	 *
	 * @return string The full name or empty string if not found.
	 */
	private function get_anonymous_user_name( $user_data ) {
		// Check for name in user_data first (this is where Thrive Quiz Builder stores the name)
		if ( isset( $user_data['name'] ) && ! empty( $user_data['name'] ) ) {
			return $user_data['name'];
		}

		// Fallback to username if name is not available
		if ( isset( $user_data['username'] ) && ! empty( $user_data['username'] ) ) {
			return $user_data['username'];
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
