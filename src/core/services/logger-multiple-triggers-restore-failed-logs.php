<?php
namespace Uncanny_Automator\Services;

use Uncanny_Automator\Automator_Status;

/**
 * Temporary class to handle restoration of 'Failed' recipes.
 *
 * This class can be remove after some time.
 *
 * @package Uncanny_Automator\Services
 */
class Multiple_Triggers_Restore_Failed_Logs {

	/**
	 * @var string
	 */
	protected $option_key = 'automtor_multiple_triggers_restore_failed_logs';

	/**
	 * Restores all 'Failed' recipes back to 'in-progress'.
	 *
	 * Allows the cron to rerun this as failed recipe but excluding multiple trigger.
	 *
	 * @return bool
	 */
	public function restore_once() {

		if ( $this->has_restored() ) {
			return false;
		}

		$failed_recipes = $this->get_failed_recipes();

		foreach ( $failed_recipes as $recipe_log ) {
			// Restore status back to zero.
			Automator()->db->recipe->mark_complete( $recipe_log['ID'], Automator_Status::NOT_COMPLETED );
		}

		automator_add_option( $this->option_key, time() );

		return true;

	}

	/**
	 * Retrieves all recipes that have been marked as failure.
	 *
	 * @return array
	 */
	public function get_failed_recipes() {

		// Restore everything.
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, completed FROM {$wpdb->prefix}uap_recipe_log WHERE completed = %d",
				Automator_Status::FAILED
			),
			ARRAY_A
		);

	}

	/**
	 * Determines whether failed recipes has been restored or not.
	 *
	 * @return bool
	 */
	public function has_restored() {

		$has_restored = automator_get_option( $this->option_key, false );

		// Return false if there is no options record.
		if ( false === $has_restored ) {
			return false;
		}

		// Only return true if timestamp was successfully stored.
		if ( is_numeric( $has_restored ) ) {
			return true;
		}

		// Otherwise, return false.
		return false;
	}
}
