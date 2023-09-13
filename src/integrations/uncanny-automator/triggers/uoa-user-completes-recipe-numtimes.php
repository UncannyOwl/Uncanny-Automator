<?php

namespace Uncanny_Automator;

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
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'UOA' );
		$this->set_trigger_code( 'UOA_RECIPE_COMPLETED' );
		$this->set_trigger_meta( 'UOA_RECIPES' );
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

		global $wpdb;
		// get recipe actions
		$table_name    = $wpdb->prefix . Automator()->db->tables->action;
		$recipe_status = $wpdb->get_var( $wpdb->prepare( "SELECT completed FROM $table_name WHERE automator_recipe_log_id = %d", $recipe_log_id ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( 0 === absint( $recipe_status ) || 5 === absint( $recipe_status ) ) {
			return false;
		}

		return true;
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
