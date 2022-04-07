<?php

namespace Uncanny_Automator;

/**
 * Class WP_LOGIN
 *
 * @package Uncanny_Automator
 */
class WP_LOGIN {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LOGIN';
		$this->trigger_meta = 'WPLOGIN';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf( esc_attr__( 'A user logs in to the site {{a number of:%1$s}} time(s)', 'uncanny-automator' ), 'NUMTIMES' ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( 'A user logs in to the site', 'uncanny-automator' ),
			'action'              => 'wp_login',
			'priority'            => 99,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'wp_login' ),
			'options_callback'    => array( $this, 'load_options' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->options->number_of_times(),
				),
			)
		);
		return $options;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_login
	 * @param $user
	 */
	public function wp_login( $user_login, $user ) {

		$user_id = $user->ID;

		$args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		);

		Automator()->maybe_add_trigger_entry( $args );
	}
}
