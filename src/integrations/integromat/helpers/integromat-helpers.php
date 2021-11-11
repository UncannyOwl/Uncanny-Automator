<?php


namespace Uncanny_Automator;

use WP_Error;
use Uncanny_Automator_Pro\Integromat_Pro_Helpers;

/**
 * Class Integromat_Helpers
 * @package Uncanny_Automator
 */
class Integromat_Helpers {

	/**
	 * @var Integromat_Helpers
	 */
	public $options;
	/**
	 * @var Integromat_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Integromat_Pro_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action( 'wp_ajax_nopriv_sendtest_integ_webhook', array( $this, 'sendtest_webhook' ) );
		add_action( 'wp_ajax_sendtest_integ_webhook', array( $this, 'sendtest_webhook' ) );
	}

	/**
	 * @param Integromat_Helpers $options
	 */
	public function setOptions( Integromat_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 * @param Integromat_Pro_Helpers $pro
	 */
	public function setPro( Integromat_Pro_Helpers $pro ) { // phpcs:ignore
		$this->pro = $pro;
	}


	/**
	 * @param $_POST
	 */
	public function sendtest_webhook() {

		Automator()->utilities->ajax_auth_check();

		$key_values = array();
		$headers    = array();

		$flags = array(
			'filter' => 'FILTER_VALIDATE_STRING',
			'flags'  => FILTER_REQUIRE_ARRAY,
		);

		$values = automator_filter_input_array( 'values', INPUT_POST, $flags );

		$values = (array) Automator()->utilities->automator_sanitize( $values, 'mixed' );
		// Sanitizing webhook key pairs
		$pairs          = array();
		$webhook_fields = isset( $values['WEBHOOK_FIELDS'] ) ? $values['WEBHOOK_FIELDS'] : array();

		if ( ! empty( $webhook_fields ) ) {
			foreach ( $webhook_fields as $key_index => $pair ) {
				$pairs[] = array(
					'KEY'   => sanitize_text_field( $pair['KEY'] ),
					'VALUE' => sanitize_text_field( $pair['VALUE'] ),
				);
			}
		}

		$values['WEBHOOK_FIELDS'] = $pairs;
		$request_type             = 'POST';

		if ( isset( $values['WEBHOOKURL'] ) ) {
			$webhook_url = $values['WEBHOOKURL'];

			if ( empty( $webhook_url ) ) {
				wp_send_json(
					array(
						'type'    => 'error',
						'message' => esc_attr__( 'Please enter a valid webhook URL.', 'uncanny-automator' ),
					)
				);
			}

			for ( $i = 1; $i <= INTEGROMAT_SENDWEBHOOK::$number_of_keys; $i ++ ) {
				$key                = $values[ 'KEY' . $i ];
				$value              = $values[ 'VALUE' . $i ];
				$key_values[ $key ] = $value;
			}

			$fields_string = http_build_query( $key_values );

		} elseif ( isset( $values['WEBHOOK_URL'] ) ) {
			$webhook_url = $values['WEBHOOK_URL'];

			if ( empty( $webhook_url ) ) {
				wp_send_json(
					array(
						'type'    => 'error',
						'message' => esc_attr__( 'Please enter a valid webhook URL.', 'uncanny-automator' ),
					)
				);
			}

			if ( ! isset( $values['WEBHOOK_FIELDS'] ) || empty( $values['WEBHOOK_FIELDS'] ) ) {
				wp_send_json(
					array(
						'type'    => 'error',
						'message' => esc_attr__( 'Please enter valid fields.', 'uncanny-automator' ),
					)
				);
			}
			$fields = $values['WEBHOOK_FIELDS'];

			$field_count = count( $fields );

			for ( $i = 0; $i <= $field_count; $i ++ ) {
				$key                = $fields[ $i ]['KEY'];
				$value              = $fields[ $i ]['VALUE'];
				$key_values[ $key ] = $value;
			}

			$header_meta = isset( $values['WEBHOOK_HEADERS'] ) ? $values['WEBHOOK_HEADERS'] : array();
			if ( ! empty( $header_meta ) ) {
				$meta_count = count( $header_meta );
				for ( $i = 0; $i <= $meta_count; $i ++ ) {
					$key = isset( $header_meta[ $i ]['NAME'] ) ? sanitize_text_field( $header_meta[ $i ]['NAME'] ) : null;
					// remove colon if user added in NAME
					$key   = str_replace( ':', '', $key );
					$value = isset( $header_meta[ $i ]['VALUE'] ) ? sanitize_text_field( $header_meta[ $i ]['VALUE'] ) : null;
					if ( ! is_null( $key ) && ! is_null( $value ) ) {
						$headers[ $key ] = $value;
					}
				}
			}

			if ( 'POST' === (string) $values['ACTION_EVENT'] || 'CUSTOM' === (string) $values['ACTION_EVENT'] ) {
				$request_type = 'POST';
			} elseif ( 'GET' === (string) $values['ACTION_EVENT'] ) {
				$request_type = 'GET';
			} elseif ( 'PUT' === (string) $values['ACTION_EVENT'] ) {
				$request_type = 'PUT';
			}
		}

		if ( $key_values && ! is_null( $webhook_url ) ) {

			$args = array(
				'method'   => $request_type,
				'body'     => $key_values,
				'timeout'  => '30',
				'blocking' => false,
			);

			if ( ! empty( $headers ) ) {
				$args['headers'] = $headers;
			}

			$response = wp_remote_request( $webhook_url, $args );

			if ( $response instanceof WP_Error ) {
				/* translators: 1. Webhook URL */
				$error_message = sprintf( esc_attr__( 'An error was found in the webhook (%1$s) response.', 'uncanny-automator' ), $webhook_url );
				wp_send_json(
					array(
						'type'    => 'error',
						'message' => $error_message,
					)
				);
			}

			/* translators: 1. Webhook URL */
			$success_message = sprintf( esc_attr__( 'Successfully sent data on %1$s.', 'uncanny-automator' ), $webhook_url );

			wp_send_json(
				array(
					'type'    => 'success',
					'message' => $success_message,
				)
			);
		}
	}
}
