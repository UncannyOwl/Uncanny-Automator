<?php

namespace Uncanny_Automator\Integrations\Sg_Security;

/**
 * Class Sg_Unblock_User
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Sg_Security\Sg_Security_Helpers get_item_helpers()
 */
class Sg_Unblock_User extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SG_SECURITY' );
		$this->set_action_code( 'SG_UNBLOCK_USER' );
		$this->set_action_meta( 'SG_USER_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		// translators: %1$s is the user ID.
		$this->set_sentence( sprintf( esc_html_x( 'Unblock {{a user:%1$s}}', 'SG Security', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Unblock {{a user}}', 'SG Security', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'USER_ID'       => array(
					'name' => esc_html_x( 'User ID', 'SG Security', 'uncanny-automator' ),
					'type' => 'int',
				),
				'USER_EMAIL'    => array(
					'name' => esc_html_x( 'User email', 'SG Security', 'uncanny-automator' ),
					'type' => 'email',
				),
				'USER_USERNAME' => array(
					'name' => esc_html_x( 'Username', 'SG Security', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'User ID', 'SG Security', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'tokens'      => true,
				'description' => esc_html_x( 'Enter a WordPress user ID. The user will be unblocked but their role will not be restored.', 'SG Security', 'uncanny-automator' ),
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

		$target_user_id = isset( $parsed[ $this->get_action_meta() ] ) ? absint( $parsed[ $this->get_action_meta() ] ) : 0;

		if ( 0 === $target_user_id ) {
			$this->add_log_error( esc_html_x( 'Invalid user ID provided.', 'SG Security', 'uncanny-automator' ) );
			return false;
		}

		$user = get_userdata( $target_user_id );

		if ( false === $user ) {
			$this->add_log_error( sprintf( esc_html_x( 'User with ID %d does not exist.', 'SG Security', 'uncanny-automator' ), $target_user_id ) );
			return false;
		}

		$visitor = $this->get_item_helpers()->get_visitor_by_user_id( $target_user_id );

		if ( null === $visitor ) {
			$this->add_log_error( sprintf( esc_html_x( 'No visitor record found for user ID: %d', 'SG Security', 'uncanny-automator' ), $target_user_id ) );
			return false;
		}

		$this->get_item_helpers()->update_visitor_block( $visitor->id, 0 );

		$this->hydrate_tokens(
			array(
				'USER_ID'       => $target_user_id,
				'USER_EMAIL'    => $user->user_email,
				'USER_USERNAME' => $user->user_login,
			)
		);

		return true;
	}
}
