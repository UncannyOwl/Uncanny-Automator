<?php

namespace Uncanny_Automator;

use TINCANNYSNC\Database;

/**
 * Class TC_USERATTAINSSCORE
 *
 * @package Uncanny_Automator
 */
class UOTC_USERATTAINSSCORE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UOTC';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		// We are only loading it if Tin Canny exists
		//if ( defined( 'UNCANNY_REPORTING_VERSION' ) ) {
		$this->trigger_code = 'USERATTAINSSCORE';
		$this->trigger_meta = 'TCUSERATTAINSSCORE';
		$this->define_trigger();
		//}
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$modules = Database::get_modules();

		$options       = array();
		$options['-1'] = esc_attr__( 'Any module', 'uncanny-automator' );

		foreach ( $modules as $module ) {
			$options[ $module->ID ] = $module->file_name;
		}

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/tin-canny-reporting/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Uncanny Reporting */
			'sentence'            => sprintf( __( 'A user attains a score {{greater than, less than or equal to:%1$s}} {{a score:%2$s}} on {{a Tin Can module:%3$s}}', 'uncanny-automator' ), 'NUMBERCOND', $this->trigger_meta, 'TCMODULEINTERACTION' ),
			/* translators: Logged-in trigger - Uncanny Reporting */
			'select_option_name'  => __( 'A user attains {{a score}} {{greater than, less than or equal to}} on {{a Tin Can module}}', 'uncanny-automator' ),
			'action'              => 'tincanny_module_result_processed',
			'priority'            => 99,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'tincan_module_result_processed' ),
			'options'             => array(
				Automator()->helpers->recipe->field->select(
					array(
						'option_code' => 'TCMODULEINTERACTION',
						'label'       => esc_attr__( 'Module', 'uncanny-automator' ),
						'options'     => $options,
					)
				),
				Automator()->helpers->recipe->field->text_field( $this->trigger_meta, esc_attr__( 'Module Score', 'uncanny-automator' ), true, 'text', '0', true ),
				Automator()->helpers->recipe->less_or_greater_than(),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $module_id
	 * @param $user_id
	 * @param $verb
	 * @param $data
	 */
	public function tincan_module_result_processed( $module_id, $user_id, $score ) {

		if ( absint( $user_id ) === 0 ) {
			return;
		}

		if ( absint( $score ) < 0 ) {
			return;
		}

		if ( ! empty( $module_id ) ) {
			if ( ! absint( $module_id ) ) {
				return;
			}
		}

		$recipes             = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$module_ids          = Automator()->get->meta_from_recipes( $recipes, 'TCMODULEINTERACTION' );
		$required_conditions = Automator()->get->meta_from_recipes( $recipes, 'NUMBERCOND' );
		$required_scores     = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );

		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {

			foreach ( $recipe['triggers'] as $trigger ) {

				$trigger_id = $trigger['ID'];

				if ( ( (int) $module_ids[ $recipe_id ][ $trigger_id ] === (int) $module_id || '-1' == $module_ids[ $recipe_id ][ $trigger_id ] ) && Automator()->utilities->match_condition_vs_number( $required_conditions[ $recipe_id ][ $trigger_id ], $required_scores[ $recipe_id ][ $trigger_id ], $score ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'          => $recipe_id,
						'trigger_id'         => $trigger_id,
						'required_condition' => $required_conditions[ $recipe_id ][ $trigger_id ],
					);
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				// Custom check for duplicate recipe run within 10 second window. Similar to Tin Canny Plugin
				global $wpdb;
				$results = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT date_time, CURRENT_TIMESTAMP as current_mysql_time
						FROM {$wpdb->prefix}uap_recipe_log
						WHERE 1=1
						AND user_id = %d
						AND automator_recipe_id = %d
						AND completed = 1 ORDER BY ID DESC ",
						$user_id,
						$matched_recipe_id['recipe_id']
					)
				);

				$can_run = true;
				if ( ! empty( $results ) ) {
					$last_time    = strtotime( $results->date_time );
					$current_time = strtotime( $results->current_mysql_time );

					if ( ( $current_time - $last_time ) <= 10 ) {
						$can_run = false;
					}
				}

				if ( ! Automator()->is_recipe_completed( $matched_recipe_id['recipe_id'], $user_id ) && $can_run ) {
					$args = array(
						'code'             => $this->trigger_code,
						'meta'             => $this->trigger_meta,
						'user_id'          => $user_id,
						'recipe_to_match'  => $matched_recipe_id['recipe_id'],
						'trigger_to_match' => $matched_recipe_id['trigger_id'],
						'ignore_post_id'   => true,
						'is_signed_in'     => true,
						'post_id'          => $module_id,
					);

					$args = Automator()->process->user->maybe_add_trigger_entry( $args, false );

					if ( $args ) {
						foreach ( $args as $result ) {
							if ( true === $result['result'] ) {

								Automator()->db->trigger->add_meta(
									$result['args']['trigger_id'],
									$result['args']['get_trigger_id'],
									$result['args']['run_number'],
									array(
										'user_id'        => $user_id,
										'trigger_id'     => $result['args']['trigger_id'],
										'meta_key'       => $this->trigger_meta,
										'meta_value'     => $score,
										'trigger_log_id' => $result['args']['get_trigger_id'],
										'run_number'     => $result['args']['run_number'],
									)
								);
								Automator()->db->trigger->add_meta(
									$result['args']['trigger_id'],
									$result['args']['get_trigger_id'],
									$result['args']['run_number'],
									array(
										'user_id'        => $user_id,
										'trigger_id'     => $result['args']['trigger_id'],
										'meta_key'       => 'TCMODULEINTERACTION',
										'meta_value'     => $module_id,
										'trigger_log_id' => $result['args']['get_trigger_id'],
										'run_number'     => $result['args']['run_number'],
									)
								);
								Automator()->db->trigger->add_meta(
									$result['args']['trigger_id'],
									$result['args']['get_trigger_id'],
									$result['args']['run_number'],
									array(
										'user_id'        => $user_id,
										'trigger_id'     => $result['args']['trigger_id'],
										'meta_key'       => 'NUMBERCOND',
										'meta_value'     => $matched_recipe_id['required_condition'],
										'trigger_log_id' => $result['args']['get_trigger_id'],
										'run_number'     => $result['args']['run_number'],
									)
								);

								Automator()->process->user->maybe_trigger_complete( $result['args'] );
							}
						}
					}
				}
			}
		}
	}

	/**
	 * @param $url
	 *
	 * @return mixed
	 */
	public function get_slide_id_from_url( $url ) {
		preg_match( '/\/uncanny-snc\/([0-9]+)\//', $url, $matches );

		return $matches;
	}
}
