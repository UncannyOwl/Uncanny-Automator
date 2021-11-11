<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Uoa_Pro_Helpers;
use WP_Error;

/**
 * Class Uoa_Helpers
 * @package Uncanny_Automator
 */
class Uoa_Helpers {
	/**
	 * @var Uoa_Helpers
	 */
	public $options;
	/**
	 * @var Uoa_Pro_Helpers
	 */
	public $pro;
	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Uoa_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
		add_action( 'wp_ajax_nopriv_sendtest_uoa_webhook', array( $this, 'sendtest_webhook' ) );
		add_action( 'wp_ajax_sendtest_uoa_webhook', array( $this, 'sendtest_webhook' ) );
	}

	/**
	 * @param Uoa_Helpers $options
	 */
	public function setOptions( Uoa_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Uoa_Pro_Helpers $pro
	 */
	public function setPro( Uoa_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param $_POST
	 */
	public function sendtest_webhook() {
		Automator()->utilities->ajax_auth_check();

		$key_values   = array();
		$headers      = array();
		$values       = (array) Automator()->utilities->automator_sanitize( $_POST['values'], 'mixed' );
		$request_type = 'POST';
		if ( isset( $values['WEBHOOKURL'] ) ) {
			$webhook_url = esc_url_raw( $values['WEBHOOKURL'] );

			if ( empty( $webhook_url ) ) {
				wp_send_json( [
					'type'    => 'error',
					'message' => esc_attr__( 'Please enter a valid webhook URL.', 'uncanny-automator' ),
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

			if ( ! empty( $fields ) ) {
				for ( $i = 0; $i <= count( $fields ); $i ++ ) {
					$key   = isset( $fields[ $i ]['KEY'] ) ? sanitize_text_field( $fields[ $i ]['KEY'] ) : null;
					$value = isset( $fields[ $i ]['VALUE'] ) ? sanitize_text_field( $fields[ $i ]['VALUE'] ) : null;
					if ( ! is_null( $key ) && ! is_null( $value ) ) {
						$key_values[ $key ] = $value;
					}
				}
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
			} elseif ( 'DELETE' === (string) $values['ACTION_EVENT'] ) {
				$request_type = 'DELETE';
			} elseif ( 'HEAD' === (string) $values['ACTION_EVENT'] ) {
				$request_type = 'HEAD';
			} elseif ( 'automator_custom_value' === (string) $values['ACTION_EVENT'] && isset( $values['ACTION_EVENT_custom'] ) ) {
				$request_type = $values['ACTION_EVENT_custom'];
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

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function get_recipes( $label = null, $option_code = 'UOARECIPE', $any_option = false ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Recipe', 'uncanny-automator' );
		}

		// post query arguments.
		$args = array(
			'post_type'      => 'uo-recipe',
			'posts_per_page' => 999, //phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, $any_option, esc_attr__( 'Any recipe', 'uncanny-automator' ) );

		$option = array(
			'option_code'              => $option_code,
			'label'                    => $label,
			'input_type'               => 'select',
			'required'                 => true,
			'options'                  => $options,
			'custom_value_description' => esc_attr__( 'Recipe slug', 'uncanny-automator' ),
		);

		return apply_filters( 'uap_option_get_recipes', $option );
	}
}
