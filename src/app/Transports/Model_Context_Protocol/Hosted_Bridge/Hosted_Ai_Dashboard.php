<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge;

use DateTimeImmutable;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Client\Client_Context_Service;
use WP_Error;

/**
 * The AI Dashboard's view of the hosted service: connected AI apps, AI
 * activity and the AI tool use meter.
 *
 * Every read needs the fixed manage_options capability, never the filterable
 * Automator capability, because AI activity can hold what an AI wrote about a
 * site. Dashboard filters are checked here and mapped to the hosted query, so
 * no raw parameter reaches the hosted service. With several hosted accounts
 * connected, apps are combined and activity is merged; most sites have one.
 */
final class Hosted_Ai_Dashboard {

	/** Dashboard result => hosted outcomes. */
	private const RESULT_OUTCOMES = array(
		'done'        => array( 'success' ),
		'failed'      => array( 'tool_error' ),
		'not_allowed' => array( 'access_denied', 'allowance_denied' ),
		'unconfirmed' => array( 'unconfirmed' ),
	);

	/** Hosted outcome => Dashboard result. */
	private const OUTCOME_RESULTS = array(
		'success'          => 'done',
		'tool_error'       => 'failed',
		'access_denied'    => 'not_allowed',
		'allowance_denied' => 'not_allowed',
		'unconfirmed'      => 'unconfirmed',
	);

	/** Connection event results; the hosted service records tool uses only. */
	private const CONNECTION_RESULTS = array( 'approved', 'refused', 'disconnected' );

	/** Dashboard sort column => hosted sort. */
	private const SORTS = array(
		'occurred_at' => 'occurred_at',
		'summary'     => 'summary',
		'client'      => 'app',
		'result'      => 'outcome',
	);

	private const VENDOR_NAMES = array(
		'claude'  => 'Claude',
		'chatgpt' => 'ChatGPT',
		'gemini'  => 'Gemini',
		'other'   => 'AI app',
	);

	private const MAX_PER_PAGE = 100;

	private const MAX_WINDOW = 10000;

	private const MAX_DAYS = 90;

	private const EMPTY_PAGE = array(
		'entries' => array(),
		'total'   => 0,
	);

	private static ?Hosted_Ai_Dashboard $instance = null;

	private Hosted_Read_Credential_Reaper $reaper;

	private Hosted_Site_Client $client;

	/**
	 * The shared instance.
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Constructor.
	 */
	public function __construct( ?Hosted_Read_Credential_Reaper $reaper = null, ?Hosted_Site_Client $client = null ) {
		$this->client = $client ?? new Hosted_Site_Client();
		$this->reaper = $reaper ?? Hosted_Read_Credential_Reaper::instance();
	}

	/**
	 * Every AI app connected through the hosted service, each with the admin
	 * who brought it: the WordPress admin whose live hosted connection it
	 * comes through. Liveness already requires that admin to manage options.
	 *
	 * @return list<array{id:string,brand:?string,name:string,connected_by:array{id:int,name:string},last_used_at:?string}>|WP_Error
	 */
	public function connections() {
		$apps = $this->live_apps();
		if ( $apps instanceof WP_Error ) {
			return $apps;
		}

		$connections = array();
		foreach ( $apps as $entry ) {
			$connections[] = array(
				'id'           => $entry['app']['id'],
				'brand'        => $entry['app']['brand'],
				'name'         => $this->app_name( $entry['app'] ),
				'connected_by' => $this->admin( $entry['user_id'] ),
				'last_used_at' => $entry['app']['last_used_at'],
			);
		}

		return $connections;
	}

	/**
	 * The vendor and brand of each AI app connected through the hosted
	 * service, for the setup wizard's check of the app the owner picked. The
	 * vendor comes from the app's sign-in address, so it names the app the
	 * owner added (claude.ai, not Claude Code); the brand only picks an icon.
	 *
	 * Read fresh: adding an AI changes nothing on the site, so a kept list would
	 * hold the wizard back for up to five minutes.
	 *
	 * @return list<array{vendor:string,brand:?string}>|WP_Error
	 */
	public function app_kinds() {
		$apps = $this->live_apps( true );
		if ( $apps instanceof WP_Error ) {
			return $apps;
		}

		$kinds = array();
		foreach ( $apps as $entry ) {
			$kinds[] = array(
				'vendor' => $entry['app']['vendor'],
				'brand'  => $entry['app']['brand'],
			);
		}

		return $kinds;
	}

