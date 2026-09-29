<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Uncanny_Automator\Integrations\Eventin\Dispatchers\Eventin_Checkin_Dispatcher;
use Uncanny_Automator\Integrations\Eventin\Tokens\Eventin_Tokens;
use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Eventin_Attendee_Checked_In
 *
 * Fires when an attendee's ticket is marked used. Eventin emits no hook for
 * this, so the trigger listens on the normalized
 * `automator_eventin_attendee_checked_in( int $attendee_id, int $event_id )`
 * action from Eventin_Checkin_Dispatcher, which owns the site-wide meta-hook
 * filtering and covers both the `added_post_meta` and `updated_post_meta`
 * paths.
 *
 * Runs on base Eventin, but in practice check-ins are performed by the Eventin
 * Pro QR scanner (eventin-pro/core/attendee/hooks.php:982). On a Free-only
 * install it fires when an admin sets the ticket status by hand through
 * `PUT eventin/v2/attendees/{id}`.
 *
 * Anonymous: the scanner operator performs the act, not the attendee. The
 * attendee's own user account is bound when their email resolves to one, so
 * downstream actions can still target them.
 *
 * @package Uncanny_Automator\Integrations\Eventin
 *
 * @property Eventin_Helpers $item_helpers
 */
class Eventin_Attendee_Checked_In extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EVENTIN_ATTENDEE_CHECKED_IN', 'EVENTIN' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'EVENTIN_EVENT' )
			->hook( 'automator_eventin_attendee_checked_in', 10, 2 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Eventin event */
				esc_html_x( 'An attendee is checked in to {{an event:%1$s}}', 'Eventin', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'An attendee is checked in to {{an event}}', 'Eventin', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Event', 'Eventin', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'events' ),
			),
		);
	}

	/**
	 * Validate the event, then bind the attendee's user account when there is one.
	 *
	 * @param array $trigger   The trigger.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$attendee_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$event_id    = isset( $hook_args[1] ) ? absint( $hook_args[1] ) : 0;

		if ( 0 === $attendee_id || 0 === $event_id ) {
			return false;
		}

		$selected_event = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Eventin_Helpers::ANY );

		if ( Eventin_Helpers::ANY !== $selected_event && absint( $selected_event ) !== $event_id ) {
			return false;
		}

		$email = (string) get_post_meta( $attendee_id, 'etn_email', true );

		$this->set_user_id( $this->item_helpers->get_user_id_by_email( $email ) );

		return true;
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger.
	 * @param array $tokens  The tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$token_class = new Eventin_Tokens();

		return array_merge(
			$tokens,
			$token_class->attendee_tokens(),
			$token_class->checkin_tokens(),
			$token_class->event_tokens()
		);
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger   The trigger.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$attendee_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$event_id    = isset( $hook_args[1] ) ? absint( $hook_args[1] ) : 0;

		$token_class = new Eventin_Tokens();

		return array_merge(
			$token_class->hydrate_attendee_tokens( $attendee_id ),
			$token_class->hydrate_checkin_tokens( $attendee_id ),
			$token_class->hydrate_event_tokens( $event_id )
		);
	}
}
