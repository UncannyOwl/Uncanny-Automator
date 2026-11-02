<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Class Response
 *
 * Immutable value object representing a parsed API response.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Automator_Platform
 */
class Response {

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
	 * Whether the error came from under `data` (the vendor's) rather than the envelope.
	 *
	 * @var bool
	 */
	private $vendor_error = false;

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
	 * Maximum length of a non-JSON body snippet kept in the error's description
	 * (for the logs; never shown to the customer).
	 *
	 * @var int
	 */
	private const BODY_SNIPPET_LENGTH = 200;

	/**
	 * The `error.type` of an answer that is not the platform's envelope: a body
	 * that is not a JSON object — a load balancer's plain-text 503, an edge's
	 * error page, a proxy's login page. The platform, or whatever sits in front
	 * of it, did not answer as the platform.
	 *
	 * @since 8.0.0
	 */
	public const TYPE_NON_JSON = 'non_json_response';

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
	 * Whether the error is the vendor's, reported under `data`, rather than one on the envelope.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_vendor_error(): bool {
		return $this->vendor_error;
	}

	/**
	 * The platform's `error.type`, or '' when the answer carries none.
	 *
	 * The type is what the plugin acts on; the texts beside it — the message
	 * for the customer, the description for the operator — may be reworded at
	 * any time (platform answers contract 4.8), so nothing reads them to decide.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function error_type(): string {

		if ( null === $this->error || ! isset( $this->error['type'] ) || ! is_scalar( $this->error['type'] ) ) {
			return '';
		}

		return (string) $this->error['type'];
	}

	/**
	 * The platform's `error.message` — the sentence it wrote for the customer
	 * (flow spec §10.2) — falling back to `error.description`, or '' when the
	 * answer carries neither. What the plugin shows a person. A platform older
	 * than the field, and the platform's own middleware, send only one text.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function error_message(): string {

		$error   = null === $this->error ? array() : $this->error;
		$message = $error['message'] ?? '';

		if ( ! is_scalar( $message ) || '' === trim( (string) $message ) ) {
			return $this->error_description();
		}

		return (string) $message;
	}

	/**
	 * The platform's `error.description` — the operator's account of the
	 * failure (flow spec §10.2), for logs and the few readers that match on
	 * it — falling back to `error.message`, or '' when the answer carries
	 * neither. What a person is shown is {@see error_message()}.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function error_description(): string {

		$error       = null === $this->error ? array() : $this->error;
		$description = $error['description'] ?? '';

		if ( empty( $description ) && isset( $error['message'] ) ) {
			$description = $error['message'];
		}

		return is_scalar( $description ) ? (string) $description : '';
	}

	/**
	 * The platform's `error.reason`: a code from a closed set the platform
	 * fixes for a refusal (a license refusal's, contract §4.10), '' when the
	 * answer names none. Unlike the texts, a reason may be decided on.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function error_reason(): string {

		$reason = ( null === $this->error ? array() : $this->error )['reason'] ?? '';

		return is_scalar( $reason ) ? (string) $reason : '';
	}

	/**
	 * Seconds the answer's `Retry-After` header asks the caller to wait, or 0
	 * when it asks for nothing.
	 *
	 * @since 8.0.0
	 *
	 * @return int
	 */
	public function retry_after(): int {

		return null === $this->raw_wp_response ? 0 : self::retry_after_seconds( $this->raw_wp_response );
	}

