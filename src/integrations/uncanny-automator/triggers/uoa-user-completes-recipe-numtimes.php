<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Class UOA_USER_COMPLETES_RECIPE_NUMTIMES
 *
 * @package Uncanny_Automator
 */
class UOA_USER_COMPLETES_RECIPE_NUMTIMES {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		// Migrate old keys to new key w/o underscore
		add_action(
			'admin_init',
			function () {
				if ( 'yes' === automator_get_option( 'uoa_recipe_completed_migrated', 'no' ) ) {
					return;
				}
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; no WordPress API rewrites a meta key or value across posts.
				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = %s WHERE meta_key LIKE %s", 'UOARECIPES', 'UOA_RECIPES' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration; no WordPress API rewrites a meta key or value across posts.
				$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = %s WHERE meta_key LIKE %s", 'UOARECIPES_readable', 'UOA_RECIPES_readable' ) );
				automator_update_option( 'uoa_recipe_completed_migrated', 'yes', false );
			},
			99
		);

		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'UOA' );
		$this->set_trigger_code( 'UOA_RECIPE_COMPLETED' );
		$this->set_trigger_meta( 'UOARECIPES' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/automator-core/' ) );
		/* Translators: Trigger sentence - Uncanny Automator */
		$this->set_sentence( sprintf( esc_html_x( 'A user completes {{a recipe:%1$s}} {{a number of:%2$s}} time(s)', 'Uncanny Automator', 'uncanny-automator' ), 'UOARECIPES:' . $this->get_trigger_meta(), 'NUMTIMES:' . $this->get_trigger_meta() ) );
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr_x( 'A user completes {{a recipe}}', 'Uncanny Automator', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->add_action( 'automator_recipe_completed' );
		$this->set_action_args_count( 4 );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();
	}

	/**
	 * @return array
	 */
	public function load_options() {
		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_trigger_meta() => array(
						Automator()->helpers->recipe->uncanny_automator->options->get_recipes( null, 'UOARECIPES' ),
						Automator()->helpers->recipe->number_of_times(),
					),
				),
			)
		);

		return $options;
	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		list( $recipe_id, $user_id, $recipe_log_id, $args ) = array_shift( $args );

		if ( empty( $user_id ) ) {
			return false;
		}

		if ( $this->get_user_id() !== $user_id ) {
			$this->set_user_id( $user_id );
		}

		// The run's own status decides, not its first action row: a run with none (no
		// actions, or its trigger failed) would read as not completed. Completed is also
		// the status the count in validate_conditions() counts.
		return Automator_Status::COMPLETED === Database::get_execution_log_store()->get_recipe_status( absint( $recipe_log_id ) );
	}

	/**
	 * Prepare to run the trigger.
	 *
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check contact status against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $recipe_id, $user_id, $recipe_log_id, $args ) = $args[0];
		// Get number of times recipe completed by user
		$user_completions = Automator()->user_completed_recipe_number_times( $recipe_id, $user_id );

		return $this->find_all( $this->trigger_recipes() )
					->where( array( 'UOARECIPES', 'NUMTIMES' ) )
					->match( array( $recipe_id, $user_completions ) )
					->format( array( 'intval', 'intval' ) )
					->get();
	}

}
