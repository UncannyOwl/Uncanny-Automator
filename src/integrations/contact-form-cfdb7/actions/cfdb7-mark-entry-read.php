<?php

namespace Uncanny_Automator\Integrations\Cfdb7;

use Uncanny_Automator\Recipe\Action;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

/**
 * Class Cfdb7_Mark_Entry_Read
 *
 * Marks a CFDB7 entry as read, mirroring CFDB7's bulk "Read" action. The
 * status is stored inside the entry's serialized values, not in a column.
 *
 * @package Uncanny_Automator\Integrations\Cfdb7
 * @since 8.1.0
 *
 * @property Cfdb7_Helpers $item_helpers
 */
class Cfdb7_Mark_Entry_Read extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CFDB7' );
		$this->set_action_code( 'CFDB7_MARK_ENTRY_READ' );
		$this->set_action_meta( 'CFDB7_ENTRY' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Entry ID */
				esc_html_x( 'Mark {{an entry:%1$s}} as read', 'Contact Form CFDB7', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Mark {{an entry}} as read', 'Contact Form CFDB7', 'uncanny-automator' ) );
	}

	/**
	 * Define action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return $this->item_helpers->get_action_tokens_config();
	}

	/**
	 * Action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->item_helpers->get_entry_id_option( $this->get_action_meta() ),
		);
	}

	/**
	 * Process action.
	 *
	 * An entry that is already read is a success: the requested end state holds.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The trigger args.
	 * @param array $parsed      The parsed field values.
	 *
	 * @return bool
	 * @throws Action_Error_Exception When the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$entry_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$entry    = $this->item_helpers->get_entry( $entry_id );

		if ( null === $entry ) {
			throw Action_Error_Exception::not_found(
				sprintf(
					/* translators: 1: Entry ID */
					esc_html_x( 'Entry %d not found.', 'Contact Form CFDB7', 'uncanny-automator' ),
					absint( $entry_id )
				)
			);
		}

		$values = $this->item_helpers->get_entry_values( $entry );

		// Rewriting an unreadable blob would destroy the submission.
		if ( null === $values ) {
			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: 1: Entry ID */
					esc_html_x( 'Entry %d could not be read.', 'Contact Form CFDB7', 'uncanny-automator' ),
					absint( $entry_id )
				)
			);
		}

		if ( ! $this->item_helpers->set_entry_status( $entry_id, $values, 'read' ) ) {
			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: 1: Entry ID */
					esc_html_x( 'Failed to update entry %d.', 'Contact Form CFDB7', 'uncanny-automator' ),
					absint( $entry_id )
				)
			);
		}

		// The end state, not the return value: the stored status has to be read.
		$stored = $this->item_helpers->get_entry_values( $this->item_helpers->get_entry( $entry_id ) );

		if ( ! is_array( $stored ) || 'read' !== ( $stored['cfdb7_status'] ?? '' ) ) {
			throw Action_Error_Exception::execution_failed(
				sprintf(
					/* translators: 1: Entry ID */
					esc_html_x( 'Entry %d was not marked as read.', 'Contact Form CFDB7', 'uncanny-automator' ),
					absint( $entry_id )
				)
			);
		}

		$this->hydrate_tokens( $this->item_helpers->hydrate_action_tokens( $entry ) );

		return true;
	}
}
