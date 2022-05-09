<?php

namespace Uncanny_Automator;

/**
 * Class WPJM_JOBAPPLICATION
 *
 * @package Uncanny_Automator
 */
class WPJM_JOBAPPLICATION {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPJM';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		$this->trigger_code = 'WPJMSUBMITJOBAPPLICATION';
		$this->trigger_meta = 'WPJMJOBAPPLICATION';

		// Check if get_resume_files function exists from WPJM resume add-on.
		if ( function_exists( 'get_resume_files' ) ) {
			$this->define_trigger();
		}

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-job-manager/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP Job Manager */
			'sentence'            => sprintf( esc_attr__( 'A user applies for {{a job:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - WP Job Manager */
			'select_option_name'  => esc_attr__( 'A user applies for {{a job}}', 'uncanny-automator' ),
			'action'              => 'new_job_application',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'new_job_application' ),
			'options'             => array(
				Automator()->helpers->recipe->wp_job_manager->options->list_wpjm_jobs( null, $this->trigger_meta ),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 *
	 */
	public function plugins_loaded() {
		$this->define_trigger();
	}

	/**
	 * @param $fields
	 */
	public function new_job_application( $application_id, $job_id ) {

		if ( empty( $job_id ) || ! is_numeric( $job_id ) ) {
			return;
		}
		$recipes    = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$conditions = $this->match_condition( $job_id, $recipes, $this->trigger_meta, $this->trigger_code );

		if ( empty( $conditions ) ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( automator_filter_has_var( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) && ! empty( automator_filter_input( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) ) ) {
			if ( $application_id !== automator_filter_input( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) ) {
				update_post_meta( $application_id, '_resume_id', automator_filter_input( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) );
			}
		}
		foreach ( $conditions['recipe_ids'] as $recipe_id => $trigger_id ) {
			if ( ! Automator()->is_recipe_completed( $recipe_id, $user_id ) ) {
				$trigger_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'recipe_to_match'  => $recipe_id,
					'trigger_to_match' => $trigger_id,
					'post_id'          => $job_id,
					'user_id'          => $user_id,
				);

				$args = Automator()->maybe_add_trigger_entry( $trigger_args, false );

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
							$trigger_meta['meta_value'] = $job_id;
							Automator()->insert_trigger_meta( $trigger_meta );

							$trigger_meta['meta_key']   = 'WPJMJOBAPPLICATIONID';
							$trigger_meta['meta_value'] = $application_id;
							Automator()->insert_trigger_meta( $trigger_meta );

							Automator()->maybe_trigger_complete( $result['args'] );
							break;
						}
					}
				}
			}
		}
	}

	/**
	 * @param      $terms
	 * @param null $recipes
	 * @param null $trigger_meta
	 * @param null $trigger_code
	 *
	 * @return array|bool
	 */
	public function match_condition( $job_id, $recipes = null, $trigger_meta = null, $trigger_code = null ) {

		if ( null === $recipes ) {
			return false;
		}

		$recipe_ids     = array();
		$entry_to_match = $job_id;

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( key_exists( $trigger_meta, $trigger['meta'] ) && ( (int) $trigger['meta'][ $trigger_meta ] === (int) $entry_to_match || $trigger['meta'][ $trigger_meta ] === '-1' ) ) {
					$recipe_ids[ $recipe['ID'] ] = $trigger['ID'];
					break;
				}
			}
		}

		if ( ! empty( $recipe_ids ) ) {
			return array(
				'recipe_ids' => $recipe_ids,
				'result'     => true,
			);
		}

		return false;
	}
}
