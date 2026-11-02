<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Class UOA_ERRORS
 *
 * @package Uncanny_Automator
 */
class UOA_ERRORS {

	/**
	 * Integration code
	 *
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
			'is_deprecated'       => true,
			/* translators: Logged-in trigger - Uncanny Automator */
			'sentence'            => esc_attr__( 'An Automator recipe completes with errors', 'uncanny-automator' ),
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

		// The run's own status decides, not its action rows: a run with none (its trigger
		// failed) would be missed. Completed with errors is the status the runner fires
		// automator_recipe_completed_with_errors for.
		if ( Automator_Status::COMPLETED_WITH_ERRORS !== Database::get_execution_log_store()->get_recipe_status( absint( $recipe_log_id ) ) ) {
			return;
		}

		// A run either "completes with errors" trigger started fires neither: a recipe on one
		// whose own action fails would fire itself, or a recipe on the other, until the trigger
		// queue stops. The run args carry the ID of the trigger that started the run.
		$started_by = get_post_meta( absint( $args['trigger_id'] ?? 0 ), 'code', true );
		if ( in_array( $started_by, array( 'UOAERRORS', 'UOA_USER_RECIPE_COMPLETED_WITH_ERRORS' ), true ) ) {
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
			Automator()->maybe_trigger_complete( $result['args'] );
		}
	}
}
