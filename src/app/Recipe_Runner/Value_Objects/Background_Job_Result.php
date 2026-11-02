<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Value_Objects;

/**
 * A finished background job's outcome, normalised from either delivery path into the
 * sync response envelope an action's process_job() already reads:
 * `{ requestID, statusCode, credits?, data }` on success, plus `error` on failure.
 *
 * The callback carries `result.data`, `request_id`, `credits` and a literal
 * `result.status_code` of 200; the poll carries the whole sync envelope in `result`.
 */
final class Background_Job_Result {

	/**
	 * The platform reported success.
	 *
	 * @var string
	 */
	const STATUS_COMPLETED = 'completed';

	/**
	 * The platform reported failure.
	 *
	 * @var string
	 */
	const STATUS_FAILED = 'failed';

	/**
	 * The plugin gave up waiting.
	 *
	 * @var string
	 */
	const STATUS_LOST = 'lost';

	/**
	 * Arrived through the callback batch.
	 *
	 * @var string
	 */
	const SOURCE_CALLBACK = 'callback';

	/**
	 * Came from the recovery poll.
	 *
	 * @var string
	 */
	const SOURCE_POLL = 'poll';

	/**
	 * Came from the site's own retry of a site-originated job.
	 *
	 * @var string
	 */
	const SOURCE_SITE = 'site';

	/**
	 * The error `type` a lost job carries, so the action can classify it.
	 *
	 * @var string
	 */
	const LOST_ERROR_TYPE = 'BACKGROUND_JOB_LOST';

	/**
	 * One of the STATUS_* values.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * One of the SOURCE_* values.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * The sync-shaped envelope.
	 *
	 * @var array<string, mixed>
	 */
	private array $envelope;

