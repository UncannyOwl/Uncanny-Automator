<?php

namespace Uncanny_Automator;

use uncanny_learndash_codes;

/**
 * Class UC_CANCEL_CODE
 *
 * @package Uncanny_Automator
 */
class UC_CANCEL_CODE {

	use Recipe\Action_Tokens;

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYCODE';
	/**
	 * @var string
	 */
	private $action_code;
	/**
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'UCCANCELCODE';
		$this->action_meta = 'WPUCCANCELCODE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/set-up-uncanny-codes-for-wordpress/' ),
			'integration'        => self::$integration,
			'requires_user'      => false,
			'code'               => $this->action_code,
			/* translators: Logged-in trigger - Uncanny Codes. */
			'sentence'           => sprintf( esc_attr__( 'Cancel {{a code:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in trigger - Uncanny Codes. */
			'select_option_name' => esc_attr__( 'Cancel {{a code}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'cancel_a_code' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 */
	public function load_options() {
		$options = array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->field->text_field( $this->action_meta, esc_attr__( 'Code', 'uncanny-automator' ), true, 'text', '', true ),
				),
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function cancel_a_code( $user_id, $action_data, $recipe_id, $args ) {

		$code_name = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );

		$code_data = \uncanny_learndash_codes\Database::is_coupon_valid( $code_name );

		if ( is_null( $code_data ) || ! is_object( $code_data ) ) {
			$error_message = esc_attr__( 'Invalid code provided.', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$cancelled = $this->cancel_code( $code_data );

		if ( true !== $cancelled ) {
			$error_message = esc_attr__( 'Something went wrong! Codes was not cancelled, try again.', 'uncanny-automator' );
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

	private function cancel_code( $code_details ) {

		if ( empty( $code_details ) || ! is_object( $code_details ) || ! isset( $code_details->ID ) || ! isset( $code_details->code_group ) ) {
			return false;
		}

		$code_id  = $code_details->ID;
		$group_id = $code_details->code_group;

		global $wpdb;
		$result = $wpdb->update(
			$wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes,
			array(
				'is_active' => 0,
			),
			array(
				'ID'         => $code_id,
				'code_group' => $group_id,
			),
			array(
				'%s',
			),
			array(
				'%d',
				'%d',
			)
		);

		if ( $result ) {
			return true;
		}

		return false;
	}
}
