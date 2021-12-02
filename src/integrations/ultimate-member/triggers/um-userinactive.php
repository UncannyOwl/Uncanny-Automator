<?php

namespace Uncanny_Automator;

class UM_USERINACTIVE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UM';

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
		$this->trigger_code = 'UMUSERINACTIVE';
		$this->trigger_meta = 'UMUSER';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/ultimate-member/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Ultimate Member */
			'sentence'            => sprintf( esc_attr__( 'A user becomes inactive', 'uncanny-automator' ) ),
			/* translators: Logged-in trigger - Ultimate Member */
			'select_option_name'  => esc_attr__( 'A user becomes inactive', 'uncanny-automator' ),
			'action'              => 'um_after_user_is_inactive',
			'priority'            => 9,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'um_after_user_is_inactive' ),
			'options'             => array(),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 */
	public function um_after_user_is_inactive( $user_id ) {
		if ( ! isset( $user_id ) ) {
			return;
		}

		$args = array(
			'code'           => $this->trigger_code,
			'meta'           => $this->trigger_meta,
			'user_id'        => $user_id,
			'ignore_post_id' => true,
			'is_signed_in'   => true,
		);

		if ( isset( Automator()->process ) && isset( Automator()->process->user ) && Automator()->process->user instanceof Automator_Recipe_Process_User ) {
			Automator()->process->user->maybe_add_trigger_entry( $args );
		} else {
			Automator()->maybe_add_trigger_entry( $args );
		}

		return;
	}

}
