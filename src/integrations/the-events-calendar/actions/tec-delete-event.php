<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class EC_DELETE_EVENT
 *
 * Deletes an event via Tribe__Events__API::deleteEvent().
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_DELETE_EVENT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'EC' );
		$this->set_action_code( 'EC_DELETE_EVENT' );
		$this->set_action_meta( 'EVENT_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );

		/* translators: %1$s is the event field */
		$this->set_sentence( sprintf( esc_html_x( 'Delete {{an event:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Delete {{an event}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Event', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'events_strict' ),
			),
			array(
				'option_code' => 'EVENT_FORCE_DELETE',
				'label'       => esc_html_x( 'Permanently delete (skip Trash)', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'required'    => false,
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( 'Tribe__Events__API' ) ) {
			$this->add_log_error( esc_html_x( 'The Events Calendar API is not available.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$event_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $event_id ) {
			$this->add_log_error( esc_html_x( 'A specific event must be selected.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( 'tribe_events' !== get_post_type( $event_id ) ) {
			/* translators: %d is the event ID */
			$this->add_log_error( sprintf( esc_html_x( 'Event with ID %d does not exist.', 'The Events Calendar', 'uncanny-automator' ), $event_id ) );
			return false;
		}

		// Canonical Automator checkbox parser — handles 'true'/'false'/'1'/'0'/'on'/'' uniformly.
		$force = filter_var( strtolower( (string) ( $parsed['EVENT_FORCE_DELETE'] ?? '' ) ), FILTER_VALIDATE_BOOLEAN );

		$result = \Tribe__Events__API::deleteEvent( $event_id, $force );

		if ( is_wp_error( $result ) || false === $result || null === $result ) {
			$this->add_log_error( esc_html_x( 'Failed to delete event.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		return true;
	}
}