	/**
	 * Read a numeric `Retry-After` header from a WP HTTP response.
	 *
	 * The platform only ever sends seconds, so the HTTP-date form is ignored
	 * rather than misread, and so is a wait of 0.
	 *
	 * @since 8.0.0
	 *
	 * @param array|\WP_Error $wp_response WP HTTP response.
	 *
	 * @return int Seconds, 0 when absent or not a positive number of seconds.
	 */
	public static function retry_after_seconds( $wp_response ): int {

		$value = wp_remote_retrieve_header( $wp_response, 'retry-after' );

		return is_string( $value ) && ctype_digit( $value ) ? (int) $value : 0;
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
	 * Read a response header by name.
	 *
	 * Header lookup is case-insensitive per RFC 7230. Returns empty
	 * string when the header is absent or `raw_wp_response` was never
	 * captured (e.g. mocked responses in unit tests).
	 *
	 * @since 8.0.0 Added for Contract H credential-lift signal observer (C.1).
	 *
	 * @param string $name Header name (e.g. `X-Automator-Credentials-Lifted`).
	 *
	 * @return string Header value, or empty string when missing.
	 */
	public function header( string $name ): string {

		if ( null === $this->raw_wp_response ) {
			return '';
		}

		return (string) wp_remote_retrieve_header( $this->raw_wp_response, $name );
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

		if ( $this->is_async && null !== $this->job_id ) {
			$result['job_id'] = $this->job_id;
		}

		return $result;
	}

	/**
	 * Build a Response from a wp_remote_request() response.
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

		$instance = is_array( $body )
			? self::from_envelope( $body, $status_code, $time_spent_ms )
			: self::without_envelope( (string) $body_raw, $status_code, $time_spent_ms );

		$instance->raw_wp_response = is_array( $wp_response ) ? $wp_response : null;

		return $instance;
	}

	/**
	 * The answer of a body that is not a JSON object.
	 *
	 * @param string $body_raw      The body as received.
	 * @param int    $status_code   The HTTP status.
	 * @param float  $time_spent_ms Time spent on the HTTP call in milliseconds.
	 *
	 * @return self
	 */
	private static function without_envelope( string $body_raw, int $status_code, float $time_spent_ms ): self {

		// 2xx with an empty body is a legitimate no-content success
		// (HTTP 204 from DELETE/unsubscribe/update endpoints). Mirrors
		// the tolerance the legacy Api_Server::get_response_body() had,
		// and sidesteps intermediaries (Railway, nginx) that strip the
		// body from 204 responses per RFC 7230.
		if ( '' === $body_raw && $status_code >= 200 && $status_code < 300 ) {
			return new self( $status_code, array(), null, null, false, null, $time_spent_ms );
		}

		// Otherwise: unparseable body on a non-2xx status, or a non-empty
		// body that isn't JSON (HTML error pages, CDN interstitials, etc.).
		$error = array(
			'type'        => self::TYPE_NON_JSON,
			'description' => 'Non-JSON response from API: ' . substr( $body_raw, 0, self::BODY_SNIPPET_LENGTH ),
		);

		return new self( 0 !== $status_code ? $status_code : 502, array(), $error, null, false, null, $time_spent_ms );
	}

	/**
	 * The answer of a structured body: `statusCode`, `data`, and an error,
	 * credits and job id wherever the platform puts them.
	 *
	 * @param array<string, mixed> $body          The decoded body.
	 * @param int                  $status_code   The HTTP status, which the envelope's own `statusCode` overrides.
	 * @param float                $time_spent_ms Time spent on the HTTP call in milliseconds.
	 *
	 * @return self
	 */
	private static function from_envelope( array $body, int $status_code, float $time_spent_ms ): self {

		$status = isset( $body['statusCode'] ) ? (int) $body['statusCode'] : $status_code;
		$data   = self::array_at( $body, 'data' ) ?? array();
		$job_id = self::async_job_id( $body, $data, $status );

		$envelope_error = self::array_at( $body, 'error' );

		$instance = new self(
			$status,
			$data,
			$envelope_error ?? self::array_at( $data, 'error' ),
			// The platform's envelope carries the credits beside `data`
			// (requestID, statusCode, credits, data); a handler may also report
			// them under `data`.
			self::array_at( $body, 'credits' ) ?? self::array_at( $data, 'credits' ),
			null !== $job_id,
			$job_id,
			$time_spent_ms
		);

		// The vendor's error: one under `data`, with none on the envelope.
		$instance->vendor_error = null === $envelope_error && null !== $instance->error;

		return $instance;
	}

	/**
	 * The job id of an async answer: a 202 status and a job id.
	 *
	 * The platform's 202 dispatch envelope puts job_id at the TOP level
	 * alongside payload_type / status_url — `body.job_id` — matching the
	 * inbound webhook callback shape. The legacy `data.job_id` nesting is
	 * accepted too, for any older v2-era responses that may still surface
	 * during the rollout window.
	 *
	 * @param array<string, mixed> $body   The decoded body.
	 * @param array<string, mixed> $data   Its `data`.
	 * @param int                  $status The answer's status.
	 *
	 * @return string|null The job id, or null when the answer is not async.
	 */
	private static function async_job_id( array $body, array $data, int $status ): ?string {

		$top_level = isset( $body['job_id'] ) ? (string) $body['job_id'] : '';
		$nested    = isset( $data['job_id'] ) ? (string) $data['job_id'] : '';
		$resolved  = '' !== $top_level ? $top_level : $nested;

		return 202 === $status && '' !== $resolved ? $resolved : null;
	}

	/**
	 * @param array<string, mixed> $source The array to read.
	 * @param string               $key    The key.
	 *
	 * @return array<string, mixed>|null The value under the key when it is an array, else null.
	 */
	private static function array_at( array $source, string $key ): ?array {

		return isset( $source[ $key ] ) && is_array( $source[ $key ] ) ? $source[ $key ] : null;
	}
}
