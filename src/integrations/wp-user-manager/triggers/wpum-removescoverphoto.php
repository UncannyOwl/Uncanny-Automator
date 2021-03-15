<?php

namespace Uncanny_Automator;

/**
 * Class WPUM_REMOVESCOVERPHOTO
 * @package Uncanny_Automator
 */
class WPUM_REMOVESCOVERPHOTO {

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
		$this->trigger_code = 'WPUMUSERPCOVERR';
		$this->trigger_meta = 'WPUMPCREMOVED';
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
			/* translators: Logged-in trigger - WP User Manager */
			'sentence'            => __( 'A user removes their profile cover image', 'uncanny-automator' ),
			/* translators: Logged-in trigger - WP User Manager */
			'select_option_name'  => __( 'A user removes their profile cover image', 'uncanny-automator' ),
			'action'              => 'wpum_user_update_remove_cover',
			'priority'            => 99,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'wpum_profile_cover_removed' ),
			'options'             => [],
		);

		$uncanny_automator->register->trigger( $trigger );
	}

	/**
	 * @param $user_id
	 */
	public function wpum_profile_cover_removed( $user_id ) {
		global $uncanny_automator;

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

		$uncanny_automator->maybe_add_trigger_entry( $pass_args );
	}

}