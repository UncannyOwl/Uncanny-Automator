<?php
/**
 * Facebook Lead Ads Page Adoption
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Http;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration;
use Uncanny_Automator\App\Infrastructure\Connections\Adopt_Result;
use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Owed_Work;
use Uncanny_Automator\App\Infrastructure\Connections\Pending_Operation_Key;
use Uncanny_Automator\App\Infrastructure\Connections\Platform_Connections_Api;

/**
 * Moves each Facebook page the site already holds a token for to signed lead
 * delivery, with no reconnect (signed delivery design §7.1).
 *
 * For every connected page without a subscribed connection: adopt the page's
 * token on the platform (`POST /app/v1/connections/adopt`, which proves the
 * token belongs to the page and carries lead access), keep the connection it
 * answers as the page's v3 row, and subscribe it. A page that went away is
 * torn down. The v2 credentials are only ever read: every v2 call (forms,
 * fields, the legacy lead fetch) keeps working off the local tokens.
 *
 * The platform's answer decides what happens to a page:
 *
 *  - adopted        the row is stored and subscribed;
 *  - invalid        the token is not the page's or no longer works, and
 *  - not permitted  the token lacks lead access: the page needs the user to
 *                   connect Facebook again. It is remembered with its reason
 *                   for the settings page and not sent again until the user
 *                   reconnects ({@see reconnected()});
 *  - not supported  (a platform without the adopter) and anything the
 *                   platform did not decide: tried again.
 *
 * A page is also adopted again, with the row it has, when the platform says
 * its stored token for the page is dead ({@see token_rejected()}): the
 * platform keeps the page connected and subscribed everywhere and delivers
 * its leads without fields until a site proves a fresh token. This site's
 * own token may be that token. The same connection comes back, so the row
 * and its subscription stand; a token dead here too is the adopter's 422,
 * and the page asks for a reconnect like any refused page. The page stays
 * marked until an adoption succeeds, so the user's reconnect proves the new
 * token too.
 *
 * The work is owed ({@see Owed_Work}): when registration completes, recovers
 * or moves the site; when the user connects or a page is refreshed; when a
 * signed lead says the platform's token for a page was rejected; and on the
 * first wp-admin load after an upgrade that finds a page without a
 * connection. It runs on its own cron event, on a wp-admin load when due
 * (never an ajax request), and with the heartbeat, which also catches a page
 * seen for the first time. Only while the registration is settled and the
 * platform plane is on: before that the secret on file is not this site's, so
 * a signed adoption would be refused or made for another site.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 */
class Facebook_Lead_Ads_Page_Adoption {

	/**
	 * WP-Cron hook that runs owed adoption.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'automator_facebook_lead_ads_page_adoption';

	/**
	 * Option holding the pages the platform refused, with why.
	 *
	 * @var string
	 */
	const REFUSALS_OPTION = 'automator_facebook_lead_ads_page_refusals';

	/**
	 * Option holding the pages whose token the platform rejected, to adopt
	 * again with this site's own token.
	 *
	 * @var string
	 */
	const READOPT_OPTION = 'automator_facebook_lead_ads_pages_to_readopt';

	/**
	 * Option holding the plugin version that last looked for pages to adopt.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'automator_facebook_lead_ads_page_adoption_version';

	/**
	 * How a page's leads reach the site.
	 */
	const MODE_SIGNED     = 'signed';
	const MODE_SETTING_UP = 'setting_up';
	const MODE_RECONNECT  = 'reconnect';
	const MODE_LEGACY     = 'legacy';

	/**
	 * Why a page needs the user to connect Facebook again.
	 */
	const REASON_INVALID       = 'invalid';
	const REASON_NOT_PERMITTED = 'not_permitted';

	/**
	 * Refusals by the platform's answer.
	 */
	const REFUSAL_REASONS = array(
		Adopt_Result::OUTCOME_INVALID       => self::REASON_INVALID,
		Adopt_Result::OUTCOME_NOT_PERMITTED => self::REASON_NOT_PERMITTED,
	);

	/**
	 * The platform's connection routes.
	 *
	 * @var Platform_Connections_Api
	 */
	private $api;

