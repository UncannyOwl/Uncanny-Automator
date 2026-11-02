<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Throwable;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Register_Answer;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Challenge_Progress;
use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Connections\Default_Connection_Lookup;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Not_Found_Exception;
use Uncanny_Automator\App\Infrastructure\Connections\Multiple_Connections_Feature;
use Uncanny_Automator\App\Infrastructure\License\Payment_Required_Message;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;

use function Uncanny_Automator\App\Infrastructure\automator_connection_registry;

/**
 * What the customer is told when a step cannot reach its app through the
 * platform — the ONE owner of those sentences. Where running the step again
 * can help, the sentence says so; nothing re-runs a failed step on its own.
 *
 * Two sources lead here:
 *
 *  - {@see self::for_failure()}: the platform refused the request. One
 *    sentence per class of {@see Platform_Failure} (platform answers contract,
 *    section 4).
 *  - {@see self::for_lookup()}: there was no connection to send it with
 *    ({@see Default_Connection_Lookup}), told apart from a failed lookup and
 *    from a default account that exists but cannot be used.
 *  - {@see self::for_registration()}: where this site's registration stands
 *    ({@see Registration_Status}, handoff R1) — told on the registration
 *    notice, the system status page and the connect page, and for an app
 *    action held while the site's address is in question (R6). A
 *    registration problem never asks for an app to be reconnected.
 *
 * "Reconnect your <App> account" is said in exactly one place,
 * {@see self::reconnect()}: the vendor account is no longer authorized
 * (`IDENTITY_REVOKED`, or a row already marked revoked). No other answer
 * means the customer has to reconnect an app (contract 4.8). The one other
 * case that sends the customer to connect an app again is a connection the
 * platform no longer holds, which sync has confirmed from the platform's list
 * (flow spec §9.3, {@see self::not_on_platform()}); a passing answer never
 * does.
 *
 * An integration's caller adds nothing here: what is specific to its vendor
 * arrives as the platform's text, which the `refused` and `dependency_failed`
 * classes show — its `message`, the sentence written for the customer, else
 * its `description` ({@see Platform_Failure::message()}, flow spec §10.2).
 *
 * Sentences are escaped for output as they are built — the fixed words by
 * `esc_html_x()`, the app name and the platform's text by `esc_html()` — like
 * every action error message.
 *
 * @since 8.0.0
 */
final class Platform_Failure_Message {

	/**
	 * The builder for each class of failure: each value names the private
	 * static method below that {@see self::for_failure()} calls. A map keeps
	 * one decision point instead of a branch per class.
	 *
	 * @var array<string, string>
	 */
	private const BUILDERS = array(
		Platform_Failure::KIND_LICENSE                => 'license',
		Platform_Failure::KIND_SITE_REGISTRATION      => 'site_registration',
		Platform_Failure::KIND_CLOCK_SKEW             => 'clock_skew',
		Platform_Failure::KIND_REQUEST_REPLAYED       => 'request_replayed',
		Platform_Failure::KIND_RATE_LIMITED           => 'rate_limited',
		Platform_Failure::KIND_BUSY                   => 'busy',
		Platform_Failure::KIND_DEPENDENCY_FAILED      => 'dependency_failed',
		Platform_Failure::KIND_CREDITS                => 'credits',
		Platform_Failure::KIND_CONNECTION_OUT_OF_SYNC => 'connection_out_of_sync',
		Platform_Failure::KIND_IDENTITY_REVOKED       => 'identity_revoked',
		Platform_Failure::KIND_SCOPE_INSUFFICIENT     => 'scope_insufficient',
		Platform_Failure::KIND_PROVIDER_UPSTREAM      => 'provider_upstream',
		Platform_Failure::KIND_SOURCE_TOKEN           => 'source_token',
		Platform_Failure::KIND_SITE_IS_LOCAL          => 'site_is_local',
		Platform_Failure::KIND_NOT_ANSWERING          => 'not_answering',
		Platform_Failure::KIND_REFUSED                => 'refused',
	);

	/**
	 * @var string
	 */
	private $text;

	/**
	 * @var string
	 */
	private $error_code;

	/**
	 * @param string $text       The sentence, escaped for output.
	 * @param string $error_code Error_Code constant for the action log.
	 */
	private function __construct( string $text, string $error_code ) {
		$this->text       = $text;
		$this->error_code = $error_code;
	}

	/**
	 * What to tell the customer about a platform failure.
	 *
	 * @param Platform_Failure       $failure  Which thing failed.
	 * @param string                 $app_name The app, e.g. "Dropbox".
	 * @param Default_Connection_Lookup|null $lookup   The integration's connection after
	 *                                                 the answer was handled; read only
	 *                                                 for the connection-out-of-sync class.
	 *
	 * @return self
	 */
	public static function for_failure( Platform_Failure $failure, string $app_name, ?Default_Connection_Lookup $lookup = null ): self {

		$builder = self::BUILDERS[ $failure->kind() ];

		return self::$builder( $failure, $app_name, $lookup );
	}

