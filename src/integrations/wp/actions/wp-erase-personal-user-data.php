<?php

namespace Uncanny_Automator;

/**
 * Class WP_ERASE_PERSONAL_USER_DATA
 *
 * @package Uncanny_Automator
 */
class WP_ERASE_PERSONAL_USER_DATA {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'WPERASEUSERDATA';
		$this->action_meta = 'ERASEUSERDATA';

		if ( is_admin() ) {
			add_action( 'wp_loaded', array( $this, 'plugins_loaded' ), 99 );
		} else {
			$this->define_action();
		}
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {
		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/wordpress/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			/* translators: Action - WordPress Core */
			'sentence'           => sprintf( esc_attr__( 'Add a WordPress data erasure request for {{a user:%1$s}}', 'uncanny-automator' ), $this->action_code ),
			/* translators: Action - WordPress Core */
			'select_option_name' => esc_attr__( 'Add a WordPress data erasure request for {{a user}}', 'uncanny-automator' ),
			'priority'           => 11,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'erase_user_personal_data' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	public function plugins_loaded() {
		$this->define_action();
	}

	/**
	 * load_options
	 *
	 */
	public function load_options() {
		$options = array(
			'options_group' => array(
				$this->action_code => array(
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => $this->action_meta . '_user',
							'label'       => esc_attr__( 'Email', 'uncanny-automator' ),
							'required'    => true,
							'tokens'      => true,
						)
					),
					array(
						'option_code' => $this->action_meta . '_flag',
						'label'       => __( 'Send personal data erasure confirmation email', 'uncanny-automator' ),
						'input_type'  => 'checkbox',
						'is_toggle'   => true,
						'description' => __( 'When this is checked, the user will receive an email to confirm the erasure of data.  If the user does not take action, their data will not be deleted.', 'uncanny-automator' ),
					),
				),
			),
		);

		$options = Automator()->utilities->keep_order_of_options( $options );

		return $options;
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function erase_user_personal_data( $user_id, $action_data, $recipe_id, $args ) {

		$flag     = Automator()->parse->text( $action_data['meta'][ $this->action_meta . '_flag' ], $recipe_id, $user_id, $args );
		$user     = Automator()->parse->text( $action_data['meta'][ $this->action_meta . '_user' ], $recipe_id, $user_id, $args );
		$the_user = get_user_by( 'email', $user );
		if ( ! $the_user instanceof \WP_User ) {
			// translators: Email
			$message                             = sprintf( __( 'Unable to find a user with the provided email (%s).', 'uncanny-automator' ), $user );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $message );

			return;
		}

		$request_id = wp_create_user_request( $the_user->user_email, 'remove_personal_data' );

		if ( is_object( $request_id ) ) {
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $request_id->get_error_message() );

			return;
		}

		if ( ! $request_id ) {
			$message                             = __( 'Unable to initiate confirmation request.', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $message );

			return;
		}

		if ( 'true' === $flag ) {
			wp_send_user_request( $request_id );
		}
		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}
}