	/**
	 * Says whether the registration is settled.
	 *
	 * @var Platform_Site_Registration
	 */
	private $registration;

	/**
	 * The pages and their tokens.
	 *
	 * @var Facebook_Lead_Ads_App_Helpers
	 */
	private $helpers;

	/**
	 * The pages' rows.
	 *
	 * @var Facebook_Lead_Ads_Page_Connections
	 */
	private $connections;

	/**
	 * Subscribes and tears down a page's connection.
	 *
	 * @var Facebook_Lead_Ads_Page_Subscription
	 */
	private $subscription;

	/**
	 * Keeps unfinished adoption owed.
	 *
	 * @var Owed_Work
	 */
	private $owed;

	/**
	 * The Idempotency-Key of each page's adoption under way.
	 *
	 * @var Pending_Operation_Key
	 */
	private $keys;

	/**
	 * Constructor.
	 *
	 * @param Platform_Connections_Api            $api          The platform's connection routes.
	 * @param Platform_Site_Registration          $registration Says whether the registration is settled.
	 * @param Facebook_Lead_Ads_App_Helpers       $helpers      The pages and their tokens.
	 * @param Facebook_Lead_Ads_Page_Connections  $connections  The pages' rows.
	 * @param Facebook_Lead_Ads_Page_Subscription $subscription Subscribes and tears down a page's connection.
	 * @param Owed_Work|null                      $owed         Keeps unfinished adoption owed.
	 * @param Pending_Operation_Key|null          $keys         The Idempotency-Key of each adoption under way.
	 */
	public function __construct(
		Platform_Connections_Api $api,
		Platform_Site_Registration $registration,
		Facebook_Lead_Ads_App_Helpers $helpers,
		Facebook_Lead_Ads_Page_Connections $connections,
		Facebook_Lead_Ads_Page_Subscription $subscription,
		?Owed_Work $owed = null,
		?Pending_Operation_Key $keys = null
	) {
		$this->api          = $api;
		$this->registration = $registration;
		$this->helpers      = $helpers;
		$this->connections  = $connections;
		$this->subscription = $subscription;
		$this->owed         = $owed ?? new Owed_Work( 'fbla_page_adoption', self::CRON_HOOK );
		$this->keys         = $keys ?? new Pending_Operation_Key();
	}

	/**
	 * Attach adoption to every moment it is owed and runs.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'automator_platform_site_registered', array( $this, 'owe' ) );
		add_action( 'automator_platform_site_recovered', array( $this, 'owe' ) );
		add_action( 'automator_platform_site_migrated', array( $this, 'owe' ) );
		add_action( 'automator_facebook_lead_ads_page_adoption', array( $this, 'run_owed' ) );
		add_action( 'automator_platform_site_heartbeat', array( $this, 'run_with_heartbeat' ), 20 );
		add_action( 'admin_init', array( $this, 'run_owed_on_admin_load' ) );
	}

	/**
	 * Owe adoption: it runs on its own cron event, now.
	 *
	 * @return void
	 */
	public function owe() {
		$this->owed->owe();
	}

	/**
	 * The user connected Facebook again: every page is asked for anew,
	 * refused ones included.
	 *
	 * @return void
	 */
	public function reconnected() {
		automator_delete_option( self::REFUSALS_OPTION );
		$this->owe();
	}

	/**
	 * A page was refreshed in settings: owe adoption when the page is not
	 * signed yet. A refused page waits for the user to reconnect.
	 *
	 * @param string $page_id The Facebook page id.
	 *
	 * @return void
	 */
	public function page_refreshed( $page_id ) {

		if ( $this->is_pending( (string) $page_id ) ) {
			$this->owe();
		}
	}

	/**
	 * A signed lead says the platform's stored token for the page was
	 * rejected: adopt the page again with this site's token. Nothing else
	 * changes; the page stays connected and subscribed. A burst of such leads
	 * owes one run. A page already refused waits for the user to reconnect,
	 * and a page this site holds no token for has nothing to offer.
	 *
	 * @param string $page_id The Facebook page id.
	 *
	 * @return void
	 */
	public function token_rejected( $page_id ) {

		$page_id = (string) $page_id;

		if ( $this->is_refused( $page_id ) || ! isset( $this->connected_pages()[ $page_id ] ) ) {
			return;
		}

		$readopts = $this->readopts();

		if ( ! isset( $readopts[ $page_id ] ) ) {
			$readopts[ $page_id ] = time();
			automator_update_option( self::READOPT_OPTION, $readopts, false );
		}

		$this->owe();
	}