	/**
	 * Every app listed through a live credential, once. A list missing an
	 * account is not a confirmed list, so it is never returned as one: an
	 * account that failed returns its failure, and a sign-in that could not be
	 * verified returns a retryable outage.
	 *
	 * @param bool $fresh Whether to ask the hosted service instead of reusing a kept list.
	 *
	 * @return array<string,array{app:array{id:string,name:string,vendor:string,brand:?string,last_used_at:?string},user_id:int}>|WP_Error
	 */
	private function live_apps( bool $fresh = false ) {
		if ( ! $this->may_read() ) {
			return $this->forbidden();
		}

		$credentials = $this->reaper->live_credentials_in_connection_order();
		if ( $this->reaper->has_unconfirmed_credentials() ) {
			return $this->unconfirmed();
		}

		$apps   = array();
		$failed = null;
		foreach ( $credentials as $credential ) {
			$reply = $fresh ? $this->client->fresh_apps( $credential ) : $this->client->apps( $credential );
			if ( $reply instanceof WP_Error ) {
				if ( Hosted_Site_Client::INVALID_CLIENT === $reply->get_error_code() ) {
					if ( $this->reaper->forget_invalid_credential( $credential ) ) {
						continue;
					}
					return $this->unconfirmed();
				}
				if ( Hosted_Site_Client::APPS_LIMIT === $reply->get_error_code() ) {
					return $reply;
				}
				$failed = $failed ?? $reply;
				continue;
			}
			foreach ( $reply as $app ) {
				// An app that comes through two admins stays with the one who connected first, at its latest use.
				if ( isset( $apps[ $app['id'] ] ) ) {
					if ( (string) $app['last_used_at'] > (string) $apps[ $app['id'] ]['app']['last_used_at'] ) {
						$apps[ $app['id'] ]['app']['last_used_at'] = $app['last_used_at'];
					}
					continue;
				}
				$apps[ $app['id'] ] = array(
					'app'     => $app,
					'user_id' => (int) $credential['user_id'],
				);
			}
		}

		return $failed ?? $apps;
	}

	/**
	 * One page of AI activity for the Dashboard's filters.
	 *
	 * @param array<string,mixed> $filters page, per_page, client_id, type, result, date_from, date_to, search, orderby, order.
	 *
	 * @return array{entries:list<array<string,mixed>>,total:int}|WP_Error
	 */
	public function activity( array $filters ) {
		if ( ! $this->may_read() ) {
			return $this->forbidden();
		}

		$query = $this->hosted_query( $filters );
		if ( null === $query || $query instanceof WP_Error ) {
			return $query ?? self::EMPTY_PAGE;
		}

		$credentials = $this->reaper->live_credentials();
		if ( $this->reaper->has_unconfirmed_credentials() ) {
			return $this->unconfirmed();
		}
		if ( 1 === count( $credentials ) ) {
			$page = $this->activity_for_credential( $credentials[0], $query );

			return $page instanceof WP_Error ? $page : $this->dashboard_page( $page['entries'], $page['total'] );
		}

		return $this->merged_activity( $credentials, $query );
	}

	/**
	 * The AI tool use meter, or null when it does not apply: the user cannot
	 * manage options, not exactly one hosted account is connected, or the
	 * account has no usage plan or could not be read.
	 *
	 * @return array{unlimited:bool,used:int,limit:?int,resets_at:string}|null
	 */
	public function ai_tool_uses(): ?array {
		if ( ! $this->may_read() ) {
			return null;
		}

		$credentials = $this->reaper->live_credentials();
		if ( 1 !== count( $credentials ) || $this->reaper->has_unconfirmed_credentials() ) {
			return null;
		}

		$usage = $this->client->usage( $credentials[0] );
		if ( $usage instanceof WP_Error && Hosted_Site_Client::INVALID_CLIENT === $usage->get_error_code() ) {
			$this->reaper->forget_invalid_credential( $credentials[0] );
		}
		if ( $usage instanceof WP_Error || null === $usage['resets_at'] ) {
			return null;
		}

		$unlimited = 'paid' === $usage['plan'] || null === $usage['limit'];
		if ( ! $unlimited && $usage['limit'] < 1 ) {
			return null;
		}

		return array(
			'unlimited' => $unlimited,
			'used'      => $usage['used'],
			'limit'     => $unlimited ? null : $usage['limit'],
			'resets_at' => $usage['resets_at'],
		);
	}

