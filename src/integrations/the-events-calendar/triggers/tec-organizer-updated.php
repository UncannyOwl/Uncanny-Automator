<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class EC_ORGANIZER_UPDATED
 *
 * Trigger: An organizer is updated. Supports "Any organizer" sentinel.
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_ORGANIZER_UPDATED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt into the lazy trigger loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		// `tribe_events_organizer_updated` only fires from TEC's
		// Tribe__Events__Organizer::update() API path (verified at
		// the-events-calendar/src/Tribe/Organizer.php:537). The standard
		// wp-admin "Edit Organizer" screen uses wp_update_post() directly
		// and never reaches that method, so the trigger never fires.
		// Switch to save_post_tribe_organizer + an "is update" guard.
		return self::new_definition( 'EC_ORGANIZER_UPDATED', 'EC' )
			->trigger_meta( 'ECORG' )
			->trigger_type( 'anonymous' )
			->hook( 'save_post_tribe_organizer', 10, 3 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_is_login_required( false );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/the-events-calendar/' ) );

		/* translators: %1$s is the organizer field token */
		$this->set_sentence( sprintf( esc_html_x( '{{An organizer:%1$s}} is updated', 'The Events Calendar', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( '{{An organizer}} is updated', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {
		return array(
			array(
				'input_type'            => 'select',
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Organizer', 'The Events Calendar', 'uncanny-automator' ),
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'organizers' ),
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

		$post_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$post    = $hook_args[1] ?? null;
		$update  = ! empty( $hook_args[2] );

		if ( 0 === $post_id ) {
			return false;
		}

		// Only fire on real updates, not on initial creation.
		if ( ! $update ) {
			return false;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( $post instanceof \WP_Post && 'auto-draft' === (string) $post->post_status ) {
			return false;
		}

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$selected = (string) $trigger['meta'][ $this->get_trigger_meta() ];

		if ( '-1' === $selected ) {
			return true;
		}

		return $post_id === absint( $selected );
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
			$this->item_helpers->tokens()->organizer_tokens( $this->get_trigger_meta() )
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
		$organizer_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		return $this->item_helpers->tokens()->hydrate_organizer_tokens( $organizer_id, $this->get_trigger_meta() );
	}
}
