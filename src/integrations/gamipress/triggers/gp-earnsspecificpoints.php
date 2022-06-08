<?php

namespace Uncanny_Automator;

/**
 * Class GP_EARNSSPECIFICPOINTS
 *
 * @package Uncanny_Automator
 */
class GP_EARNSSPECIFICPOINTS {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * SetAutomatorTriggers constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'GPSPECIFICPOINTS';
		$this->trigger_meta = 'GPPOINTS';
		$this->define_trigger();
	}

	/**
	 * Define trigger settings
	 */
	public function define_trigger() {
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/gamipress/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - GamiPress */
			'sentence'            => sprintf( esc_attr__( 'A user earns {{greater than, less than, or equal to:%3$s}} {{a number of:%1$s}} {{a specific type of:%2$s}} points in a single transaction', 'uncanny-automator-pro' ), 'GPPOINTVALUE', $this->trigger_meta, 'NUMBERCOND' ),
			/* translators: Logged-in trigger - GamiPress */
			'select_option_name'  => esc_attr__( 'A user earns {{greater than, less than, or equal to}} {{a number of}} {{a specific type of}} points in a single transaction', 'uncanny-automator-pro' ),
			'action'              => 'gamipress_award_points_to_user',
			'priority'            => 20,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'earns_specific_points' ),
			'options'             => array(
				Automator()->helpers->recipe->gamipress->options->list_gp_points_types( esc_attr__( 'Point type', 'uncanny-automator' ), $this->trigger_meta ),
				Automator()->helpers->recipe->field->int(
					array(
						'option_code' => 'GPPOINTVALUE',
						'label'       => esc_attr__( 'Points', 'uncanny-automator-pro' ),
						'placeholder' => esc_attr__( 'Example: 15', 'uncanny-automator-pro' ),
						'input_type'  => 'int',
						'default'     => null,
					)
				),
				Automator()->helpers->recipe->field->less_or_greater_than(),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Trigger handler function.
	 *
	 * @param $user_id
	 * @param $points
	 * @param $points_type
	 * @param $args
	 *
	 * @return void
	 */
	public function earns_specific_points( $user_id, $points, $points_type, $args ) {
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_type      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$required_points    = Automator()->get->meta_from_recipes( $recipes, 'GPPOINTVALUE' );
		$required_condition = Automator()->get->meta_from_recipes( $recipes, 'NUMBERCOND' );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( isset( $required_type[ $recipe_id ] ) && isset( $required_type[ $recipe_id ][ $trigger_id ] ) ) {
					if ( (string) $required_type[ $recipe_id ][ $trigger_id ] === (string) $points_type ) {
						if ( Automator()->utilities->match_condition_vs_number( $required_condition[ $recipe_id ][ $trigger_id ], $required_points[ $recipe_id ][ $trigger_id ], $points ) ) {
							$matched_recipe_ids[] = array(
								'recipe_id'  => $recipe_id,
								'trigger_id' => $trigger_id,
							);
						}
					}
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				if ( ! Automator()->is_recipe_completed( $matched_recipe_id['recipe_id'], $user_id ) ) {
					$pass_args = array(
						'code'             => $this->trigger_code,
						'meta'             => $this->trigger_meta,
						'recipe_to_match'  => $matched_recipe_id['recipe_id'],
						'trigger_to_match' => $matched_recipe_id['trigger_id'],
						'ignore_post_id'   => true,
						'user_id'          => $user_id,
					);

					$args = Automator()->maybe_add_trigger_entry( $pass_args, false );
					if ( $args ) {
						foreach ( $args as $result ) {
							if ( true === $result['result'] ) {

								$trigger_meta = array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								);

								$trigger_meta['meta_key']   = $this->trigger_meta;
								$trigger_meta['meta_value'] = maybe_serialize( gamipress_get_points_type_singular( $points_type ) );
								Automator()->insert_trigger_meta( $trigger_meta );

								$trigger_meta['meta_key']   = 'GPPOINTVALUE';
								$trigger_meta['meta_value'] = maybe_serialize( $points );
								Automator()->insert_trigger_meta( $trigger_meta );

								Automator()->maybe_trigger_complete( $result['args'] );
							}
						}
					}
				}
			}
		}
	}

}
