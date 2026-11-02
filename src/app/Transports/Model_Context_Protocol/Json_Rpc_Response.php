<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

/**
 * MCP tool result payloads.
 *
 * Helper class for creating MCP tool success and error result payloads.
 *
 * @since 7.0.0
 */
class Json_Rpc_Response {

	/** @since 8.0.0 */
	public const UNCONFIRMED_WRITE_GUIDANCE = 'Check the affected site first. Do not retry until its state is confirmed, even if WordPress-reported details suggest retrying.';

	/**
	 * Create a success response.
	 *
	 * @param string     $message Success message.
	 * @param array|null $data    Optional response data.
	 * @return array MCP success response.
	 */
	public static function create_success_response( string $message, ?array $data = null ): array {

		$structured = array( 'message' => $message );

		if ( null !== $data ) {
			$structured['data'] = $data;
		}

		$result = array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => wp_json_encode( $structured ),
				),
			),
		);

		// MCP 2025-06-18: When outputSchema is defined, structuredContent MUST be returned.
		// Always include it — harmless for tools without outputSchema, required for those with it.
		$result['structuredContent'] = $structured;

		return $result;
	}

	/**
	 * Create an error response.
	 *
	 * @param string     $message Error message.
	 * @param array|null $data    Optional error data.
	 * @return array MCP error response.
	 */
	public static function create_error_response( string $message, ?array $data = null ): array {

		$content = array(
			'type' => 'text',
			'text' => wp_json_encode(
				array(
					'message' => $message,
				)
			),
		);

		if ( null !== $data ) {
			$content['text'] = wp_json_encode(
				array(
					'message' => $message,
					'data'    => $data,
				)
			);
		}

		return array(
			'isError' => true,
			'content' => array(
				$content,
			),
		);
	}

	/**
	 * Preserve the native error's safe status and recovery fields.
	 *
	 * @since 8.0.0
	 *
	 * @param \WP_Error $error Native error.
	 * @param bool $possibly_modified Whether the operation may already have changed the site.
	 * @param bool $refused_before_running Whether the server stopped before dispatch.
	 * @return array MCP error response.
	 */
	public static function create_wp_error_response( \WP_Error $error, bool $possibly_modified = false, bool $refused_before_running = false ): array {
		$code       = $error->get_error_code();
		$safe_code  = ( is_int( $code ) || ( is_string( $code ) && strlen( $code ) <= 80 && 1 === preg_match( '/^[a-z][a-z0-9_.-]*$/iD', $code ) && 0 === preg_match( '/^(?:sk[-_](?:live|test|proj)[-_]|gh[pousr]_)/i', $code ) ) ) ? $code : 'wordpress_error';
		$data       = array( 'code' => $safe_code );
		$error_data = $error->get_error_data();
		if ( is_array( $error_data ) ) {
			foreach ( array( 'status', 'upstream_status' ) as $field ) {
				$status = $error_data[ $field ] ?? null;
				if ( ( is_int( $status ) || ( is_string( $status ) && ctype_digit( $status ) ) ) && (int) $status >= 100 && (int) $status <= 599 ) {
					$data[ $field ] = (int) $status;
				}
			}
			$retry_after = $error_data['retry_after'] ?? null;
			if ( ( is_int( $retry_after ) || ( is_string( $retry_after ) && ctype_digit( $retry_after ) ) ) && (int) $retry_after >= 0 ) {
				$data['retry_after'] = (int) $retry_after;
			}
			if ( isset( $error_data['retryable'] ) && is_bool( $error_data['retryable'] ) ) {
				$data['retryable'] = $error_data['retryable'];
			}
			if ( isset( $error_data['next_action'] ) && is_string( $error_data['next_action'] ) ) {
				$data['next_action'] = sanitize_text_field( $error_data['next_action'] );
			}
		}

		$message = $error->get_error_message();
		if ( $possibly_modified ) {
			$guidance            = self::UNCONFIRMED_WRITE_GUIDANCE;
			$message             = 'WordPress reported: ' . $message . ' ' . $guidance;
			$data['outcome']     = 'unconfirmed';
			$data['next_action'] = $guidance;
			unset( $data['retryable'] );
		} elseif ( $refused_before_running ) {
			$data['outcome'] = 'refused_before_running';
		}

		return self::create_error_response( $message, $data );
	}

	/**
	 * Keep native data while replacing untrusted recovery advice after a possible write.
	 *
	 * @since 8.0.0
	 *
	 * @param array $result Native response data.
	 * @param string $guidance Safe recovery instruction.
	 * @param bool $trusted_recovery Whether the upload sanitizer supplied the response text.
	 * @return array Native data with unconfirmed recovery fields.
	 */
	public static function mark_unconfirmed_native_result( array $result, string $guidance, bool $trusted_recovery = false ): array {
		$result['outcome']     = 'unconfirmed';
		$result['next_action'] = $guidance;
		$body                  = $result['body'] ?? null;
		$body_is_object        = $body instanceof \stdClass;
		if ( ! is_array( $body ) && ! $body_is_object ) {
			return $result;
		}
		$fields = $body_is_object ? get_object_vars( $body ) : $body;
		unset( $fields['retryable'] );
		if ( array_key_exists( 'outcome', $fields ) ) {
			$fields['outcome'] = 'unconfirmed';
		}
		if ( array_key_exists( 'next_action', $fields ) ) {
			$fields['next_action'] = $guidance;
		}
		$data           = $fields['data'] ?? null;
		$data_is_object = $data instanceof \stdClass;
		if ( is_array( $data ) || $data_is_object ) {
			$data = $data_is_object ? get_object_vars( $data ) : $data;
			unset( $data['retryable'] );
			$data['outcome'] = 'unconfirmed';
			if ( ! $trusted_recovery || ! is_string( $data['next_action'] ?? null ) ) {
				$data['next_action'] = $guidance;
			}
			$fields['data'] = $data_is_object ? (object) $data : $data;
		}
		$result['body'] = $body_is_object ? (object) $fields : $fields;
		return $result;
	}
}
