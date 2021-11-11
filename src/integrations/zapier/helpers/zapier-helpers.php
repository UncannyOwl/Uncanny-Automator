<?php


namespace Uncanny_Automator;


use Uncanny_Automator_Pro\Zapier_Pro_Helpers;
use WP_Error;

/**
 * Class Zapier_Helpers
 * @package Uncanny_Automator
 */
class Zapier_Helpers {

	/**
	 * @var Zapier_Helpers
	 */
	public $options;
	/**
	 * @var Zapier_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Zapier_Pro_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action( 'wp_ajax_nopriv_sendtest_zp_webhook', array( $this, 'sendtest_webhook' ) );
		add_action( 'wp_ajax_sendtest_zp_webhook', array( $this, 'sendtest_webhook' ) );
	}

	/**
	 * @param Zapier_Helpers $options
	 */
	public function setOptions( Zapier_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Zapier_Pro_Helpers $pro
	 */
	public function setPro( Zapier_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}


	/**
	 * @param $_POST
	 */
	public function sendtest_webhook() {



		Automator()->utilities->ajax_auth_check();

		$key_values = array();
		$headers    = array();
		$values     = (array) Automator()->utilities->automator_sanitize( $_POST['values'], 'mixed' );
		// Sanitizing webhook key pairs
		$pairs          = array();
		$webhook_fields = isset( $_POST['values']['WEBHOOK_FIELDS'] ) ? $_POST['values']['WEBHOOK_FIELDS'] : array();
		if ( ! empty( $webhook_fields ) ) {
			foreach ( $webhook_fields as $key_index => $pair ) {
				$pairs[] = [
					'KEY'   => sanitize_text_field( $pair['KEY'] ),
					'VALUE' => sanitize_text_field( $pair['VALUE'] ),
				];
			}
		}
		$values['WEBHOOK_FIELDS'] = $pairs;
		$request_type             = 'POST';
		if ( isset( $values['WEBHOOKURL'] ) ) {
			$webhook_url = $values['WEBHOOKURL'];

			if ( empty( $webhook_url ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => esc_attr__( 'Please enter a valid webhook URL.', 'uncanny-automator' ),
				] );
			}

			for ( $i = 1; $i <= ZAPIER_SENDWEBHOOK::$number_of_keys; $i ++ ) {
				$key                = $values[ 'KEY' . $i ];
				$value              = $values[ 'VALUE' . $i ];
				$key_values[ $key ] = $value;
			}

			$fields_string = http_build_query( $key_values );

		} elseif ( isset( $values['WEBHOOK_URL'] ) ) {
			$webhook_url = $values['WEBHOOK_URL'];

			if ( empty( $webhook_url ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => esc_attr__( 'Please enter a valid webhook URL.', 'uncanny-automator' ),
				] );
			}

			if ( ! isset( $values['WEBHOOK_FIELDS'] ) || empty( $values['WEBHOOK_FIELDS'] ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => esc_attr__( 'Please enter valid fields.', 'uncanny-automator' ),
				] );
			}
			$fields = $values['WEBHOOK_FIELDS'];

			for ( $i = 0; $i <= count( $fields ); $i ++ ) {
				$key                = $fields[ $i ]['KEY'];
				$value              = $fields[ $i ]['VALUE'];
				$key_values[ $key ] = $value;
			}

			$header_meta = isset( $values['WEBHOOK_HEADERS'] ) ? $values['WEBHOOK_HEADERS'] : array();
			if ( ! empty( $header_meta ) ) {
				for ( $i = 0; $i <= count( $header_meta ); $i ++ ) {
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
				wp_send_json( [
					'type'    => 'error',
					'message' => $error_message,
				] );
			}

			/* translators: 1. Webhook URL */
			$success_message = sprintf( esc_attr__( 'Successfully sent data on %1$s.', 'uncanny-automator' ), $webhook_url );

			wp_send_json( array(
				'type'    => 'success',
				'message' => $success_message,
			) );
		}
	}
	/**
	 *        if ( ! $this->load_options ) {
	 *
	 *
	 * return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
	 * }
	 */
}
