<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class HS_CONVERSATION_CUSTOMER_REPLY_RECEIVED
 *
 * @package Uncanny_Automator
 */
class HS_CONVERSATION_CUSTOMER_REPLY_RECEIVED {

	use Recipe\Triggers;

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
				esc_html__( '{{A conversation:%1$s}} in {{a mailbox:%2$s}} receives a reply from a customer', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'MAILBOX:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( '{{A conversation}} in {{a mailbox}} receives a reply from a customer', 'uncanny-automator' )
		);

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_tokens(
			array(
				'conversation_url'    => array( 'name' => esc_html__( 'Conversation URL', 'uncanny-automator' ) ),
				'conversation_title'  => array( 'name' => esc_html__( 'Conversation title', 'uncanny-automator' ) ),
				'customer_name'       => array( 'name' => esc_html__( 'Customer name', 'uncanny-automator' ) ),
				'customer_email'      => array( 'name' => esc_html__( 'Customer email', 'uncanny-automator' ) ),
				'conversation_status' => array( 'name' => esc_html__( 'Conversation status', 'uncanny-automator' ) ),
				'tags'                => array( 'name' => esc_html__( 'Tag name', 'uncanny-automator' ) ),
				'tags_url_search'     => array( 'name' => esc_html__( 'Tag search query link', 'uncanny-automator' ) ),
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

		return $this->get_helper()->is_webhook_request_matches_event( $args[0][1], 'convo.customer.reply.created' );

	}

	public function validate_conditions( ...$args ) {

		$params = array_shift( $args[0] );

		$matching_recipes_triggers = $this->find_all( $this->trigger_recipes() )
			->where( array( $this->get_trigger_meta(), 'MAILBOX' ) )
			->match( array( absint( $params['id'] ), absint( $params['mailboxId'] ) ) )
			->format( array( 'intval', 'intval' ) )
			->get();

		return $matching_recipes_triggers;

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
	 * Continue trigger process even for logged-in user.
	 *
	 * @return boolean True.
	 */
	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

	public function parse_additional_tokens( $parsed, $args, $trigger ) {

		$params = array_shift( $args['trigger_args'] );

		$customer_name = implode( ' ', array( $params['primaryCustomer']['first'], $params['primaryCustomer']['last'] ) );

		if ( empty( $customer_name ) ) {
			$customer_name = $params['primaryCustomer']['email'];
		}

		$hydrated_tokens = array(
			// The $this->get_trigger_meta() refers to the Conversation.
			$this->get_trigger_meta() => $params['id'], // Parsing auto-generated relevant token.
			'MAILBOX'                 => $params['mailboxId'], // Parsing auto-generated relevant token.
			'conversation_url'        => 'https://secure.helpscout.net/conversation/' . $params['id'],
			'conversation_title'      => $params['subject'],
			'customer_name'           => $customer_name,
			'customer_email'          => $params['primaryCustomer']['email'],
			'conversation_status'     => $params['status'],
			'tags'                    => implode( ', ', array_column( $params['tags'], 'tag' ) ),
			'tags_url_search'         => add_query_arg(
				array(
					'query' => rawurlencode( 'tag:' . implode( ',', array_column( $params['tags'], 'tag' ) ) ),
				),
				'https://secure.helpscout.net/search'
			),
		);

		return $parsed + $hydrated_tokens;

	}

}
