<?php

namespace Uncanny_Automator;

/**
 * Class UOA_RECIPECOMPLETED
 *
 * @package Uncanny_Automator
 */
class UOA_RECIPECOMPLETED {

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
	 * @var string
	 */
	private $num_times;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'UOARECIPES';
		$this->trigger_meta = 'UOARECIPE';
		$this->num_times    = 'NUMTIMES';
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
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a recipe:%1$s}} without errors {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, $this->num_times ),
			/* translators: Logged-in trigger - Uncanny Automator */
			'select_option_name'  => esc_attr__( 'A user completes {{a recipe}} without errors {{a number of}} time(s)', 'uncanny-automator' ),
			'action'              => 'automator_recipe_completed',
			'priority'            => 99,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'on_completion' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @return array
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->uncanny_automator->options->get_recipes(),
					Automator()->helpers->recipe->field->int(
						array(
							'option_code' => $this->num_times,
							'label'       => esc_attr__( 'Number of times', 'uncanny-automator' ),
							'placeholder' => esc_attr__( 'Example: 1', 'uncanny-automator' ),
							'default'     => '1',
						)
					),
				),

			)
		);
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $recipe_id
	 * @param $user_id
	 * @param $recipe_log_id
	 * @param $args
	 */
	public function on_completion( $recipe_id, $user_id, $recipe_log_id, $args ) {
		// It's a logged in recipe, User ID is required.
		if ( empty( $user_id ) ) {
			return;
		}
		global $wpdb;
		// get recipe actions
		$table_name = $wpdb->prefix . Automator()->db->tables->action;
		$errors     = $wpdb->get_var( $wpdb->prepare( "SELECT error_message FROM $table_name WHERE automator_recipe_log_id = %d AND completed = %d", $recipe_log_id, 1 ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $errors ) ) {
			// bail early
			return;
		}

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_recipe    = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$num_times          = Automator()->get->meta_from_recipes( $recipes, $this->num_times );
		$user_completions   = Automator()->user_completed_recipe_number_times( $recipe_id, $user_id );
		$matched_recipe_ids = array();

		if ( empty( $recipes ) ) {
			return;
		}
		if ( empty( $required_recipe ) ) {
			return;
		}
		if ( empty( $num_times ) ) {
			return;
		}
		if ( 0 === $user_completions ) {
			return;
		}

		//Add where option is set to Any product
		foreach ( $recipes as $_recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = absint( $trigger['ID'] );
				if ( intval( '-1' ) === intval( $required_recipe[ $_recipe_id ][ $trigger_id ] ) || (int) $recipe_id === (int) $required_recipe[ $_recipe_id ][ $trigger_id ] ) {
					if ( ! isset( $num_times[ $_recipe_id ] ) ) {
						continue;
					}
					if ( ! isset( $num_times[ $_recipe_id ][ $trigger_id ] ) ) {
						continue;
					}
					if ( absint( $user_completions ) === absint( $num_times[ $_recipe_id ][ $trigger_id ] ) ) {
						$matched_recipe_ids[ $_recipe_id ] = array(
							'recipe_id'  => $_recipe_id,
							'trigger_id' => $trigger_id,
						);
					}
				}
			}
		}

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$pass_args = array(
				'code'             => $this->trigger_code,
				'meta'             => $this->trigger_meta,
				'user_id'          => $user_id,
				'recipe_to_match'  => $matched_recipe_id['recipe_id'],
				'trigger_to_match' => $matched_recipe_id['trigger_id'],
				'ignore_post_id'   => true,
			);
			$args      = Automator()->maybe_add_trigger_entry( $pass_args, false );
			if ( empty( $args ) ) {
				continue;
			}
			foreach ( $args as $result ) {
				if ( false === $result['result'] ) {
					continue;
				}
				//              $recipe = get_post( $recipe_id );
				//
				//              if ( ! $recipe ) {
				//                  continue;
				//              }

				$trigger_meta = array(
					'user_id'        => $user_id,
					'trigger_id'     => $result['args']['trigger_id'],
					'trigger_log_id' => $result['args']['get_trigger_id'],
					'run_number'     => $result['args']['run_number'],
				);

				Automator()->db->token->save( 'recipe_id', $recipe_id, $trigger_meta );
				Automator()->db->token->save( 'recipe_log_id', $recipe_log_id, $trigger_meta );

				Automator()->maybe_trigger_complete( $result['args'] );
			}
		}
	}
}