	/**
	 * Check the Dashboard filters and build the hosted query.
	 *
	 * @return array<string,int|string>|WP_Error|null Null when the filters can match no tool use.
	 */
	private function hosted_query( array $filters ) {
		$page     = $this->integer( $filters['page'] ?? 1 );
		$per_page = $this->integer( $filters['per_page'] ?? 20 );
		if ( null === $page || null === $per_page || $page < 1 || $per_page < 1 || $per_page > self::MAX_PER_PAGE || $page * $per_page > self::MAX_WINDOW ) {
			return $this->invalid( 'page' );
		}

		$query = array(
			'page'     => $page,
			'per_page' => $per_page,
		);

		$client_id = $filters['client_id'] ?? null;
		if ( null !== $client_id ) {
			if ( ! is_string( $client_id ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{1,64}$/D', $client_id ) ) {
				return $this->invalid( 'client_id' );
			}
			$query['app_id'] = $client_id;
		}

		$type   = $filters['type'] ?? null;
		$result = $filters['result'] ?? null;
		if ( null !== $type && ! in_array( $type, array( 'tool_use', 'connection' ), true ) ) {
			return $this->invalid( 'type' );
		}
		if ( null !== $result && ( ! is_string( $result ) || ( ! isset( self::RESULT_OUTCOMES[ $result ] ) && ! in_array( $result, self::CONNECTION_RESULTS, true ) ) ) ) {
			return $this->invalid( 'result' );
		}
		if ( 'connection' === $type || in_array( $result, self::CONNECTION_RESULTS, true ) ) {
			return null;
		}
		if ( null !== $result ) {
			$query['outcome'] = implode( ',', self::RESULT_OUTCOMES[ $result ] );
		}

		$range = $this->date_range( $filters['date_from'] ?? null, $filters['date_to'] ?? null );
		if ( $range instanceof WP_Error ) {
			return $range;
		}
		$query += $range;

		$search = $filters['search'] ?? null;
		if ( null !== $search ) {
			if ( ! is_string( $search ) || '' === $search || mb_strlen( $search ) > 100 ) {
				return $this->invalid( 'search' );
			}
			$query['search'] = $search;
		}

		$orderby = $filters['orderby'] ?? null;
		if ( null !== $orderby ) {
			if ( ! is_string( $orderby ) || ! isset( self::SORTS[ $orderby ] ) ) {
				return $this->invalid( 'orderby' );
			}
			$query['sort'] = self::SORTS[ $orderby ];
		}

		$order = $filters['order'] ?? null;
		if ( null !== $order ) {
			if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
				return $this->invalid( 'order' );
			}
			$query['order'] = $order;
		}

		return $query;
	}

	/**
	 * Site-time days as hosted instants: from the start of date_from to the
	 * start of the day after date_to.
	 *
	 * @param mixed $date_from Y-m-d or null.
	 * @param mixed $date_to   Y-m-d or null.
	 *
	 * @return array<string,string>|WP_Error
	 */
	private function date_range( $date_from, $date_to ) {
		$range = array();
		$from  = null === $date_from ? null : $this->day( $date_from );
		$to    = null === $date_to ? null : $this->day( $date_to );
		if ( false === $from || false === $to ) {
			return $this->invalid( 'date_from' );
		}

		if ( null !== $from ) {
			$range['from'] = $from->format( 'c' );
		}
		if ( null !== $to ) {
			$to          = $to->modify( '+1 day' );
			$range['to'] = $to->format( 'c' );
		}
		// Elapsed time, as the hosted service measures it: a range across a clock change is an hour longer or shorter.
		if ( null !== $from && null !== $to && ( $to <= $from || $to->getTimestamp() - $from->getTimestamp() > self::MAX_DAYS * DAY_IN_SECONDS ) ) {
			return $this->invalid( 'date_to' );
		}

		return $range;
	}

	/**
	 * Midnight of a Y-m-d day in the site's time zone, or false.
	 *
	 * @param mixed $value Day.
	 *
	 * @return DateTimeImmutable|false
	 */
	private function day( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			return false;
		}

