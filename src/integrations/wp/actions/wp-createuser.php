<?php

namespace Uncanny_Automator;

/**
 * Class WP_CREATEUSER
 *
 * @package Uncanny_Automator
 */
class WP_CREATEUSER {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * @var string
	 */
	private $action_code;
	/**
	 * @var string
	 */
	private $action_meta;
	/**
	 * @var false
	 */
	private $key_generated;
	/**
	 * @var null
	 */
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

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/wordpress-core/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			'is_deprecated'      => true,
			/* translators: Action - WordPress */
			'sentence'           => sprintf( esc_attr__( 'Create the user {{username:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WordPress */
			'select_option_name' => esc_attr__( 'Create a {{user}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'create_user' ),
			'options_callback'	  => array( $this, 'load_options' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		
		Automator()->helpers->recipe->wp->options->load_options = true;

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options_group'      => array(
					$this->action_meta => array(
						Automator()->helpers->recipe->field->text_field( 'USERNAME', esc_attr__( 'Username', 'uncanny-automator' ), true, 'text', '', true, esc_attr__( 'Only alphanumeric, _, space, ., -, @', 'uncanny-automator' ) ),
	
						Automator()->helpers->recipe->field->text_field( 'EMAIL', esc_attr__( 'Email', 'uncanny-automator' ), true, 'text', '', true, '' ),
	
						Automator()->helpers->recipe->field->text_field( 'FIRSTNAME', esc_attr__( 'First name', 'uncanny-automator' ), true, 'text', '', false, '' ),
	
						Automator()->helpers->recipe->field->text_field( 'LASTNAME', esc_attr__( 'Last name', 'uncanny-automator' ), true, 'text', '', false, '' ),
	
						Automator()->helpers->recipe->field->text_field( 'WEBSITE', esc_attr__( 'Website', 'uncanny-automator' ), true, 'text', '', false, '' ),
	
						Automator()->helpers->recipe->field->text_field( 'PASSWORD', esc_attr__( 'Password', 'uncanny-automator' ), true, 'text', '', false, esc_attr__( 'Leave blank to automatically generate a password', 'uncanny-automator' ) ),
	
						Automator()->helpers->recipe->wp->options->wp_user_roles(),
	
						Automator()->helpers->recipe->field->text_field( 'SENDREGEMAIL', esc_attr__( 'Send user notification', 'uncanny-automator' ), true, 'checkbox', '', false, esc_attr__( 'Send the new user an email about their account.', 'uncanny-automator' ) ),
						array(
							'input_type'        => 'repeater',
							'option_code'       => 'USERMETA_PAIRS',
							'label'             => esc_attr__( 'Meta', 'uncanny-automator' ),
							'required'          => false,
							'fields'            => array(
								array(
									'input_type'      => 'text',
									'option_code'     => 'meta_key',
									'label'           => esc_attr__( 'Key', 'uncanny-automator' ),
									'supports_tokens' => true,
									'required'        => true,
								),
								array(
									'input_type'      => 'text',
									'option_code'     => 'meta_value',
									'label'           => esc_attr__( 'Value', 'uncanny-automator' ),
									'supports_tokens' => true,
									'required'        => true,
								),
							),
							'add_row_button'    => esc_attr__( 'Add pair', 'uncanny-automator' ),
							'remove_row_button' => esc_attr__( 'Remove pair', 'uncanny-automator' ),
						),
					),
				),
			)
		);
		return $options;
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

		// Username is mandatory. Return error its not valid.
		if ( isset( $action_data['meta']['USERNAME'] ) ) {
			$username = Automator()->parse->text( $action_data['meta']['USERNAME'], $recipe_id, $user_id, $args );
			if ( ! validate_username( $username ) ) {
				$action_data['complete_with_errors'] = true;
				/* translators: Create a {{user}} - Error while creating a new user */
				Automator()->complete->action( 0, $action_data, $recipe_id, sprintf( esc_attr__( 'Invalid username: %1$s', 'uncanny-automator' ), $username ) );

				return;
			}
		} else {
			$action_data['complete_with_errors'] = true;
			/* translators: Create a {{user}} - Error while creating a new user */
			Automator()->complete->action( 0, $action_data, $recipe_id, esc_attr__( 'Username was not set', 'uncanny-automator' ) );

			return;
		}

		// Email is mandatory. Return error its not valid.
		if ( isset( $action_data['meta']['EMAIL'] ) ) {
			$email = Automator()->parse->text( $action_data['meta']['EMAIL'], $recipe_id, $user_id, $args );
			if ( ! is_email( $email ) ) {
				$action_data['complete_with_errors'] = true;
				/* translators: Create a {{user}} - Error while creating a new user */
				Automator()->complete->action( 0, $action_data, $recipe_id, sprintf( esc_attr__( 'Invalid email: %1$s', 'uncanny-automator' ), $email ) );

				return;
			}
		} else {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( 0, $action_data, $recipe_id, esc_attr__( 'Username was not set', 'uncanny-automator' ) );

			return;
		}

		$userdata = array(
			'user_login' => $username,   //(string) The user's login username.
			'user_email' => $email,   //(string) The user email address.
		);

		if ( isset( $action_data['meta']['PASSWORD'] ) && ! empty( $action_data['meta']['PASSWORD'] ) ) {
			$userdata['user_pass'] = Automator()->parse->text( $action_data['meta']['PASSWORD'], $recipe_id, $user_id, $args );
		} else {
			$userdata['user_pass'] = wp_generate_password();
		}

		if ( isset( $action_data['meta']['WEBSITE'] ) && ! empty( $action_data['meta']['WEBSITE'] ) ) {
			$userdata['user_url'] = Automator()->parse->text( $action_data['meta']['WEBSITE'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['FIRSTNAME'] ) && ! empty( $action_data['meta']['FIRSTNAME'] ) ) {
			$userdata['first_name'] = Automator()->parse->text( $action_data['meta']['FIRSTNAME'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['LASTNAME'] ) && ! empty( $action_data['meta']['LASTNAME'] ) ) {
			$userdata['last_name'] = Automator()->parse->text( $action_data['meta']['LASTNAME'], $recipe_id, $user_id, $args );
		}

		if ( isset( $action_data['meta']['WPROLE'] ) && ! empty( $action_data['meta']['WPROLE'] ) ) {
			$userdata['role'] = $action_data['meta']['WPROLE'];
		} else {
			$userdata['role'] = get_option( 'default_role', 'subscriber' );
		}

		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			$messages = $user_id->get_error_messages();
			$err      = array();
			if ( $messages ) {
				foreach ( $messages as $msg ) {
					$err[] = $msg;
				}
			}
			if ( $err ) {
				$err = join( ', ', $err );
			}
			if ( ! empty( $err ) ) {
				$error_message = $err;
			} else {
				$error_message = esc_attr__( 'Failed to create a user', 'uncanny-automator' );
			}
			$action_data['complete_with_errors'] = true;
			/* translators: Create a {{user}} - Error while creating a new user */
			Automator()->complete->action( 0, $action_data, $recipe_id, $error_message );

			return;
		}

		$failed_meta_updates = array();

		if ( isset( $action_data['meta']['USERMETA_PAIRS'] ) && ! empty( $action_data['meta']['USERMETA_PAIRS'] ) ) {
			$fields = json_decode( $action_data['meta']['USERMETA_PAIRS'], true );

			foreach ( $fields as $meta ) {
				if ( isset( $meta['meta_key'] ) && ! empty( $meta['meta_key'] ) && isset( $meta['meta_value'] ) && ! empty( $meta['meta_value'] ) ) {
					$key   = Automator()->parse->text( $meta['meta_key'], $recipe_id, $user_id, $args );
					$value = Automator()->parse->text( $meta['meta_value'], $recipe_id, $user_id, $args );
					update_user_meta( $user_id, $key, $value );
				} else {
					$failed_meta_updates[ $meta['meta_key'] ] = $meta['meta_value'];
				}
			}
		}

		if ( ! empty( $failed_meta_updates ) ) {
			$failed_keys = "'" . implode( "','", array_keys( $failed_meta_updates ) ) . "'";
			/* translators: Create a {{user}} - Error while creating a new user */
			Automator()->complete->action( $user_id, $action_data, $recipe_id, sprintf( esc_attr__( 'Meta keys failed to update: %1$s', 'uncanny-automator' ), $failed_keys ) );
		}

		wp_new_user_notification( $user_id, null, 'both' );

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}

}
