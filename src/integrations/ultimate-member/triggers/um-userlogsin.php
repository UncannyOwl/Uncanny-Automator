<?php

namespace Uncanny_Automator;

/**
 * Class UM_USERLOGSIN
 *
 * @package Uncanny_Automator
 */
class UM_USERLOGSIN {
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
		$this->trigger_code = 'UMUSERLOGSIN';
		$this->trigger_meta = 'UMFORM';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$options = Automator()->helpers->recipe->ultimate_member->options->get_um_forms( esc_attr__( 'Form', 'uncanny-automator' ), $this->trigger_meta, 'login' );

		$options['options'] = array( '-1' => esc_attr__( 'Any form', 'uncanny-automator' ) ) + $options['options'];

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/ultimate-member/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - Ultimate Member */
			'sentence'            => sprintf( esc_attr__( 'A user logs in with {{a form:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - Ultimate Member */
			'select_option_name'  => esc_attr__( 'A user logs in with {{a form}}', 'uncanny-automator' ),
			'action'              => 'um_user_login',
			'priority'            => 9,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'um_user_login' ),
			'options'             => array(
				$options,
			),
		);

		Automator()->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $um_args
	 */
	public function um_user_login( $um_args ) {
		if ( ! isset( $um_args['form_id'] ) ) {
			return;
		}

		if ( function_exists( 'um_user' ) ) {
			$user_id = um_user( 'ID' );
		} else {
			return;
		}

		$args = array(
			'code'         => $this->trigger_code,
			'meta'         => $this->trigger_meta,
			'post_id'      => absint( $um_args['form_id'] ),
			'user_id'      => absint( $user_id ),
			'is_signed_in' => true,
		);

		if ( isset( Automator()->process ) && isset( Automator()->process->user ) && Automator()->process->user instanceof Automator_Recipe_Process_User ) {
			Automator()->process->user->maybe_add_trigger_entry( $args );
		} else {
			Automator()->maybe_add_trigger_entry( $args );
		}

		return;
	}
}
