<?php
namespace Uncanny_Automator;

class HELPSCOUT_CONVERSATION_TAG_ADD {

	use Recipe\Actions;

	use Recipe\Action_Tokens;

	protected $helper = null;

	public function __construct() {

		$this->setup_action();

		$this->helper = new Helpscout_Helpers( false );

	}

	/**
	 * Setups our action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'HELPSCOUT' );

		$this->set_action_code( 'HELPSCOUT_CONVERSATION_TAG_ADD' );

		$this->set_action_meta( 'HELPSCOUT_CONVERSATION_TAG_ADD_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/helpscout/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action - WordPress */
				esc_attr__(
					'Add {{a tag:%1$s}} to {{a conversation:%2$s}}',
					'uncanny-automator'
				),
				$this->get_action_meta(),
				'CONVERSATION:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			/* translators: Action - WordPress */
			esc_attr__(
				'Add {{a tag}} to {{a conversation}}',
				'uncanny-automator'
			)
		);

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->register_action();

	}

	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code'           => $this->get_action_meta(),
							'label'                 => esc_attr__( 'Tag', 'uncanny-automator' ),
							'input_type'            => 'text',
							'supports_custom_value' => true,
							'required'              => true,
						),
						array(
							'option_code'           => 'MAILBOX',
							'label'                 => esc_attr__( 'Mailbox', 'uncanny-automator' ),
							'input_type'            => 'select',
							'options'               => $this->helper->fetch_mailboxes(),
							'supports_custom_value' => true,
							'required'              => true,
							'is_ajax'               => true,
							'endpoint'              => 'helpscout_fetch_conversations',
							'fill_values_in'        => 'CONVERSATION',
						),
						array(
							'option_code'           => 'CONVERSATION',
							'label'                 => esc_attr__( 'Conversation', 'uncanny-automator' ),
							'input_type'            => 'select',
							'options'               => array(),
							'supports_custom_value' => false,
							'required'              => true,
						),
					),
				),
			)
		);
	}


	/**
	 * Process action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$tags            = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;
		$conversation_id = isset( $parsed['CONVERSATION'] ) ? sanitize_text_field( $parsed['CONVERSATION'] ) : 0;

		try {

			$this->helper->api_request(
				array(
					'conversation_id' => $conversation_id,
					'tags'            => $tags,
					'action'          => 'update_conversation_tag',
				),
				$action_data
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			// Log error if there are any error messages.
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

}
