<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class TELEGRAM_MESSAGE_RECEIVED
 *
 * @package Uncanny_Automator
 */
class TELEGRAM_MESSAGE_RECEIVED {

	use Recipe\Triggers;

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'MESSAGE_RECEIVED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'TELEGRAM_MESSAGE_RECEIVED';

	protected $functions;

	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void.
	 */
	public function setup_trigger() {

		$this->functions = new Telegram_Functions();

		$this->set_integration( 'TELEGRAM' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		// The action hook to attach this trigger into.
		$this->add_action( Telegram_Webhook::INCOMING_WEBHOOK_ACTION );

		$this->set_uses_api( true );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 1 );

		/* Translators: Trigger sentence */
		$this->set_sentence( sprintf( esc_html__( 'A text message is received', 'uncanny-automator' ) ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( 'A text message is received', 'uncanny-automator' ) );

		$this->set_tokens(
			array(
				'CHAT_ID'    => array(
					'name' => __( 'Chat ID', 'uncanny-automator' ),
				),
				'FIRST_NAME' => array(
					'name' => __( 'First name', 'uncanny-automator' ),
				),
				'LAST_NAME'  => array(
					'name' => __( 'Last name', 'uncanny-automator' ),
				),
				'USERNAME'   => array(
					'name' => __( 'Username', 'uncanny-automator' ),
				),
				'CHAT_TYPE'  => array(
					'name' => __( 'Chat type', 'uncanny-automator' ),
				),
				'CHAT_TITLE' => array(
					'name' => __( 'Chat title', 'uncanny-automator' ),
				),
				'DATE'       => array(
					'name' => __( 'Date', 'uncanny-automator' ),
				),
				'TEXT'       => array(
					'name' => __( 'Text', 'uncanny-automator' ),
				),

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

		$hook_args    = array_shift( $args );
		$request      = array_shift( $hook_args );
		$request_body = $request->get_json_params();

		if ( empty( $request_body['message']['text'] ) && empty( $request_body['channel_post']['text'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Prepare to run.
	 *
	 * Sets the conditional trigger to true.
	 *
	 * @param $args
	 *
	 * @return void.
	 */
	public function prepare_to_run( $args ) {
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

		$request = array_shift( $args['trigger_args'] );

		$request_body = $request->get_json_params();

		$message = array();

		if ( isset( $request_body['message'] ) ) {
			$message = $request_body['message'];
		} elseif ( isset( $request_body['channel_post'] ) ) {
			$message = $request_body['channel_post'];
		}

		if ( empty( $message ) ) {
			return $parsed;
		}

		$output = array();

		if ( ! isset( $message['text'] ) ) {
			return $parsed;
		}

		$output['DATE'] = $message['date'];
		$output['TEXT'] = isset( $message['text'] ) ? $message['text'] : '';

		if ( isset( $message['chat'] ) ) {
			$chat                 = $message['chat'];
			$output['CHAT_ID']    = $chat['id'];
			$output['CHAT_TITLE'] = isset( $chat['title'] ) ? $chat['title'] : '';
			$output['CHAT_TYPE']  = isset( $chat['type'] ) ? $chat['type'] : '';
		}

		if ( isset( $message['from'] ) ) {
			$from                 = $message['from'];
			$output['USERNAME']   = isset( $from['username'] ) ? $from['username'] : '';
			$output['FIRST_NAME'] = isset( $from['first_name'] ) ? $from['first_name'] : '';
			$output['LAST_NAME']  = isset( $from['last_name'] ) ? $from['last_name'] : '';
		}

		return $output;
	}
}
