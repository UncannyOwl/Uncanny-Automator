<?php

namespace Uncanny_Automator\Integrations\Wpjm;

/**
 * Class Wpjm_Submitresume
 *
 * @package Uncanny_Automator\Integrations\Wpjm
 */
class Wpjm_Submitresume extends \Uncanny_Automator\Recipe\Trigger {

	const TRIGGER_CODE = 'WPJMSUBMITRESUME';
	const TRIGGER_META = 'WPJMJOBRESUME';

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

		$this->add_action( 'resume_manager_resume_submitted' );
		$this->set_action_args_count( 1 );

		$this->set_sentence( esc_html_x( 'A user submits a resume', 'WP Job Manager', 'uncanny-automator' ) );

		$this->set_readable_sentence( esc_html_x( 'A user submits a resume', 'WP Job Manager', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options
	 */
	public function options() {
		return array();
	}

	/**
	 * Validate trigger
	 */
	public function validate( $trigger, $hook_args ) {
		list( $resume_id ) = $hook_args;

		if ( empty( $resume_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Hydrate tokens
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $resume_id ) = $hook_args;

		// Use centralized token hydration
		$tokens                       = Wpjm_Token_Manager::hydrate_resume_tokens( $resume_id );
		$tokens[ self::TRIGGER_CODE ] = $resume_id;

		return $tokens;
	}

	/**
	 * Define tokens
	 */
	public function define_tokens( $trigger, $tokens ) {
		// Use centralized token definitions
		$custom_tokens = Wpjm_Token_Manager::get_resume_tokens();

		return array_merge( $tokens, $custom_tokens );
	}
}