	/**
	 * Facebook Lead Ads is being disconnected: end every page's connection,
	 * forget the refusals and re-adoptions and owe nothing. Safe to run twice.
	 *
	 * @return void
	 */
	public function disconnected() {
		$this->subscription->tear_down_all();
		automator_delete_option( self::REFUSALS_OPTION );
		automator_delete_option( self::READOPT_OPTION );
		$this->owed->settle();
	}

	/**
	 * Run adoption if it is owed. Cron callback.
	 *
	 * @return void
	 */
	public function run_owed() {

		if ( $this->owed->is_owed() ) {
			$this->run();
		}
	}

	/**
	 * On a wp-admin load: owe adoption the first time this plugin version
	 * finds a page without a connection, and run owed adoption when its
	 * attempt is due. Never on an ajax request: a visitor's request must not
	 * pay for platform round trips.
	 *
	 * @return void
	 */
	public function run_owed_on_admin_load() {

		if ( wp_doing_ajax() ) {
			return;
		}

		$this->owe_after_upgrade();

		if ( Platform_Http::platform_enabled() && $this->owed->is_due() ) {
			$this->run();
		}
	}

	/**
	 * The heartbeat's run: catches pages seen for the first time, and settles
	 * owed adoption. Nothing before the registration settles.
	 *
	 * @return void
	 */
	public function run_with_heartbeat() {

		if ( Platform_Http::platform_enabled() && $this->registration->is_settled() ) {
			$this->run();
		}
	}

	/**
	 * Adopt and subscribe every connected page that is not signed, and tear
	 * down the connections of pages that went away.
	 *
	 * @return bool Whether every page is settled: signed, or refused.
	 */
	public function run() {

		if ( ! Platform_Http::platform_enabled() || ! $this->registration->is_settled() ) {
			$this->owed->failed();
			return false;
		}

		$pages = $this->connected_pages();

		// Nothing to adopt. Rows are left alone: a disconnect ends them
		// itself ({@see disconnected()}), and a connect stores its pages in
		// two writes, so a run between them must not read "no pages" as
		// "every page went away".
		if ( array() === $pages ) {
			$this->owed->settle();
			return true;
		}

		$this->forget_readopts_except( $pages );

		$rows      = $this->rows_by_page( $pages );
		$unsettled = 0;

		foreach ( $pages as $page ) {
			if ( ! $this->is_refused( $page['id'] ) && ! $this->settle_page( $page, $rows[ $page['id'] ] ?? null ) ) {
				++$unsettled;
			}
		}

		if ( 0 === $unsettled ) {
			$this->owed->settle();
			return true;
		}

		$this->owed->failed();

		return false;
	}

	/**
	 * How the page's leads reach the site now.
	 *
	 * @param string $page_id The Facebook page id.
	 *
	 * @return string One of the MODE_* constants.
	 */
	public function delivery_mode( $page_id ) {

		if ( ! Platform_Http::platform_enabled() || ! $this->registration->is_settled() ) {
			return self::MODE_LEGACY;
		}

		if ( $this->is_refused( (string) $page_id ) ) {
			return self::MODE_RECONNECT;
		}

		$row = $this->connections->row_for_page( (string) $page_id );

		return null !== $row && $this->connections->is_subscribed( $row ) ? self::MODE_SIGNED : self::MODE_SETTING_UP;
	}

	/**
	 * Why the platform refused the page's token.
	 *
	 * @param string $page_id The Facebook page id.
	 *
	 * @return string One of the REASON_* constants, '' when not refused.
	 */
	public function refusal_reason( $page_id ) {

		$refusals = $this->refusals();
		$reason   = $refusals[ (string) $page_id ]['reason'] ?? '';

		return is_string( $reason ) ? $reason : '';
	}

