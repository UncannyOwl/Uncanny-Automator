<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class HS_CONVERSATION_TAG_UPDATED
 *
 * @package Uncanny_Automator
 */
class HS_CONVERSATION_TAG_UPDATED {

	use Recipe\Triggers;

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

	public function __construct() {

		if ( automator_get_option( 'uap_helpscout_enable_webhook', false ) ) {

			$this->set_helper( new Helpscout_Helpers( false ) );

			$this->setup_trigger();

		}

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void.
	 */
	public function setup_trigger() {

		$this->set_integration( 'HELPSCOUT' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_pro( false );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		// The action hook to attach this trigger into.
		$this->add_action( 'automator_helpscout_webhook_received' );

		$this->set_uses_api( true );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 2 );

		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html__( "A conversation's tags are updated", 'uncanny-automator' )
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( "A conversation's tags are updated", 'uncanny-automator' )
		);

		$this->set_tokens(
			array(
				'assigned_to'            => array( 'name' => esc_html__( 'Assigned to', 'uncanny-automator' ) ),
				'conversation_url'       => array( 'name' => esc_html__( 'Conversation URL', 'uncanny-automator' ) ),
				'conversation_created'   => array( 'name' => esc_html__( 'Conversation created on', 'uncanny-automator' ) ),
				'conversation_status'    => array( 'name' => esc_html__( 'Conversation status', 'uncanny-automator' ) ),
				'conversation_title'     => array( 'name' => esc_html__( 'Conversation title', 'uncanny-automator' ) ),
				'customer_email'         => array( 'name' => esc_html__( 'Customer email', 'uncanny-automator' ) ),
				'customer_name'          => array( 'name' => esc_html__( 'Customer name', 'uncanny-automator' ) ),
				'customer_waiting_since' => array( 'name' => esc_html__( 'Customer waiting since', 'uncanny-automator' ) ),
				'folder_id'              => array( 'name' => esc_html__( 'Folder ID', 'uncanny-automator' ) ),
				'mailbox_id'             => array( 'name' => esc_html__( 'Mailbox ID', 'uncanny-automator' ) ),
				'tags'                   => array( 'name' => esc_html__( 'Tags', 'uncanny-automator' ) ),
			)
		);

		// Register the trigger.
		$this->register_trigger();

	}

	/**
	 * Validate the trigger.
	 *
	 * @return boolean True.
	 */
	public function validate_trigger( ...$args ) {

		if ( empty( $args[0][1] ) ) {
			return false;
		}

		return $this->get_helper()->is_webhook_request_matches_event( $args[0][1], 'convo.tags' );

	}

	/**
	 * Prepare to run.
	 *
	 * Sets the conditional trigger to true.
	 *
	 * @return void.
	 */
	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( false );

	}

	/**
	 * Continue trigger process even for logged-in user.
	 *
	 * @return boolean True.
	 */
	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

	public function parse_additional_tokens( $parsed, $args, $trigger ) {

		$params = $args['trigger_args'][0];

		$customer_name = implode( ' ', array( $params['primaryCustomer']['first'], $params['primaryCustomer']['last'] ) );

		if ( empty( trim( $customer_name ) ) ) {
			$customer_name = $params['primaryCustomer']['email'];
		}

		$threads = $params['_embedded']['threads'];

		$assignees = array_column( $threads, 'assignedTo' );

		$assignee = $assignees[0]; // Helpscout index zero is the recent assignee.

		$assign_to = implode( ' ', array( $assignee['first'], $assignee['last'] ) ) . ' (' . $assignee['email'] . ')';

		if ( 1 === $assignee['id'] ) {
			$assign_to = 'Anyone'; // Helpscout `anyone` assignee has id value of 1.
		}

		$hydrated_tokens = array(
			'assigned_to'            => $assign_to,
			'conversation_url'       => 'https://secure.helpscout.net/conversation/' . $params['conversationId'],
			'conversation_created'   => $this->get_helper()->format_date_timestamp( strtotime( $params['createdAt'] ) ),
			'conversation_status'    => $params['status'],
			'conversation_title'     => $params['subject'],
			'customer_email'         => $params['primaryCustomer']['email'],
			'customer_name'          => $customer_name,
			'customer_waiting_since' => $this->get_helper()->format_date_timestamp( strtotime( $params['customerWaitingSince']['time'] ) ),
			'folder_id'              => $params['folderId'],
			'mailbox_id'             => $params['mailboxId'],
			'tags'                   => implode( ', ', array_column( $params['tags'], 'tag' ) ),
		);

		return $parsed + $hydrated_tokens;
	}

}
