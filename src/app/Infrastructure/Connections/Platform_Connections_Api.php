<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Http;

/**
 * The platform routes a site uses to read and repair its own connections and
 * to share them with its other sites — staging-copies contract §3, all under
 * `/app/v1`:
 *
 *  - `GET  /connections`               this site's connections (§3.4)
 *  - `POST /connections/bind-existing` attach to an account on offer (§3.3)
 *  - `POST /connections/adopt`         bind to the account of a credential
 *                                      the site already holds (Facebook Lead
 *                                      Ads signed delivery design, §6.1)
 *  - `GET  /sites/sharing`             the site's sharing switch (§3.1)
 *  - `POST /sites/sharing`             set it (§3.1)
 *
 * Sent through {@see Platform_Http}: the license headers and the site's
 * request signature are added to every `/app/v1` request on
 * `http_request_args` by the shared injectors, and a rejection of the site's
 * credentials is heard on `http_response` by the observer that re-registers
 * the site. This class only builds requests and classifies answers — by
 * `statusCode` and `error.type`, never by description. Every call is safe to
 * repeat (§3.6).
 *
 * @since 8.0.0
 */
final class Platform_Connections_Api {

	/**
	 * Collaborator with Platform_Http's `get()` / `post()` shape; tests inject a
	 * recorder.
	 *
	 * @var object
	 */
	private $http;

	/**
	 * @param object|null $http Transport. Defaults to {@see Platform_Http}.
	 */
	public function __construct( ?object $http = null ) {

		$this->http = $http ?? new Platform_Http();
	}

	/**
	 * This site's connections, as the platform holds them.
	 *
	 * @return Connection_List_Result
	 */
	public function list_connections(): Connection_List_Result {

		$response = (array) $this->http->get( $this->url( '/connections' ) );
		$status   = $this->status( $response );

		if ( 200 !== $status || ! isset( $response['data'] ) || ! is_array( $response['data'] ) ) {
			return Connection_List_Result::failed( $status, $this->error_type( $response ) );
		}

		$connections = array();
		foreach ( $response['data'] as $entry ) {
			$connection = Platform_Connection::from_list_entry( $entry );
			if ( null !== $connection ) {
				$connections[] = $connection;
			}
		}

		return Connection_List_Result::ok( $connections );
	}

	/**
	 * Attach this site to an account another site of the same account put on
	 * offer. Repeating it for an account this site already holds answers the
	 * same connection and changes nothing.
	 *
	 * @param string $integration The integration slug.
	 * @param string $identity_id The account.
	 *
	 * @return Bind_Existing_Result
	 */
	public function bind_existing( string $integration, string $identity_id ): Bind_Existing_Result {

		$response = (array) $this->http->post(
			$this->url( '/connections/bind-existing' ),
			array(
				'integration' => $integration,
				'identity_id' => $identity_id,
			)
		);

		$status = $this->status( $response );

		if ( 200 === $status ) {
			$data       = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
			$connection = Platform_Connection::from_attachment( $integration, $data );

			return null !== $connection
				? Bind_Existing_Result::attached( $connection )
				: Bind_Existing_Result::not_attached( Bind_Existing_Result::OUTCOME_UNAVAILABLE, $status );
		}

		return Bind_Existing_Result::not_attached( $this->bind_refusal( $status, $this->error_type( $response ) ), $status );
	}

	/**
	 * Hand the platform a vendor credential this site already holds, so it
	 * binds this site to the account the credential names. The platform
	 * proves the credential before it stores anything ({@see Adopt_Result}).
	 * Adopting the same credential again from this site answers the same
	 * connection.
	 *
	 * The idempotency key is the caller's to give: what makes two adoptions
	 * the same request is the credential, and only the integration knows
	 * which of its fields identify it.
	 *
	 * @since 8.0.0
	 *
	 * @param string               $integration     The integration slug.
	 * @param array<string, mixed> $credentials     The credential, in the shape the integration's adopter reads.
	 * @param string               $label           The account as this site names it.
	 * @param string               $idempotency_key Sent as the `Idempotency-Key` header.
	 *
	 * @return Adopt_Result
	 */
	public function adopt( string $integration, array $credentials, string $label, string $idempotency_key ): Adopt_Result {

		$response = (array) $this->http->post(
			$this->url( '/connections/adopt' ),
			array(
				'integration' => $integration,
				'credentials' => $credentials,
				'label'       => $label,
			),
			array( 'Idempotency-Key' => $idempotency_key )
		);

		$status = $this->status( $response );

		if ( 200 === $status ) {
			$data       = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();
			$connection = Platform_Connection::from_adoption( $integration, $data );

			return null !== $connection
				? Adopt_Result::adopted( $connection )
				: Adopt_Result::not_adopted( Adopt_Result::OUTCOME_UNAVAILABLE, $status );
		}

		return Adopt_Result::not_adopted( $this->adopt_refusal( $status, $this->error_type( $response ) ), $status );
	}

	/**
	 * The site's sharing switch as the platform holds it.
	 *
	 * @return Connection_Sharing_State
	 */
	public function read_sharing(): Connection_Sharing_State {

		return $this->sharing_state( (array) $this->http->get( $this->url( '/sites/sharing' ) ) );
	}