	/**
	 * Bring one page to signed delivery: adopt it when it has no row, or when
	 * the platform rejected its stored token; then subscribe it.
	 *
	 * @param array{id: string, name: string, token: string} $page The page.
	 * @param Connection|null                                $row  The page's row, if it has one.
	 *
	 * @return bool Whether the page is settled: subscribed, or refused.
	 */
	private function settle_page( array $page, $row ) {

		if ( null === $row || $this->is_readopt_owed( $page['id'] ) ) {
			$row = $this->adopt( $page ) ?? $row;
		}

		if ( $this->is_refused( $page['id'] ) ) {
			return true;
		}

		if ( null === $row || $this->is_readopt_owed( $page['id'] ) ) {
			return false;
		}

		return $this->connections->is_subscribed( $row ) || $this->subscription->subscribe( $row );
	}

	/**
	 * Adopt the page's token and keep the connection the platform answers.
	 *
	 * Each adoption is its own operation with its own Idempotency-Key, reused
	 * only to retry it while its outcome is unknown ({@see
	 * Pending_Operation_Key}). A key derived from the page and token would
	 * answer an adoption after a disconnect from the one before it, for a
	 * connection the platform has since disconnected. The fingerprint covers
	 * the token and the page's name, the body fields that can change while
	 * an adoption is pending: the platform refuses a key it saw with another
	 * body with 422 VALIDATION_ERROR, which reads as a refused token.
	 *
	 * @param array{id: string, name: string, token: string} $page The page.
	 *
	 * @return Connection|null The page's row, null when not adopted.
	 */
	private function adopt( array $page ) {

		$page_id = $page['id'];
		$subject = Facebook_Lead_Ads_Page_Connections::INTEGRATION . ':' . $page_id;

		$result = $this->api->adopt(
			Facebook_Lead_Ads_Page_Connections::INTEGRATION,
			array(
				'page_id'           => $page_id,
				'page_access_token' => $page['token'],
			),
			$page['name'],
			$this->keys->key_for( 'adopt', $subject, hash( 'sha256', (string) wp_json_encode( array( $page['token'], $page['name'] ) ) ) )
		);

		$this->keys->conclude( 'adopt', $subject, $result->http_status() );

		if ( $result->is_refusal() ) {
			$this->refuse( $page_id, self::REFUSAL_REASONS[ $result->outcome() ] );
			return null;
		}

		$connection = $result->connection();

		if ( null === $connection ) {
			automator_log( 'Adopting the Facebook page ' . $page_id . ' did not complete (' . $result->outcome() . ', HTTP ' . $result->http_status() . '); it will be retried.', 'Facebook_Lead_Ads_Page_Adoption', false );
			return null;
		}

		$row = $this->connections->store_adopted( $page_id, $page['name'], $connection );

		if ( null === $row ) {
			automator_log( 'The adopted Facebook page connection ' . $connection->connection_id() . ' could not be stored; it will be retried.', 'Facebook_Lead_Ads_Page_Adoption', false );
			return null;
		}

		$this->forget_readopt( $page_id );

		return $row;
	}

	/**
	 * Each active page row by its page; rows of pages that went away are torn
	 * down.
	 *
	 * @param array<string, array{id: string, name: string, token: string}> $pages The connected pages, by id.
	 *
	 * @return array<string, Connection>
	 */
	private function rows_by_page( array $pages ) {

		$rows = array();

		foreach ( $this->connections->page_rows() as $row ) {

			$page_id = $this->connections->page_id_of( $row );

			if ( ! isset( $pages[ $page_id ] ) ) {
				$this->subscription->tear_down( $row );
				continue;
			}

			if ( ! isset( $rows[ $page_id ] ) ) {
				$rows[ $page_id ] = $row;
			}
		}

		return $rows;
	}

