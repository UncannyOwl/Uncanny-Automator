<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator\Integrations\Helpscout;

/**
 * Class Hs_Note_Added
 *
 * @package Uncanny_Automator
 * @method Helpscout_Helpers get_item_helpers()
 */
class Hs_Note_Added extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'HS_NOTE_ADDED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'HS_NOTE_ADDED_META';

	/**
	 * Check if trigger requirements are met
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return automator_get_option( 'uap_helpscout_enable_webhook', false );
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_trigger() {

		$this->set_integration( 'HELPSCOUT' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_uses_api( true );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'knowledge-base/helpscout/' ) );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: Conversation */
				esc_html_x( 'A note is added to {{a conversation:%1$s}}', 'HelpScout', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A note is added to {{a conversation}}', 'HelpScout', 'uncanny-automator' )
		);

		$this->add_action( 'automator_helpscout_webhook_received', 10, 2 );
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'           => 'MAILBOX',
				'label'                 => esc_html_x( 'Mailbox', 'HelpScout', 'uncanny-automator' ),
				'token_name'           => esc_html_x( 'Selected mailbox', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => $this->get_item_helpers()->fetch_mailboxes( true ),
				'supports_custom_value' => false,
				'required'              => true,
			),
			array(
				'option_code'           => self::TRIGGER_META,
				'label'                 => esc_html_x( 'Conversation', 'HelpScout', 'uncanny-automator' ),
				'token_name'           => esc_html_x( 'Selected conversation', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => array(),
				'supports_custom_value' => false,
				'required'              => true,
				'ajax'                  => array(
					'endpoint'       => 'helpscout_fetch_conversations',
					'event'          => 'parent_fields_change',
					'listen_fields'  => array( 'MAILBOX' ),
				),
			),
		);
	}

	/**
	 * Returns the trigger's tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {

		$helpscout_tokens = array(
			array(
				'tokenId'   => 'number',
				'tokenName' => esc_html_x( 'Conversation number', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'conversation_id',
				'tokenName' => esc_html_x( 'Conversation ID', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'folderId',
				'tokenName' => esc_html_x( 'Folder ID', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'conversation_url',
				'tokenName' => esc_html_x( 'Conversation URL', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'conversation_title',
				'tokenName' => esc_html_x( 'Conversation title', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'customer_name',
				'tokenName' => esc_html_x( 'Customer name', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'customer_email',
				'tokenName' => esc_html_x( 'Customer email', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'conversation_status',
				'tokenName' => esc_html_x( 'Conversation status', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'note',
				'tokenName' => esc_html_x( 'Note', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'note_added_by',
				'tokenName' => esc_html_x( 'Note added by', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'note_assigned_to',
				'tokenName' => esc_html_x( 'Note assigned to', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		return array_merge( $tokens, $helpscout_tokens );
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $params, $headers ) = $hook_args;

		// Check that this is a note created event
		if ( ! $this->get_item_helpers()->is_webhook_request_matches_event( $headers, 'convo.note.created' ) ) {
			return false;
		}

		// Check conversation and mailbox match
		$conversation_id = $trigger['meta'][ self::TRIGGER_META ];
		$mailbox_id      = $trigger['meta']['MAILBOX'];

		// Allow "Any conversation" (-1) and "Any mailbox" (-1)
		$conversation_matches = ( intval( -1 ) === intval( $conversation_id ) || absint( $params['id'] ) === absint( $conversation_id ) );
		$mailbox_matches      = ( intval( -1 ) === intval( $mailbox_id ) || absint( $params['mailboxId'] ) === absint( $mailbox_id ) );

		return $conversation_matches && $mailbox_matches;
	}

	/**
	 * Hydrate tokens
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $params, $headers ) = $hook_args;

		$note = $params['_embedded']['threads'][0]; // This trigger should fire with the recent note added.

		$customer_name = $this->extract_note_user_display_name( $params['primaryCustomer'] );
		$note_user     = $this->extract_note_user_display_name( $note['createdBy'] );
		$note_assignee = $this->extract_note_user_display_name( $note['assignedTo'] );

		// Smart conversation URL - use web href if available, otherwise construct it
		$conversation_url = 'https://secure.helpscout.net/conversation/' . $params['id'];
		if ( isset( $params['_links']['web']['href'] ) && ! empty( $params['_links']['web']['href'] ) ) {
			$conversation_url = $params['_links']['web']['href'];
		} elseif ( isset( $params['number'] ) ) {
			$conversation_url = 'https://secure.helpscout.net/conversation/' . $params['id'] . '/' . $params['number'];
		}

		return array(
			self::TRIGGER_META        => $params['id'], // Parsing auto-generated relevant token.
			'MAILBOX'                 => $params['mailboxId'], // Parsing auto-generated relevant token.
			'customer_name'           => ! empty( $customer_name ) ? $customer_name : $params['customer']['email'],
			'customer_email'          => $params['primaryCustomer']['email'],
			'conversation_url'        => $conversation_url,
			'conversation_title'      => $params['subject'],
			'conversation_status'     => $params['status'],
			'note'                    => $note['body'],
			'note_added_by'           => $note_user,
			'note_assigned_to'        => $note_assignee,
			'number'                  => isset( $params['number'] ) ? $params['number'] : '',
			'conversation_id'         => $params['id'],
			'folderId'                => isset( $params['folderId'] ) ? $params['folderId'] : '',
		);
	}

	/**
	 * Extracts the information for note user and return the user display name.
	 *
	 * @param array $note_user The embedded threads index 0 with specific key of either `createdBy` or `assignedTo`.
	 *
	 * @return string The user's display name.
	 */
	protected function extract_note_user_display_name( $note_user ) {

		$user = implode( ' ', array( $note_user['first'], $note_user['last'] ) );

		if ( empty( trim( $user ) ) ) {
			$user = $note_user['email'];
		}

		if ( 1 === $note_user['id'] ) {
			$user = 'Anyone';
		}

		return $user;
	}
}
