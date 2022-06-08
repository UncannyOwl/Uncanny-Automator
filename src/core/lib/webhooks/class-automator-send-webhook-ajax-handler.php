<?php

namespace Uncanny_Automator;

use WP_Error;

/**
 * Send_Webhook_Ajax_Handler
 */
class Automator_Send_Webhook_Ajax_Handler {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_Send_Webhook_Ajax_Handler
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Send_Webhook_Ajax_Handler constructor
	 */
	public function __construct() {
		// Send sample data ajax catch
		add_action( 'wp_ajax_nopriv_automator_webhook_send_test_data', array( $this, 'webhook_send_test_data' ) );
		add_action( 'wp_ajax_automator_webhook_send_test_data', array( $this, 'webhook_send_test_data' ) );

		// Return field data
		add_action( 'wp_ajax_nopriv_automator_webhook_build_test_data', array( $this, 'webhook_build_test_data' ) );
		add_action( 'wp_ajax_automator_webhook_build_test_data', array( $this, 'webhook_build_test_data' ) );
	}

	/**
	 * Send test data to the selected webhook URL
	 *
	 * @return void
	 */
	public function webhook_send_test_data() {
		Automator()->utilities->ajax_auth_check();
		$values = (array) automator_filter_input_array( 'values', INPUT_POST );
		// This is for v1.0 of send webhook action in Pro
		if ( isset( $values['WEBHOOKURL'] ) ) {
			$this->call_webhook( $values, true );

			return;
		}
		// Current send webhook method
		$this->call_webhook( $values, false );
	}

	/**
	 * Send data to Webhook
	 *
	 * @param $data
	 * @param bool $legacy
	 * @param bool $is_sample
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function call_webhook( $data, $legacy = false ) {

		$data_type    = Automator()->send_webhook->get_data_type( $data );
		$headers      = Automator()->send_webhook->get_headers( $data );
		$webhook_url  = Automator()->send_webhook->get_url( $data, $legacy );
		$fields       = Automator()->send_webhook->get_fields( $data, $legacy, $data_type, array() );
		$request_type = Automator()->send_webhook->request_type( $data );
		$headers      = Automator()->send_webhook->get_content_type( $data_type, $headers );

		if ( empty( $webhook_url ) ) {
			wp_send_json(
				array(
					'type'    => 'error',
					'message' => esc_attr__( 'Please enter valid fields.', 'uncanny-automator' ),
				)
			);
		}
		if ( empty( $fields ) ) {
			wp_send_json(
				array(
					'type'    => 'error',
					'message' => esc_attr__( 'Please enter data in fields.', 'uncanny-automator' ),
				)
			);
		}

		$args = array(
			'method'  => $request_type,
			'body'    => $fields,
			'timeout' => '30',
		);

		if ( ! empty( $headers ) ) {
			$args['headers'] = $headers;
		}

		$response = Automator_Send_Webhook::call_webhook( $webhook_url, $args, $request_type );
		if ( $response instanceof WP_Error ) {
			/* translators: 1. Webhook URL */
			$error_message = esc_attr__( 'There was an issue sending data to:', 'uncanny-automator' );
			$error_message .= sprintf( ' %s', $webhook_url );
			$error_message .= '<h5>' . esc_attr__( 'Response:', 'uncanny-automator' ) . '</h5>';
			$error_message .= sprintf( '%s', join( '- <br />', $response->get_error_messages() ) );
			wp_send_json(
				array(
					'type'    => 'error',
					'message' => $error_message,
				)
			);
		}
		/* translators: 1. Webhook URL */
		//      $body            = wp_remote_retrieve_body( $response );
		//      $msg             = wp_remote_retrieve_response_message( $response );
		//      $type            = wp_remote_retrieve_header( $response, 'content-type' );
		$success_message = esc_attr__( 'Data successfully sent to:', 'uncanny-automator' );
		$success_message .= sprintf( ' %s', $webhook_url );
		//      $success_message .= '<h5>' . esc_attr__( 'Response:', 'uncanny-automator' ) . '</h5>';
		//      $success_message .= "<strong>Message:</strong> $msg";
		//      $success_message .= "<br /><strong>Contet-Type:</strong> $type";
		//      $success_message .= "<br /><strong>Body:</strong><pre>$body</pre>";

		wp_send_json(
			array(
				'type'    => 'success',
				'message' => $success_message,
			)
		);
	}

	/**
	 * Build and return sample data
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function webhook_build_test_data() {
		Automator()->utilities->ajax_auth_check();
		$data      = (array) automator_filter_input_array( 'values', INPUT_POST );
		$data_type = Automator()->send_webhook->get_data_type( $data );
		$fields    = Automator()->send_webhook->get_fields( $data, false, $data_type, array(), true );

		wp_send_json(
			array(
				'type'    => 'gray',
				'message' => wp_unslash( $fields ),
			),
			200,
			JSON_PRETTY_PRINT
		);
	}
}
