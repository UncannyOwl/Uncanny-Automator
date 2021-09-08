<?php

namespace Uncanny_Automator;

/**
 * Class UOA_ERRORS
 * @package Uncanny_Automator
 */
class UOA_ERRORS {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'UOA';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'UOAERRORS';
		$this->trigger_meta = 'UOAERROR';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/automator-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Uncanny Automator */
			'sentence'            => sprintf( esc_attr__( 'An Automator recipe completes with errors', 'uncanny-automator' ) ),
			/* translators: Logged-in trigger - Uncanny Automator */
			'select_option_name'  => esc_attr__( 'An Automator recipe completes with errors', 'uncanny-automator' ),
			'action'              => 'automator_recipe_completed',
			'priority'            => 99,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'error' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $recipe_id
	 * @param $user_id
	 * @param $recipe_log_id
	 * @param $args
	 */
	public function error( $recipe_id, $user_id, $recipe_log_id, $args ) {

		global $wpdb;
		// get recipe actions
		$table_name = $wpdb->prefix . Automator()->db->tables->action;
		$errors     = $wpdb->get_results( $wpdb->prepare( "SELECT automator_action_id FROM $table_name WHERE automator_recipe_log_id = %d AND error_message != %s", $recipe_log_id, '' ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $errors ) ) {
			// bail early
			return;
		}

		$args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
		);

		$args = Automator()->maybe_add_trigger_entry( $args, false );
		if ( empty( $args ) ) {
			return;
		}
		foreach ( $args as $result ) {
			if ( false === $result['result'] ) {
				continue;
			}

			$recipe = get_post( $recipe_id );
			if ( ! $recipe ) {
				continue;
			}

			Automator()->insert_trigger_meta(
				array(
					'user_id'        => $user_id,
					'trigger_id'     => $result['args']['trigger_id'],
					'meta_key'       => 'UOAERRORS_recipe_id',
					'meta_value'     => $recipe->ID,
					'trigger_log_id' => $result['args']['get_trigger_id'],
					'run_number'     => $result['args']['run_number'],
				)
			);
			Automator()->insert_trigger_meta(
				array(
					'user_id'        => $user_id,
					'trigger_id'     => $result['args']['trigger_id'],
					'meta_key'       => 'UOAERRORS_recipe_title',
					'meta_value'     => $recipe->post_title,
					'trigger_log_id' => $result['args']['get_trigger_id'],
					'run_number'     => $result['args']['run_number'],
				)
			);
			Automator()->insert_trigger_meta(
				array(
					'user_id'        => $user_id,
					'trigger_id'     => $result['args']['trigger_id'],
					'meta_key'       => 'UOAERRORS_recipe_edit_link',
					'meta_value'     => get_edit_post_link( $recipe->ID ),
					'trigger_log_id' => $result['args']['get_trigger_id'],
					'run_number'     => $result['args']['run_number'],
				)
			);
			Automator()->insert_trigger_meta(
				array(
					'user_id'        => $user_id,
					'trigger_id'     => $result['args']['trigger_id'],
					'meta_key'       => 'UOAERRORS_recipe_log_url',
					'meta_value'     => "recipe_id=$recipe_id&user_id=$user_id",
					'trigger_log_id' => $result['args']['get_trigger_id'],
					'run_number'     => $result['args']['run_number'],
				)
			);
			Automator()->insert_trigger_meta(
				array(
					'user_id'        => $user_id,
					'trigger_id'     => $result['args']['trigger_id'],
					'meta_key'       => 'UOAERRORS_action_log_url',
					'meta_value'     => "recipe_id=$recipe_id&user_id=$user_id",
					'trigger_log_id' => $result['args']['get_trigger_id'],
					'run_number'     => $result['args']['run_number'],
				)
			);
			// FUTURE USE STORE THE ACTION ID OR OTHER INFORMATION ABOUT THE ACTION THAT ERRORED OUT
			//				foreach ( $errors as $error ) {
			//					$automator_action_id = $error->automator_action_id;
			//					$action = get_post($automator_action_id);
			//					Automator()->insert_trigger_meta(
			//						[
			//							'user_id'        => $user_id,
			//							'trigger_id'     => $args['trigger_id'],
			//							'meta_key'       => 'UOAERRORS_action_id',
			//							'meta_value'     => $action->ID,
			//							'trigger_log_id' => $args['get_trigger_id'],
			//							'run_number'     => $args['run_number'],
			//						]
			//					);
			//
			//
			//				}

			Automator()->maybe_trigger_complete( $result['args'] );

		}
	}
}
