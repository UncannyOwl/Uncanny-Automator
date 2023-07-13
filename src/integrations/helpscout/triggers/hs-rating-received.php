<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class HS_RATING_RECEIVED
 *
 * @package Uncanny_Automator
 */
class HS_RATING_RECEIVED {

	use Recipe\Triggers;

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
				esc_html__( 'A conversation receives {{a specific rating:%1$s}}', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( 'A conversation receives {{a specific rating}}', 'uncanny-automator' )
		);

		$this->set_tokens(
			array(
				'customer_name'    => array( 'name' => esc_html__( 'Customer name', 'uncanny-automator' ) ),
				'customer_email'   => array( 'name' => esc_html__( 'Customer email', 'uncanny-automator' ) ),
				'conversation_id'  => array( 'name' => esc_html__( 'Conversation ID', 'uncanny-automator' ) ),
				'conversation_url' => array( 'name' => esc_html__( 'Conversation URL', 'uncanny-automator' ) ),
				'date_created'     => array( 'name' => esc_html__( 'Date created', 'uncanny-automator' ) ),
				'date_modified'    => array( 'name' => esc_html__( 'Date modified', 'uncanny-automator' ) ),
				'user_id'          => array( 'name' => esc_html__( 'User ID', 'uncanny-automator' ) ),
				'user_email'       => array( 'name' => esc_html__( 'User email', 'uncanny-automator' ) ),
				'user_firstName'   => array( 'name' => esc_html__( 'User first name', 'uncanny-automator' ) ),
				'user_lastName'    => array( 'name' => esc_html__( 'User last name', 'uncanny-automator' ) ),
				'mailbox_id'       => array( 'name' => esc_html__( 'Mailbox ID', 'uncanny-automator' ) ),
				'rating_label'     => array( 'name' => esc_html__( 'Rating label', 'uncanny-automator' ) ),
				'rating_comments'  => array( 'name' => esc_html__( 'Rating comments', 'uncanny-automator' ) ),
				'thread_id'        => array( 'name' => esc_html__( 'Thread ID', 'uncanny-automator' ) ),
			)
		);

		$this->set_options_callback( array( $this, 'load_options' ) );

		// Register the trigger.
		$this->register_trigger();

	}

	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_trigger_meta() => array(
						array(
							'option_code'           => $this->get_trigger_meta(),
							'label'                 => esc_attr__( 'Rating', 'uncanny-automator' ),
							'input_type'            => 'select',
							'options'               => array(
								'-1'       => esc_html__( 'Any rating', 'uncanny-automator' ),
								'great'    => esc_html__( 'Great', 'uncanny-automator' ),
								'okay'     => esc_html__( 'Okay', 'uncanny-automator' ),
								'not_good' => esc_html__( 'Not Good', 'uncanny-automator' ),
							),
							'supports_custom_value' => false,
							'required'              => true,
							'options_show_id'       => false,
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

		return $this->get_helper()->is_webhook_request_matches_event( $args[0][1], 'satisfaction.ratings' );

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

	public function validate_conditions( ...$args ) {

		$params = array_shift( $args[0] );

		$matching_recipes_triggers = $this->find_all( $this->trigger_recipes() )
			->where( array( $this->get_trigger_meta() ) )
			->match( array( $params['rating'] ) )
			->format( array( 'trim' ) )
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

		$customer_name = implode( ' ', array( $params['customer']['firstName'], $params['customer']['lastName'] ) );

		$hydrated_tokens = array(
			'customer_name'    => ! empty( $customer_name ) ? $customer_name : $params['customer']['email'],
			'customer_email'   => $params['customer']['email'],
			'conversation_id'  => $params['conversationId'],
			'conversation_url' => 'https://secure.helpscout.net/conversation/' . $params['conversationId'],
			'date_created'     => $this->get_helper()->format_date_timestamp( strtotime( $params['createdAt'] ) ),
			'date_modified'    => $this->get_helper()->format_date_timestamp( strtotime( $params['modifiedAt'] ) ),
			'user_id'          => $params['user']['id'],
			'user_email'       => $params['user']['email'],
			'user_firstName'   => $params['user']['firstName'],
			'user_lastName'    => $params['user']['lastName'],
			'mailbox_id'       => $params['mailboxId'],
			'rating_label'     => $params['rating'],
			'rating_comments'  => $params['comments'],
			'thread_id'        => $params['threadId'],
		);

		return $parsed + $hydrated_tokens;

	}

}
