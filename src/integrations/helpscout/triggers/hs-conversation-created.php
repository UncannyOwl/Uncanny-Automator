<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator\Integrations\Helpscout;

/**
 * Class Hs_Conversation_Created
 *
 * @package Uncanny_Automator
 * @method Helpscout_Helpers get_item_helpers()
 */
class Hs_Conversation_Created extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'HS_CONVERSATION_CREATED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'HS_CONVERSATION_CREATED_META';

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
				/* translators: %1$s: Mailbox */
				esc_html_x( 'A conversation is created in {{a mailbox:%1$s}}', 'Helpscout', 'uncanny-automator' ),
				'MAILBOX:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A conversation is created in {{a mailbox}}', 'Helpscout', 'uncanny-automator' )
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
				'token_name'           => esc_html_x( 'Selected mailbox', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => $this->get_item_helpers()->fetch_mailboxes( true ),
				'supports_custom_value' => false,
				'required'              => true,
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
			array(
				'tokenId'   => 'message',
				'tokenName' => esc_html_x( 'Message', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'thread_count',
				'tokenName' => esc_html_x( 'Thread count', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'int',
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

		// Check that this is a conversation created event
		if ( ! $this->get_item_helpers()->is_webhook_request_matches_event( $headers, 'convo.created' ) ) {
			return false;
		}

		// Check mailbox match
		$mailbox_id = $trigger['meta']['MAILBOX'];

		// Allow "Any mailbox" (-1)
		$mailbox_matches = ( intval( -1 ) === intval( $mailbox_id ) || absint( $params['mailboxId'] ) === absint( $mailbox_id ) );

		return $mailbox_matches;
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

		$customer_name = '';
		if ( isset( $params['primaryCustomer'] ) && is_array( $params['primaryCustomer'] ) ) {
			if ( isset( $params['primaryCustomer']['first'], $params['primaryCustomer']['last'] ) ) {
				$customer_name = implode( ' ', array( $params['primaryCustomer']['first'], $params['primaryCustomer']['last'] ) );
			}
			
			if ( empty( trim( $customer_name ) ) && isset( $params['primaryCustomer']['email'] ) ) {
				$customer_name = $params['primaryCustomer']['email'];
			}
		}

		// Get assignee information from root level (who the conversation is assigned to)
		$assign_to = '';
		if ( isset( $params['assignee'] ) && is_array( $params['assignee'] ) ) {
			$assignee = $params['assignee'];
			
			if ( isset( $assignee['first'], $assignee['last'], $assignee['email'], $assignee['id'] ) ) {
				$assign_to = implode( ' ', array( $assignee['first'], $assignee['last'] ) ) . ' (' . $assignee['email'] . ')';
				
				if ( 1 === $assignee['id'] ) {
					$assign_to = 'Anyone'; // Helpscout `anyone` assignee has id value of 1.
				}
			}
		}

		$conversation_created = '';

		if ( isset( $params['createdAt'] ) ) {
			$conversation_created = $this->get_item_helpers()->format_date_timestamp( strtotime( $params['createdAt'] ) );
		}

		$customer_waiting_since = '';

		if ( isset( $params['customerWaitingSince']['time'] ) ) {
			$customer_waiting_since = $this->get_item_helpers()->format_date_timestamp( strtotime( $params['customerWaitingSince']['time'] ) );
		}

		// Get first message from conversation threads (the initial customer message)
		$first_message = '';
		$thread_count = 0;
		if ( isset( $params['_embedded']['threads'] ) && is_array( $params['_embedded']['threads'] ) ) {
			$thread_count = count( $params['_embedded']['threads'] );
			if ( ! empty( $params['_embedded']['threads'] ) ) {
				$first_thread = $params['_embedded']['threads'][0];
				if ( isset( $first_thread['body'] ) ) {
					$first_message = $first_thread['body'];
				}
			}
		}

		// Smart conversation URL - use web href if available, otherwise construct it
		$conversation_url = 'https://secure.helpscout.net/conversation/' . $params['id'];
		if ( isset( $params['_links']['web']['href'] ) && ! empty( $params['_links']['web']['href'] ) ) {
			$conversation_url = $params['_links']['web']['href'];
		} elseif ( isset( $params['number'] ) ) {
			$conversation_url = 'https://secure.helpscout.net/conversation/' . $params['id'] . '/' . $params['number'];
		}

		return array(
			'MAILBOX'                 => $params['mailboxId'], // Parsing auto-generated relevant token.
			'number'                  => isset( $params['number'] ) ? $params['number'] : '',
			'conversation_id'         => isset( $params['id'] ) ? $params['id'] : '',
			'folderId'                => isset( $params['folderId'] ) ? $params['folderId'] : '',
			'mailbox_id'              => isset( $params['mailboxId'] ) ? $params['mailboxId'] : '',
			'conversation_url'        => $conversation_url,
			'conversation_title'      => isset( $params['subject'] ) ? $params['subject'] : esc_html_x( 'No subject', 'Helpscout', 'uncanny-automator' ),
			'conversation_status'     => isset( $params['status'] ) ? $params['status'] : esc_html_x( 'No status', 'Helpscout', 'uncanny-automator' ),
			'conversation_created'    => $conversation_created,
			'customer_name'           => $customer_name,
			'customer_email'          => isset( $params['primaryCustomer']['email'] ) ? $params['primaryCustomer']['email'] : '',
			'customer_waiting_since'  => $customer_waiting_since,
			'assigned_to'             => $assign_to,
			'tags'                    => isset( $params['tags'] ) && is_array( $params['tags'] ) ? implode( ', ', array_column( $params['tags'], 'tag' ) ) : '',
			'message'                 => $first_message,
			'thread_count'            => $thread_count,
		);
	}
}
