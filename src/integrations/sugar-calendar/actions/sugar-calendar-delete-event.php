<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar;

/**
 * Class Sugar_Calendar_Delete_Event
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Sugar_Calendar\Sugar_Calendar_Helpers get_item_helpers()
 */
class Sugar_Calendar_Delete_Event extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SUGAR_CALENDAR' );
		$this->set_action_code( 'SUGAR_CALENDAR_DELETE_EVENT' );
		$this->set_action_meta( 'SC_EVENT' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		// translators: %1$s is the event.
		$this->set_sentence( sprintf( esc_html_x( 'Delete {{an event:%1$s}}', 'Sugar Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Delete {{an event}}', 'Sugar Calendar', 'uncanny-automator' ) );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Event', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->get_item_helpers()->get_events( false ),
				'supports_custom_value' => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$post_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( empty( $post_id ) ) {
			$this->add_log_error( esc_html_x( 'Event ID is required.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		// Look up the SC event by its WP post ID.
		$event = sugar_calendar_get_event_by_object( $post_id );

		if ( empty( $event->id ) ) {
			$this->add_log_error(
				sprintf(
					// translators: %d is the post ID.
					esc_html_x( 'No Sugar Calendar event found for post ID %d.', 'Sugar Calendar', 'uncanny-automator' ),
					$post_id
				)
			);
			return false;
		}

		// Delete from the sc_events table first; abort if it fails to avoid orphaning the WP post.
		$deleted = sugar_calendar_delete_event( $event->id );

		if ( ! $deleted ) {
			$this->add_log_error(
				sprintf(
					// translators: %d is the SC event ID.
					esc_html_x( 'Failed to delete Sugar Calendar event with ID %d.', 'Sugar Calendar', 'uncanny-automator' ),
					$event->id
				)
			);
			return false;
		}

		// Delete the WP post.
		wp_delete_post( $post_id, true );

		return true;
	}
}