	/**
	 * The pages the site holds a token for, while the integration is connected.
	 *
	 * @return array<string, array{id: string, name: string, token: string}> By page id. Use each
	 *                                                                    page's `id`: a numeric key
	 *                                                                    reads back as an integer.
	 */
	private function connected_pages() {

		if ( ! $this->helpers->has_connection() ) {
			return array();
		}

		$pages = array();

		foreach ( $this->helpers->get_pages_credentials() as $page ) {

			$page_id = isset( $page['id'] ) && is_scalar( $page['id'] ) ? (string) $page['id'] : '';
			$token   = isset( $page['access_token'] ) && is_string( $page['access_token'] ) ? $page['access_token'] : '';

			if ( '' === $page_id || '' === $token ) {
				continue;
			}

			$pages[ $page_id ] = array(
				'id'    => $page_id,
				'name'  => isset( $page['name'] ) && is_scalar( $page['name'] ) ? (string) $page['name'] : '',
				'token' => $token,
			);
		}

		return $pages;
	}

	/**
	 * Owe adoption once per plugin version, when a connected page has no
	 * subscribed connection and was not refused.
	 *
	 * @return void
	 */
	private function owe_after_upgrade() {

		if ( AUTOMATOR_PLUGIN_VERSION === automator_get_option( self::VERSION_OPTION, '' ) ) {
			return;
		}

		automator_update_option( self::VERSION_OPTION, AUTOMATOR_PLUGIN_VERSION );

		foreach ( $this->connected_pages() as $page ) {
			if ( $this->is_pending( $page['id'] ) ) {
				$this->owe();
				return;
			}
		}
	}

	/**
	 * Whether the page still needs adopting or subscribing.
	 *
	 * @param string $page_id The Facebook page id.
	 *
	 * @return bool
	 */
	private function is_pending( $page_id ) {

		if ( $this->is_refused( $page_id ) ) {
			return false;
		}

		$row = $this->connections->row_for_page( $page_id );

		return null === $row || ! $this->connections->is_subscribed( $row ) || $this->is_readopt_owed( $page_id );
	}

	/**
	 * @param string $page_id The Facebook page id.
	 *
	 * @return bool Whether the platform rejected its stored token and this
	 *              site has not proven its own since.
	 */
	private function is_readopt_owed( $page_id ) {
		return isset( $this->readopts()[ (string) $page_id ] );
	}

	/**
	 * @param string $page_id The Facebook page id.
	 *
	 * @return void
	 */
	private function forget_readopt( $page_id ) {

		$readopts = $this->readopts();

		if ( isset( $readopts[ (string) $page_id ] ) ) {
			unset( $readopts[ (string) $page_id ] );
			automator_update_option( self::READOPT_OPTION, $readopts, false );
		}
	}

	/**
	 * Forget the re-adoptions of pages that are no longer connected.
	 *
	 * @param array<string, array{id: string, name: string, token: string}> $pages The connected pages.
	 *
	 * @return void
	 */
	private function forget_readopts_except( array $pages ) {

		$readopts = $this->readopts();
		$kept     = array_intersect_key( $readopts, $pages );

		if ( count( $kept ) !== count( $readopts ) ) {
			automator_update_option( self::READOPT_OPTION, $kept, false );
		}
	}

	/**
	 * @return array<string, int> When each page's token was reported rejected, by page id.
	 */
	private function readopts() {

		$readopts = automator_get_option( self::READOPT_OPTION, array() );

		return is_array( $readopts ) ? $readopts : array();
	}

	/**
	 * @param string $page_id The Facebook page id.
	 *
	 * @return bool
	 */
	private function is_refused( $page_id ) {
		return '' !== $this->refusal_reason( $page_id );
	}

	/**
	 * Remember that the platform refused the page's token, and why.
	 *
	 * @param string $page_id The Facebook page id.
	 * @param string $reason  One of the REASON_* constants.
	 *
	 * @return void
	 */
	private function refuse( $page_id, $reason ) {

		$refusals             = $this->refusals();
		$refusals[ $page_id ] = array(
			'reason' => $reason,
			'at'     => time(),
		);

		automator_update_option( self::REFUSALS_OPTION, $refusals, false );
	}

	/**
	 * @return array<string, array{reason: string, at: int}>
	 */
	private function refusals() {

		$refusals = automator_get_option( self::REFUSALS_OPTION, array() );

		return is_array( $refusals ) ? $refusals : array();
	}
}
