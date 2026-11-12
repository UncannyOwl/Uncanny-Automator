<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Door;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Webhooks\Integrations_Webhook_Controller;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Webhooks\Receiver_Header;
use Uncanny_Automator\App\Infrastructure\Webhooks\Webhook_Router;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function Uncanny_Automator\App\Infrastructure\automator_platform_callback_receiver;
use function Uncanny_Automator\App\Infrastructure\automator_webhook_router;

/**
 * The platform's second door into this site, outside the REST API: the site's
 * own address with `?automator_platform=register-challenge` (the registration
 * challenge, {@see Registration_Challenge_Controller}) or
 * `?automator_platform=callback` (the callback receiver,
 * {@see Integrations_Webhook_Controller}). For a site whose REST API refuses
 * anonymous callers ("Only authenticated users can access the REST API"), so
 * the platform can neither confirm it nor deliver to it at `/wp-json/`.
 * {@see Registration_Door} decides when the platform is given this door.
 *
 * One implementation, two doors. A request is built as WordPress's REST
 * server builds it for the REST route (the same route, query, body params,
 * headers and raw body) and answered by the same code the route answers
 * with: WordPress's own check of the body ({@see WP_REST_Request::has_valid_params()},
 * the only check these routes have before their callback, as neither
 * declares arguments), then the challenge controller's
 * {@see Registration_Challenge_Controller::sign()}, or the webhook router's
 * {@see Webhook_Router::handle_answering_own_refusal()} of the receiver and its answer header
 * ({@see Receiver_Header::stamp()}). Every answer, refusal and side effect is
 * the REST route's: the signature and proof checks, the replay allowance, the
 * re-verification a refused delivery asks for. What the REST API adds around a
 * route is left out on purpose: its authentication filters and the plugins
 * that lock it hook there, and nothing here is authenticated by WordPress —
 * every request proves itself by its signature or the attempt it names.
 *
 * Its rules, each a fall-through to WordPress untouched when not met:
 *  - only a `POST`, with exactly one of the two values above — no other value
 *    reaches anything, and no other handler is reachable;
 *  - not in wp-admin, WP-Cron or XML-RPC: the door is the site's front end;
 *  - only while {@see Registration_Door::is_query_door_open()}: a site the
 *    platform reaches through the REST API has no second way in.
 *
 * It answers on `wp_loaded`, last: after WordPress, every plugin and the theme
 * have loaded — a delivery's drain runs recipes on `shutdown`, and they need
 * everything registered on `init` and `wp_loaded` — and before WordPress
 * parses the request, where the REST API, a site lock (`parse_request`), a
 * force-login or maintenance plugin, the theme and the canonical redirect
 * answer. It answers whichever host the request names, as the REST route does
 * (a move's check of the old address arrives under the old host). It sends
 * no-cache headers and ends the request; `shutdown` still runs, so the drain
 * a delivery scheduled runs after the answer, as at the REST route.
 *
 * @since 8.0.4
 */
final class Query_Door {

	/**
	 * The query variable that names the endpoint.
	 */
	public const QUERY_VAR = 'automator_platform';

	/**
	 * The registration challenge.
	 */
	public const CHALLENGE = 'register-challenge';

	/**
	 * The callback receiver.
	 */
	public const CALLBACK = 'callback';

	/**
	 * When on `wp_loaded` it answers: last.
	 */
	public const PRIORITY = PHP_INT_MAX;

	/**
	 * @var Registration_Challenge_Controller|null
	 */
	private $challenge;

	/**
	 * @var Integrations_Webhook_Controller|null
	 */
	private $receiver;

	/**
	 * @var Webhook_Router|null
	 */
	private $router;

	/**
	 * @var Registration_Door
	 */
	private $door;

	/**
	 * Sends an answer and ends the request.
	 *
	 * @var callable(WP_REST_Response): void
	 */
	private $send;

