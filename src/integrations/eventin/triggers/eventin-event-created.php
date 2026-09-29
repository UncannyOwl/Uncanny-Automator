<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Uncanny_Automator\Integrations\Eventin\Tokens\Eventin_Tokens;
use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Eventin_Event_Created
 *
 * Fires when an event is created through `POST eventin/v2/events`.
 * `eventin_event_created( $event, $request )` is emitted at
 * core/event/Api/EventController.php:890 — a single fire site, unlike the
 * purchase hook.
 *
 * No options, deliberately. An event cannot be pre-selected before it exists,
 * so there is no legitimate selector to offer; the trigger fires for every
 * created event and exposes the category and event type as tokens instead.
 *
 * Token timing is safe. The hook fires at the END of create_item(), after
 * `$event->create()` has written the post and all its meta, after
 * assign_categories() and assign_tags() (:860-868), and after the meeting-link
 * update (:876-878). Every token below is therefore readable at fire time —
 * which is not something a "created" hook can generally be relied on for.
 *
 * REST-only, same caveat as {{An event}} is updated: a classic-editor publish
 * or a bare wp_insert_post() fires nothing. Eventin Pro's webhook layer bridges
 * `publish_etn` into `eventin_create_etn` (eventin-pro/core/webhook/hooks.php:148),
 * but that hook exists only when Pro is active and carries just a post ID.
 *
 * @package Uncanny_Automator\Integrations\Eventin
 *
 * @property Eventin_Helpers $item_helpers
 */
class Eventin_Event_Created extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EVENTIN_EVENT_CREATED', 'EVENTIN' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'EVENTIN_EVENT' )
			->hook( 'eventin_event_created', 10, 2 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'An event is created', 'Eventin', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'An event is created', 'Eventin', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * None — see the class docblock.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Validate trigger.
	 *
	 * @param array $trigger   The trigger.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		return 0 !== $this->resolve_event_id( $hook_args );
	}

	/**
	 * Read the event ID off the hook payload.
	 *
	 * @param array $hook_args The hook arguments.
	 *
	 * @return int
	 */
	private function resolve_event_id( $hook_args ) {
		return $this->item_helpers->get_model_id( $hook_args[0] ?? null );
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
		return array_merge( $tokens, ( new Eventin_Tokens() )->event_tokens() );
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
		return ( new Eventin_Tokens() )->hydrate_event_tokens( $this->resolve_event_id( $hook_args ) );
	}
}
