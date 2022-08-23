<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Actions;

/**
 * Class WHATSAPP_SEND_MESSAGE
 *
 * @package Uncanny_Automator
 */
class WHATSAPP_SEND_MESSAGE {

	use Actions;

	/**
	 * The prefix for the action fields.
	 *
	 * @var string
	 */
	const PREFIX = 'WHATSAPP_SEND_MESSAGE';

	public function __construct() {

		add_filter( 'automator_get_action_completed_status', array( $this, 'set_completed_status' ), 10, 7 );

		add_filter( 'automator_get_action_error_message', array( $this, 'set_error_message' ), 10, 7 );

		add_filter( 'automator_pro_get_action_completed_labels', array( $this, 'set_action_completed_label' ), 10, 1 );

		add_action( 'automator_action_created', array( $this, 'action_meta_persist_wamid_data' ), 10, 1 );

		add_action( 'automator_whatsapp_webhook_noresponse_closure', array( $this, 'noresponse_closure' ), 10, 3 );

		$this->setup_action();

	}

	/**
	 * Method noresponse_closure.
	 *
	 * This method will complete WhatsApp action if in case no response from the Webhook was received.
	 *
	 * @return void.
	 */
	public function noresponse_closure( $response ) {

		if ( ! empty( $response['data']['messages'][0]['id'] ) ) {

			$helper = Automator()->helpers->recipe->whatsapp->options;

			$action_data = $helper->get_action_data_by_wamid( $response['data']['messages'][0]['id'] );

			$error_message = esc_html__( 'No response was received from Meta after 1 minute. Make sure you have set-up your webhook configuration correctly.' );

			$recipe_error_message = Automator()->db->action->get_error_message( $action_data['recipe_log_id'] );

			if ( ! empty( $recipe_error_message ) && 10 === intval( $recipe_error_message->completed ) ) {

				Automator()->db->action->mark_complete( $action_data['action_id'], $action_data['recipe_log_id'], 1, $error_message );

				Automator()->db->recipe->mark_complete( $action_data['recipe_log_id'], 1 );

			}
		}

	}

	/**
	 * Persist the WAMID after the action creation.
	 *
	 * @param array $entry
	 *
	 * @return void
	 */
	public function action_meta_persist_wamid_data( $action_arguments = array() ) {

		// Check if action has `await` argument.
		if ( empty( $action_arguments['args']['await'] ) ) {
			return;
		}

		// Add `whatsapp_meta` to {uap_action_log_meta}.
		Automator()->db->action->add_meta(
			$action_arguments['user_id'],
			$action_arguments['action_log_id'],
			$action_arguments['action_id'],
			'whatsapp_meta',
			wp_json_encode( $action_arguments['args'] )
		);

		// Add `whatsapp_wamid` to {uap_action_log_meta}.
		Automator()->db->action->add_meta(
			$action_arguments['user_id'],
			$action_arguments['action_log_id'],
			$action_arguments['action_id'],
			'whatsapp_wamid',
			$action_arguments['args']['await']['whatsapp_response']['data']['messages'][0]['id']
		);

	}

	/**
	 * Method set_action_completed_label.
	 *
	 * Callback method to action `automator_pro_get_action_completed_labels`.
	 *
	 * Creates new label called `Completed, pending response`.
	 *
	 * @param array $labels The accepted labels.
	 *
	 * @return array $labels The list of labels.
	 */
	public function set_action_completed_label( $labels = array() ) {

		$labels[10] = __( 'Completed, pending response', 'uncanny-automator' );

		return $labels;

	}

	/**
	 * Method set_error_message.
	 *
	 * Callback method to action `automator_get_action_error_message`.
	 *
	 * Sets the error message after the action is executed,
	 *
	 * @param string $message
	 * @param integer $user_id
	 * @param array $action_data
	 * @param string $error_message
	 * @param integer $recipe_log_id
	 * @param array $args
	 */
	public function set_error_message( $message, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {

		// Only filter this action
		if ( 'WHATSAPP_SEND_MESSAGE_CODE' !== $action_data['meta']['code'] ) {

			return $message;

		}

		if ( key_exists( 'await', $args ) ) {

			// Completed is stored as tiny int. Maybe update it to enum?
			$message = __( 'Message sent. Waiting for response. The status will be updated once the response is received.', 'uncanny-automatar' );

		}

		return $message;

	}

	/**
	 * Method set_completed_status
	 *
	 * Sets the completed status to integer 10.
	 *
	 * @return integer $completed The completed status code.
	 */
	public function set_completed_status( $completed, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {

		// Only filter this action
		if ( 'WHATSAPP_SEND_MESSAGE_CODE' !== $action_data['meta']['code'] ) {
			return $completed;
		}

		if ( key_exists( 'await', $args ) ) {
			// Completed is stored as tiny int. Maybe update it to enum?
			$completed = 10;
		}

		return $completed;

	}

	protected function setup_action() {

		$this->set_integration( 'WHATSAPP' );

		$this->set_action_code( self::PREFIX . '_CODE' );

		$this->set_action_meta( self::PREFIX . '_META' );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/whatsapp/' ) );

		$this->set_is_pro( false );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Send a WhatsApp message to {{a number:%1$s}}', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Send a WhatsApp message to {{a number}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->register_action();

	}

	public function load_options() {

		$options = array(
			'options_group' => array(
				$this->get_action_meta() => array(
					array(
						'option_code'           => $this->get_action_meta(),
						'label'                 => esc_attr__( 'To', 'uncanny-automator' ),
						'description'           => esc_attr__( 'The recipient must have opted-in to receive text messages from your number.', 'uncanny-automator' ),
						'input_type'            => 'text',
						'placeholder'           => esc_attr__( '+1 123 345 6789', 'uncanny-automator' ),
						'required'              => true,
						'supports_token'        => true,
						'supports_custom_value' => true,
					),
					array(
						'option_code'           => $this->get_action_meta() . '_body',
						'label'                 => esc_attr__( 'Body', 'uncanny-automator' ),
						'input_type'            => 'textarea',
						'required'              => true,
						'supports_token'        => true,
						'supports_custom_value' => true,
					),
				),
			),
		);

		$options = Automator()->utilities->keep_order_of_options( $options );

		return $options;
	}

	/**
	 * Get formatted code.
	 *
	 * @param  string $option_code The option code.
	 *
	 * @return string The prefix underscore option code string.
	 */
	protected function get_formatted_code( $option_code = '' ) {

		return sprintf( '%1$s_%2$s', self::PREFIX, $option_code );

	}


	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helper = Automator()->helpers->recipe->whatsapp->options;

		$to = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : null;

		$message = isset( $parsed[ $this->get_action_meta() . '_body' ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() . '_body' ] ) : null;

		try {

			$body = array(
				'action'       => 'send_message',
				'to'           => $to,
				'message'      => $message,
				'phone_id'     => $helper->get_phone_number_id(),
				'access_token' => $helper->get_access_token(),
			);

			$response = $helper->api_call( $body, $action_data );

			$action_data['args']['await'] = array(
				'whatsapp_response' => $response,
			);

			wp_schedule_single_event( time() + 60, 'automator_whatsapp_webhook_noresponse_closure', array( $response ) );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

			return;

		}

	}

}
