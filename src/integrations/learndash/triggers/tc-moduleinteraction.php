<?php

namespace Uncanny_Automator;

use TINCANNYSNC\Database;

/**
 * Class WP_LOGIN
 * @package Uncanny_Automator
 */
class TC_MODULEINTERACTION {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'LD';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		// This is a LD integration but we are only loading it if Tin Canny exists
		if ( defined( 'UNCANNY_REPORTING_VERSION' ) ) {
			$this->trigger_code = 'MODULEINTERACTION';
			$this->trigger_meta = 'TCMODULEINTERACTION';
			$this->define_trigger();
		}
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$options       = [];
		$modules       = Database::get_modules();
		$options['-1'] =  esc_attr__( 'Any module', 'uncanny-automator' );

		foreach ( $modules as $module ) {
			$options[ $module->ID ] = $module->file_name;
		}

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnDash */
			'sentence'            => sprintf(  esc_attr__( '{{A Tin Can verb:%1$s}} is recorded from {{a Tin Can module:%2$s}}', 'uncanny-automator' ), 'TCVERB', $this->trigger_meta ),
			/* translators: Logged-in trigger - LearnDash */
			'select_option_name'  =>  esc_attr__( '{{A Tin Can verb}} is recorded from {{a Tin Can module}}', 'uncanny-automator' ),
			'action'              => 'tincanny_module_completed',
			'priority'            => 99,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'tincanny_module_completed_func' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->field->select_field( $this->trigger_meta,  esc_attr__( 'Module', 'uncanny-automator' ), $options ),
				$uncanny_automator->helpers->recipe->field->select_field( 'TCVERB',  esc_attr_x( 'Verb', 'Tin Can verb', 'uncanny-automator' ), [
					'completed'   => 'Completed',
					'passed'      => 'Passed',
					'failed'      => 'Failed',
					'answered'    => 'Answered',
					'attempted'   => 'Attempted',
					'experienced' => 'Experienced',
				] ),
			],
		);

		$uncanny_automator->register->trigger( $trigger );
	}

	/**
	 * @param $module_id
	 * @param $user_id
	 * @param $verb
	 * @param $data
	 */
	public function tincanny_module_completed_func( $module_id, $user_id, $verb, $data = [] ) {

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

		global $uncanny_automator;

		$recipes    = $uncanny_automator->get->recipes_from_trigger_code( $this->trigger_code );
		$module_ids = $uncanny_automator->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$verbs      = $uncanny_automator->get->meta_from_recipes( $recipes, 'TCVERB' );

		$matched_recipe_ids = [];

		foreach ( $recipes as $recipe_id => $recipe ) {

			foreach ( $recipe['triggers'] as $trigger ) {

				$trigger_id = $trigger['ID'];

				if ( ( $module_ids[ $recipe_id ][ $trigger_id ] === $module_id || '-1' == $module_ids[ $recipe_id ][ $trigger_id ] ) && $verbs[ $recipe_id ][ $trigger_id ] === $verb ) {

					$matched_recipe_ids[] = [
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					];
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {

				$args = [
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
					'is_signed_in'     => true,
					'post_id'          => $module_id,
				];

				//$uncanny_automator->maybe_add_trigger_entry( $args );
				$args = $uncanny_automator->maybe_add_trigger_entry( $args, false );
				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => 'TCVERB',
									'meta_value'     => $verb,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);

							$uncanny_automator->maybe_trigger_complete( $result['args'] );
							$uncanny_automator->insert_trigger_meta(
								[
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => $this->trigger_meta,
									'meta_value'     => $module_id,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								]
							);

							$uncanny_automator->maybe_trigger_complete( $result['args'] );
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
