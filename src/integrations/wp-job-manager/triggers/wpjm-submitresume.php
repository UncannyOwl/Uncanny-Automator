<?php

namespace Uncanny_Automator;

/**
 * Class WPJM_SUBMITRESUME
 * @package Uncanny_Automator
 */
class WPJM_SUBMITRESUME {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPJM';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPJMSUBMITRESUME';
		$this->trigger_meta = 'WPJMJOBRESUME';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP Job Manager */
			'sentence'            => esc_attr__( 'A user submits a resume', 'uncanny-automator' ),
			/* translators: Logged-in trigger - WP Job Manager */
			'select_option_name'  => esc_attr__( 'A user submits a resume', 'uncanny-automator' ),
			'action'              => 'resume_manager_resume_submitted',
			'priority'            => 20,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'resume_manager_resume_submitted' ),
			'options'             => [],
		);

		$uncanny_automator->register->trigger( $trigger );
	}

	/**
	 * @param $fields
	 */
	public function resume_manager_resume_submitted( $resume_id ) {

		global $uncanny_automator;

		if ( empty( $resume_id ) ) {
			return;
		}
		$user_id = get_current_user_id();
		$trigger_args = [
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'post_id'        => intval( $resume_id ),
			'ignore_post_id' => true,
			'user_id'        => $user_id,
		];

		$args = $uncanny_automator->maybe_add_trigger_entry( $trigger_args, false );

		if ( $args ) {
			foreach ( $args as $result ) {
				if ( true === $result['result'] ) {
					$trigger_meta = [
						'user_id'        => $user_id,
						'trigger_id'     => $result['args']['trigger_id'],
						'trigger_log_id' => $result['args']['get_trigger_id'],
						'run_number'     => $result['args']['run_number'],
					];

					$trigger_meta['meta_key']   = $this->trigger_code;
					$trigger_meta['meta_value'] = $resume_id;
					$uncanny_automator->insert_trigger_meta( $trigger_meta );
					$uncanny_automator->maybe_trigger_complete( $result['args'] );
					break;
				}
			}
		}
	}
}