		return DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
	}

	/**
	 * Merge activity from several accounts and page it. The total counts every
	 * matching entry; a page needs each account's first entries up to its end.
	 * A page missing an account is not a confirmed page, so an account that
	 * fails fails the page.
	 *
	 * @param list<array<string,mixed>> $credentials Live credentials.
	 * @param array<string,int|string>  $query       Hosted query.
	 *
	 * @return array{entries:list<array<string,mixed>>,total:int}|WP_Error
	 */
	private function merged_activity( array $credentials, array $query ) {
		$offset  = ( $query['page'] - 1 ) * $query['per_page'];
		$needed  = $offset + $query['per_page'];
		$streams = array();
		$total   = 0;

		foreach ( $credentials as $credential ) {
			$account = $this->account_activity( $credential, $query, $needed );
			if ( $account instanceof WP_Error ) {
				return $account;
			}
			$streams[] = $account['entries'];
			$total    += $account['total'];
		}

		$merged = $this->merge_in_order( $streams, (string) ( $query['sort'] ?? 'occurred_at' ), 'asc' !== ( $query['order'] ?? 'desc' ), $needed );

		return $this->dashboard_page( array_slice( $merged, $offset, $query['per_page'] ), $total );
	}

	/**
	 * Merge accounts' logs without reordering any of them: each step takes the
	 * account head that comes first in the hosted order, and the earlier account
	 * wins a full tie. A deeper fetch only extends the logs' tails, so positions
	 * already shown never change from one page to the next.
	 *
	 * @param list<list<array<string,mixed>>> $streams    Each account's entries, in the hosted order.
	 * @param string                          $key        Sort key.
	 * @param bool                            $descending Whether the key sorts descending.
	 * @param int                             $limit      How many merged entries are needed.
	 *
	 * @return list<array<string,mixed>>
	 */
	private function merge_in_order( array $streams, string $key, bool $descending, int $limit ): array {
		$merged = array();
		$heads  = array_fill( 0, count( $streams ), 0 );

		for ( $taken = 0; $taken < $limit; $taken++ ) {
			$next = null;
			foreach ( $streams as $index => $stream ) {
				if ( isset( $stream[ $heads[ $index ] ] ) && ( null === $next || $this->precedes( $stream[ $heads[ $index ] ], $streams[ $next ][ $heads[ $next ] ], $key, $descending ) ) ) {
					$next = $index;
				}
			}
			if ( null === $next ) {
				break;
			}
			$merged[] = $streams[ $next ][ $heads[ $next ] ];
			++$heads[ $next ];
		}

		return $merged;
	}

	/**
	 * Whether one entry comes strictly before another in the hosted order: the
	 * sort key, then newest first to the microsecond, then record ID.
	 */
	private function precedes( array $a, array $b, string $key, bool $descending ): bool {
		$order = strcmp( $this->sort_value( $a, $key ), $this->sort_value( $b, $key ) );
		if ( $descending ) {
			$order = -$order;
		}
		if ( 0 === $order ) {
			$order = strcmp( $b['sort_at'], $a['sort_at'] );
		}
		if ( 0 === $order ) {
			$order = strcmp( $a['id'], $b['id'] );
		}

		return $order < 0;
	}

	/**
	 * One account's first entries for a merged log, all or nothing.
	 *
	 * @param array<string,mixed>      $credential Live credential.
	 * @param array<string,int|string> $query      Hosted query.
	 *
	 * @return array{entries:list<array<string,mixed>>,total:int}|WP_Error
	 */
	private function account_activity( array $credential, array $query, int $needed ) {
		$size    = min( self::MAX_PER_PAGE, $needed );
		$entries = array();
		$total   = 0;
		$pages   = (int) ceil( $needed / $size );
		for ( $hosted_page = 1; $hosted_page <= $pages; $hosted_page++ ) {
			$reply = $this->client->activity(
				$credential,
				array_merge(
					$query,
					array(
						'page'     => $hosted_page,
						'per_page' => $size,
					)
				)
			);
			if ( $reply instanceof WP_Error ) {
				if ( Hosted_Site_Client::INVALID_CLIENT === $reply->get_error_code() ) {
					return $this->reaper->forget_invalid_credential( $credential ) ? self::EMPTY_PAGE : $this->unconfirmed();
				}
				return $reply;
			}
			$total   = $reply['total'];
			$entries = array_merge( $entries, $reply['entries'] );
			if ( count( $reply['entries'] ) < $size ) {
				break;
			}
		}

		return array(
			'entries' => $entries,
			'total'   => $total,
		);
	}

	/**
	 * An ended credential contributes no activity; an uncertain read remains an error.
	 *
	 * @since 8.0.0
	 * @param array $credential Stored credential.
	 * @param array $query Activity filters.
	 * @return array|WP_Error
	 */
	private function activity_for_credential( array $credential, array $query ) {
		$reply = $this->client->activity( $credential, $query );
		if ( $reply instanceof WP_Error && Hosted_Site_Client::INVALID_CLIENT === $reply->get_error_code() ) {
			return $this->reaper->forget_invalid_credential( $credential ) ? self::EMPTY_PAGE : $this->unconfirmed();
		}
		return $reply;
	}

	/**
	 * The value an entry is sorted by. A summary or app sort uses the key the
	 * hosted query ordered by, which it returns with each entry; comparing it
	 * byte for byte matches the query exactly, whatever the database's locale.
	 */
	private function sort_value( array $entry, string $key ): string {
		switch ( $key ) {
			case 'summary':
			case 'app':
				return (string) $entry['sort_key'];
			case 'outcome':
				return $entry['outcome'];
			default:
				return $entry['sort_at'];
		}
	}

	/**
	 * Hosted entries in the Dashboard's vocabulary.
	 *
	 * @param list<array<string,mixed>> $entries Validated hosted entries.
	 *
	 * @return array{entries:list<array<string,mixed>>,total:int}
	 */
	private function dashboard_page( array $entries, int $total ): array {
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$rows   = array();
		foreach ( $entries as $entry ) {
			$rows[] = array(
				'id'                  => $entry['id'],
				'client'              => array(
					'id'    => $entry['app']['id'],
					'brand' => $entry['app']['brand'],
					'name'  => $this->app_name( $entry['app'] ),
				),
				'type'                => 'tool_use',
				'summary'             => '' === $entry['summary'] ? $entry['tool_name'] : $entry['summary'],
				'result'              => self::OUTCOME_RESULTS[ $entry['outcome'] ],
				'occurred_at'         => $entry['occurred_at'],
				'occurred_at_display' => (string) wp_date( $format, (int) strtotime( $entry['occurred_at'] ) ),
			);
		}

		return array(
			'entries' => $rows,
			'total'   => $total,
		);
	}

	/**
	 * The admin a row names: their ID and display name, or login when the
	 * display name is empty.
	 *
	 * @return array{id:int,name:string}
	 */
	private function admin( int $user_id ): array {
		$user = get_userdata( $user_id );
		$name = $user ? trim( (string) $user->display_name ) : '';

		return array(
			'id'   => $user_id,
			'name' => '' !== $name ? $name : ( $user ? (string) $user->user_login : (string) $user_id ),
		);
	}

	/**
	 * An app's name, or its vendor's when the reported name was empty.
	 */
	private function app_name( array $app ): string {
		return '' === $app['name'] ? self::VENDOR_NAMES[ $app['vendor'] ] : $app['name'];
	}

	/**
	 * A sign-in could not be verified.
	 *
	 * @since 8.0.0
	 */
	private function unconfirmed(): WP_Error {
		return new WP_Error(
			Hosted_Site_Client::UNAVAILABLE,
			esc_html__( 'Your AI connections could not be checked. Refresh this page to try again. If the problem continues, ask your host to check outgoing connections.', 'uncanny-automator' ),
			array( 'retry_after' => 30 )
		);
	}

	/**
	 * Whether the current user may read AI data. The fixed capability, never the Automator filter.
	 */
	private function may_read(): bool {
		return current_user_can( ( new Client_Context_Service() )->get_client_access_capability() ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Fixed manage_options.
	}

	/**
	 * An integer from a REST parameter, or null.
	 *
	 * @param mixed $value Parameter.
	 */
	private function integer( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value;
		}

		return is_string( $value ) && 1 === preg_match( '/^\d{1,6}$/D', $value ) ? (int) $value : null;
	}

	/**
	 * A refused filter.
	 */
	private function invalid( string $param ): WP_Error {
		return new WP_Error( 'rest_invalid_param', 'This AI activity filter is not valid.', array( 'param' => $param ) );
	}

	/**
	 * A refused reader.
	 */
	private function forbidden(): WP_Error {
		return new WP_Error( 'rest_forbidden', 'You do not have permission to view AI activity.' );
	}
}
