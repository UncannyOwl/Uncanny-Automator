<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

/**
 * What the platform answered to a registration request, read in one place: the
 * decoded envelope Platform_Http::post() returns.
 *
 * The status is the envelope's `statusCode`, 0 when the request got no answer (a
 * transport failure, or a body with no envelope). The error object rides at the top
 * level on some routes and under `data` on others, the same dual position
 * Response.php reads it from.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration::__construct()}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Register_Answer {

	/**
	 * Ceiling on a platform-supplied Retry-After. The hint may lengthen a backoff
	 * but a hostile or buggy value must not be able to park a site forever.
	 */
	private const MAX_RETRY_AFTER_SECONDS = DAY_IN_SECONDS;

	/**
	 * The `error.reason` of a host refusal whose name had no address at the
	 * challenge's last try. One of the platform's closed set of host-refusal
	 * reasons; the only one that can pass by itself.
	 *
	 * @since 8.0.0
	 */
	public const REASON_HOST_DOES_NOT_RESOLVE = 'host_does_not_resolve';

	/**
	 * The platform's `error.reason` for a host no pinned request can reach (not
	 * printable ASCII): it must be registered in its A-label form. A property
	 * of the address itself, which DNS cannot change.
	 */
	public const REASON_HOST_NOT_PINNABLE = 'host_not_pinnable';

	/**
	 * @var array<string, mixed>
	 */
	private $response;

	/**
	 * @param array<string, mixed> $response The decoded response; empty when the request got no answer.
	 */
	public function __construct( array $response = array() ) {

		$this->response = $response;
	}

	/**
	 * The envelope's `statusCode`; 0 when the request got no answer.
	 *
	 * @return int
	 */
	public function status(): int {

		return isset( $this->response['statusCode'] ) ? (int) $this->response['statusCode'] : 0;
	}

	/**
	 * The envelope's `data` payload; empty when it carries none.
	 *
	 * @return array<string, mixed>
	 */
	public function data(): array {

		return isset( $this->response['data'] ) && is_array( $this->response['data'] ) ? $this->response['data'] : array();
	}

	/**
	 * The envelope's `error.type`, or '' when there is none. The error object
	 * rides at the top level on some routes and under `data` on others — the
	 * same dual position Response.php reads it from.
	 *
	 * @return string
	 */
	public function type(): string {

		if ( isset( $this->response['error']['type'] ) ) {
			return (string) $this->response['error']['type'];
		}

		return isset( $this->response['data']['error']['type'] ) ? (string) $this->response['data']['error']['type'] : '';
	}

	/**
	 * The platform's description of an error answer — the operator's text, for
	 * logs (flow spec §10.2) — '' when it gave none. What an admin is shown is
	 * {@see message()}.
	 *
	 * @return string
	 */
	public function description(): string {

		return $this->error_text( 'description' );
	}

	/**
	 * The platform's reason code for a host refusal (`error.reason`), '' when
	 * it gave none. A closed set the plugin decides on, unlike the
	 * description; a reason it does not know is read by the type.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function reason(): string {

		return $this->error_text( 'reason' );
	}

	/**
	 * How the host challenge is going, from a `425 TOO_EARLY`
	 * (`error.challenge`); null when the answer, or the platform, says nothing
	 * of it.
	 *
	 * @since 8.0.0
	 *
	 * @return Challenge_Progress|null
	 */
	public function challenge_progress(): ?Challenge_Progress {

		$error = isset( $this->response['error'] ) && is_array( $this->response['error'] ) ? $this->response['error'] : array();

		return Challenge_Progress::from_block( $error['challenge'] ?? null );
	}

	/**
	 * Whether this is the host refusal of a name that had no address yet
	 * ({@see REASON_HOST_DOES_NOT_RESOLVE}): the one host refusal that can pass
	 * by itself, from register or from register/complete alike.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_unresolved_host(): bool {

		return 400 === $this->status() && 'VALIDATION_ERROR' === $this->type() && self::REASON_HOST_DOES_NOT_RESOLVE === $this->reason();
	}

	/**
	 * Whether this is the refusal of a host only its A-label form reaches
	 * ({@see REASON_HOST_NOT_PINNABLE}), from register or from register/complete.
	 * Decided by the reason alone: whatever status and type carry it, the
	 * address is the cause.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_unpinnable_host(): bool {

		return self::REASON_HOST_NOT_PINNABLE === $this->reason();
	}

	/**
	 * The platform's sentence for the customer (`error.message`), else its
	 * description, '' when it gave neither: the text a notice shows.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function message(): string {

		$message = $this->error_text( 'message' );

		return '' !== trim( $message ) ? $message : $this->description();
	}

	/**
	 * One text field of the error, in the envelope or nested under `data`.
	 *
	 * @param string $field `description`, `message` or `reason`.
	 *
	 * @return string
	 */
	private function error_text( string $field ): string {

		if ( isset( $this->response['error'][ $field ] ) && is_scalar( $this->response['error'][ $field ] ) ) {
			return (string) $this->response['error'][ $field ];
		}

		return isset( $this->response['data']['error'][ $field ] ) && is_scalar( $this->response['data']['error'][ $field ] ) ? (string) $this->response['data']['error'][ $field ] : '';
	}

	/**
	 * The platform's Retry-After hint in seconds, clamped; 0 when absent.
	 *
	 * @return int
	 */
	public function retry_after(): int {

		$hint = isset( $this->response['retryAfter'] ) ? (int) $this->response['retryAfter'] : 0;

		return max( 0, min( $hint, self::MAX_RETRY_AFTER_SECONDS ) );
	}

	/**
	 * The decoded response as received, for the signals that hand it to listeners.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {

		return $this->response;
	}
}
