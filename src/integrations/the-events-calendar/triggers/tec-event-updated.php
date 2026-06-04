<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class EC_EVENT_UPDATED
 *
 * Trigger: {{An event}} is updated. Supports "Any event" sentinel.
 *
 * Listens only to `save_post_tribe_events` — that single hook fires for
 * every save path. The update-only gate is `$update === true` (third
 * hook arg), so a create never passes validate().
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_EVENT_UPDATED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt into the lazy trigger loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'EC_EVENT_UPDATED', 'EC' )
			->trigger_meta( 'ECEVENTS' )
			->trigger_type( 'anonymous' )
			->hook( 'save_post_tribe_events', 20, 3 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_is_login_required( false );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/the-events-calendar/' ) );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s is the event field token */
				esc_html_x( '{{An event:%1$s}} is updated', 'The Events Calendar', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( '{{An event}} is updated', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {
		return array(
			array(
				'input_type'            => 'select',
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Event', 'The Events Calendar', 'uncanny-automator' ),
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'events' ),
			),
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $hook_args[0] ) ) {
			return false;
		}

		$event_id = absint( $hook_args[0] );
		if ( 0 === $event_id ) {
			return false;
		}

		// UPDATE-ONLY: skip the create branch.
		$is_update = isset( $hook_args[2] ) && true === (bool) $hook_args[2];
		if ( ! $is_update ) {
			return false;
		}

		// Standard guards.
		if ( wp_is_post_autosave( $event_id ) || wp_is_post_revision( $event_id ) ) {
			return false;
		}
		$post = isset( $hook_args[1] ) && $hook_args[1] instanceof \WP_Post
			? $hook_args[1]
			: get_post( $event_id );
		if ( ! $post instanceof \WP_Post || 'auto-draft' === $post->post_status ) {
			return false;
		}

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected = (string) $trigger['meta'][ $this->get_trigger_meta() ];

		if ( '-1' === $selected ) {
			return true;
		}

		return absint( $selected ) === $event_id;
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
		unset( $trigger );
		$event_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		return $this->item_helpers->tokens()->hydrate_event_tokens( $event_id, $this->get_trigger_meta() );
	}
}
