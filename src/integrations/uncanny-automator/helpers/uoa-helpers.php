<?php


namespace Uncanny_Automator;


/**
 * Class Uoa_Helpers
 * @package Uncanny_Automator
 */
class Uoa_Helpers {
	/**
	 * Uoa_Helpers constructor.
	 */
	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		add_action( 'wp_ajax_nopriv_sendtest_uoa_webhook', array( $this, 'sendtest_webhook' ) );
		add_action( 'wp_ajax_sendtest_uoa_webhook', array( $this, 'sendtest_webhook' ) );
	}

	/**
	 * @var Uoa_Helpers
	 */
	public $options;
	/**
	 * @var \Uncanny_Automator_Pro\Uoa_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * @param Uoa_Helpers $options
	 */
	public function setOptions( Uoa_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Uoa_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Uoa_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param $_POST
	 */
	public function sendtest_webhook() {

		global $uncanny_automator;

		$uncanny_automator->utilities->ajax_auth_check( $_POST );

		$key_values   = [];
		$headers      = [];
		$values       = (array) $uncanny_automator->uap_sanitize( $_POST['values'], 'mixed' );
		$request_type = 'POST';
		if ( isset( $values['WEBHOOKURL'] ) ) {
			$webhook_url = esc_url_raw( $values['WEBHOOKURL'] );

			if ( empty( $webhook_url ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => __( 'Please enter a valid webhook URL.', 'uncanny-automator' ),
				] );
			}

			for ( $i = 1; $i <= WP_SENDWEBHOOK::$number_of_keys; $i ++ ) {
				$key                = sanitize_text_field( $values[ 'KEY' . $i ] );
				$value              = sanitize_text_field( $values[ 'VALUE' . $i ] );
				$key_values[ $key ] = $value;
			}

		} elseif ( isset( $values['WEBHOOK_URL'] ) ) {
			$webhook_url = esc_url_raw( $values['WEBHOOK_URL'] );

			if ( empty( $webhook_url ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => __( 'Please enter a valid webhook URL.', 'uncanny-automator' ),
				] );
			}

			if ( ! isset( $values['WEBHOOK_FIELDS'] ) || empty( $values['WEBHOOK_FIELDS'] ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => __( 'Please enter valid fields.', 'uncanny-automator' ),
				] );
			}

			$fields = $values['WEBHOOK_FIELDS'];

			if( ! empty( $fields ) ) {
				for ( $i = 0; $i <= count( $fields ); $i ++ ) {
					$key   = isset( $fields[ $i ]['KEY'] ) ? sanitize_text_field( $fields[ $i ]['KEY'] ) : null;
					$value = isset( $fields[ $i ]['VALUE'] ) ? sanitize_text_field( $fields[ $i ]['VALUE'] ) : null;
					if ( ! is_null( $key ) && ! is_null( $value ) ) {
						$key_values[ $key ] = $value;
					}
				}
			}
			$header_meta = isset( $values['WEBHOOK_HEADERS'] ) ? $values['WEBHOOK_HEADERS'] : [];

			if( ! empty( $header_meta ) ) {
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

			if ( $response instanceof \WP_Error ) {
				/* translators: 1. Webhook URL */
				$error_message = sprintf( __( 'An error was found in the webhook (%1$s) response.', 'uncanny-automator' ), $webhook_url );
				wp_send_json( [
					'type'    => 'error',
					'message' => $error_message,
				] );
			}

			/* translators: 1. Webhook URL */
			$success_message = sprintf( __( 'Successfully sent data on %1$s.', 'uncanny-automator' ), $webhook_url );

			wp_send_json( array(
				'type'    => 'success',
				'message' => $success_message,
			) );
		}
	}
}