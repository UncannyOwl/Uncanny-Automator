<?php

namespace Uncanny_Automator;

/**
 * Class BDB_USERSENTINVITE
 * @package Uncanny_Automator
 */
class BDB_USERSENTINVITE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'BDB';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'BDBUSERSENTINVITE';
		$this->trigger_meta = 'BDBINVITE';
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
			'sentence'            => __( 'A user sends an email invitation', 'uncanny-automator' ),
			'select_option_name'  => __( 'A user sends an email invitation', 'uncanny-automator' ),
			'action'              => 'bp_member_invite_submit',
			'priority'            => 10,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'bp_core_activated_user' ),
			'options'             => [],
		);

		$uncanny_automator->register->trigger( $trigger );
	}

	/**
	 *  Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $key
	 * @param $user
	 */
	public function bp_core_activated_user( $user_id, $post ) {

		global $uncanny_automator;

		$args = [
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
