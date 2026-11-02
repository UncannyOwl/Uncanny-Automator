<?php
namespace Uncanny_Automator;

/*
 * Class UOA_RECIPE_COMPLETED_WITH_ERRORS
 *
 * @package Uncanny_Automator\Pretty_Links\Trigger
 *
 * @version 1.0.0
 */
class UOA_USER_RECIPE_COMPLETED_WITH_ERRORS extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Setups the Trigger properties.
	 *
	 * @link https://developer.automatorplugin.com/adding-a-custom-trigger-to-uncanny-automator/
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_integration( 'UOA' );

		$this->set_trigger_code( 'UOA_USER_RECIPE_COMPLETED_WITH_ERRORS' );

		$this->set_trigger_meta( 'UOA_USER_RECIPE_COMPLETED_WITH_ERRORS_META' );

		$this->set_trigger_type( 'user' );

		// translators: Trigger sentence - Uncanny Automator
		$this->set_sentence( esc_html_x( 'A user completes a recipe with errors', 'Uncanny Automator', 'uncanny-automator' ) );

		$this->set_readable_sentence( esc_html_x( 'A user completes a recipe with errors', 'Uncanny Automator', 'uncanny-automator' ) );

		// The action hook to listen into. Automator invokes 'automator_recipe_completed' when a recipe is completed.
		$this->add_action( 'automator_recipe_completed_with_errors', 99, 4 );
	}

	/**
	 * Validates the Trigger entry.
	 *
	 * @param mixed[] $trigger
	 * @param mixed[] $hook_args The hook's recipe ID, user ID, recipe log ID and run args.
	 *
	 * @return bool False when this trigger or "An Automator recipe completes with errors" started the run.
	 */
	public function validate( $trigger, $hook_args ) {

		$run_args = (array) ( $hook_args[3] ?? array() );

		// A run either "completes with errors" trigger started fires neither: a recipe on one
		// whose own action fails would fire itself, or a recipe on the other, until the trigger
		// queue stops. The run's trigger ID is read, not its code: a run closed after its
		// trigger failed carries only the ID.
		$started_by = get_post_meta( absint( $run_args['trigger_id'] ?? 0 ), 'code', true );

		return ! in_array( $started_by, array( 'UOA_USER_RECIPE_COMPLETED_WITH_ERRORS', 'UOAERRORS' ), true );
	}
}
