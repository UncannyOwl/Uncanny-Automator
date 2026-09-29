<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Uncanny_Automator\Integrations\Eventin\Dispatchers\Eventin_Order_Dispatcher;
use Uncanny_Automator\Integrations\Eventin\Tokens\Eventin_Tokens;
use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Eventin_Ticket_Purchased
 *
 * Fires when an Eventin order reaches `completed`, whether it was paid through
 * Eventin's own gateways, through WooCommerce, or marked Completed by hand in
 * the admin.
 *
 * Bound to Eventin_Order_Dispatcher's normalized event rather than directly to
 * `eventin_order_completed`. That hook covers only the payment pipeline; the
 * admin status-change route emits `eventin_order_status_completed` instead
 * (core/Order/OrderController.php:885) and never the former, so a listener on
 * `eventin_order_completed` alone silently misses every offline or manual
 * completion. The dispatcher normalizes both and dedupes per request.
 *
 * User-scoped: the sentence promises "A user purchases …", so the run is bound
 * to the buyer's WP account and the COMMON user tokens resolve against them.
 *
 * Two consequences follow from that, both handled below:
 *
 * 1. `set_is_login_required( false )` stays. The buyer is not necessarily the
 *    author of the request — an admin marking an order Completed, or a gateway
 *    callback landing with no session, both fire this. Leaving the framework's
 *    auth gate on (abstract-trigger.php:597) would reject those before
 *    validate() ever runs. The user is bound explicitly in validate() instead.
 * 2. The buyer is the user whose session placed the order, the customer staff
 *    chose when they placed it for someone else, or the customer WooCommerce
 *    recorded when a guest paid through it. Any other guest checkout has no
 *    such user: the order's email was typed by the buyer, so it never binds
 *    an account. validate() rejects that order rather than opening a user run
 *    against user 0. Guest purchases belong to the anonymous triggers, whose
 *    email token the recipe's user selector can map.
 *
 * @package Uncanny_Automator\Integrations\Eventin
 *
 * @property Eventin_Helpers $item_helpers
 */
class Eventin_Ticket_Purchased extends Trigger {

	/**
	 * Option code of the ticket filter.
	 */
	const TICKET = 'EVENTIN_TICKET';

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EVENTIN_TICKET_PURCHASED', 'EVENTIN' )
			->trigger_type( 'user' )
			->trigger_meta( 'EVENTIN_EVENT' )
			->hook( 'automator_eventin_order_completed', 10, 1 );
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
				/* translators: 1: Ticket, 2: Event */
				esc_html_x( 'A user purchases {{a ticket:%1$s}} for {{an event:%2$s}}', 'Eventin', 'uncanny-automator' ),
				// A trigger's secondary options are addressed as OPTION_CODE:TRIGGER_META.
				// A bare code does not bind to the field, so the builder renders the
				// placeholder as inert text instead of an editable token.
				self::TICKET . ':' . $this->get_trigger_meta(),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'A user purchases {{a ticket}} for {{an event}}', 'Eventin', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * The event field is declared first because the ticket list is derived from
	 * it — ticket variations live on the event's own `etn_ticket_variations`
	 * meta, so there is nothing to offer until an event is chosen.
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
			array(
				'option_code'     => self::TICKET,
				'label'           => esc_html_x( 'Ticket', 'Eventin', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_parent_config(
					'tickets',
					array( $this->get_trigger_meta() )
				),
			),
		);
	}

	/**
	 * Validate the event and ticket, then bind the buyer.
	 *
	 * Binding is a pass/fail condition here, not a best effort: this is a user
	 * trigger, so a fire with no resolvable account would open a run whose
	 * user-scoped tokens and actions have nothing to act on.
	 *
	 * @param array $trigger   The trigger.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// The dispatcher passes a plain order ID; get_model_id() also accepts an
		// OrderModel, so a third party re-dispatching the event with a model still
		// validates.
		$order_id = $this->item_helpers->get_model_id( $hook_args[0] ?? null );

		if ( 0 === $order_id || Eventin_Helpers::ORDER_POST_TYPE !== get_post_type( $order_id ) ) {
			return false;
		}

		$event_id = absint( get_post_meta( $order_id, 'event_id', true ) );

		if ( 0 === $event_id ) {
			return false;
		}

		$selected_event = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Eventin_Helpers::ANY );

		if ( Eventin_Helpers::ANY !== $selected_event && absint( $selected_event ) !== $event_id ) {
			return false;
		}

		$selected_ticket = (string) ( $trigger['meta'][ self::TICKET ] ?? Eventin_Helpers::ANY );

		if ( ! $this->order_has_ticket( $order_id, $selected_ticket ) ) {
			return false;
		}

		$user_id = $this->item_helpers->get_order_buyer_id( $order_id );

		// Guest checkout: no account to bind. See the class docblock.
		if ( 0 === $user_id ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * Whether the order contains the selected ticket.
	 *
	 * An order's `tickets[]` rows carry `ticket_slug` + `ticket_quantity`
	 * (core/Order/OrderModel.php:80-84), which is why the dropdown's value is
	 * the ticket slug rather than an ID.
	 *
	 * @param int    $order_id The order post ID.
	 * @param string $selected The selected ticket slug, or the "Any" sentinel.
	 *
	 * @return bool
	 */
	private function order_has_ticket( $order_id, $selected ) {

		$tickets = get_post_meta( $order_id, 'tickets', true );
		$tickets = is_array( $tickets ) ? $tickets : array();

		if ( Eventin_Helpers::ANY === $selected ) {
			return ! empty( $tickets );
		}

		$selected = (string) $selected;

		foreach ( $tickets as $ticket ) {

			$ticket_slug = (string) ( $ticket['ticket_slug'] ?? '' );

			if ( $selected === $ticket_slug ) {
				return true;
			}
		}

		return false;
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

		return array_merge( $tokens, $token_class->order_tokens(), $token_class->event_tokens() );
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

		$order_id = $this->item_helpers->get_model_id( $hook_args[0] ?? null );
		$event_id = absint( get_post_meta( $order_id, 'event_id', true ) );

		$token_class = new Eventin_Tokens();

		return array_merge(
			$token_class->hydrate_order_tokens( $order_id ),
			$token_class->hydrate_event_tokens( $event_id )
		);
	}
}
