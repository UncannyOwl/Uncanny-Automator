<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Api_Client;

/**
 * Class Api_Response
 *
 * Immutable value object representing a parsed API response.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Api_Client
 */
class Api_Response {

	/**
	 * The HTTP status code.
	 *
	 * @var int
	 */
	private $status_code;

	/**
	 * The parsed response data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Error info, or null if no error.
	 *
	 * @var array|null
	 */
	private $error;

	/**
	 * Credits info, or null if not present.
	 *
	 * @var array|null
	 */
	private $credits;

	/**
	 * Whether this is an async (202) response with a job ID.
	 *
	 * @var bool
	 */
	private $is_async;

	/**
	 * The async job ID, or null if not async.
	 *
	 * @var string|null
	 */
	private $job_id;

	/**
	 * Time spent on the HTTP call in milliseconds.
	 *
	 * @var float
	 */
	private $time_spent_ms;

	/**
	 * The raw WP HTTP response array.
	 *
	 * Stored for backward compat with code that reads Api_Server::$last_response.
	 *
	 * @var array|null
	 */
	private $raw_wp_response;

	/**
	 * Maximum length of a non-JSON body snippet included in error messages.
	 *
	 * @var int
	 */
	private const BODY_SNIPPET_LENGTH = 200;

	/**
	 * Constructor.
	 *
	 * @param int         $status_code   The HTTP status code.
	 * @param array       $data          The parsed response data.
	 * @param array|null  $error         Error info or null.
	 * @param array|null  $credits       Credits info or null.
	 * @param bool        $is_async      Whether the response is async.
	 * @param string|null $job_id        The async job ID or null.
	 * @param float       $time_spent_ms Time spent in milliseconds.
	 */
	public function __construct(
		int $status_code,
		array $data,
		?array $error = null,
		?array $credits = null,
		bool $is_async = false,
		?string $job_id = null,
		float $time_spent_ms = 0.0
	) {
		$this->status_code   = $status_code;
		$this->data          = $data;
		$this->error         = $error;
		$this->credits       = $credits;
		$this->is_async      = $is_async;
		$this->job_id        = $job_id;
		$this->time_spent_ms = $time_spent_ms;
	}

	/**
	 * Get the HTTP status code.
	 *
	 * @return int
	 */
	public function status_code(): int {
		return $this->status_code;
	}

	/**
	 * Get the parsed response data.
	 *
	 * @return array
	 */
	public function data(): array {
		return $this->data;
	}

	/**
	 * Get the error info, or null if no error.
	 *
	 * @return array|null
	 */
	public function error(): ?array {
		return $this->error;
	}

	/**
	 * Get the credits info, or null if not present.
	 *
	 * @return array|null
	 */
	public function credits(): ?array {
		return $this->credits;
	}

	/**
	 * Whether this is an async response.
	 *
	 * @return bool
	 */
	public function is_async(): bool {
		return $this->is_async;
	}

	/**
	 * Get the async job ID, or null.
	 *
	 * @return string|null
	 */
	public function job_id(): ?string {
		return $this->job_id;
	}

	/**
	 * Get the time spent on the HTTP call in milliseconds.
	 *
	 * @return float
	 */
	public function time_spent_ms(): float {
		return $this->time_spent_ms;
	}

	/**
	 * Get the raw WP HTTP response array.
	 *
	 * Used by the Api_Server facade to populate Api_Server::$last_response for backward compat.
	 *
	 * @return array|null
	 */
	public function raw_wp_response(): ?array {
		return $this->raw_wp_response;
	}

	/**
	 * Whether the response indicates success.
	 *
	 * A response is successful when the status code is 2xx and no error is present.
	 *
	 * @return bool
	 */
	public function is_success(): bool {
		return $this->status_code >= 200
			&& $this->status_code < 300
			&& null === $this->error;
	}

	/**
	 * Convert to the legacy array format for backward compatibility.
	 *
	 * Returns the format expected by existing integrations:
	 * array( 'statusCode' => int, 'data' => array, 'error' => ?array, 'credits' => ?array )
	 *
	 * @return array
	 */
	public function to_legacy_array(): array {
		$result = array(
			'statusCode' => $this->status_code,
			'data'       => $this->data,
		);

		if ( null !== $this->error ) {
			$result['error'] = $this->error;
		}

		if ( null !== $this->credits ) {
			$result['credits'] = $this->credits;
		}

		return $result;
	}

	/**
	 * Build an Api_Response from a wp_remote_request() response.
	 *
	 * Handles non-JSON responses gracefully (502 HTML pages, CloudFlare errors, empty body).
	 *
	 * @param array|\WP_Error $wp_response   The response from wp_remote_request().
	 * @param float           $time_spent_ms Time spent on the HTTP call in milliseconds.
	 *
	 * @return self
	 */
	public static function from_wp_response( $wp_response, float $time_spent_ms ): self {
		$status_code = (int) wp_remote_retrieve_response_code( $wp_response );
		$body_raw    = wp_remote_retrieve_body( $wp_response );
		$body        = json_decode( $body_raw, true );

		// Non-JSON response — create an error response with a body snippet.
		if ( null === $body || ! is_array( $body ) ) {
			$snippet                   = substr( (string) $body_raw, 0, self::BODY_SNIPPET_LENGTH );
			$error                     = array(
				'type'        => 'non_json_response',
				'description' => 'Non-JSON response from API: ' . $snippet,
			);
			$instance                  = new self(
				0 !== $status_code ? $status_code : 502,
				array(),
				$error,
				null,
				false,
				null,
				$time_spent_ms
			);
			$instance->raw_wp_response = is_array( $wp_response ) ? $wp_response : null;
			return $instance;
		}

		// Parse the structured response.
		$parsed_status = isset( $body['statusCode'] ) ? (int) $body['statusCode'] : $status_code;
		$data          = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();

		// Extract error — top-level or nested in data.
		$error = null;
		if ( isset( $body['error'] ) && is_array( $body['error'] ) ) {
			$error = $body['error'];
		} elseif ( isset( $data['error'] ) && is_array( $data['error'] ) ) {
			$error = $data['error'];
		}

		// Extract credits from data.
		$credits = null;
		if ( isset( $data['credits'] ) && is_array( $data['credits'] ) ) {
			$credits = $data['credits'];
		}

		// Detect async: 202 status + job_id in data.
		$is_async = 202 === $parsed_status && isset( $data['job_id'] );
		$job_id   = $is_async ? (string) $data['job_id'] : null;

		$instance                  = new self(
			$parsed_status,
			$data,
			$error,
			$credits,
			$is_async,
			$job_id,
			$time_spent_ms
		);
		$instance->raw_wp_response = is_array( $wp_response ) ? $wp_response : null;

		return $instance;
	}
}