	/**
	 * Use the named constructors.
	 *
	 * @param string               $status   One of the STATUS_* values.
	 * @param string               $source   One of the SOURCE_* values.
	 * @param array<string, mixed> $envelope The sync-shaped envelope.
	 *
	 * @throws \InvalidArgumentException When the status or the source is unknown.
	 */
	private function __construct( string $status, string $source, array $envelope ) {
		if ( ! in_array( $status, array( self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_LOST ), true ) ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'Unknown background job outcome "%s".', $status ) ) );
		}
		if ( ! in_array( $source, array( self::SOURCE_CALLBACK, self::SOURCE_POLL, self::SOURCE_SITE ), true ) ) {
			throw new \InvalidArgumentException( esc_html( sprintf( 'Unknown background job outcome source "%s".', $source ) ) );
		}
		$this->status   = $status;
		$this->source   = $source;
		$this->envelope = $envelope;
	}

	/**
	 * Normalise one `async_completion` event.
	 *
	 * @param string               $status     `completed` or `failed`; anything else is a failure.
	 * @param array<string, mixed> $result     `{ status_code, data }` on success.
	 * @param array<string, mixed> $error      `{ code, type, description, message }` on failure.
	 * @param array<string, mixed> $credits    Credits block, empty when the price was zero.
	 * @param string               $request_id The platform request id.
	 *
	 * @return self
	 */
	public static function from_callback( string $status, array $result, array $error, array $credits, string $request_id ): self {
		if ( self::STATUS_COMPLETED === $status ) {
			$envelope = array(
				'requestID'  => $request_id,
				'statusCode' => (int) ( $result['status_code'] ?? 200 ),
			);
			if ( array() !== $credits ) {
				$envelope['credits'] = $credits;
			}
			$envelope['data'] = is_array( $result['data'] ?? null ) ? $result['data'] : array();

			return new self( self::STATUS_COMPLETED, self::SOURCE_CALLBACK, $envelope );
		}

		if ( self::STATUS_FAILED !== $status ) {
			$error = array(
				'code'        => 500,
				'type'        => 'UNKNOWN_STATUS',
				'description' => sprintf( 'The platform reported an unknown job status "%s".', $status ),
				'message'     => 'Unknown job status',
			);
		}

		return new self( self::STATUS_FAILED, self::SOURCE_CALLBACK, self::failed_envelope( $request_id, $error ) );
	}

	/**
	 * The outcome of the site's own retry of a site-originated job: the write settled, synchronously.
	 *
	 * @since 8.0.0
	 *
	 * @param string               $status `completed` or `failed`; anything else is a failure.
	 * @param array<string, mixed> $result The write's sync envelope `{ requestID, statusCode, data }` on success.
	 * @param array<string, mixed> $error  `{ code, type, description, message }` on failure.
	 *
	 * @return self
	 */
	public static function from_site( string $status, array $result, array $error ): self {
		if ( self::STATUS_COMPLETED === $status ) {
			$envelope         = $result;
			$envelope['data'] = is_array( $result['data'] ?? null ) ? $result['data'] : array();

			return new self( self::STATUS_COMPLETED, self::SOURCE_SITE, $envelope + array( 'statusCode' => 200 ) );
		}

		return new self( self::STATUS_FAILED, self::SOURCE_SITE, self::failed_envelope( (string) ( $result['requestID'] ?? '' ), $error ) );
	}

	/**
	 * A site job's settled outcome, rebuilt from the status and envelope it was stored with. Anything but a
	 * completed status is a failure.
	 *
	 * @since 8.0.0
	 *
	 * @param string               $status   The stored status.
	 * @param array<string, mixed> $envelope The stored envelope, as to_envelope() gave it.
	 *
	 * @return self
	 */
	public static function settled_on_site( string $status, array $envelope ): self {
		return new self( self::STATUS_COMPLETED === $status ? self::STATUS_COMPLETED : self::STATUS_FAILED, self::SOURCE_SITE, $envelope );
	}

	/**
	 * Normalise a `GET /app/v1/jobs/{id}` body. Null while the job is queued or running.
	 *
	 * @param array<string, mixed> $body The decoded poll body.
	 *
	 * @return self|null
	 */
	public static function from_poll( array $body ): ?self {
		$status     = (string) ( $body['status'] ?? '' );
		$request_id = (string) ( $body['request_id'] ?? '' );

		if ( self::STATUS_COMPLETED === $status ) {
			$envelope = is_array( $body['result'] ?? null ) ? $body['result'] : array();
			if ( ! isset( $envelope['requestID'] ) ) {
				$envelope = array( 'requestID' => $request_id ) + $envelope;
			}

			return new self( self::STATUS_COMPLETED, self::SOURCE_POLL, $envelope );
		}

		if ( self::STATUS_FAILED === $status ) {
			$error = is_array( $body['error'] ?? null ) ? $body['error'] : array();

			return new self( self::STATUS_FAILED, self::SOURCE_POLL, self::failed_envelope( $request_id, $error ) );
		}

		return null;
	}

	/**
	 * The plugin stopped waiting for the job.
	 *
	 * @param string $message Why, in the words the run's error log shows.
	 *
	 * @return self
	 */
	public static function lost( string $message ): self {
		return new self(
			self::STATUS_LOST,
			self::SOURCE_POLL,
			self::failed_envelope(
				'',
				array(
					'code'        => 504,
					'type'        => self::LOST_ERROR_TYPE,
					'description' => $message,
					'message'     => $message,
				)
			)
		);
	}

	/**
	 * Build the failure envelope: an error block, an empty data block, the error's code as the status. The block
	 * keeps what the platform said beside the type when it said it: the `reason` the customer's sentence may be
	 * chosen by, and the scope lists of SCOPE_INSUFFICIENT the connection is marked with.
	 *
	 * @param string               $request_id The platform request id.
	 * @param array<string, mixed> $error      The error block.
	 *
	 * @return array<string, mixed>
	 */
	private static function failed_envelope( string $request_id, array $error ): array {
		$description = (string) ( $error['description'] ?? $error['message'] ?? 'The background job failed.' );
		$block       = array(
			'code'        => (int) ( $error['code'] ?? 500 ),
			'type'        => (string) ( $error['type'] ?? '' ),
			'description' => $description,
			'message'     => (string) ( $error['message'] ?? $description ),
		);

		if ( isset( $error['reason'] ) && is_scalar( $error['reason'] ) ) {
			$block['reason'] = (string) $error['reason'];
		}
		foreach ( array( 'required_scopes', 'granted_scopes' ) as $key ) {
			if ( isset( $error[ $key ] ) && is_array( $error[ $key ] ) ) {
				$block[ $key ] = array_values( $error[ $key ] );
			}
		}

		return array(
			'requestID'  => $request_id,
			'statusCode' => $block['code'],
			'data'       => array(),
			'error'      => $block,
		);
	}

	/**
	 * Whether the job did not succeed (failed or lost).
	 *
	 * @return bool
	 */
	public function is_failed(): bool {
		return self::STATUS_COMPLETED !== $this->status;
	}

	/**
	 * Whether the plugin gave up on the job.
	 *
	 * @return bool
	 */
	public function is_lost(): bool {
		return self::STATUS_LOST === $this->status;
	}

	/**
	 * One of the STATUS_* values.
	 *
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * One of the SOURCE_* values.
	 *
	 * @return string
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * The sync-shaped envelope process_job() reads.
	 *
	 * @return array<string, mixed>
	 */
	public function to_envelope(): array {
		return $this->envelope;
	}
}