	/**
	 * Every collaborator is the one the REST route answers with, resolved on
	 * use: a page load that is not for this door builds none of them.
	 *
	 * @param Registration_Challenge_Controller|null $challenge The challenge controller; a new one by default.
	 * @param Integrations_Webhook_Controller|null   $receiver  The callback receiver; the REST route's by default.
	 * @param Webhook_Router|null                    $router    The router whose handling the receiver's answer is; the shared one by default.
	 * @param Registration_Door|null                 $door      Whether this door is open.
	 * @param callable|null                          $send      Sends an answer and ends the request ({@see emit()}, then exit) by default.
	 */
	public function __construct(
		?Registration_Challenge_Controller $challenge = null,
		?Integrations_Webhook_Controller $receiver = null,
		?Webhook_Router $router = null,
		?Registration_Door $door = null,
		?callable $send = null
	) {

		$this->challenge = $challenge;
		$this->receiver  = $receiver;
		$this->router    = $router;
		$this->door      = $door ?? new Registration_Door();
		$this->send      = $send ?? static function ( WP_REST_Response $response ): void {
			self::emit( $response );
			exit;
		};
	}

	/**
	 * This door's address for an endpoint: the site's own address with the
	 * query variable. On the site's own host, as the platform requires of both
	 * addresses.
	 *
	 * @param string $endpoint {@see CHALLENGE} or {@see CALLBACK}.
	 *
	 * @return string
	 */
	public static function url( string $endpoint ): string {

		return add_query_arg( self::QUERY_VAR, $endpoint, home_url( '/' ) );
	}

	/**
	 * Whether a URL is one of this door's addresses (on any host).
	 *
	 * @param string $url The URL.
	 *
	 * @return bool
	 */
	public static function is_door_address( string $url ): bool {

		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( ! is_string( $query ) ) {
			return false;
		}

		wp_parse_str( $query, $vars );

		return isset( $vars[ self::QUERY_VAR ] ) && self::is_endpoint( $vars[ self::QUERY_VAR ] );
	}

	/**
	 * Listen on `wp_loaded`, last. Registered once however often the
	 * bootstrap runs: the callback is static.
	 *
	 * @return void
	 */
	public function register_hooks(): void {

		add_action( 'wp_loaded', array( self::class, 'open' ), self::PRIORITY );
	}

	/**
	 * `wp_loaded` callback: answer the request when it is for this door.
	 *
	 * @return void
	 */
	public static function open(): void {

		( new self() )->handle_request();
	}

	/**
	 * Answer the request and end it, or leave it to WordPress untouched.
	 *
	 * @return void
	 */
	public function handle_request(): void {

		$endpoint = $this->requested_endpoint();
		if ( null === $endpoint || ! $this->door->is_query_door_open() ) {
			return;
		}

		( $this->send )( $this->answer( $endpoint, $this->request( $endpoint ) ) );
	}

