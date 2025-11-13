<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator\Integrations\Helpscout;

/**
 * Class Hs_Conversation_Tag_Updated
 *
 * @package Uncanny_Automator
 * @property Helpscout_App_Helpers $helpers
 * @property Helpscout_Api_Caller $api
 * @property Helpscout_Webhooks $webhooks
 */
class Hs_Conversation_Tag_Updated extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'HS_CONVERSATION_TAG_UPDATED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'HS_CONVERSATION_TAG_UPDATED_META';

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
			esc_html_x( "A conversation's tags are updated", 'Help Scout', 'uncanny-automator' )
		);

		$this->set_readable_sentence(
			esc_html_x( "A conversation's tags are updated", 'Help Scout', 'uncanny-automator' )
		);

		$this->add_action( 'automator_helpscout_webhook_received', 10, 2 );
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {
		return array(); // This trigger has no options
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
				'tokenId'   => 'folder_id',
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
				'tokenId'   => 'conversation_created',
				'tokenName' => esc_html_x( 'Conversation created on', 'Help Scout', 'uncanny-automator' ),
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
				'tokenId'   => 'customer_waiting_since',
				'tokenName' => esc_html_x( 'Customer waiting since', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'assigned_to',
				'tokenName' => esc_html_x( 'Assigned to', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'tags',
				'tokenName' => esc_html_x( 'Tags', 'Help Scout', 'uncanny-automator' ),
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

		// Check that this is a tag updated event
		return $this->webhooks->is_webhook_request_matches_event( $headers, 'convo.tags' );
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

		if ( empty( trim( $customer_name ) ) ) {
			$customer_name = $params['primaryCustomer']['email'];
		}

		$threads   = $params['_embedded']['threads'];
		$assignees = array_column( $threads, 'assignedTo' );
		$assignee  = $assignees[0]; // Helpscout index zero is the recent assignee.

		$assign_to = implode( ' ', array( $assignee['first'], $assignee['last'] ) ) . ' (' . $assignee['email'] . ')';

		if ( 1 === $assignee['id'] ) {
			$assign_to = 'Anyone'; // Helpscout `anyone` assignee has id value of 1.
		}

		$conversation_id = $params['id'] ?? '';

		if ( isset( $params['conversationId'] ) ) {
			$conversation_id = $params['conversationId'];
		}

		$conversation_created = '';

		if ( isset( $params['createdAt'] ) ) {
			$conversation_created = $this->helpers->format_date_timestamp( strtotime( $params['createdAt'] ) );
		}

		$customer_waiting_since = '';

		if ( isset( $params['customerWaitingSince']['time'] ) ) {
			$customer_waiting_since = $this->helpers->format_date_timestamp( strtotime( $params['customerWaitingSince']['time'] ) );
		}

		// Smart conversation URL - use web href if available, otherwise construct it
		$conversation_url = 'https://secure.helpscout.net/conversation/' . $conversation_id;
		if ( isset( $params['_links']['web']['href'] ) && ! empty( $params['_links']['web']['href'] ) ) {
			$conversation_url = $params['_links']['web']['href'];
		} elseif ( isset( $params['number'] ) ) {
			$conversation_url = 'https://secure.helpscout.net/conversation/' . $conversation_id . '/' . $params['number'];
		}

		return array(
			'number'                 => $params['number'] ?? '',
			'conversation_id'        => $conversation_id,
			'folder_id'              => $params['folderId'] ?? '',
			'mailbox_id'             => $params['mailboxId'] ?? '',
			'conversation_url'       => $conversation_url,
			'conversation_title'     => $params['subject'] ?? '',
			'conversation_status'    => $params['status'] ?? '',
			'conversation_created'   => $conversation_created,
			'customer_name'          => $customer_name ?? '',
			'customer_email'         => $params['primaryCustomer']['email'] ?? '',
			'customer_waiting_since' => $customer_waiting_since,
			'assigned_to'            => $assign_to,
			'tags'                   => implode( ', ', array_column( $params['tags'] ?? array(), 'tag' ) ),
		);
	}
}
