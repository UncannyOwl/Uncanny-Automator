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
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( self::TRIGGER_CODE, 'WPJM' )
			->trigger_meta( self::TRIGGER_META )
			->hook( 'resume_manager_resume_submitted', 10, 1 );
	}

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
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_uses_api( false );

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

		// Credit the resume's author. resume_manager_resume_submitted fires
		// for guest submissions and programmatic inserts too, so resolve the
		// user from the resume post instead of the current request.
		$resume = get_post( $resume_id );

		if ( ! $resume instanceof \WP_Post ) {
			return false;
		}

		$author_id = (int) $resume->post_author;

		if ( $author_id <= 0 || false === get_user_by( 'ID', $author_id ) ) {
			return false;
		}

		$this->set_user_id( $author_id );

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
