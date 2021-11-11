<?php

namespace Uncanny_Automator;

/**
 * Uncanny Toolkit - Trigger: A user is imported by the Import Users module
 */
class UT_USER_IMPORTED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYTOOLKIT';

	/**
	 * Trigger Code
	 *
	 * @var string
	 */
	private $trigger_code;
	/**
	 * Trigger Meta
	 *
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		if ( ! defined( 'UNCANNY_TOOLKIT_PRO_VERSION' ) ) {
			return;
		}
		$this->trigger_code = 'UTUSERIMPORTED';
		$this->trigger_meta = 'UOUSERIMPORTED';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/uncanny-toolkit/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - Uncanny Toolkit */
			'sentence'            => esc_attr__( 'A user is imported by the Import Users module', 'uncanny-automator' ),
			/* translators: Logged-in trigger - Uncanny Toolkit */
			'select_option_name'  => esc_attr__( 'A user is imported by the Import Users module', 'uncanny-automator' ),
			'action'              => 'uo_after_user_row_imported',
			'priority'            => 20,
			'accepted_args'       => 4,
			'validation_function' => array( $this, 'a_user_is_imported' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Running an actual function on the trigger
	 *
	 * @param $user_id
	 * @param $csv_data
	 * @param $csv_header
	 * @param $key_location
	 */
	public function a_user_is_imported( $user_id, $csv_data, $csv_header, $key_location ) {
		if ( ! is_numeric( $user_id ) ) {
			return;
		}
		$meta_value = Uncanny_Toolkit_Helpers::build_token_data( $csv_data, $csv_header, $key_location, $user_id );

		$args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'ignore_post_id' => true,
			'user_id'        => $user_id,
			'is_signed_in'   => true,
		);

		$results = Automator()->process->user->maybe_add_trigger_entry( $args, false );

		$serialized = maybe_serialize( $meta_value );
		if ( empty( $results ) ) {
			return;
		}
		foreach ( $results as $rr ) {
			if ( ! $rr['result'] ) {
				continue;
			}
			$trigger_id     = (int) $rr['args']['trigger_id'];
			$user_id        = (int) $rr['args']['user_id'];
			$trigger_log_id = (int) $rr['args']['trigger_log_id'];
			$run_number     = (int) $rr['args']['run_number'];
			$token_args     = array(
				'user_id'        => $user_id,
				'trigger_id'     => $trigger_id,
				'run_number'     => $run_number, //get run number
				'trigger_log_id' => $trigger_log_id,
			);

			Automator()->db->trigger->add_token_meta( 'imported_row', $serialized, $token_args );

			Automator()->process->user->maybe_trigger_complete( $rr['args'] );
		}
	}
}
