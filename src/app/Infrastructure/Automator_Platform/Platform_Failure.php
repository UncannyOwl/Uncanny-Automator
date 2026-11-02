<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Which thing failed, read from a platform answer's `error.type`.
 *
 * The platform answers contract (2026-10-02) gives each failure on `/v3` and
 * `/app/v1` a type that says which layer refused — the license, the site
 * signature, the connection, the credits, the action — and each class here has
 * exactly one right reaction. The plugin decides on `statusCode` and
 * `error.type` only. The texts beside the type — `message` for the customer,
 * `description` for the operator (flow spec §10.2) — may be reworded at any
 * time (contract 4.8) and are never read to decide anything; a person is shown
 * {@see message()}.
 *
 *  | error.type (status)                                   | Class                  |
 *  |-------------------------------------------------------|------------------------|
 *  | UNAUTHENTICATED                                       | license                |
 *  | SITE_NOT_REGISTERED, VERIFICATION_ERROR               | site_registration      |
 *  | CLOCK_SKEW                                            | clock_skew             |
 *  | REQUEST_REPLAYED                                      | request_replayed       |
 *  | RATE_LIMITED                                          | rate_limited           |
 *  | FAILED_DEPENDENCY (503)                               | busy                   |
 *  | FAILED_DEPENDENCY (any other status, in practice 424) | dependency_failed      |
 *  | SERVER_ERROR at 424, the collapsed 5xx of `/v2`       | refused                |
 *  | INSUFFICIENT_PRIVILEGES (402)                         | credits                |
 *  | CONNECTION_NOT_FOUND, CONNECTION_SIGNATURE_INVALID,   |                        |
 *  | CONNECTION_INACTIVE                                   | connection_out_of_sync |
 *  | IDENTITY_REVOKED                                      | identity_revoked       |
 *  | SCOPE_INSUFFICIENT                                    | scope_insufficient     |
 *  | PROVIDER_UPSTREAM                                     | provider_upstream      |
 *  | INVALID_SOURCE_TOKEN                                  | source_token           |
 *  | SITE_IS_LOCAL                                         | site_is_local          |
 *  | non_json_response (the plugin's: no envelope at all)  | not_answering          |
 *  | anything else                                         | refused                |
 *
 * Two types name two things each, and the status tells them apart — the only
 * places a status takes part, deciding a message, never a connection:
 *
 *  - `INSUFFICIENT_PRIVILEGES`: a 402 is the license or credit check `/v2`
 *    shares (contract 4.4); a 403 is the vendor refusing the operation (4.5).
 *  - `FAILED_DEPENDENCY`: a 503 is the platform's own store being down — the
 *    license store (4.1) or the replay store (4.2) — so the platform is busy.
 *    Any other status, in practice 424, is something the action depends on
 *    failing: a vendor's 5xx, a handler that could not finish a step the
 *    action needs, the platform's own database. The platform's response
 *    boundary answers it as 424 with the vendor's or the handler's
 *    description, and the description is the reason.
 *
 * The status 424 is not owned by `FAILED_DEPENDENCY`. It is the platform's
 * collapsed 5xx (§9.3, the platform's `WireStatus`): every answer that would
 * have been a 5xx, a `/v2` bug, a database blip, the queue-deadline refusal,
 * a vendor's failure, goes out as 424, typed `FAILED_DEPENDENCY`,
 * `SERVER_ERROR` or whatever the handler gave. Whatever its type, a 424 is
 * "not now", never a verdict. The type here only picks the message, and never
 * makes a 424 permanent. What a license fetch latches on, and what it backs
 * off for, is decided in
 * {@see \Uncanny_Automator\App\Infrastructure\License\License_Manager::get_license_data()},
 * which also reads a header this class does not.
 *
 * Reactions live with their owners: what the stored connection undergoes in
 * {@see \Uncanny_Automator\App\Infrastructure\Connections\Connection_Error_Handler},
 * what the customer is told in {@see Platform_Failure_Message}.
 *
 * @since 8.0.0
 */
final class Platform_Failure {

	public const KIND_LICENSE                = 'license';
	public const KIND_SITE_REGISTRATION      = 'site_registration';
	public const KIND_CLOCK_SKEW             = 'clock_skew';
	public const KIND_REQUEST_REPLAYED       = 'request_replayed';
	public const KIND_RATE_LIMITED           = 'rate_limited';
	public const KIND_BUSY                   = 'busy';
	public const KIND_DEPENDENCY_FAILED      = 'dependency_failed';
	public const KIND_CREDITS                = 'credits';
	public const KIND_CONNECTION_OUT_OF_SYNC = 'connection_out_of_sync';
	public const KIND_IDENTITY_REVOKED       = 'identity_revoked';
	public const KIND_SCOPE_INSUFFICIENT     = 'scope_insufficient';
	public const KIND_PROVIDER_UPSTREAM      = 'provider_upstream';
	public const KIND_SOURCE_TOKEN           = 'source_token';

	/**
	 * The site is LOCAL (contract 4.8, L247): what it asked for — webhooks, a
	 * callback, a move onto its address, another site's account — is never
	 * available to it. Final: shown as the platform describes it, never retried,
	 * never a re-register.
	 */
	public const KIND_SITE_IS_LOCAL = 'site_is_local';

	/**
	 * The answer is not the platform's envelope ({@see Response::TYPE_NON_JSON}):
	 * the platform, or what sits in front of it, did not answer as the
	 * platform. Told in plain words, by status; the body it carried is for the
	 * logs, never the customer.
	 *
	 * @since 8.0.0
	 */
	public const KIND_NOT_ANSWERING = 'not_answering';

	/**
	 * The operation itself was refused (a vendor's or the platform's answer to
	 * the request's content): shown to the customer as the platform describes it.
	 */
	public const KIND_REFUSED = 'refused';

	/**
	 * Classes a type has only at one status, keyed "{status} {type}". Read
	 * before {@see self::KIND_BY_TYPE}, which holds what the type means at any
	 * other status.
	 *
	 * @var array<string, string>
	 */
	private const KIND_BY_STATUS_AND_TYPE = array(
		'402 INSUFFICIENT_PRIVILEGES' => self::KIND_CREDITS,
		'503 FAILED_DEPENDENCY'       => self::KIND_BUSY,
	);

	/**
	 * @var array<string, string>
	 */
	private const KIND_BY_TYPE = array(
		'UNAUTHENTICATED'              => self::KIND_LICENSE,
		'SITE_NOT_REGISTERED'          => self::KIND_SITE_REGISTRATION,
		'VERIFICATION_ERROR'           => self::KIND_SITE_REGISTRATION,
		'CLOCK_SKEW'                   => self::KIND_CLOCK_SKEW,
		'REQUEST_REPLAYED'             => self::KIND_REQUEST_REPLAYED,
		'RATE_LIMITED'                 => self::KIND_RATE_LIMITED,
		'FAILED_DEPENDENCY'            => self::KIND_DEPENDENCY_FAILED,
		'CONNECTION_NOT_FOUND'         => self::KIND_CONNECTION_OUT_OF_SYNC,
		'CONNECTION_SIGNATURE_INVALID' => self::KIND_CONNECTION_OUT_OF_SYNC,
		'CONNECTION_INACTIVE'          => self::KIND_CONNECTION_OUT_OF_SYNC,
		'IDENTITY_REVOKED'             => self::KIND_IDENTITY_REVOKED,
		'SCOPE_INSUFFICIENT'           => self::KIND_SCOPE_INSUFFICIENT,
		'PROVIDER_UPSTREAM'            => self::KIND_PROVIDER_UPSTREAM,
		'INVALID_SOURCE_TOKEN'         => self::KIND_SOURCE_TOKEN,
		'SITE_IS_LOCAL'                => self::KIND_SITE_IS_LOCAL,
		Response::TYPE_NON_JSON        => self::KIND_NOT_ANSWERING,
	);

	/**
	 * @var Response
	 */
	private $response;

	/**
	 * @var string
	 */
	private $kind;

	/**
	 * @param Response $response The failed answer.
	 * @param string   $kind     One of the KIND_* constants.
	 */
	private function __construct( Response $response, string $kind ) {
		$this->response = $response;
		$this->kind     = $kind;
	}

	/**
	 * Classify an answer; null when it is not a failure.
	 *
	 * @param Response $response The platform's answer.
	 *
	 * @return self|null
	 */
	public static function from_response( Response $response ): ?self {

		if ( null === $response->error() && 400 > $response->status_code() ) {
			return null;
		}

		return self::of( $response );
	}

	/**
	 * Classify an answer the caller already knows is an error answer — one
	 * carrying an `error` block, as {@see Client::send()} throws on.
	 *
	 * @param Response $response The platform's error answer.
	 *
	 * @return self
	 */
	public static function of( Response $response ): self {

		$type = $response->error_type();
		$kind = self::KIND_BY_STATUS_AND_TYPE[ $response->status_code() . ' ' . $type ]
			?? self::KIND_BY_TYPE[ $type ]
			?? self::KIND_REFUSED;

		return new self( $response, $kind );
	}

	/**
	 * @return string One of the KIND_* constants.
	 */
	public function kind(): string {
		return $this->kind;
	}

	/**
	 * @return string The platform's `error.type`.
	 */
	public function type(): string {
		return $this->response->error_type();
	}

	/**
	 * @return int The answer's status.
	 */
	public function status(): int {
		return $this->response->status_code();
	}

	/**
	 * @return string The platform's description, the operator's text: for logs and the readers that match on it, never shown as the customer's sentence, never for deciding.
	 */
	public function description(): string {
		return $this->response->error_description();
	}

	/**
	 * @since 8.0.0
	 *
	 * @return string The platform's sentence for the customer, else its description: for showing, never for deciding.
	 */
	public function message(): string {
		return $this->response->error_message();
	}

	/**
	 * @since 8.0.0
	 *
	 * @return string The platform's `error.reason`, '' when it names none: a code that may be decided on.
	 */
	public function reason(): string {
		return $this->response->error_reason();
	}

	/**
	 * Seconds this server's clock is ahead of the platform's (negative when
	 * behind), measured from the answer's `Date` header; null when the answer
	 * carries no usable date.
	 *
	 * @return int|null
	 */
	public function clock_difference(): ?int {

		$raw = $this->response->raw_wp_response();

		return null === $raw ? null : Clock_Skew_Monitor::difference( $raw );
	}

	/**
	 * @return bool Whether the stored row and the platform disagree (contract 4.3).
	 */
	public function is_connection_out_of_sync(): bool {
		return self::KIND_CONNECTION_OUT_OF_SYNC === $this->kind;
	}

	/**
	 * Whether the answer came from a layer that refused the request before
	 * looking at any connection — the license, the site signature, a
	 * throttle, the platform's own store being down, the credit check — or
	 * that was not the platform's answer at all (an edge's error page), so it
	 * says nothing about whether a connection exists, whatever its status
	 * (contract 4.8). No stored connection may change because of one.
	 *
	 * @return bool
	 */
	public function says_nothing_about_a_connection(): bool {
		return in_array(
			$this->kind,
			array(
				self::KIND_LICENSE,
				self::KIND_SITE_REGISTRATION,
				self::KIND_CLOCK_SKEW,
				self::KIND_REQUEST_REPLAYED,
				self::KIND_RATE_LIMITED,
				self::KIND_BUSY,
				self::KIND_CREDITS,
				self::KIND_NOT_ANSWERING,
			),
			true
		);
	}

	/**
	 * @return Response The answer itself.
	 */
	public function response(): Response {
		return $this->response;
	}
}
