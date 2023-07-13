<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class HS_NOTE_ADDED
 *
 * @package Uncanny_Automator
 */
class HS_NOTE_ADDED {

	use Recipe\Triggers;

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
		$this->set_action_hook( 'automator_helpscout_webhook_received' );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 2 );

		$this->set_uses_api( true );

		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html__( 'A note is added to {{a conversation:%1$s}}', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( 'A note is added to {{a conversation}}', 'uncanny-automator' )
		);

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_tokens(
			array(
				'conversation_url'    => array( 'name' => esc_html__( 'Conversation URL', 'uncanny-automator' ) ),
				'conversation_title'  => array( 'name' => esc_html__( 'Conversation title', 'uncanny-automator' ) ),
				'customer_name'       => array( 'name' => esc_html__( 'Customer name', 'uncanny-automator' ) ),
				'customer_email'      => array( 'name' => esc_html__( 'Customer email', 'uncanny-automator' ) ),
				'conversation_status' => array( 'name' => esc_html__( 'Conversation status', 'uncanny-automator' ) ),
				'note'                => array( 'name' => esc_html__( 'Note', 'uncanny-automator' ) ),
				'note_added_by'       => array( 'name' => esc_html__( 'Note added by', 'uncanny-automator' ) ),
				'note_assigned_to'    => array( 'name' => esc_html__( 'Note assigned to', 'uncanny-automator' ) ),
			)
		);

		// Register the trigger.
		$this->register_trigger();

	}

	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_trigger_meta() => array(
						array(
							'option_code'           => 'MAILBOX',
							'label'                 => esc_attr__( 'Mailbox', 'uncanny-automator' ),
							'input_type'            => 'select',
							'options'               => $this->get_helper()->fetch_mailboxes(),
							'supports_custom_value' => true,
							'required'              => true,
							'is_ajax'               => true,
							'endpoint'              => 'helpscout_fetch_conversations',
							'fill_values_in'        => $this->get_trigger_meta(),
						),
						array(
							'option_code'           => $this->get_trigger_meta(),
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
	 * Validate the trigger.
	 *
	 * @return boolean True.
	 */
	public function validate_trigger( ...$args ) {

		if ( empty( $args[0][1] ) ) {
			return false;
		}

		return $this->get_helper()->is_webhook_request_matches_event( $args[0][1], 'convo.note.created' );

	}

	/**
	 * Prepare to run.
	 *
	 * Sets the conditional trigger to true.
	 *
	 * @return void.
	 */
	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}

	/**
	 * Validates the condition and returns the matching helpers and triggers.
	 *
	 * @param array ...$args The arguments from Automator Trigger, and etc.
	 *
	 * @return array The matching recipe and triggers.
	 */
	public function validate_conditions( ...$args ) {

		$params = array_shift( $args[0] );

		$matching_recipes_triggers = $this->find_all( $this->trigger_recipes() )
			->where( array( $this->get_trigger_meta(), 'MAILBOX' ) )
			->match( array( absint( $params['id'] ), absint( $params['mailboxId'] ) ) )
			->format( array( 'intval' ) )
			->get();

		return $matching_recipes_triggers;

	}

	/**
	 * Continue trigger process even for logged-in user.
	 *
	 * @return boolean True.
	 */
	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

	/**
	 * Method parse_additional_tokens.
	 *
	 * @param $parsed
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function parse_additional_tokens( $parsed, $args, $trigger ) {

		$params = $args['trigger_args'][0];

		$note = $params['_embedded']['threads'][0]; // This trigger should fire with the recent note added.

		$customer_name = $this->extract_note_user_display_name( $params['primaryCustomer'] );
		$note_user     = $this->extract_note_user_display_name( $note['createdBy'] );
		$note_assignee = $this->extract_note_user_display_name( $note['assignedTo'] );

		$hydrated_tokens = array(
			$this->get_trigger_meta() => $params['id'], // Parsing auto-generated relevant token.
			'MAILBOX'                 => $params['mailboxId'], // Parsing auto-generated relevant token.
			'customer_name'           => ! empty( $customer_name ) ? $customer_name : $params['customer']['email'],
			'customer_email'          => $params['primaryCustomer']['email'],
			'conversation_url'        => 'https://secure.helpscout.net/conversation/' . $params['id'],
			'conversation_title'      => $params['subject'],
			'conversation_status'     => $params['status'],
			'note'                    => $note['body'],
			'note_added_by'           => $note_user,
			'note_assigned_to'        => $note_assignee,
		);

		return $parsed + $hydrated_tokens;

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
