<?php

namespace Uncanny_Automator\Integrations\Wpjm;

use Uncanny_Automator\Integrations\WpJobManager\Tokens\WPJM_Legacy_Tokens;

/**
 * Class Wpjm_Jobapplication
 *
 * @package Uncanny_Automator\Integrations\Wpjm
 */
class Wpjm_Jobapplication extends \Uncanny_Automator\Recipe\Trigger {

	const TRIGGER_CODE = 'WPJMSUBMITJOBAPPLICATION';
	const TRIGGER_META = 'WPJMJOBAPPLICATION';

	/**
	 * @method \Uncanny_Automator\Integrations\Wpjm\Wpjm_Helpers get_item_helpers()
	 */

	/**
	 * Check if requirements are met
	 */
	public function requirements_met() {
		return function_exists( 'get_resume_files' );
	}

	/**
	 * Setup trigger
	 */
	protected function setup_trigger() {
		$this->set_integration( 'WPJM' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_pro( false );
		$this->set_is_login_required( true );
		$this->set_trigger_type( 'user' );
		$this->set_uses_api( false );

		// Deprecated new_job_application hook, added a new hook job_manager_applications_new_job_application
		$this->add_action( 'new_job_application' );
		$this->set_action_args_count( 2 );

		// translators: %1$s is the job
		$this->set_sentence( sprintf( esc_html_x( 'A user applies for {{a job:%1$s}}', 'WP Job Manager', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		$this->set_readable_sentence( esc_html_x( 'A user applies for {{a job}}', 'WP Job Manager', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label' => esc_html_x( 'Job', 'WP Job Manager', 'uncanny-automator' ),
				'input_type' => 'select',
				'required' => true,
				'options' => $this->get_item_helpers()->list_wpjm_jobs(),
				'relevant_tokens' => array(
					$this->get_trigger_meta() => esc_html_x( 'Job', 'WP Job Manager', 'uncanny-automator' ),
					$this->get_trigger_meta() . '_ID' => esc_html_x( 'Job ID', 'WP Job Manager', 'uncanny-automator' ),
					$this->get_trigger_meta() . '_URL' => esc_html_x( 'Job URL', 'WP Job Manager', 'uncanny-automator' ),
				),
			),
		);
	}

	/**
	 * Validate trigger
	 */
	public function validate( $trigger, $hook_args ) {
		list( $application_id, $job_id ) = $hook_args;

		if ( empty( $job_id ) || ! is_numeric( $job_id ) ) {
			return false;
		}

		$selected_job = $trigger['meta'][ self::TRIGGER_META ];

		// Check if any job matches or if "Any job" is selected
		if ( intval( '-1' ) === intval( $selected_job ) ) {
			return true;
		}

		return (int) $job_id === (int) $selected_job;
	}

	/**
	 * Hydrate tokens
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $application_id, $job_id ) = $hook_args;

		// Handle resume ID if present
		if ( automator_filter_has_var( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) && ! empty( automator_filter_input( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) ) ) {
			if ( automator_filter_input( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) !== $application_id ) {
				update_post_meta( $application_id, '_resume_id', automator_filter_input( 'wp_job_manager_resumes_apply_with_resume', INPUT_POST ) );
			}
		}

		$tokens             = array();
		$job_tokens         = Wpjm_Token_Manager::get_job_tokens( 'WPJMJOBAPPLICATION' );
		$application_tokens = Wpjm_Token_Manager::get_application_tokens( 'WPJMJOBAPPLICATIONID' );

		// Parse common tokens.
		foreach ( $application_tokens as $token ) {
			$token_id = $token['tokenId'] ?? null;
			if ( null !== $token_id ) {
				$tokens[ $token_id ] = Wpjm_Token_Manager::hydrate_application_tokens( $application_id, $token_id );
			}
		}

		foreach ( $job_tokens as $token ) {
			$token_id = $token['tokenId'] ?? null;
			if ( null !== $token_id ) {
				$tokens[ $token_id ] = Wpjm_Token_Manager::hydrate_job_tokens( $job_id, $token_id );
			}
		}

		$tokens[ $this->get_trigger_meta() . '_ID' ]  = $job_id;
		$tokens[ $this->get_trigger_meta() ]          = wpjm_get_the_job_title( $job_id );
		$tokens[ $this->get_trigger_meta() . '_URL' ] = get_permalink( $job_id );

		// Save legacy tokens for backwards compatibility. 😳
		$legacy_token_storage = new WPJM_Legacy_Tokens( $this->trigger_records );
		$legacy_token_storage->save_legacy_tokens_values( 'job', 'WPJMJOBAPPLICATION', $job_id );
		$legacy_token_storage->save_legacy_tokens_values( 'application', 'WPJMJOBAPPLICATIONID', $application_id );

		return $tokens;
	}

	/**
	 * Define tokens
	 */
	public function define_tokens( $trigger, $tokens ) {
		$custom_tokens = array_merge(
		// Use centralized token definitions
			Wpjm_Token_Manager::get_job_tokens( 'WPJMJOBAPPLICATION' ),
			Wpjm_Token_Manager::get_application_tokens( 'WPJMJOBAPPLICATIONID' )
		);

		return array_merge( $tokens, $custom_tokens );
	}
}
