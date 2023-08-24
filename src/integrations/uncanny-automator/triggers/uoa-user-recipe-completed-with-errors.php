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

		$this->set_sentence(
			/* translators: Trigger sentence */
			esc_attr__( 'A user completes a recipe with errors', 'uncanny-automator' )
		);

		$this->set_readable_sentence(
		/* translators: Trigger sentence */
			esc_attr__( 'A user completes a recipe with errors', 'uncanny-automator' )
		);

		// The action hook to listen into. Automator invokes 'automator_recipe_completed' when a recipe is completed.
		$this->add_action( 'automator_recipe_completed_with_errors', 99, 4 );

	}

	/**
	 * Validates the Trigger entry.
	 *
	 * @param mixed[] $trigger
	 * @param mixed[] $hook_args
	 *
	 * @return bool Only fires the trigger when its processed once.
	 */
	public function validate( $trigger, $hook_args ) {

		static $processed_once = 0;

		if ( 'UOA_USER_RECIPE_COMPLETED_WITH_ERRORS' === $trigger['meta']['code'] ) {
			$processed_once++;
		}

		return $processed_once === 1;

	}

}

