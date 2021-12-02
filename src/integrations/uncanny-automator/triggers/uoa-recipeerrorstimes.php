<?php

namespace Uncanny_Automator;

/**
 *
 */
class UOA_RECIPEERRORSTIMES {

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
		$this->trigger_code = 'UOARECIPEERRORS';
		$this->trigger_meta = 'UOARECIPE';
		$this->num_times    = 'RECIPENUMTIMES';
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
			'type'                => 'anonymous',
			/* translators: Logged-in trigger - Uncanny Automator */
			'sentence'            => sprintf( esc_attr__( '{{A recipe:%1$s}} completes with errors {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, $this->num_times ),
			/* translators: Logged-in trigger - Uncanny Automator */
			'select_option_name'  => esc_attr__( '{{A recipe}} completes with errors {{a number of}} time(s)', 'uncanny-automator' ),
			'action'              => 'automator_recipe_completed_with_errors',
			'priority'            => 299,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'on_completion' ),
			'options'             => array(
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
	public function on_completion( $recipe_id, $user_id, $recipe_log_id, $args ) {

		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_recipe    = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$num_times          = Automator()->get->meta_from_recipes( $recipes, $this->num_times );
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

		global $wpdb;
		$table_name      = $wpdb->prefix . Automator()->db->tables->recipe;
		$completed_count = $wpdb->get_var( $wpdb->prepare( "SELECT count(ID) FROM $table_name WHERE automator_recipe_id = %d AND completed = %d", $recipe_id, 2 ) ); //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 0 === $completed_count ) {
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
					if ( 0 === absint( $completed_count ) % absint( $num_times[ $_recipe_id ][ $trigger_id ] ) ) {
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
				'user_id'          => 0,
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
				$recipe = get_post( $recipe_id );

				if ( ! $recipe ) {
					continue;
				}

				Automator()->insert_trigger_meta(
					array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'meta_key'       => 'UOARECIPES_recipe_id',
						'meta_value'     => $recipe->ID,
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					)
				);
				Automator()->insert_trigger_meta(
					array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'meta_key'       => 'UOARECIPES_recipe_title',
						'meta_value'     => $recipe->post_title,
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					)
				);
				Automator()->insert_trigger_meta(
					array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'meta_key'       => 'UOARECIPES_recipe_edit_link',
						'meta_value'     => get_edit_post_link( $recipe->ID ),
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					)
				);
				Automator()->insert_trigger_meta(
					array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'meta_key'       => 'UOARECIPES_recipe_log_url',
						'meta_value'     => "recipe_id=$recipe_id&user_id=$user_id",
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					)
				);
				Automator()->insert_trigger_meta(
					array(
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'meta_key'       => 'UOARECIPES_action_log_url',
						'meta_value'     => "recipe_id=$recipe_id&user_id=$user_id",
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					)
				);

				Automator()->maybe_trigger_complete( $result['args'] );
			}
		}
	}
}
