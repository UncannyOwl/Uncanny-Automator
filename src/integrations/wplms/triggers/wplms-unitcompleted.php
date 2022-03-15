<?php

namespace Uncanny_Automator;

/**
 * Class WPLMS_UNITCOMPLETED
 *
 * @package Uncanny_Automator
 */
class WPLMS_UNITCOMPLETED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPLMS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPLMSUNITCOMPLETED';
		$this->trigger_meta = 'WPLMS_UNIT';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-lms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP LMS */
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a unit:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WP LMS */
			'select_option_name'  => esc_attr__( 'A user completes {{a unit}}', 'uncanny-automator' ),
			'action'              => 'wplms_unit_complete',
			'priority'            => 10,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'wplms_unit_completed' ),
			'options'             => array(
				Automator()->helpers->recipe->wplms->options->all_wplms_units(),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param integer $unit_id
	 * @param null $course_progress
	 * @param integer $course_id
	 * @param integer $user_id
	 */
	public function wplms_unit_completed( $unit_id, $course_progress, $course_id, $user_id ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( empty( $user_id ) ) {
			return;
		}
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_unit      = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		if ( empty( $recipes ) ) {
			return;
		}

		if ( empty( $required_unit ) ) {
			return;
		}

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$recipe_id  = absint( $recipe_id );
				$trigger_id = absint( $trigger['ID'] );
				if ( ! isset( $required_unit[ $recipe_id ] ) ) {
					continue;
				}
				if ( ! isset( $required_unit[ $recipe_id ][ $trigger_id ] ) ) {
					continue;
				}
				if (
					( intval( '-1' ) === intval( $required_unit[ $recipe_id ][ $trigger_id ] ) )
					||
					( absint( $unit_id ) === absint( $required_unit[ $recipe_id ][ $trigger_id ] ) )
				) {
					$matched_recipe_ids[ $recipe_id ] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
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
				'is_signed_in'     => true,
			);

			$arr = Automator()->maybe_add_trigger_entry( $pass_args, false );
			if ( $arr ) {
				foreach ( $arr as $result ) {
					if ( true === $result['result'] ) {
						$token_args = array(
							'unit_id'         => $unit_id,
							'course_progress' => $course_progress,
							'course_id'       => $course_id,
							'user_id'         => $user_id,
							'action'          => 'unit_completed',
						);
						do_action( 'automator_wplms_save_tokens', $token_args, $result['args'] );
						Automator()->maybe_trigger_complete( $result['args'] );
					}
				}
			}
		}
	}
}
