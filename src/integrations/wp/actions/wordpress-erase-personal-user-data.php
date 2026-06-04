<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_ERASE_PERSONAL_USER_DATA
 *
 * @package Uncanny_Automator
 * @property Wp_Helpers $item_helpers
 */
class WP_ERASE_PERSONAL_USER_DATA extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WPERASEUSERDATA' );
		$this->set_action_meta( 'ERASEUSERDATA' );
		$this->set_requires_user( false );
		// translators: %1$s is the user.
		$this->set_sentence( sprintf( esc_html_x( 'Add a WordPress data erasure request for {{a user:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_action_meta() . '_user:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Add a WordPress data erasure request for {{a user}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => 'ERASEUSERDATA_user',
				'label'       => esc_html_x( 'Email', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'ERASEUSERDATA_flag',
				'label'       => esc_html_x( 'Send personal data erasure confirmation email', 'WordPress', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
				'description' => esc_html_x( 'When this is checked, the user will receive an email to confirm the erasure of data.  If the user does not take action, their data will not be deleted.', 'WordPress', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$flag     = $parsed['ERASEUSERDATA_flag'] ?? '';
		$email    = sanitize_text_field( $parsed['ERASEUSERDATA_user'] ?? '' );
		$the_user = get_user_by( 'email', $email );

		if ( ! $the_user instanceof \WP_User ) {
			$this->add_log_error(
				sprintf(
					// translators: %s is the email.
					esc_html_x( 'Unable to find a user with the provided email (%s).', 'WordPress', 'uncanny-automator' ),
					$email
				)
			);
			return false;
		}

		$request_id = wp_create_user_request( $the_user->user_email, 'remove_personal_data' );

		if ( is_wp_error( $request_id ) ) {
			$this->add_log_error( $request_id->get_error_message() );
			return false;
		}

		if ( ! $request_id ) {
			$this->add_log_error( esc_html_x( 'Unable to initiate confirmation request.', 'WordPress', 'uncanny-automator' ) );
			return false;
		}

		if ( 'true' === $flag ) {
			wp_send_user_request( $request_id );
		}

		return true;
	}
}
