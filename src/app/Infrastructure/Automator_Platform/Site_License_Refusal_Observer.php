<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Outcomes;
use Uncanny_Automator\App\Infrastructure\Http\Request_Headers;

/**
 * Hears the platform refuse this site's license, whichever request heard it,
 * so the registration status tells the license first (decision k, 29 Oct):
 * a site the platform refuses by license is not "registered" to its admin.
 *
 * A refusal is remembered when the answer is the license gate's verdict: a
 * 401 `UNAUTHENTICATED` or a 402 `INSUFFICIENT_PRIVILEGES` whose
 * `error.reason` is one of {@see self::VERDICTS} (contract §4.10). The credit
 * cap (`credits_exhausted`) is the free account's usage, not the license; the
 * check being down (`license_service_unavailable`) is no verdict at all; an
 * answer with no reason, or one this plugin does not know, is told with its
 * type's generic sentence and not remembered.
 *
 * It is forgotten when the platform accepts a request that carried this
 * site's license: every route on the platform plane and the `/v3` action
 * surface passes the license gate first, so a 2xx there is the gate letting
 * the license through. A refusal recorded for another license key is dropped
 * when read ({@see Registration_Outcomes::license_refusal()}).
 *
 * Heard on `http_response`, like {@see Site_Credentials_Rejection_Observer}:
 * every transport that reaches the platform passes it. A background job's
 * answer reaches this site another way and is handed to {@see hear_answer()}.
 * Only an error status is decoded; a 2xx is decided on its status alone.
 *
 * @since 8.0.0
 */
final class Site_License_Refusal_Observer {

	/**
	 * The license gate's verdicts on this site's license (contract §4.10).
	 */
	private const VERDICTS = array(
		'license_headers_missing',
		'license_key_invalid',
		'license_product_mismatch',
		'site_not_on_license',
		'license_expired',
		'license_disabled',
		'license_inactive',
		'license_not_valid',
	);

	/**
	 * The status and type each verdict arrives with.
	 */
	private const VERDICT_ANSWERS = array( '401 UNAUTHENTICATED', '402 INSUFFICIENT_PRIVILEGES' );

	/**
	 * @var Registration_Outcomes
	 */
	private $outcomes;

	/**
	 * @var Platform_Url_Matcher
	 */
	private $urls;

	/**
	 * @param Registration_Outcomes|null $outcomes Where the refusal is kept.
	 * @param Platform_Url_Matcher|null  $urls     Which URLs are the platform's license-gated surfaces.
	 */
	public function __construct( ?Registration_Outcomes $outcomes = null, ?Platform_Url_Matcher $urls = null ) {

		$this->outcomes = $outcomes ?? new Registration_Outcomes();
		$this->urls     = $urls ?? Platform_Url_Matcher::from_constants();
	}

	/**
	 * @return void
	 */
	public function register_hooks(): void {

		add_filter( 'http_response', array( $this, 'observe' ), 10, 3 );
	}

	/**
	 * Filter callback. Never alters the response.
	 *
	 * @param mixed $response WP HTTP response.
	 * @param mixed $args     WP HTTP request args, after `http_request_args`.
	 * @param mixed $url      Request URL.
	 *
	 * @return mixed The response, unchanged.
	 */
	public function observe( $response, $args, $url ) {

		if ( ! is_array( $response ) || ! is_array( $args ) || ! is_string( $url ) || ! $this->is_licensed_platform_request( $args, $url ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 <= $code && 300 > $code ) {
			$this->outcomes->forget_license_refusal();

			return $response;
		}

		if ( 400 <= $code ) {
			$this->hear_answer( Response::from_wp_response( $response, 0.0 ) );
		}

		return $response;
	}

	/**
	 * Hear the platform's answer to a request this site sent with its
	 * license: a background job's outcome, or a decoded answer.
	 *
	 * @param Response $response The platform's answer.
	 *
	 * @return void
	 */
	public function hear_answer( Response $response ): void {

		if ( ! $this->is_license_verdict( $response ) ) {
			return;
		}

		$this->outcomes->record_license_refusal( $response->status_code(), $response->error_type(), $response->error_reason(), $response->error_message() );
	}

	/**
	 * Whether the request went to a license-gated platform surface carrying
	 * this site's license.
	 *
	 * @param array<string, mixed> $args WP HTTP request args.
	 * @param string               $url  Request URL.
	 *
	 * @return bool
	 */
	private function is_licensed_platform_request( array $args, string $url ): bool {

		$headers = is_array( $args['headers'] ?? null ) ? $args['headers'] : array();

		return $this->urls->is_site_signed( $url ) && Request_Headers::has( $headers, 'license-key' );
	}

	/**
	 * Whether the answer is the license gate's verdict on this site's license.
	 *
	 * @param Response $response The platform's answer.
	 *
	 * @return bool
	 */
	private function is_license_verdict( Response $response ): bool {

		return in_array( $response->status_code() . ' ' . $response->error_type(), self::VERDICT_ANSWERS, true )
			&& in_array( $response->error_reason(), self::VERDICTS, true );
	}
}
