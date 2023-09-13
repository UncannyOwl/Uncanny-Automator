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
		$values    = (array) automator_filter_input_array( 'values', INPUT_POST );
		$action_id = (int) automator_filter_input( 'item_id', INPUT_POST );
		// This is for v1.0 of send webhook action in Pro
		if ( isset( $values['WEBHOOKURL'] ) ) {
			$this->call_webhook( $values, true, $action_id );

			return;
		}
		// Current send webhook method
		$this->call_webhook( $values, false, $action_id );
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
	private function call_webhook( $data, $legacy = false, $action_id = null ) {

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

		$status_code = absint( wp_remote_retrieve_response_code( $response ) );

		$response_headers = wp_remote_retrieve_headers( $response );
		$response_body    = wp_remote_retrieve_body( $response );
		$header_leafs     = Automator_Send_Webhook::parse_headers( $response_headers );

		// Parse incoming response.
		$response_leafs = Automator_Send_Webhook::get_leafs( json_decode( $response_body, true ) );

		$all_tokens = array_merge( $header_leafs, $response_leafs );

		// Save response to build action tokens.
		update_post_meta( $action_id, 'webhook_response_tokens', wp_json_encode( $all_tokens ) );

		/* translators: Webhook response */
		$message = '<strong>' . sprintf( esc_attr__( 'Data successfully sent to: %s', 'uncanny-automator' ), $webhook_url ) . '</strong><br/>';

		$response_headers_data = $response_headers instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary ? $response_headers->getAll() : $response_headers;

		// Format the response in a nice format.
		$response = sprintf(
			/* translators: Webhook response format */
			esc_attr__( '%1$sHeaders%2$s %3$s %5$s %1$sBody%2$s %4$s', 'uncanny-automator' ),
			'<br/><strong>',
			'</strong><br/>',
			wp_strip_all_tags( wp_json_encode( (array) $response_headers_data ) ),
			$response_body,
			'<br />'
		);

		$type = 'success';

		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			$type = 'error';
			/* translators: Webhook response */
			$message = '<strong>' . sprintf( esc_attr__( 'Server has responded with error code: %1$s %2$s', 'uncanny-automator' ), $status_code, $webhook_url ) . '</strong><br/>';
		}

		wp_send_json(
			array(
				'type'    => $type,
				'message' => $message . $response,
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

		$stripped_fields = is_string( $fields ) ? stripcslashes( $fields ) : '';

		wp_send_json(
			array(
				'type'    => 'gray',
				'message' => $stripped_fields,
			),
			200,
			JSON_PRETTY_PRINT
		);
	}
}
