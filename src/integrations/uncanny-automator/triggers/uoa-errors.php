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

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Uncanny Automator */
			'sentence'            => sprintf( esc_attr__( 'An Automator recipe completes with errors', 'uncanny-automator' ) ),
			/* translators: Logged-in trigger - Uncanny Automator */
			'select_option_name'  => esc_attr__( 'An Automator recipe completes with errors', 'uncanny-automator' ),
			'action'              => 'uap_recipe_completed',
			'priority'            => 99,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'error' ),
			'options'             => array(),
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
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

		global $uncanny_automator;

		global $wpdb;
		// get recipe actions
		$table_name = $wpdb->prefix . 'uap_action_log';
		$q          = "SELECT automator_action_id FROM $table_name WHERE automator_recipe_log_id = {$recipe_log_id} AND error_message != ''";
		$errors     = $wpdb->get_results( $q );

		if ( empty( $errors ) ) {
			// bail early
			return;
		}
		if ( ! empty( $errors ) ) {

			$args = [
				'code'           => $this->trigger_code,
				'meta'           => $this->trigger_meta,
				'user_id'        => $user_id,
				'ignore_post_id' => true,
			];

			$uncanny_automator->maybe_add_trigger_entry( $args, false );

			$args = $uncanny_automator->maybe_add_trigger_entry( $args, false );
			if ( $args ) {
				foreach ( $args as $result ) {
					if ( true === $result['result'] ) {

						$recipe = get_post( $recipe_id );

						if ( $recipe ) {

							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOAERRORS_recipe_id',
									'meta_value'     => $recipe->ID,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOAERRORS_recipe_title',
									'meta_value'     => $recipe->post_title,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOAERRORS_recipe_edit_link',
									'meta_value'     => get_edit_post_link( $recipe->ID ),
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOAERRORS_recipe_log_url',
									'meta_value'     => "recipe_id=$recipe_id&user_id=$user_id",
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'UOAERRORS_action_log_url',
									'meta_value'     => "recipe_id=$recipe_id&user_id=$user_id",
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);
							// FUTURE USE STORE THE ACTION ID OR OTHER INFORMATION ABOUT THE ACTION THAT ERRORED OUT
							//				foreach ( $errors as $error ) {
							//					$automator_action_id = $error->automator_action_id;
							//					$action = get_post($automator_action_id);
							//					$uncanny_automator->insert_trigger_meta(
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
						}
						$uncanny_automator->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}