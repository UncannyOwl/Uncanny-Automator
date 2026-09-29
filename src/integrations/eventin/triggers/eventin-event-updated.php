<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Uncanny_Automator\Integrations\Eventin\Tokens\Eventin_Tokens;
use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Eventin_Event_Updated
 *
 * Fires when an event is saved through `PUT eventin/v2/events/{id}` — the route
 * the Eventin admin UI and the block editor both use.
 * `eventin_event_updated( $event, $request )` is emitted at
 * core/event/Api/EventController.php:1104.
 *
 * REST-only, deliberately. A classic-editor save or a bare wp_update_post()
 * fires nothing. Eventin Pro's webhook layer bridges core `post_updated` into
 * `eventin_update_etn` (eventin-pro/core/webhook/hooks.php:163), but that hook
 * exists only when Pro is active and carries only a post ID, so a Free trigger
 * cannot be built on it.
 *
 * @package Uncanny_Automator\Integrations\Eventin
 *
 * @property Eventin_Helpers $item_helpers
 */
class Eventin_Event_Updated extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EVENTIN_EVENT_UPDATED', 'EVENTIN' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'EVENTIN_EVENT' )
			->hook( 'eventin_event_updated', 10, 2 );
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
				esc_html_x( '{{An event:%1$s}} is updated', 'Eventin', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( '{{An event}} is updated', 'Eventin', 'uncanny-automator' ) );
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
	 * Validate the event.
	 *
	 * @param array $trigger   The trigger.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$event_id = $this->resolve_event_id( $hook_args );

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