	/**
	 * Set the site's sharing switch. The answer carries the value the platform
	 * now holds.
	 *
	 * @param bool $share Whether to share.
	 *
	 * @return Connection_Sharing_State
	 */
	public function write_sharing( bool $share ): Connection_Sharing_State {

		return $this->sharing_state(
			(array) $this->http->post( $this->url( '/sites/sharing' ), array( 'share_connections' => $share ) )
		);
	}

	/**
	 * Classify a bind-existing answer that is not a 200
	 * ({@see Bind_Existing_Result}).
	 *
	 * @param int    $status     The answer's status.
	 * @param string $error_type The answer's error.type.
	 *
	 * @return string One of Bind_Existing_Result's OUTCOME_* constants.
	 */
	private function bind_refusal( int $status, string $error_type ): string {

		$answers = array(
			403 => array( 'INSUFFICIENT_PRIVILEGES', Bind_Existing_Result::OUTCOME_NOT_OFFERED ),
			404 => array( 'RESOURCE_NOT_FOUND', Bind_Existing_Result::OUTCOME_ACCOUNT_GONE ),
			400 => array( 'VALIDATION_ERROR', Bind_Existing_Result::OUTCOME_INVALID ),
		);

		if ( isset( $answers[ $status ] ) ) {
			return $answers[ $status ][0] === $error_type ? $answers[ $status ][1] : Bind_Existing_Result::OUTCOME_UNAVAILABLE;
		}

		return $this->is_typed_refusal( $status, $error_type ) ? Bind_Existing_Result::OUTCOME_REFUSED : Bind_Existing_Result::OUTCOME_UNAVAILABLE;
	}

	/**
	 * Classify an adopt answer that is not a 200 ({@see Adopt_Result}). Only
	 * the three typed answers are the platform's decision; anything else is
	 * tried again.
	 *
	 * @param int    $status     The answer's status.
	 * @param string $error_type The answer's error.type.
	 *
	 * @return string One of Adopt_Result's OUTCOME_* constants.
	 */
	private function adopt_refusal( int $status, string $error_type ): string {

		$answers = array(
			404 => array( 'RESOURCE_NOT_FOUND', Adopt_Result::OUTCOME_NOT_SUPPORTED ),
			422 => array( 'VALIDATION_ERROR', Adopt_Result::OUTCOME_INVALID ),
			403 => array( 'INSUFFICIENT_PRIVILEGES', Adopt_Result::OUTCOME_NOT_PERMITTED ),
		);

		return isset( $answers[ $status ] ) && $answers[ $status ][0] === $error_type
			? $answers[ $status ][1]
			: Adopt_Result::OUTCOME_UNAVAILABLE;
	}

	/**
	 * @param array<string, mixed> $response A decoded sharing answer.
	 *
	 * @return Connection_Sharing_State
	 */
	private function sharing_state( array $response ): Connection_Sharing_State {

		$status = $this->status( $response );

		if ( 200 === $status && isset( $response['data']['share_connections'] ) && is_bool( $response['data']['share_connections'] ) ) {
			return Connection_Sharing_State::known( $response['data']['share_connections'] );
		}

		$error_type = $this->error_type( $response );

		// The controller answers 404; under enforcement the signature check
		// answers first, with 401 (contract §9). Both mean "register".
		if ( 'SITE_NOT_REGISTERED' === $error_type && ( 404 === $status || 401 === $status ) ) {
			return Connection_Sharing_State::unknown( Connection_Sharing_State::FAILURE_NOT_REGISTERED );
		}

		return Connection_Sharing_State::unknown( $this->is_typed_refusal( $status, $error_type ) ? Connection_Sharing_State::FAILURE_REFUSED : Connection_Sharing_State::FAILURE_UNAVAILABLE );
	}

	/**
	 * Whether an answer is the platform refusing the request: a 4xx carrying
	 * its typed envelope. 401 (the license or the site signature) and 429 (a
	 * throttle) are not about the request, and a 4xx without a type came from
	 * something in front of the platform.
	 *
	 * @param int    $status     The answer's status.
	 * @param string $error_type The answer's error.type.
	 *
	 * @return bool
	 */
	private function is_typed_refusal( int $status, string $error_type ): bool {

		return 400 <= $status && $status < 500 && 401 !== $status && 429 !== $status && '' !== $error_type;
	}

	/**
	 * @param array<string, mixed> $response A decoded answer ({@see Platform_Http}).
	 *
	 * @return int The status, 0 when there was no answer.
	 */
	private function status( array $response ): int {

		return isset( $response['statusCode'] ) ? (int) $response['statusCode'] : 0;
	}

	/**
	 * The envelope's `error.type`, top level or under `data`, '' when absent.
	 *
	 * @param array<string, mixed> $response A decoded answer.
	 *
	 * @return string
	 */
	private function error_type( array $response ): string {

		if ( isset( $response['error']['type'] ) && is_string( $response['error']['type'] ) ) {
			return $response['error']['type'];
		}

		return isset( $response['data']['error']['type'] ) && is_string( $response['data']['error']['type'] ) ? $response['data']['error']['type'] : '';
	}

	/**
	 * @param string $path Route under the platform API path.
	 *
	 * @return string Fully-qualified URL.
	 */
	private function url( string $path ): string {

		$base = defined( 'AUTOMATOR_API_URL' )
			? rtrim( (string) AUTOMATOR_API_URL, '/' )
			: 'https://api.automatorplugin.com';

		return $base . AUTOMATOR_PLATFORM_API_PATH . $path;
	}
}
