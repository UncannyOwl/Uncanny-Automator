<?php

namespace Uncanny_Automator;
/**
 * Class WP_CREATEUSER
 * @package Uncanny_Automator
 */
class WP_CREATEUSER {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

	private $action_code;
	private $action_meta;
	private $key_generated;
	private $key;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code   = 'CREATEUSER';
		$this->action_meta   = 'USERNAME';
		$this->key_generated = false;
		$this->key           = null;
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		global $uncanny_automator;

		$action = array(
			'author'             => $uncanny_automator->get_author_name( $this->action_code ),
			'support_link'       => $uncanny_automator->get_author_support_link( $this->action_code ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - WordPress */
			'sentence'           => sprintf(  esc_attr__( 'Create the user {{username:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WordPress */
			'select_option_name' =>  esc_attr__( 'Create a {{user}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'create_user' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options_group'      => [
				$this->action_meta => [
					$uncanny_automator->helpers->recipe->field->text_field( 'USERNAME',  esc_attr__( 'Username', 'uncanny-automator' ), true, 'text', '', true,  esc_attr__( 'Only alphanumeric, _, space, ., -, @', 'uncanny-automator' ) ),

					$uncanny_automator->helpers->recipe->field->text_field( 'EMAIL',  esc_attr__( 'Email', 'uncanny-automator' ), true, 'text', '', true, '' ),

					$uncanny_automator->helpers->recipe->field->text_field( 'FIRSTNAME',  esc_attr__( 'First name', 'uncanny-automator' ), true, 'text', '', false, '' ),

					$uncanny_automator->helpers->recipe->field->text_field( 'LASTNAME',  esc_attr__( 'Last name', 'uncanny-automator' ), true, 'text', '', false, '' ),

					$uncanny_automator->helpers->recipe->field->text_field( 'WEBSITE',  esc_attr__( 'Website', 'uncanny-automator' ), true, 'text', '', false, '' ),

					$uncanny_automator->helpers->recipe->field->text_field( 'PASSWORD',  esc_attr__( 'Password', 'uncanny-automator' ), true, 'text', '', false,  esc_attr__( 'Leave blank to automatically generate a password', 'uncanny-automator' ) ),

					$uncanny_automator->helpers->recipe->wp->options->wp_user_roles(),

					$uncanny_automator->helpers->recipe->field->text_field( 'SENDREGEMAIL',  esc_attr__( 'Send user notification', 'uncanny-automator' ), true, 'checkbox', '', false,  esc_attr__( 'Send the new user an email about their account.', 'uncanny-automator' ) ),
					[
						'input_type'        => 'repeater',
						'option_code'       => 'USERMETA_PAIRS',
						'label'             =>  esc_attr__( 'Meta', 'uncanny-automator' ),
						'required'          => false,
						'fields'            => [
							[
								'input_type'      => 'text',
								'option_code'     => 'meta_key',
								'label'           =>  esc_attr__( 'Key', 'uncanny-automator' ),
								'supports_tokens' => true,
								'required'        => true,
							],
							[
								'input_type'      => 'text',
								'option_code'     => 'meta_value',
								'label'           =>  esc_attr__( 'Value', 'uncanny-automator' ),
								'supports_tokens' => true,
								'required'        => true,
							],
						],
						'add_row_button'    =>  esc_attr__( 'Add pair', 'uncanny-automator' ),
						'remove_row_button' =>  esc_attr__( 'Remove pair', 'uncanny-automator' ),
					],
				],
			],
		);

		$uncanny_automator->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function create_user( $user_id, $action_data, $recipe_id, $args ) {


		global $uncanny_automator;

		// Username is mandatory. Return error its not valid.
		if ( isset( $action_data['meta']['USERNAME'] ) ) {
			$username = $uncanny_automator->parse->text( $action_data['meta']['USERNAME'], $recipe_id, $user_id, $args );
			if ( ! validate_username( $username ) ) {
				$uncanny_automator->complete->action( $user_id, $action_data, $recipe_id, sprintf(
				/* translators: Create a {{user}} - Error while creating a new user */
					 esc_attr__( 'Invalid username: %1$s', 'uncanny-automator' ),
					$username ) );
			}
		} else {
			$uncanny_automator->complete->action( $user_id, $action_data, $recipe_id,
				/* translators: Create a {{user}} - Error while creating a new user */
				 esc_attr__( 'Username was not set', 'uncanny-automator' )
			);

			return;
		}

		// Email is mandatory. Return error its not valid.
		if ( isset( $action_data['meta']['EMAIL'] ) ) {
			$email = $uncanny_automator->parse->text( $action_data['meta']['EMAIL'], $recipe_id, $user_id, $args );
			if ( ! is_email( $email ) ) {
				$uncanny_automator->complete->action( $user_id, $action_data, $recipe_id, sprintf(
				/* translators: Create a {{user}} - Error while creating a new user */
					 esc_attr__( 'Invalid email: %1$s', 'uncanny-automator' )
					, $email ) );
			}
		} else {
			$uncanny_automator->complete->action( $user_id, $action_data, $recipe_id,  esc_attr__( 'Username was not set', 'uncanny-automator' ) );

			return;
		}

		$userdata = array(
			'user_login' => $username,   //(string) The user's login username.
			'user_email' => $email,   //(string) The user email address.
		);

		if ( isset( $action_data['meta']['PASSWORD'] ) && ! empty( $action_data['meta']['PASSWORD'] ) ) {
			$userdata['user_pass'] = $uncanny_automator->parse->text( $action_data['meta']['PASSWORD'], $recipe_id, $user_id, $args );
		} else {
			$userdata['user_pass'] = wp_generate_password();
		}

		if ( isset( $action_data['meta']['WEBSITE'] ) && ! empty( $action_data['meta']['WEBSITE'] ) ) {
			$userdata['user_url'] = $uncanny_automator->parse->text( $action_data['meta']['WEBSITE'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['FIRSTNAME'] ) && ! empty( $action_data['meta']['FIRSTNAME'] ) ) {
			$userdata['first_name'] = $uncanny_automator->parse->text( $action_data['meta']['FIRSTNAME'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['LASTNAME'] ) && ! empty( $action_data['meta']['LASTNAME'] ) ) {
			$userdata['last_name'] = $uncanny_automator->parse->text( $action_data['meta']['LASTNAME'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['WPROLE'] ) && ! empty( $action_data['meta']['WPROLE'] ) ) {
			$userdata['role'] = $action_data['meta']['WPROLE'];
		}

		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			$uncanny_automator->complete->action( $user_id, $action_data, $recipe_id,
				/* translators: Create a {{user}} - Error while creating a new user */
				 esc_attr__( 'Failed to create a user', 'uncanny-automator' )
			);

			return;
		}

		$failed_meta_updates = [];


		if ( isset( $action_data['meta']['USERMETA_PAIRS'] ) && ! empty( $action_data['meta']['USERMETA_PAIRS'] ) ) {
			$fields = json_decode( $action_data['meta']['USERMETA_PAIRS'], true );

			foreach ( $fields as $meta ) {
				if ( isset( $meta['meta_key'] ) && ! empty( $meta['meta_key'] ) && isset( $meta['meta_value'] ) && ! empty( $meta['meta_value'] ) ) {
					$key   = $uncanny_automator->parse->text( $meta['meta_key'], $recipe_id, $user_id, $args );
					$value = $uncanny_automator->parse->text( $meta['meta_value'], $recipe_id, $user_id, $args );
					update_user_meta( $user_id, $key, $value );
				} else {
					$failed_meta_updates[ $meta['meta_key'] ] = $meta['meta_value'];
				}
			}
		}

		if ( ! empty( $failed_meta_updates ) ) {
			$failed_keys = "'" . implode( "','", array_keys( $failed_meta_updates ) ) . "'";
			$uncanny_automator->complete->action( $user_id, $action_data, $recipe_id, sprintf(
			/* translators: Create a {{user}} - Error while creating a new user */
				 esc_attr__( 'Meta keys failed to update: %1$s', 'uncanny-automator' ),
				$failed_keys ) );
		}

		wp_new_user_notification( $user_id, null, 'both' );

		$uncanny_automator->complete->action( $user_id, $action_data, $recipe_id );
	}

}
