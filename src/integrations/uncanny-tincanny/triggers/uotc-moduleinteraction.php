<?php

namespace Uncanny_Automator;

use TINCANNYSNC\Database;

/**
 * Class TC_MODULEINTERACTION
 *
 * @package Uncanny_Automator
 */
class UOTC_MODULEINTERACTION {

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
			$this->trigger_code = 'MODULEINTERACTION';
			$this->trigger_meta = 'TCMODULEINTERACTION';
			$this->define_trigger();
		//}
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$options       = array();
		$modules       = Database::get_modules();
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
			'sentence'            => sprintf( esc_attr__( '{{A Tin Can verb:%1$s}} is recorded from {{a Tin Can module:%2$s}}', 'uncanny-automator' ), 'TCVERB', $this->trigger_meta ),
			/* translators: Logged-in trigger - Uncanny Reporting */
			'select_option_name'  => esc_attr__( '{{A Tin Can verb}} is recorded from {{a Tin Can module}}', 'uncanny-automator' ),
			'action'              => 'tincanny_module_completed',
			'priority'            => 99,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'tincanny_module_completed_func' ),
			'options'             => array(
				Automator()->helpers->recipe->field->select(
					array(
						'option_code' => $this->trigger_meta,
						'label'       => esc_attr__( 'Module', 'uncanny-automator' ),
						'options'     => $options,
					)
				),
				Automator()->helpers->recipe->field->select(
					array(
						'option_code'           => 'TCVERB',
						'supports_custom_value' => true,
						'label'                 => esc_attr_x( 'Verb', 'Tin Can verb', 'uncanny-automator' ),
						'options'               => array(
							'-1'          => 'Any',
							'completed'   => 'Completed',
							'passed'      => 'Passed',
							'failed'      => 'Failed',
							'answered'    => 'Answered',
							'attempted'   => 'Attempted',
							'experienced' => 'Experienced',
						),
					)
				),
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
	public function tincanny_module_completed_func( $module_id, $user_id, $verb, $data = array() ) {

		if ( empty( $user_id ) ) {
			return;
		}

		if ( empty( $verb ) ) {
			return;
		}

		if ( empty( $module_id ) ) {
			if ( ! absint( $module_id ) ) {
				return;
			}
		}

		$recipes    = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$module_ids = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$verbs      = Automator()->get->meta_from_recipes( $recipes, 'TCVERB' );

		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {

			foreach ( $recipe['triggers'] as $trigger ) {

				$trigger_id = $trigger['ID'];

				if ( ( (int) $module_ids[ $recipe_id ][ $trigger_id ] === (int) $module_id || '-1' == $module_ids[ $recipe_id ][ $trigger_id ] ) && ( strtolower( $verbs[ $recipe_id ][ $trigger_id ] ) === strtolower( $verb ) || '-1' == $verbs[ $recipe_id ][ $trigger_id ] ) ) {

					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
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
					$last_time = strtotime( $results->date_time );
					//$last_time_round = round(ceil($last_time/10) * 10 );
					$current_time = strtotime( $results->current_mysql_time );
					//$current_time_round = round(ceil($current_time/10) * 10 );

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
										'meta_key'       => 'TCVERB',
										'meta_value'     => $verb,
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
										'meta_key'       => $this->trigger_meta,
										'meta_value'     => $module_id,
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
