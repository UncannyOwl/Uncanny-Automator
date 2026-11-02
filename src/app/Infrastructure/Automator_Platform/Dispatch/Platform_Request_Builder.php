<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch;

/**
 * The request parameters a platform call is sent with: endpoint, body, the
 * action's log context, headers and timeout — without the credentials, which
 * the integration's caller adds, because only it knows where they come from.
 *
 * The endpoint generation decides the shape. The legacy Slim platform on /v2
 * routes through the one endpoint of the integration, with an `action` field in
 * the body. The Laravel platform on /v3 and later serves a path for each action
 * (`POST /v3/dropbox/list-folder`), so the action leaves the body and becomes
 * the last segment of the path, kebab-cased, and the body carries flat fields
 * only.
 *
 * @internal Collaborator of {@see \Uncanny_Automator\App_Integrations\Automator_Platform\Caller}; not part of the plugin's API.
 *
 * @since 8.0.0
 */
final class Platform_Request_Builder {

	/**
	 * @param string                    $endpoint    The integration's endpoint, e.g. `v3/dropbox`.
	 * @param bool                      $is_modern   Whether the endpoint is /v3 or later.
	 * @param array<string, mixed>      $body        The caller's body, with its `action` field when it names one.
	 * @param array<string, mixed>|null $action_data The action's log context; what a resend replays.
	 * @param Platform_Request_Options  $options     What the call asked for.
	 *
	 * @return array{endpoint: string, body: array<string, mixed>, action: array<string, mixed>|null, headers?: array<string, mixed>, return_vendor_errors?: true, timeout?: int}
	 */
	public function build( string $endpoint, bool $is_modern, array $body, $action_data, Platform_Request_Options $options ): array {

		if ( $is_modern ) {
			$endpoint = $this->path_per_action( $endpoint, $body );
			unset( $body['action'] );
		}

		$params = array(
			'endpoint' => $endpoint,
			'body'     => $body,
			'action'   => $action_data,
		);

		$headers = $this->headers( $is_modern, $options );
		if ( array() !== $headers ) {
			$params['headers'] = $headers;
		}

		// Only a caller that asked gets a vendor error under `data` back as a response.
		if ( $options->returns_vendor_errors() ) {
			$params['return_vendor_errors'] = true;
		}

		$timeout = $options->timeout();
		if ( null !== $timeout ) {
			$params['timeout'] = $timeout;
		}

		return $params;
	}

	/**
	 * The integration's endpoint with the action as its last segment; the
	 * endpoint itself when the body names no action.
	 *
	 * @param string               $endpoint The integration's endpoint.
	 * @param array<string, mixed> $body     The caller's body.
	 *
	 * @return string
	 */
	private function path_per_action( string $endpoint, array $body ): string {

		$action = isset( $body['action'] ) ? (string) $body['action'] : '';

		return '' === $action
			? $endpoint
			: rtrim( $endpoint, '/' ) . '/' . str_replace( '_', '-', $action );
	}

	/**
	 * The headers of the call. The `Idempotency-Key` is /v3 only: the platform's
	 * middleware guards writes — registering and unregistering webhooks,
	 * creates, deletes — and a caller picks a deterministic key so a second
	 * click dedupes there. The caller's own headers are merged last and win on a
	 * collision: an upload-from-url action ships a key it has already
	 * canonicalised, which replaces any the generic `idempotency_key` argument
	 * would have minted. A background write of an owner action carries the
	 * platform's async-dispatch pair, `X-Automator-Async` and its own
	 * `X-Automator-Idempotency-Key`, whatever the endpoint generation.
	 *
	 * @param bool                     $is_modern Whether the endpoint is /v3 or later.
	 * @param Platform_Request_Options $options   What the call asked for.
	 *
	 * @return array<string, mixed>
	 */
	private function headers( bool $is_modern, Platform_Request_Options $options ): array {

		$headers = array();

		if ( $is_modern && null !== $options->idempotency_key() ) {
			$headers['Idempotency-Key'] = $options->idempotency_key();
		}

		if ( null !== $options->background_key() ) {
			$headers['X-Automator-Async']           = '1';
			$headers['X-Automator-Idempotency-Key'] = $options->background_key();
		}

		return array_merge( $headers, $options->headers() );
	}
}
