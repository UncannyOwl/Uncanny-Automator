<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class ESAF_AFFILIATE_ADDED
 *
 * @package Uncanny_Automator
 */
class ESAF_AFFILIATE_ADDED {
	use Recipe\Triggers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'AFFILIATE_ADDED_CODE';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'AFFILIATE_ADDED_META';

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'ESAF' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/easy-affiliate/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence */
			esc_attr__( 'An affiliate is added', 'uncanny-automator' )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'An affiliate is added', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->add_action( 'esaf_event_affiliate-added' );
		$this->set_action_args_count( 1 );
		$this->register_trigger();

	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		$event = array_shift( $args );
		if ( empty( $event[0]->evt_id_type ) && empty( $event[0]->evt_id ) && 'user' !== $event[0]->evt_id_type ) {
			return false;
		}

		return true;
	}

	/**
	 * Prepare to run the trigger.
	 *
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( false );
	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}
}
