<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch;

/**
 * What one `api_request()` was asked to do, read from its `$args` in one place.
 *
 * `$args` is a bag: the flags below, plus whatever an integration's own hooks
 * want from it (`prepare_request_credentials()` can scope a credential by it).
 * The whole bag, defaults filled in, goes on to those hooks as {@see args()}.
 *
 *  - `exclude_credentials` (bool, default false): send no credentials.
 *  - `exclude_error_check` (bool, default false): hand the answer back as it is.
 *  - `include_timeout` (int|null, default the caller's request timeout): seconds.
 *  - `idempotency_key` (string|null, default null): the `Idempotency-Key` of a /v3 write.
 *  - `headers` (array, default none): headers the caller built itself.
 *  - `async` (bool, default true): whether an owner action that can run in the background may send
 *    this call as a background write. Only a literal `false` keeps it synchronous.
 *  - `return_vendor_errors` (bool, default false): a vendor error nested under `data` comes back as a response
 *    instead of an exception; envelope and transport errors still throw.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App_Integrations\Automator_Platform\Caller}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Platform_Request_Options {

	/**
	 * @var array<string, mixed>
	 */
	private $args;

	/**
	 * The key the call goes out as a background write with, when it is one.
	 *
	 * @var string|null
	 */
	private $background_key = null;

	/**
	 * @param array<string, mixed> $args The arguments, defaults filled in.
	 */
	private function __construct( array $args ) {

		$this->args = $args;
	}

	/**
	 * @param array|string|object $args            The `api_request()` arguments.
	 * @param int|null            $default_timeout The caller's request timeout in seconds, if it set one.
	 *
	 * @return self
	 */
	public static function from_args( $args, $default_timeout ): self {

		return new self(
			wp_parse_args(
				$args,
				array(
					'exclude_credentials'  => false,
					'exclude_error_check'  => false,
					'include_timeout'      => $default_timeout,
					'idempotency_key'      => null,
					'headers'              => array(),
					'async'                => true,
					'return_vendor_errors' => false,
				)
			)
		);
	}

	/**
	 * The arguments with their defaults, as the integration's hooks receive them.
	 *
	 * @return array<string, mixed>
	 */
	public function args(): array {

		return $this->args;
	}

	/**
	 * @return bool Whether the answer goes through the integration's error check.
	 */
	public function checks_errors(): bool {

		return ! wp_validate_boolean( $this->args['exclude_error_check'] );
	}

	/**
	 * @return bool Whether the request carries the integration's credentials.
	 */
	public function includes_credentials(): bool {

		return ! wp_validate_boolean( $this->args['exclude_credentials'] );
	}

	/**
	 * @return int|null Seconds, or null when the request keeps the client's own timeout.
	 */
	public function timeout(): ?int {

		$timeout = null === $this->args['include_timeout'] ? 0 : absint( $this->args['include_timeout'] );

		return 0 < $timeout ? $timeout : null;
	}

	/**
	 * @return string|null The caller's idempotency key, or null when it gave none.
	 */
	public function idempotency_key(): ?string {

		$key = $this->args['idempotency_key'];

		return is_string( $key ) && '' !== $key ? $key : null;
	}

	/**
	 * @return array<string, mixed> The headers the caller built itself.
	 */
	public function headers(): array {

		return is_array( $this->args['headers'] ) ? $this->args['headers'] : array();
	}

	/**
	 * @return bool Whether the call may go out as a background write: only a literal `false` for `async` says no.
	 */
	public function allows_background(): bool {

		return false !== $this->args['async'];
	}

	/**
	 * @return bool Whether a vendor error nested under `data` comes back as a response instead of an exception.
	 */
	public function returns_vendor_errors(): bool {

		return wp_validate_boolean( $this->args['return_vendor_errors'] );
	}

	/**
	 * These options with the key the call goes out as a background write with.
	 *
	 * The key is not an argument of the call: it comes from the owner action, and only for the action's own
	 * write, so the caller works it out and hands it on here.
	 *
	 * @param string|null $key The owner action's dispatch key; null when the call is not a background write.
	 *
	 * @return self A copy; these options are unchanged.
	 */
	public function with_background_key( ?string $key ): self {

		$copy                 = clone $this;
		$copy->background_key = $key;

		return $copy;
	}

	/**
	 * @return string|null The background dispatch key, or null when the call is not a background write.
	 */
	public function background_key(): ?string {

		return $this->background_key;
	}
}
