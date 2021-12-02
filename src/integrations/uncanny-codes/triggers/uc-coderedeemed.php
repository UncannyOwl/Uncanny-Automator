<?php

namespace Uncanny_Automator;

/**
 * Class UC_CODEREDEEMED
 *
 * @package Uncanny_Automator
 */
class UC_CODEREDEEMED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYCODE';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'CODEREDEEMED';
		$this->trigger_meta = 'UNCANNYCODES';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/uncanny-codes/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - Uncanny Codes */
			'sentence'            => sprintf( esc_attr__( 'A user redeems a code', 'uncanny-automator' ) ),
			/* translators: Logged-in trigger - Uncanny Codes */
			'select_option_name'  => esc_attr__( 'A user redeems a code', 'uncanny-automator' ),
			'action'              => 'ulc_user_redeemed_code',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'user_redeemed_code' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * @param $user_id
	 * @param $coupon_id
	 * @param $result
	 */
	public function user_redeemed_code( $user_id, $coupon_id, $result ) {

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		if ( empty( $user_id ) ) {
			return;
		}

		$args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'ignore_post_id' => true,
			'user_id'        => $user_id,
		);

		Automator()->maybe_add_trigger_entry( $args );
	}
}
