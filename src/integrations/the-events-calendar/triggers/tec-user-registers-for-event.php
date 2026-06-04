<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class EC_REGISTER
 *
 * Trigger: A user registers for an event.
 *
 * Listens to the normalized internal action fired by
 * The_Events_Calendar_Helpers, which bridges all ticket providers
 * (RSVP, WooCommerce, PayPal/TPP, Tickets Commerce) into a single
 * `automator_event_tickets_user_registered` action.
 *
 * Sacred contract values (DO NOT CHANGE):
 *   - integration code: EC
 *   - trigger code:     USERREGISTERED
 *   - trigger meta:     ECEVENTS
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_REGISTER extends Trigger {

	use Has_Dependency;

	/**
	 * Opt into the lazy trigger loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USERREGISTERED', 'EC' )
			->trigger_meta( 'ECEVENTS' )
			->hook( The_Events_Calendar_Helpers::USER_REGISTERED_ACTION, 10, 4 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/the-events-calendar/' ) );

		/* translators: %1$s is the event field token */
		$this->set_sentence( sprintf( esc_html_x( 'A user registers for {{an event:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user registers for {{an event}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * Gate registration on Event Tickets being active — the provider
	 * hooks that feed this trigger only exist when ET is present.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return $this->et_active();
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {
		return array(
			array(
				'input_type'  => 'select',
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Event', 'The Events Calendar', 'uncanny-automator' ),
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'events' ),
			),
		);
	}

	/**
	 * Validate that the recipe-selected event matches the event the
	 * normalized action fired for. The "Any event" sentinel `-1` always
	 * passes.
	 *
	 * Hook arg layout (from the helper's provider normalization):
	 *   [0] event_id, [1] product_id, [2] order_id, [3] user_id
	 *
	 * @param array<string,mixed> $trigger
	 * @param array<int,mixed>    $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		if ( ! isset( $hook_args[0] ) ) {
			return false;
		}

		// Order ID must be present — preserves the legacy guard.
		if ( empty( $hook_args[2] ) ) {
			return false;
		}

		$selected_event_id = (string) $trigger['meta'][ $this->get_trigger_meta() ];

		// Any event sentinel.
		if ( '-1' === $selected_event_id ) {
			return true;
		}

		return absint( $hook_args[0] ) === absint( $selected_event_id );
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			$this->item_helpers->tokens()->event_tokens( $this->get_trigger_meta() )
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array<string,string>
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$event_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;

		return $this->item_helpers->tokens()->hydrate_event_tokens( $event_id, $this->get_trigger_meta() );
	}
}
