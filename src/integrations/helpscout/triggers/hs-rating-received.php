<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator\Integrations\Helpscout;

/**
 * Class Hs_Rating_Received
 *
 * @package Uncanny_Automator
 * @property Helpscout_App_Helpers $helpers
 * @property Helpscout_Api_Caller $api
 * @property Helpscout_Webhooks $webhooks
 */
class Hs_Rating_Received extends \Uncanny_Automator\Recipe\App_Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'HS_RATING_RECEIVED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'HS_RATING_RECEIVED_META';

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
				/* translators: %1$s: Rating */
				esc_html_x( 'A conversation receives {{a specific rating:%1$s}}', 'Help Scout', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A conversation receives {{a specific rating}}', 'Help Scout', 'uncanny-automator' )
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
				'option_code'           => self::TRIGGER_META,
				'label'                 => esc_html_x( 'Rating', 'Help Scout', 'uncanny-automator' ),
				'token_name'            => esc_html_x( 'Selected rating', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => array(
					array(
						'text'  => esc_html_x( 'Any rating', 'Helpscout', 'uncanny-automator' ),
						'value' => '-1',
					),
					array(
						'text'  => esc_html_x( 'Great', 'Helpscout', 'uncanny-automator' ),
						'value' => 'great',
					),
					array(
						'text'  => esc_html_x( 'Okay', 'Helpscout', 'uncanny-automator' ),
						'value' => 'okay',
					),
					array(
						'text'  => esc_html_x( 'Not Good', 'Helpscout', 'uncanny-automator' ),
						'value' => 'not_good',
					),
				),
				'supports_custom_value' => false,
				'required'              => true,
				'options_show_id'       => false,
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
				'tokenId'   => 'conversation_id',
				'tokenName' => esc_html_x( 'Conversation ID', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'conversation_url',
				'tokenName' => esc_html_x( 'Conversation URL', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'date_created',
				'tokenName' => esc_html_x( 'Date created', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'date_modified',
				'tokenName' => esc_html_x( 'Date modified', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'user_id',
				'tokenName' => esc_html_x( 'User ID', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'user_email',
				'tokenName' => esc_html_x( 'User email', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'user_firstName',
				'tokenName' => esc_html_x( 'User first name', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'user_lastName',
				'tokenName' => esc_html_x( 'User last name', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'mailbox_id',
				'tokenName' => esc_html_x( 'Mailbox ID', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'rating_label',
				'tokenName' => esc_html_x( 'Rating label', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'rating_comments',
				'tokenName' => esc_html_x( 'Rating comments', 'Help Scout', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'thread_id',
				'tokenName' => esc_html_x( 'Thread ID', 'Help Scout', 'uncanny-automator' ),
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

		// Check that this is a satisfaction rating event
		if ( ! $this->webhooks->is_webhook_request_matches_event( $headers, 'satisfaction.ratings' ) ) {
			return false;
		}

		// Check rating matches
		$selected_rating = $trigger['meta'][ self::TRIGGER_META ];

		// Allow "Any rating" (-1)
		if ( intval( '-1' ) === intval( $selected_rating ) ) {
			return true;
		}

		return isset( $params['rating'] ) && $selected_rating === $params['rating'];
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

		$customer_name = implode( ' ', array( $params['customer']['firstName'], $params['customer']['lastName'] ) );

		// Smart conversation URL - use web href if available, otherwise construct it
		$conversation_url = 'https://secure.helpscout.net/conversation/' . $params['conversationId'];
		if ( isset( $params['_links']['web']['href'] ) && ! empty( $params['_links']['web']['href'] ) ) {
			$conversation_url = $params['_links']['web']['href'];
		}

		return array(
			'customer_name'    => ! empty( $customer_name ) ? $customer_name : $params['customer']['email'],
			'customer_email'   => $params['customer']['email'],
			'conversation_id'  => $params['conversationId'],
			'conversation_url' => $conversation_url,
			'date_created'     => $this->helpers->format_date_timestamp( strtotime( $params['createdAt'] ) ),
			'date_modified'    => $this->helpers->format_date_timestamp( strtotime( $params['modifiedAt'] ) ),
			'user_id'          => $params['user']['id'],
			'user_email'       => $params['user']['email'],
			'user_firstName'   => $params['user']['firstName'],
			'user_lastName'    => $params['user']['lastName'],
			'mailbox_id'       => $params['mailboxId'],
			'rating_label'     => $params['rating'],
			'rating_comments'  => $params['comments'],
			'thread_id'        => $params['threadId'],
		);
	}
}