	/**
	 * What to tell the customer about a platform failure on an integration's
	 * connection: for a connection the platform and this site disagree about,
	 * read after connection sync handled the answer, the integration's default
	 * connection decides between "being re-checked, run it again" and the
	 * connect prompt. The one place a caller's answer and a background job's
	 * outcome are both told from.
	 *
	 * @since 8.0.0
	 *
	 * @param Platform_Failure         $failure     Which thing failed.
	 * @param string                   $integration The integration code.
	 * @param string                   $app_name    The app, e.g. "Dropbox".
	 * @param Connection_Registry|null $registry    Reads the default connection; the shared one by default.
	 *
	 * @return self
	 */
	public static function for_integration( Platform_Failure $failure, string $integration, string $app_name, ?Connection_Registry $registry = null ): self {

		$registry = $failure->is_connection_out_of_sync() ? ( $registry ?? automator_connection_registry() ) : null;
		$lookup   = null !== $registry ? $registry->lookup_default( $integration )->for_v3_dispatch() : null;

		return self::for_failure( $failure, $app_name, $lookup );
	}

	/**
	 * What to tell the customer when there is no connection to send with.
	 *
	 * Found is reached only after a connection-out-of-sync answer that
	 * connection sync has already repaired: run the step again. A copy still
	 * quarantining what it inherited, a switched-off default and a default
	 * that needs connecting again each say what the row's state is
	 * ({@see self::unavailable()}). The lookup is read as a `/v3` request
	 * reads it ({@see Default_Connection_Lookup::for_v3_dispatch()}).
	 *
	 * @param Default_Connection_Lookup $lookup   The lookup's outcome.
	 * @param string                    $app_name The app, e.g. "Dropbox".
	 *
	 * @return self
	 */
	public static function for_lookup( Default_Connection_Lookup $lookup, string $app_name ): self {

		switch ( $lookup->state() ) {
			case Default_Connection_Lookup::STATE_FOUND:
				return self::rechecking( $app_name );
			case Default_Connection_Lookup::STATE_LOOKUP_FAILED:
				return new self(
					/* translators: %s: app name, e.g. "Dropbox" */
					sprintf( esc_html_x( "Automator could not read this site's %s connection from the database. Run the step again; if this keeps happening, check the site's database.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) ),
					Error_Code::SYSTEM_ERROR
				);
			case Default_Connection_Lookup::STATE_RECHECKING:
			case Default_Connection_Lookup::STATE_SWITCHED_OFF:
			case Default_Connection_Lookup::STATE_INACTIVE:
				return self::unavailable( $lookup->connection(), $app_name );
			case Default_Connection_Lookup::STATE_UNCHOSEN:
				return self::unchosen( $app_name );
			default:
				return self::not_connected( $app_name );
		}
	}

	/**
	 * What to tell about where this site's registration stands (handoff R1),
	 * on every surface that shows it and for an app action it refuses (R6).
	 * Never "reconnect your app": the app's connection is not the problem.
	 *
	 * @since 8.0.0
	 *
	 * @param Registration_Status $status Where the registration stands.
	 *
	 * @return self
	 */
	public static function for_registration( Registration_Status $status ): self {

		switch ( $status->state() ) {
			case Registration_Status::REGISTERED:
				return new self( esc_html_x( 'This site is registered with the Automator App Platform.', 'Automator App Platform', 'uncanny-automator' ), Error_Code::UNKNOWN );
			case Registration_Status::NO_LICENSE:
				return self::license_needed_to_connect();
			case Registration_Status::LICENSE_REFUSED:
				return self::license_refused( $status );
			case Registration_Status::PENDING:
				return self::registration_pending( $status );
			case Registration_Status::REVERIFYING:
				return self::site_registration();
			case Registration_Status::ADDRESS_CHANGED:
				/* translators: %s: this site's wp-admin URL */
				return new self( sprintf( esc_html_x( "This site's address changed, so its app actions are paused until an administrator answers the site-address notice at %s.", 'Automator App Platform', 'uncanny-automator' ), esc_url( admin_url() ) ), Error_Code::AUTH_FAILED );
			case Registration_Status::COPY_PENDING:
				return new self( esc_html_x( 'This site is being registered as a copy, so its app actions are paused until that finishes. That usually takes under a minute.', 'Automator App Platform', 'uncanny-automator' ), Error_Code::AUTH_FAILED );
			case Registration_Status::MOVE_PENDING:
				return new self( esc_html_x( 'This site is moving to its new address, so its app actions are paused until the Automator App Platform confirms the move.', 'Automator App Platform', 'uncanny-automator' ), Error_Code::AUTH_FAILED );
			case Registration_Status::REFUSED:
				return self::registration_refused( $status );
			case Registration_Status::UNREACHABLE:
				return self::registration_unreachable( $status );
			case Registration_Status::RETRYING:
				return self::registration_retrying( $status );
			default:
				return new self( esc_html_x( 'This site has not registered with the Automator App Platform yet. It registers on the next admin page load, or when an administrator re-checks the registration.', 'Automator App Platform', 'uncanny-automator' ), Error_Code::AUTH_FAILED );
		}
	}

	/**
	 * The platform is confirming this site. Once a check of this site's
	 * address failed, what it got is said instead of how long it usually takes.
	 *
	 * @param Registration_Status $status The pending registration.
	 *
	 * @return self
	 */
	private static function registration_pending( Registration_Status $status ): self {

		$check = self::challenge_check( $status );

		$text = '' === $check
			/* translators: %s: how long ago, e.g. "2 mins" */
			? sprintf( esc_html_x( 'The Automator App Platform is confirming this site (started %s ago). This usually takes under a minute.', 'Automator App Platform', 'uncanny-automator' ), esc_html( human_time_diff( $status->since() ) ) )
			/* translators: 1: how long ago, e.g. "2 mins"; 2: what the last check got, e.g. "it answered HTTP 403" */
			: sprintf( esc_html_x( 'The Automator App Platform is confirming this site (started %1$s ago), but its last check could not reach it: %2$s. It tries again on its own.', 'Automator App Platform', 'uncanny-automator' ), esc_html( human_time_diff( $status->since() ) ), $check );

		return new self( $text, Error_Code::AUTH_FAILED );
	}

	/**
	 * The platform could not reach this site's challenge endpoint, with what
	 * its last check got when the platform said.
	 *
	 * @param Registration_Status $status The unreachable registration.
	 *
	 * @return self
	 */
	private static function registration_unreachable( Registration_Status $status ): self {

		$check = self::challenge_check( $status );

		$text = '' === $check
			/* translators: %s: this site's registration challenge URL */
			? sprintf( esc_html_x( "The Automator App Platform could not reach this site to confirm it (at %s). Make sure this site's REST API is reachable from the internet, then re-check the registration. Security plugins, firewalls and password protection can block it.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $status->challenge_url() ) )
			/* translators: 1: this site's registration challenge URL; 2: what the last check got, e.g. "it answered HTTP 403" */
			: sprintf( esc_html_x( "The Automator App Platform could not reach this site to confirm it (at %1\$s): %2\$s. Make sure this site's REST API is reachable from the internet, then re-check the registration. Security plugins, firewalls and password protection can block it.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $status->challenge_url() ), $check );

		return new self( $text, Error_Code::AUTH_FAILED );
	}

	/**
	 * What the host challenge's last check of this site got, from the
	 * platform's outcome code in the status's `reason`; '' for a code that
	 * says nothing the admin can act on, or one this plugin does not know.
	 *
	 * @param Registration_Status $status A pending or unreachable registration.
	 *
	 * @return string Escaped for output.
	 */
	private static function challenge_check( Registration_Status $status ): string {

		if ( Challenge_Progress::OUTCOME_HTTP_STATUS === $status->reason() ) {
			/* translators: %d: the HTTP status this site answered, e.g. 403 */
			return 0 < $status->status() ? sprintf( esc_html_x( 'it answered HTTP %d', 'Automator App Platform', 'uncanny-automator' ), $status->status() ) : '';
		}

		$checks = array(
			'timeout'               => esc_html_x( 'it did not answer in time', 'Automator App Platform', 'uncanny-automator' ),
			'tls'                   => esc_html_x( 'its HTTPS certificate was not accepted', 'Automator App Platform', 'uncanny-automator' ),
			'connection_failed'     => esc_html_x( 'the connection to it failed', 'Automator App Platform', 'uncanny-automator' ),
			'host_does_not_resolve' => esc_html_x( 'its address was not found in DNS', 'Automator App Platform', 'uncanny-automator' ),
			'unknown_attempt'       => esc_html_x( 'another site answered at its address', 'Automator App Platform', 'uncanny-automator' ),
			'invalid_proof'         => esc_html_x( 'its answer could not be verified', 'Automator App Platform', 'uncanny-automator' ),
		);

		return $checks[ $status->reason() ] ?? '';
	}

	/**
	 * @return string The sentence, escaped for output.
	 */
	public function text(): string {
		return $this->text;
	}

	/**
	 * @return string Error_Code constant for the action log.
	 */
	public function error_code(): string {
		return $this->error_code;
	}

	/**
	 * The action failure carrying this message.
	 *
	 * @param int            $status   The HTTP status, as the exception code.
	 * @param Throwable|null $previous The platform answer behind it.
	 *
	 * @return Action_Error_Exception
	 */
	public function to_exception( int $status = 0, ?Throwable $previous = null ): Action_Error_Exception {
		return new Action_Error_Exception( $this->text, $this->error_code, $status, $previous );
	}

	/**
	 * The failure of a step that had no connection to send with
	 * ({@see self::for_lookup()}), carrying this message and its error code.
	 *
	 * @return Connection_Not_Found_Exception
	 */
	public function to_connection_not_found_exception(): Connection_Not_Found_Exception {
		return new Connection_Not_Found_Exception( $this->text, $this->error_code );
	}

	// ── one builder per class of failure ──

	/**
	 * 4.1 — `UNAUTHENTICATED`: the platform did not authenticate the request.
	 * Says nothing about any connection.
	 *
	 * Told as the platform's refusal with its reason, pointing at the license —
	 * the one thing behind this type the site owner can fix — but never as a
	 * verdict on the license. The type also covers malformed signature headers
	 * (4.2), and a platform that still answers the old way sends it for a
	 * throttle, an unknown connection or a vendor's dead token; only the
	 * platform's text, which is never read to decide, says which. A refusal
	 * that names its reason (contract §4.10) is the license's, and its message
	 * is the customer's sentence with its own next step: told as it is.
	 *
	 * @param Platform_Failure $failure The failure.
	 *
	 * @return self
	 */
	private static function license( Platform_Failure $failure ): self {

		if ( '' !== $failure->reason() && '' !== $failure->message() ) {
			return new self( esc_html( $failure->message() ), Error_Code::AUTH_FAILED );
		}

		$reason = $failure->message();

		$text = '' === $reason
			? esc_html_x( "The Automator App Platform did not accept this request. Check that this site's license is active and includes this site.", 'Automator App Platform', 'uncanny-automator' )
			/* translators: %s: the platform's reason */
			: sprintf( esc_html_x( "The Automator App Platform did not accept this request (%s). Check that this site's license is active and includes this site.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $reason ) );

		return new self( $text, Error_Code::AUTH_FAILED );
	}

	/**
	 * Decision k — the platform refused this site's license: its sentence for
	 * the customer, as {@see license()} tells it on an action; the one license
	 * sentence when it gave none.
	 *
	 * @since 8.0.0
	 *
	 * @param Registration_Status $status The LICENSE_REFUSED status.
	 *
	 * @return self
	 */
	private static function license_refused( Registration_Status $status ): self {

		if ( '' === $status->description() ) {
			return self::license_needed_to_connect();
		}

		return new self( esc_html( $status->description() ), Error_Code::AUTH_FAILED );
	}

	/**
	 * 4.2 / 4.6 — the site's registration is not accepted. The rejection
	 * observer has already asked for the registration to be repaired.
	 *
	 * @return self
	 */
	private static function site_registration(): self {
		return new self(
			esc_html_x( "This site's registration with the Automator App Platform is being repaired. Run the step again in a minute.", 'Automator App Platform', 'uncanny-automator' ),
			Error_Code::AUTH_FAILED
		);
	}

	/**
	 * R1 — the platform refused this site's registration: told with the
	 * platform's description (else its status and type), and when it is tried
	 * again on its own, if it is.
	 *
	 * @param Registration_Status $status The refused registration.
	 *
	 * @return self
	 */
	private static function registration_refused( Registration_Status $status ): self {

		if ( Registration_Status::REASON_HOST === $status->reason() ) {
			return self::host_refused( $status );
		}

		$why = '' !== $status->description()
			? esc_html( $status->description() )
			: esc_html( trim( $status->status() . ' ' . $status->type() ) );

		// The platform's customer sentence is whole (contract §4.10); an older
		// platform's text, or the status and type, is a fragment.
		$text = 1 === preg_match( '/[.!?]$/', $status->description() )
			/* translators: %s: the platform's sentence, ending in its own full stop */
			? sprintf( esc_html_x( "The Automator App Platform refused this site's registration. %s", 'Automator App Platform', 'uncanny-automator' ), $why )
			/* translators: %s: the platform's reason */
			: sprintf( esc_html_x( "The Automator App Platform refused this site's registration: %s.", 'Automator App Platform', 'uncanny-automator' ), $why );

		$text .= $status->until() > time()
			/* translators: %s: how long until the next attempt, e.g. "45 mins" */
			? ' ' . sprintf( esc_html_x( 'It is tried again on its own in %s; an administrator can re-check it sooner once the cause is fixed.', 'Automator App Platform', 'uncanny-automator' ), esc_html( human_time_diff( $status->until() ) ) )
			: ' ' . esc_html_x( 'An administrator can re-check it once the cause is fixed.', 'Automator App Platform', 'uncanny-automator' );

		return new self( $text, Error_Code::AUTH_FAILED );
	}

	/**
	 * L286 — the platform's challenge job refused this site's address. Told in
	 * plain words, with what to do, from what the plugin can know: the
	 * refusal's type, and this site's own address (the challenge URL it
	 * registered). The platform's text may be reworded at any time (contract
	 * 4.8), so it is never read here; it stays in the log. Its `error.reason`,
	 * when it sends one, is read once, upstream: a site's host that does
	 * not resolve yet is retried, not held, and told by
	 * {@see registration_retrying()} — except a move's new address, held here.
	 *
	 *  - `SITE_IS_LOCAL`: the address is not a public one;
	 *  - an `http://` address: the platform confirms a site only over HTTPS;
	 *  - an IP address: the platform confirms a site only at a domain name;
	 *  - anything else (`VALIDATION_ERROR`): the address has no public address
	 *    in DNS — none at all, or only a private one — most often a domain that
	 *    just changed and has not reached every resolver yet.
	 *
	 * Final until the address changes or an admin re-checks, so each sentence
	 * ends with the re-check.
	 *
	 * @since 8.0.0
	 *
	 * @param Registration_Status $status The refused registration, reason {@see Registration_Status::REASON_HOST}.
	 *
	 * @return self
	 */
	private static function host_refused( Registration_Status $status ): self {

		if ( Register_Answer::REASON_HOST_NOT_PINNABLE === $status->platform_reason() ) {
			return self::unpinnable_host( $status );
		}

		$url  = $status->challenge_url();
		$host = self::host_of( $url );

		if ( 'SITE_IS_LOCAL' === $status->type() ) {
			/* translators: %s: this site's host, e.g. "shop.internal" */
			$text = sprintf( esc_html_x( "This site's address (%s) is not a public address, so the Automator App Platform can't confirm it. Use the site's public address, then re-check the registration.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $host ) );
		} elseif ( 'http' === strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
			$text = esc_html_x( "This site's address uses HTTP, and the Automator App Platform confirms a site only over HTTPS. Serve the site over HTTPS (it needs an SSL certificate), then re-check the registration.", 'Automator App Platform', 'uncanny-automator' );
		} elseif ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			/* translators: %s: this site's IP address, e.g. "203.0.113.7" */
			$text = sprintf( esc_html_x( "This site's address is an IP address (%s), and the Automator App Platform confirms a site only at a domain name. Give the site a domain name, then re-check the registration.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $host ) );
		} else {
			/* translators: %s: this site's host, e.g. "shop.example.com" */
			$text = sprintf( esc_html_x( "The Automator App Platform couldn't find a public address for this site (%s) in DNS, so it couldn't confirm it. If the domain changed recently, DNS can take a while to update everywhere; re-check the registration once it has.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $host ) );
		}

		return new self( $text, Error_Code::AUTH_FAILED );
	}

	/**
	 * This site's host, lowercase, as its challenge URL names it. Read from the
	 * URL directly: parse_url() replaces some bytes of a non-ASCII host (the
	 * "ß" of straße.de), which would leave the sentence naming no address.
	 *
	 * @param string $url The challenge URL.
	 *
	 * @return string '' when the URL names no host.
	 */
	private static function host_of( string $url ): string {

		if ( 1 !== preg_match( '#^[a-z][a-z0-9+.\-]*://(?:[^@/?\#]*@)?(\[[^\]]*\]|[^:/?\#]*)#i', $url, $match ) ) {
			return '';
		}

		return strtolower( trim( $match[1], '[]' ) );
	}

	/**
	 * The platform reaches this site's host only in its A-label form
	 * (`host_not_pinnable`): not printable ASCII, a property of the address that
	 * DNS cannot change. The ASCII form is named when this server can work it
	 * out (the intl extension), with the platform's own non-transitional
	 * conversion so "ß" is kept, never turned into "ss", and the admin can set
	 * the site's address to it.
	 *
	 * @since 8.0.0
	 *
	 * @param Registration_Status $status The refused registration.
	 *
	 * @return self
	 */
	private static function unpinnable_host( Registration_Status $status ): self {

		$host  = self::host_of( $status->challenge_url() );
		$ascii = function_exists( 'idn_to_ascii' ) && '' !== $host ? idn_to_ascii( $host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46 ) : false;

		$text = is_string( $ascii ) && '' !== $ascii && $ascii !== $host && 1 === preg_match( '/^[\x21-\x7e]+$/', $ascii )
			/* translators: 1: this site's host as WordPress has it, e.g. "münchen-shop.de", 2: its ASCII form, e.g. "xn--mnchen-shop-thb.de" */
			? sprintf( esc_html_x( "This site's address (%1\$s) has characters the Automator App Platform can't use. Set WordPress Address (URL) and Site Address (URL) in Settings, General to its ASCII form, %2\$s, then re-check the registration.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $host ), esc_html( $ascii ) )
			/* translators: %s: this site's host as WordPress has it */
			: sprintf( esc_html_x( "This site's address (%s) isn't a host name the Automator App Platform can use. Check WordPress Address (URL) and Site Address (URL) in Settings, General, then re-check the registration.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $host ) );

		return new self( $text, Error_Code::AUTH_FAILED );
	}

	/**
	 * R1 — the last attempt failed in a way that can pass: why
	 * ({@see retrying_why()}), and when it is tried again.
	 *
	 * @param Registration_Status $status The registration being retried.
	 *
	 * @return self
	 */
	private static function registration_retrying( Registration_Status $status ): self {

		$text = self::retrying_why( $status );

		$text .= ' ' . ( $status->until() > time()
			/* translators: %s: how long until the next attempt, e.g. "1 min" */
			? sprintf( esc_html_x( 'It is tried again on its own in %s.', 'Automator App Platform', 'uncanny-automator' ), esc_html( human_time_diff( $status->until() ) ) )
			: esc_html_x( 'It is tried again on the next admin page load.', 'Automator App Platform', 'uncanny-automator' ) );

		return new self( $text, Error_Code::AUTH_FAILED );
	}

	/**
	 * Why the last attempt failed, ahead of when it is tried again. A host that
	 * did not resolve yet is told by its reason, from this site's own address;
	 * otherwise the platform's sentence, when it gave one.
	 *
	 * @since 8.0.0
	 *
	 * @param Registration_Status $status The registration being retried.
	 *
	 * @return string Escaped for output.
	 */
	private static function retrying_why( Registration_Status $status ): string {

		if ( Registration_Status::REASON_HOST_UNRESOLVED === $status->reason() ) {
			$host = self::host_of( $status->challenge_url() );

			/* translators: %s: this site's host, e.g. "shop.example.com" */
			return sprintf( esc_html_x( "The Automator App Platform couldn't find a public address for this site (%s) in DNS yet, so it couldn't confirm it. If the domain changed recently, DNS can take a while to update everywhere.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $host ) );
		}

		return '' !== $status->description()
			/* translators: %s: the platform's reason */
			? sprintf( esc_html_x( 'The last attempt to register this site with the Automator App Platform failed: %s.', 'Automator App Platform', 'uncanny-automator' ), esc_html( $status->description() ) )
			: esc_html_x( 'The last attempt to register this site with the Automator App Platform failed.', 'Automator App Platform', 'uncanny-automator' );
	}

	/**
	 * 4.2 — the server clock is too far from the platform's.
	 *
	 * @param Platform_Failure $failure The failure.
	 *
	 * @return self
	 */
	private static function clock_skew( Platform_Failure $failure ): self {

		$seconds = $failure->clock_difference();

		if ( null === $seconds ) {
			$text = esc_html_x( "The Automator App Platform refused this request because this server's clock is more than 5 minutes off. App connections need an accurate server clock: ask your host to synchronize it, then run the step again.", 'Automator App Platform', 'uncanny-automator' );
		} elseif ( 0 <= $seconds ) {
			/* translators: %s: a duration, e.g. "10 minutes" */
			$text = sprintf( esc_html_x( "The Automator App Platform refused this request because this server's clock is %s ahead of its own. App connections need an accurate server clock: ask your host to synchronize it, then run the step again.", 'Automator App Platform', 'uncanny-automator' ), esc_html( human_time_diff( 0, $seconds ) ) );
		} else {
			/* translators: %s: a duration, e.g. "10 minutes" */
			$text = sprintf( esc_html_x( "The Automator App Platform refused this request because this server's clock is %s behind its own. App connections need an accurate server clock: ask your host to synchronize it, then run the step again.", 'Automator App Platform', 'uncanny-automator' ), esc_html( human_time_diff( 0, abs( $seconds ) ) ) );
		}

		return new self( $text, Error_Code::SYSTEM_ERROR );
	}

	/**
	 * 4.2 — still a replay after the one resend the signature layer makes.
	 *
	 * @return self
	 */
	private static function request_replayed(): self {
		return new self(
			esc_html_x( 'The Automator App Platform received this request twice and refused the second copy. Run the step again.', 'Automator App Platform', 'uncanny-automator' ),
			Error_Code::API_ERROR
		);
	}

	/**
	 * 4.1 / 4.5 — a throttle, the platform's or the vendor's (the answer does
	 * not say which), with no short wait left to take inside the request.
	 *
	 * @param Platform_Failure $failure The failure.
	 *
	 * @return self
	 */
	private static function rate_limited( Platform_Failure $failure ): self {

		$text = esc_html_x( 'Too many requests were made in a short time. Run the step again in a few minutes.', 'Automator App Platform', 'uncanny-automator' );

		return new self( $text, Error_Code::from_http_status( $failure->status(), $text ) );
	}

	/**
	 * 4.1 / 4.2 — the platform's own license or replay store is down (503),
	 * with no short wait left to take inside the request.
	 *
	 * @param Platform_Failure $failure The failure.
	 *
	 * @return self
	 */
	private static function busy( Platform_Failure $failure ): self {

		$text = esc_html_x( 'The Automator App Platform is busy. Run the step again in a few minutes.', 'Automator App Platform', 'uncanny-automator' );

		return new self( $text, Error_Code::from_http_status( $failure->status(), $text ) );
	}

	/**
	 * 4.5 — the action's dependency failed: the vendor answered with an error
	 * of its own, or the handler could not finish a step the action needs
	 * (the platform answers both 424). The platform's text is the reason;
	 * running the step again can succeed.
	 *
	 * @param Platform_Failure $failure  The failure.
	 * @param string           $app_name The app.
	 *
	 * @return self
	 */
	private static function dependency_failed( Platform_Failure $failure, string $app_name ): self {

		/* translators: %s: app name, e.g. "Dropbox" */
		$fallback = sprintf( esc_html_x( '%s returned an unexpected error. Run the step again.', 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) );

		return self::described( $failure, $fallback );
	}

	/**
	 * 4.4 — the license or credit check `/v2` shares: what `/v2` shows.
	 *
	 * @param Platform_Failure $failure  The failure.
	 * @param string           $app_name The app.
	 *
	 * @return self
	 */
	private static function credits( Platform_Failure $failure, string $app_name ): self {

		// The credit-limit refusal is recognised by its description (the /v2
		// rule); every other 402 is shown in the platform's words for the customer.
		$text = Payment_Required_Message::is_credit_limit( $failure->description() )
			? esc_html( Payment_Required_Message::CREDIT_LIMIT )
			: esc_html( $failure->message() );

		if ( '' === $text ) {
			return self::refused( $failure, $app_name );
		}

		return new self( $text, Error_Code::from_http_status( $failure->status(), $text ) );
	}

	/**
	 * 4.3 — the stored row and the platform disagree. Connection sync has been
	 * asked to repair the row; what it found decides the sentence.
	 *
	 * @param Platform_Failure       $failure  The failure.
	 * @param string                 $app_name The app.
	 * @param Default_Connection_Lookup|null $lookup   The connection after the answer.
	 *
	 * @return self
	 */
	private static function connection_out_of_sync( Platform_Failure $failure, string $app_name, ?Default_Connection_Lookup $lookup ): self {

		unset( $failure );

		return null === $lookup ? self::rechecking( $app_name ) : self::for_lookup( $lookup, $app_name );
	}

	/**
	 * 4.3 / 4.5 — the vendor account is no longer authorized.
	 *
	 * @param Platform_Failure $failure  The failure.
	 * @param string           $app_name The app.
	 *
	 * @return self
	 */
	private static function identity_revoked( Platform_Failure $failure, string $app_name ): self {

		unset( $failure );

		return self::reconnect( $app_name );
	}

	/**
	 * The account has not granted a scope the step needs.
	 *
	 * @param Platform_Failure $failure  The failure.
	 * @param string           $app_name The app.
	 *
	 * @return self
	 */
	private static function scope_insufficient( Platform_Failure $failure, string $app_name ): self {

		unset( $failure );

		return self::authorize_again( $app_name );
	}

	/**
	 * 4.5 — the vendor did not answer, or a token could not be refreshed just
	 * now. Never "reconnect".
	 *
	 * @param Platform_Failure $failure  The failure.
	 * @param string           $app_name The app.
	 *
	 * @return self
	 */
	private static function provider_upstream( Platform_Failure $failure, string $app_name ): self {

		/* translators: %s: app name, e.g. "Dropbox" */
		$text = sprintf( esc_html_x( '%s did not answer the Automator App Platform in time. Run the step again.', 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) );

		return new self( $text, Error_Code::from_http_status( $failure->status(), $text ) );
	}

	/**
	 * The answer was not the platform's envelope — a load balancer's plain-text
	 * 503, an edge's error page, a proxy's login page. Told by its status in
	 * plain words: a server or throttle status means the platform is not
	 * answering right now; any other means an answer this site could not read,
	 * most often something in between rewriting it. The body it carried stays
	 * in the description, for the logs, and never reaches the customer.
	 *
	 * @since 8.0.0
	 *
	 * @param Platform_Failure $failure The failure.
	 *
	 * @return self
	 */
	private static function not_answering( Platform_Failure $failure ): self {

		$status = $failure->status();

		$text = 500 <= $status || 429 === $status || 408 === $status
			/* translators: %d: HTTP status, e.g. 503 */
			? sprintf( esc_html_x( 'The Automator App Platform is not answering right now (HTTP %d). Run the step again in a few minutes.', 'Automator App Platform', 'uncanny-automator' ), $status )
			/* translators: %d: HTTP status, e.g. 200 */
			: sprintf( esc_html_x( "The Automator App Platform's answer could not be read (HTTP %d). Run the step again in a few minutes. If it keeps happening, check that no proxy or firewall between this site and the Automator App Platform changes its answers.", 'Automator App Platform', 'uncanny-automator' ), $status );

		return new self( $text, Error_Code::from_http_status( $status, $text ) );
	}

	/**
	 * 4.6 — the upload's source token did not verify, or expired.
	 *
	 * @return self
	 */
	private static function source_token(): self {
		return new self(
			esc_html_x( 'The upload link expired or could not be verified. Run the step again.', 'Automator App Platform', 'uncanny-automator' ),
			Error_Code::API_ERROR
		);
	}

	/**
	 * 4.8 — `409 SITE_IS_LOCAL`: the site is local, and what it asked for is
	 * never available to it. The platform's text, else
	 * {@see self::local_site()}'s sentence; never retried.
	 *
	 * @param Platform_Failure $failure The failure.
	 *
	 * @return self
	 */
	private static function site_is_local( Platform_Failure $failure ): self {
		return self::described( $failure, self::local_site()->text() );
	}

	/**
	 * The one sentence a LOCAL site's webhook triggers, sharing and staging-copy
	 * controls are explained with (L247, {@see Local_Site_Status}) — and what a
	 * `register-site-for-webhooks` the site never sends is answered with.
	 *
	 * @return self
	 */
	public static function local_site(): self {

		$text = esc_html_x( 'Webhooks need the site at a public URL, and this site is local.', 'Automator App Platform', 'uncanny-automator' );

		return new self( $text, Error_Code::from_http_status( 409, $text ) );
	}

	/**
	 * A registered site whose license is no longer active (deactivated or
	 * lapsed) asked to connect an app: the platform would only refuse the start
	 * for the license, so nothing is sent.
	 *
	 * @since 8.0.0
	 *
	 * @return self
	 */
	public static function license_needed_to_connect(): self {
		return new self(
			esc_html_x( 'This site needs an active Automator license before it can connect apps. Activate it on the License screen.', 'Automator App Platform', 'uncanny-automator' ),
			Error_Code::AUTH_FAILED
		);
	}

	/**
	 * An upload a LOCAL site never sends (L247): the platform fetches every
	 * upload from a signed link to this site, whatever the file field names
	 * (a Media Library file, or a public URL this site serves on), and it
	 * never reaches a local site. Refused on the site, before anything is
	 * sent.
	 *
	 * @since 8.0.0
	 *
	 * @return self
	 */
	public static function local_site_upload(): self {

		$text = esc_html_x( 'This site is local, so the Automator App Platform cannot fetch the file from it. Run this step on a site at a public URL.', 'Automator App Platform', 'uncanny-automator' );

		return new self( $text, Error_Code::from_http_status( 409, $text ) );
	}

	/**
	 * 4.5 and the rest — the operation itself was refused: shown as the
	 * platform describes it, with the code its status gives.
	 *
	 * @param Platform_Failure $failure  The failure.
	 * @param string           $app_name The app.
	 *
	 * @return self
	 */
	private static function refused( Platform_Failure $failure, string $app_name ): self {

		/* translators: 1: app name, e.g. "Dropbox"; 2: HTTP status */
		$fallback = sprintf( esc_html_x( 'The %1$s request failed (HTTP %2$d).', 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ), $failure->status() );

		return self::described( $failure, $fallback );
	}

	/**
	 * The platform's sentence for the customer — its message, else its
	 * description; `$fallback` when it gives neither — with the code its
	 * status gives.
	 *
	 * @param Platform_Failure $failure  The failure.
	 * @param string           $fallback The sentence when the platform gives no text, escaped.
	 *
	 * @return self
	 */
	private static function described( Platform_Failure $failure, string $fallback ): self {

		$text = esc_html( $failure->message() );

		if ( '' === $text ) {
			$text = $fallback;
		}

		return new self( $text, Error_Code::from_http_status( $failure->status(), $text ) );
	}

	// ── connection sentences ──

	/**
	 * The ONLY "reconnect your <App> account" sentence.
	 *
	 * @param string $app_name The app.
	 *
	 * @return self
	 */
	private static function reconnect( string $app_name ): self {
		return new self(
			/* translators: %s: app name, e.g. "Dropbox" */
			sprintf( esc_html_x( '%1$s no longer accepts the account this site is connected to. Reconnect your %1$s account on the %1$s settings page.', 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) ),
			Error_Code::AUTH_FAILED
		);
	}

	/**
	 * The account must grant a permission it has not.
	 *
	 * @param string $app_name The app.
	 *
	 * @return self
	 */
	private static function authorize_again( string $app_name ): self {
		return new self(
			/* translators: %s: app name, e.g. "Dropbox" */
			sprintf( esc_html_x( 'Your %1$s account has not granted a permission this step needs. Authorize %1$s again on the %1$s settings page and allow every permission it asks for.', 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) ),
			Error_Code::PERMISSION_DENIED
		);
	}

	/**
	 * The row is marked for re-authorization and the answer that marked it did
	 * not name the missing scopes (the platform need not send them), so the
	 * sentence claims no more than the row's state.
	 *
	 * @param string $app_name The app.
	 *
	 * @return self
	 */
	private static function reauthorize( string $app_name ): self {
		return new self(
			/* translators: %s: app name, e.g. "Dropbox" */
			sprintf( esc_html_x( "This site's %1\$s connection must be authorized again before the step can run. Authorize %1\$s again on the %1\$s settings page.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) ),
			Error_Code::AUTH_FAILED
		);
	}

	/**
	 * The connection is being checked against the platform's list: the list
	 * could not be read yet, or sync repaired the row and the request sent
	 * again with it was refused too. A connection the list confirmed gone is
	 * {@see self::not_on_platform()} instead.
	 *
	 * @param string $app_name The app.
	 *
	 * @return self
	 */
	private static function rechecking( string $app_name ): self {
		return new self(
			/* translators: %s: app name, e.g. "Dropbox" */
			sprintf( esc_html_x( "Automator is re-checking this site's %s connection with the Automator App Platform. Run the step again in a minute.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) ),
			Error_Code::AUTH_FAILED
		);
	}

	/**
	 * The platform no longer holds this site's connection: sync found it
	 * missing from the platform's list after a refused call (flow spec §9.3).
	 * Nothing brings it back, so running the step again cannot help; the
	 * customer connects the app again. The settings page offers exactly that
	 * control for the row (it no longer resolves, so it shows "Connect"), and
	 * the new connection replaces the row for the same account.
	 *
	 * @since 8.0.0
	 *
	 * @param string $app_name The app.
	 *
	 * @return self
	 */
	private static function not_on_platform( string $app_name ): self {
		return new self(
			/* translators: %s: app name, e.g. "Dropbox" */
			sprintf( esc_html_x( "The Automator App Platform no longer holds this site's %1\$s connection. Connect %1\$s again on the %1\$s settings page.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) ),
			Error_Code::APP_NOT_CONNECTED
		);
	}

	/**
	 * The app has accounts and none is chosen. The choice ships with multiple
	 * connections; until then the settings page offers Connect, so the step
	 * says to connect one ({@see Multiple_Connections_Feature}).
	 *
	 * @since 8.0.0
	 *
	 * @param string $app_name The app, e.g. "Dropbox".
	 *
	 * @return self
	 */
	private static function unchosen( string $app_name ): self {

		if ( ! ( new Multiple_Connections_Feature() )->is_enabled() ) {
			return self::not_connected( $app_name );
		}

		return new self(
			/* translators: %1$s: app name, e.g. "Dropbox" */
			sprintf( esc_html_x( "No %1\$s account is chosen for this site's recipes yet. Choose one on the %1\$s settings page.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) ),
			Error_Code::APP_NOT_CONNECTED
		);
	}

	/**
	 * There is no connection: the connect prompt.
	 *
	 * @param string $app_name The app.
	 *
	 * @return self
	 */
	private static function not_connected( string $app_name ): self {
		return new self(
			/* translators: %s: app name, e.g. "Dropbox" */
			sprintf( esc_html_x( 'No %1$s account is connected to this site. Connect one on the %1$s settings page.', 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) ),
			Error_Code::APP_NOT_CONNECTED
		);
	}

	/**
	 * A row exists but cannot be used. A revoked row and a row marked for
	 * re-authorization keep their own sentences — each stays as it is until
	 * the customer authorizes the app again, so the sentence says so; the
	 * missing-permission one only when the answer named the scopes. So does a
	 * row the platform no longer holds. Anything else is switched off (a
	 * copy's quarantine) or being re-checked.
	 *
	 * @param Connection|null $connection The row, or null while a copy is quarantining.
	 * @param string          $app_name   The app.
	 *
	 * @return self
	 */
	private static function unavailable( ?Connection $connection, string $app_name ): self {

		if ( null !== $connection && $connection->is_revoked() ) {
			return self::reconnect( $app_name );
		}

		if ( null !== $connection && $connection->requires_reauth() ) {
			return array() !== (array) ( $connection->metadata()['required_scopes'] ?? array() )
				? self::authorize_again( $app_name )
				: self::reauthorize( $app_name );
		}

		if ( null !== $connection && $connection->is_not_on_platform() ) {
			return self::not_on_platform( $app_name );
		}

		return new self(
			/* translators: %s: app name, e.g. "Dropbox" */
			sprintf( esc_html_x( "This site's %1\$s connection is switched off or is being re-checked, so the step did not run. Open the %1\$s settings page to see its state.", 'Automator App Platform', 'uncanny-automator' ), esc_html( $app_name ) ),
			Error_Code::APP_NOT_CONNECTED
		);
	}
}
