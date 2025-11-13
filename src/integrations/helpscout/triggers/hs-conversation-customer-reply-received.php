<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator\Integrations\Helpscout;

/**
 * Class Hs_Conversation_Customer_Reply_Received
 *
 * @package Uncanny_Automator
 * @property Helpscout_App_Helpers $helpers
 * @property Helpscout_Api_Caller $api
 * @property Helpscout_Webhooks $webhooks
 */
class Hs_Conversation_Customer_Reply_Received extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'HS_CONVERSATION_CUSTOMER_REPLY_RECEIVED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'HS_CONVERSATION_CUSTOMER_REPLY_RECEIVED_META';

	/**
	 * Check if trigger requirements are met
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return $this->webhooks->get_webhooks_enabled_status();
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
				/* translators: %1$s: Conversation, %2$s: Mailbox */
				esc_html_x( '{{A conversation:%1$s}} in {{a mailbox:%2$s}} receives a reply from a customer', 'Helpscout', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'MAILBOX:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( '{{A conversation}} in {{a mailbox}} receives a reply from a customer', 'Helpscout', 'uncanny-automator' )
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
				'label'                 => esc_html_x( 'Mailbox', 'Help Scout', 'uncanny-automator' ),
				'token_name'            => esc_html_x( 'Selected mailbox', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => $this->helpers->get_mailboxes( true ),
				'supports_custom_value' => false,
				'required'              => true,
			),
			array(
				'option_code'           => self::TRIGGER_META,
				'label'                 => esc_html_x( 'Conversation', 'Help Scout', 'uncanny-automator' ),
				'token_name'            => esc_html_x( 'Selected conversation', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => array(),
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
				'tokenId'   => 'mailbox_id',
				'tokenName' => esc_html_x( 'Mailbox ID', 'Help Scout', 'uncanny-automator' ),
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
				'tokenId'   => 'conversation_status',
				'tokenName' => esc_html_x( 'Conversation status', 'Help Scout', 'uncanny-automator' ),
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
				'tokenId'   => 'tags',
				'tokenName' => esc_html_x( 'Tag name', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'tags_url_search',
				'tokenName' => esc_html_x( 'Tag search query link', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'url',
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

		// Check that this is a customer reply event
		if ( ! $this->webhooks->is_webhook_request_matches_event( $headers, 'convo.customer.reply.created' ) ) {
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

		$customer_name = implode( ' ', array( $params['primaryCustomer']['first'], $params['primaryCustomer']['last'] ) );

		if ( empty( $customer_name ) ) {
			$customer_name = $params['primaryCustomer']['email'];
		}

		// Smart conversation URL - use web href if available, otherwise construct it
		$conversation_url = 'https://secure.helpscout.net/conversation/' . $params['id'];
		if ( isset( $params['_links']['web']['href'] ) && ! empty( $params['_links']['web']['href'] ) ) {
			$conversation_url = $params['_links']['web']['href'];
		} elseif ( isset( $params['number'] ) ) {
			$conversation_url = 'https://secure.helpscout.net/conversation/' . $params['id'] . '/' . $params['number'];
		}

		return array(
			// The self::TRIGGER_META refers to the Conversation.
			self::TRIGGER_META    => $params['id'], // Parsing auto-generated relevant token.
			'MAILBOX'             => $params['mailboxId'], // Parsing auto-generated relevant token.
			'number'              => $params['number'] ?? '',
			'conversation_id'     => $params['id'],
			'folderId'            => $params['folderId'] ?? '',
			'mailbox_id'          => $params['mailboxId'],
			'conversation_url'    => $conversation_url,
			'conversation_title'  => $params['subject'],
			'conversation_status' => $params['status'],
			'customer_name'       => $customer_name,
			'customer_email'      => $params['primaryCustomer']['email'],
			'tags'                => implode( ', ', array_column( $params['tags'] ?? array(), 'tag' ) ),
			'tags_url_search'     => add_query_arg(
				array(
					'query' => rawurlencode( 'tag:' . implode( ',', array_column( $params['tags'] ?? array(), 'tag' ) ) ),
				),
				'https://secure.helpscout.net/search'
			),
		);
	}
}