	/**
	 * Send an answer as the REST API sends it: its status, its headers
	 * ({@see headers_for()}) and its data as JSON.
	 *
	 * @param WP_REST_Response $response The answer.
	 *
	 * @return void
	 */
	public static function emit( WP_REST_Response $response ): void {

		status_header( $response->get_status() );

		if ( ! headers_sent() ) {
			// WordPress's no-cache set removes this header rather than sending it (nocache_headers()).
			header_remove( 'Last-Modified' );
			foreach ( self::headers_for( $response ) as $name => $value ) {
				header( $name . ': ' . $value );
			}
		}

		echo wp_json_encode( $response->get_data() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A JSON answer, encoded as the REST API encodes it.
	}

	/**
	 * The headers an answer is sent with: WordPress's no-cache set (nothing
	 * between the platform and WordPress may keep an answer of this door), the
	 * JSON content type and `nosniff` the REST API sends, and the answer's own
	 * (the receiver's `X-Automator-Receiver`), each on one line.
	 *
	 * @param WP_REST_Response $response The answer.
	 *
	 * @return array<string, string> Header name => value.
	 */
	public static function headers_for( WP_REST_Response $response ): array {

		$headers = wp_get_nocache_headers();
		unset( $headers['Last-Modified'] );

		$headers['Content-Type']           = 'application/json; charset=' . get_option( 'blog_charset' );
		$headers['X-Content-Type-Options'] = 'nosniff';

		foreach ( $response->get_headers() as $name => $value ) {
			$headers[ $name ] = (string) preg_replace( '/\s+/', ' ', (string) $value );
		}

		return $headers;
	}

	/**
	 * The endpoint a request is for: a `POST` to the site's front end with
	 * exactly one of the two values; null otherwise.
	 *
	 * @return string|null
	 */
	private function requested_endpoint(): ?string {

		if ( is_admin() || wp_doing_cron() || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return null;
		}

		// Compared with two constants and used as nothing else; every request proves itself by its signature or the attempt it names.
		$value = isset( $_GET[ self::QUERY_VAR ] ) ? wp_unslash( $_GET[ self::QUERY_VAR ] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared with two constants, never used otherwise.
		if ( ! self::is_endpoint( $value ) ) {
			return null;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';

		return 'POST' === $method ? $value : null;
	}

	/**
	 * Whether a value names one of the two endpoints, exactly.
	 *
	 * @param mixed $value The query variable's value.
	 *
	 * @return bool
	 */
	private static function is_endpoint( $value ): bool {

		return self::CHALLENGE === $value || self::CALLBACK === $value;
	}

	/**
	 * The request, as {@see WP_REST_Server::serve_request()} builds it for
	 * the endpoint's REST route.
	 *
	 * @param string $endpoint The endpoint.
	 *
	 * @return WP_REST_Request
	 */
	private function request( string $endpoint ): WP_REST_Request {

		$request = new WP_REST_Request( 'POST', self::route( $endpoint ) );

		// What the REST server reads, read the same way (files aside: neither
		// endpoint reads them); the endpoints check what they use.
		$request->set_query_params( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authenticated by its signature or proof, as at the REST route.
		$request->set_body_params( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Authenticated by its signature or proof, as at the REST route.
		$request->set_headers( ( new WP_REST_Server() )->get_headers( wp_unslash( $_SERVER ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The signature covers the headers it reads; sanitizing would change them.
		$request->set_body( WP_REST_Server::get_raw_data() );

		return $request;
	}

	/**
	 * The endpoint's answer: the REST route's, from the same code.
	 *
	 * @param string          $endpoint The endpoint.
	 * @param WP_REST_Request $request  The request.
	 *
	 * @return WP_REST_Response
	 */
	private function answer( string $endpoint, WP_REST_Request $request ): WP_REST_Response {

		// WordPress's check before any route callback: a body that is not JSON
		// under a JSON content type is refused 400 `rest_invalid_json`, before
		// the endpoint sees it (and so without the receiver header).
		$valid = $request->has_valid_params();
		if ( is_wp_error( $valid ) ) {
			return rest_convert_error_to_response( $valid );
		}

		if ( self::CHALLENGE === $endpoint ) {
			return ( $this->challenge ?? new Registration_Challenge_Controller() )->sign( $request );
		}

		$response = ( $this->router ?? automator_webhook_router() )->handle_answering_own_refusal( $this->receiver ?? automator_platform_callback_receiver(), $request );
		Receiver_Header::stamp( $response, null, $request );

		return $response;
	}

	/**
	 * The REST route of an endpoint: one of the two, as
	 * {@see requested_endpoint()} returns it.
	 *
	 * @param string $endpoint The endpoint.
	 *
	 * @return string
	 */
	private static function route( string $endpoint ): string {

		return self::CHALLENGE === $endpoint ? Registration_Challenge_Controller::route() : Integrations_Webhook_Controller::route();
	}
}
