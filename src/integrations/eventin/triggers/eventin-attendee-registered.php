<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Uncanny_Automator\Integrations\Eventin\Dispatchers\Eventin_Order_Dispatcher;
use Uncanny_Automator\Integrations\Eventin\Tokens\Eventin_Tokens;
use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Eventin_Attendee_Registered
 *
 * Fires once per attendee on a completing order — the per-guest fan-out of a
 * purchase. A four-ticket order fires this four times and the purchase trigger
 * once: pick this one to mail every guest, the purchase trigger to mail the
 * buyer.
 *
 * Bound to Eventin_Order_Dispatcher's normalized event rather than to Eventin's
 * own `eventin_attendee_payment_completed`. That hook has exactly one emitter,
 * OrderAttendee::update_attendee_payment_status() (core/Order/OrderAttendee.php:46),
 * which runs only on `eventin_order_completed` — so it never fires for an order
 * completed from the admin screens, which take a separate route that writes the
 * attendee status inline (core/Order/OrderController.php:869). See the
 * dispatcher for the full pipeline map.
 *
 * Anonymous: the attendee is frequently not the buyer and carries only an email
 * address, so there may be no WP user behind them at all. The typed email never
 * binds an account; the recipe's user selector can map the attendee email token.
 *
 * @package Uncanny_Automator\Integrations\Eventin
 *
 * @property Eventin_Helpers $item_helpers
 */
class Eventin_Attendee_Registered extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EVENTIN_ATTENDEE_REGISTERED', 'EVENTIN' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'EVENTIN_EVENT' )
			->hook( 'automator_eventin_attendee_registered', 10, 2 );
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
				esc_html_x( 'An attendee is registered for {{an event:%1$s}}', 'Eventin', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'An attendee is registered for {{an event}}', 'Eventin', 'uncanny-automator' ) );
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
	 * Validate the attendee and the event.
	 *
	 * @param array $trigger   The trigger.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// The dispatcher passes a plain attendee ID; get_model_id() also accepts
		// an Attendee_Model, so a third party re-dispatching the event with a
		// model still validates.
		$attendee_id = $this->item_helpers->get_model_id( $hook_args[0] ?? null );

		if ( 0 === $attendee_id || ! $this->item_helpers->is_attendee( $attendee_id ) ) {
			return false;
		}

		$event_id = $this->item_helpers->get_attendee_event_id( $attendee_id );

		if ( 0 === $event_id ) {
			return false;
		}

		$selected_event = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Eventin_Helpers::ANY );

		if ( Eventin_Helpers::ANY !== $selected_event && absint( $selected_event ) !== $event_id ) {
			return false;
		}

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

		return array_merge( $tokens, $token_class->attendee_tokens(), $token_class->event_tokens() );
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

		$attendee_id = $this->item_helpers->get_model_id( $hook_args[0] ?? null );
		$event_id    = $this->item_helpers->get_attendee_event_id( $attendee_id );

		$token_class = new Eventin_Tokens();

		return array_merge(
			$token_class->hydrate_attendee_tokens( $attendee_id ),
			$token_class->hydrate_event_tokens( $event_id )
		);
	}
}
