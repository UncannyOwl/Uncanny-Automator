<?php

namespace Uncanny_Automator;

/**
 * Class WPUM_UPDATESCOVERPHOTO
 * @package Uncanny_Automator
 */
class WPUM_UPDATESCOVERPHOTO {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPUSERMANAGER';

	/**
	 * @var string
	 */
	private $trigger_code;
	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'WPUMUSERCOVER';
		$this->trigger_meta = 'WPUMCPUPDATED';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		// global $uncanny_automator;
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-user-manager/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WP User Manager */
			'sentence'            => __( 'A user updates their cover photo', 'uncanny-automator' ),
			/* translators: Logged-in trigger - WP User Manager */
			'select_option_name'  => __( 'A user updates their cover photo', 'uncanny-automator' ),
			'action'              => 'wpum_user_update_change_cover',
			'priority'            => 99,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'wpum_cover_photo_updated' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $user_id
	 * @param $value
	 */
	public function wpum_cover_photo_updated( $user_id, $value ) {
		// global $uncanny_automator;

		if ( 0 === absint( $user_id ) ) {
			// Its a logged in recipe and
			// user ID is 0. Skip process
			return;
		}

		$pass_args = [
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
		];

		Automator()->maybe_add_trigger_entry( $pass_args );
	}

}
