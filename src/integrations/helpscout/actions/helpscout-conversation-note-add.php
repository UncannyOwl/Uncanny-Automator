<?php

namespace Uncanny_Automator\Integrations\Helpscout;

/**
 * Class Helpscout_Conversation_Note_Add
 *
 * @package Uncanny_Automator
 * @property Helpscout_App_Helpers $helpers
 * @property Helpscout_Api_Caller $api
 */
class Helpscout_Conversation_Note_Add extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'HELPSCOUT' );
		$this->set_action_code( 'HELPSCOUT_CONVERSATION_NOTE_ADD' );
		$this->set_action_meta( 'HELPSCOUT_CONVERSATION_NOTE_ADD_META' );
		$this->set_is_pro( false );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/helpscout/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Note, %2$s: Conversation */
				esc_html_x( 'Add {{a note:%1$s}} to {{a conversation:%2$s}}', 'Help Scout', 'uncanny-automator' ),
				$this->get_action_meta(),
				'CONVERSATION:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Add {{a note}} to {{a conversation}}', 'Help Scout', 'uncanny-automator' )
		);

		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'NOTE_ID' => array(
					'name' => esc_html_x( 'Note ID', 'Help Scout', 'uncanny-automator' ),
					'type' => 'int',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Note', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'textarea',
				'supports_custom_value' => true,
				'required'              => true,
			),
			array(
				'option_code'           => 'MAILBOX',
				'label'                 => esc_html_x( 'Mailbox', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => $this->helpers->get_mailboxes(),
				'supports_custom_value' => true,
				'required'              => true,
			),
			array(
				'option_code'           => 'CONVERSATION',
				'label'                 => esc_html_x( 'Conversation', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'supports_custom_value' => false,
				'required'              => true,
				'ajax'                  => array(
					'endpoint'      => 'helpscout_fetch_conversations',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'MAILBOX' ),
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$note            = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';
		$conversation_id = isset( $parsed['CONVERSATION'] ) ? sanitize_text_field( $parsed['CONVERSATION'] ) : 0;

		if ( empty( $note ) ) {
			throw new \Exception( esc_html_x( 'Note is required', 'Help Scout', 'uncanny-automator' ), 422 );
		}

		if ( empty( $conversation_id ) ) {
			throw new \Exception( esc_html_x( 'Conversation is required', 'Help Scout', 'uncanny-automator' ), 422 );
		}

		$response = $this->api->api_request(
			array(
				'conversation_id' => $conversation_id,
				'note'            => $note,
				'action'          => 'add_conversation_note',
			),
			$action_data
		);

		$this->hydrate_tokens(
			array(
				'NOTE_ID' => isset( $response['data']['data']['resourceId'] ) ? sanitize_text_field( $response['data']['data']['resourceId'] ) : 0,
			)
		);

		return true;
	}
}
