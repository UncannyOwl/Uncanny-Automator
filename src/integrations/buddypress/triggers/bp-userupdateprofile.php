<?php

namespace Uncanny_Automator;

/**
 * Class BP_USERUPDATEPROFILE
 * @package Uncanny_Automator
 */
class BP_USERUPDATEPROFILE {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'BP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'BPUSERUPDATEPROFILE';
		$this->trigger_meta = 'BPUSER';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {



		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/buddypress/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - BuddyPress */
			'sentence'            => esc_attr__( 'A user updates their profile', 'uncanny-automator' ),
			/* translators: Logged-in trigger - BuddyPress */
			'select_option_name'  => esc_attr__( 'A user updates their profile', 'uncanny-automator' ),
			'action'              => 'xprofile_updated_profile',
			'priority'            => 10,
			'accepted_args'       => 5,
			'validation_function' => array( $this, 'bp_user_updated_profile' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 *  Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $posted_field_ids
	 * @param $errors
	 * @param $old_values
	 * @param $new_values
	 */
	public function bp_user_updated_profile( $user_id, $posted_field_ids, $errors, $old_values, $new_values ) {



		$args = [
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		];

		Automator()->maybe_add_trigger_entry( $args );
	}
}
