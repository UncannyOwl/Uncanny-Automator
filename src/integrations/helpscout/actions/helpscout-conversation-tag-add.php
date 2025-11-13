<?php

namespace Uncanny_Automator\Integrations\Helpscout;

/**
 * Class Helpscout_Conversation_Tag_Add
 *
 * @package Uncanny_Automator
 * @property Helpscout_App_Helpers $helpers
 * @property Helpscout_Api_Caller $api
 */
class Helpscout_Conversation_Tag_Add extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'HELPSCOUT' );
		$this->set_action_code( 'HELPSCOUT_CONVERSATION_TAG_ADD' );
		$this->set_action_meta( 'HELPSCOUT_CONVERSATION_TAG_ADD_META' );
		$this->set_is_pro( false );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/helpscout/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Tag, %2$s: Conversation */
				esc_html_x( 'Add {{a tag:%1$s}} to {{a conversation:%2$s}}', 'Help Scout', 'uncanny-automator' ),
				$this->get_action_meta(),
				'CONVERSATION:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Add {{a tag}} to {{a conversation}}', 'Help Scout', 'uncanny-automator' )
		);

		$this->set_background_processing( true );
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
				'label'                 => esc_html_x( 'Tag', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'text',
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

		$tags            = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;
		$conversation_id = isset( $parsed['CONVERSATION'] ) ? sanitize_text_field( $parsed['CONVERSATION'] ) : 0;

		$this->api->api_request(
			array(
				'conversation_id' => $conversation_id,
				'tags'            => $tags,
				'action'          => 'update_conversation_tag',
			),
			$action_data
		);

		return true;
	}
}
