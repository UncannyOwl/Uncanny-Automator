<?php

namespace Uncanny_Automator\Integrations\Wpjm;

use Uncanny_Automator\Integrations\WpJobManager\Tokens\WPJM_Legacy_Tokens;

/**
 * Class Wpjm_Submitjob
 *
 * @package Uncanny_Automator\Integrations\Wpjm
 */
class Wpjm_Submitjob extends \Uncanny_Automator\Recipe\Trigger {

	const TRIGGER_CODE = 'WPJMSUBMITJOB';
	const TRIGGER_META = 'WPJMJOBTYPE';

	/**
	 * @method \Uncanny_Automator\Integrations\Wpjm\Wpjm_Helpers get_item_helpers()
	 */

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

		$this->add_action( 'transition_post_status' );
		$this->set_action_args_count( 3 );

		// translators: %1$s is the job type
		$this->set_sentence( sprintf( esc_html_x( 'A user submits a {{specific type of:%1$s}} job', 'WP Job Manager', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		$this->set_readable_sentence( esc_html_x( 'A user submits a {{specific type of}} job', 'WP Job Manager', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label' => esc_html_x( 'Job type', 'WP Job Manager', 'uncanny-automator' ),
				'input_type' => 'select',
				'required' => true,
				'options' => $this->get_item_helpers()->list_wpjm_job_types(),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Validate trigger
	 */
	public function validate( $trigger, $hook_args ) {
		list( $new_status, $old_status, $post ) = $hook_args;

		if ( $new_status === $old_status ) {
			return false;
		}

		if ( empty( $post ) ) {
			return false;
		}

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$job_id = $post->ID;

		if ( 'job_listing' !== $post->post_type ) {
			return false;
		}

		if ( ! $this->is_valid_job_status( $post->post_status ) ) {
			return false;
		}

		$job_terms         = wpjm_get_the_job_types( $job_id );
		$selected_job_type = $trigger['meta'][ self::TRIGGER_META ];

		// Check if any job type matches or if "Any type" is selected
		if ( '-1' === $selected_job_type ) {
			return true;
		}

		if ( ! empty( $job_terms ) ) {
			foreach ( $job_terms as $term ) {
				if ( (int) $term->term_id === (int) $selected_job_type ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Hydrate tokens
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $new_status, $old_status, $post ) = $hook_args;
		$job_id                                 = $post->ID;

		$tokens        = array();
		$common_tokens = Wpjm_Token_Manager::get_job_tokens( self::TRIGGER_CODE, true );

		// Parse common tokens.
		foreach ( $common_tokens as $token ) {
			$token_id = $token['tokenId'] ?? null;
			if ( null !== $token_id ) {
				$tokens[ $token_id ] = Wpjm_Token_Manager::hydrate_job_tokens( $job_id, $token_id );
			}
		}

		// Fill Legacy Tokens for backwards compatibility.
		$tokens['WPJMJOBID']    = $job_id;
		$tokens['WPJMJOBTITLE'] = wpjm_get_the_job_title( $job_id );
		$tokens['WPJMJOBURL']   = get_permalink( $job_id );

		return $tokens;
	}

	/**
	 * Define tokens
	 */
	public function define_tokens( $trigger, $tokens ) {
		// Use centralized token definitions
		$custom_tokens = Wpjm_Token_Manager::get_job_tokens( self::TRIGGER_CODE, true );

		return array_merge( $tokens, $custom_tokens );
	}



	/**
	 * Validates if the job status is appropriate based on approval requirements.
	 *
	 * @param string $post_status The post status to validate.
	 * @return bool True if the status is valid for the current approval setting.
	 */
	private function is_valid_job_status( $post_status ) {
		$requires_approval = get_option( 'job_manager_submission_requires_approval', false );

		if ( $requires_approval ) {
			return 'pending' === $post_status;
		}

		return 'publish' === $post_status;
	}
}
