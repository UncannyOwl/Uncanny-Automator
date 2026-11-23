<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\App_Events\Value_Objects;

use DateTimeImmutable;
use Uncanny_Automator\App\Recipe_Logs\App_Events\Status\App_Event_Status;
use Uncanny_Automator\App\Recipe_Logs\Lists\Invalid_Log_List_Param;

/**
 * One read of the App events list: which page, which app events, in what
 * order. Built from the REST parameters, which arrive as strings; a value
 * it cannot use is refused with Invalid_Log_List_Param, naming it.
 *
 * Dates are the site's calendar days; the bounds are kept as Unix
 * timestamps because the inbox records `received_at` as a TIMESTAMP.
 *
 * @since 8.1.0
 */
final class App_Event_Query {

	/**
	 * The orders the list can sort by.
	 *
	 * @var string[]
	 */
	const ORDERBY = array( 'date', 'status' );

	/**
	 * Most entries one page may hold.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Longest search text accepted.
	 *
	 * @var int
	 */
	const MAX_SEARCH_LENGTH = 200;

	/**
	 * @var int
	 */
	private $page = 1;

	/**
	 * @var int
	 */
	private $per_page = 20;

	/**
	 * The app filter, as asked: the app's integration code (`DROPBOX`).
	 *
	 * @var string|null
	 */
	private $app_code;

	/**
	 * The platform's slug the inbox stores for that app (`dropbox`). The
	 * code itself until the list resolves it ({@see self::for_integration()}),
	 * since an app with no source is named by its slug.
	 *
	 * @var string|null
	 */
	private $integration;

	/**
	 * The inbox status to keep (`pending`, `processing`, `completed`, `failed`).
	 *
	 * @var string|null
	 */
	private $inbox_status;

	/**
	 * Earliest received time kept, Unix seconds.
	 *
	 * @var int|null
	 */
	private $received_from;

	/**
	 * Latest received time kept, Unix seconds.
	 *
	 * @var int|null
	 */
	private $received_to;

	/**
	 * @var string|null
	 */
	private $search;

	/**
	 * @var string
	 */
	private $orderby = 'date';

	/**
	 * @var string
	 */
	private $order = 'desc';

	/**
	 * The inbox statuses in the order a sort by status lists them, A to Z by label.
	 *
	 * @var string[]
	 */
	private $status_order = array();

	/**
	 * Built by from_params() only.
	 */
	private function __construct() {
	}

	/**
	 * Read the REST parameters.
	 *
	 * @param array<string, mixed> $params The request's parameters.
	 *
	 * @return self
	 *
	 * @throws Invalid_Log_List_Param For a parameter the list cannot take.
	 */
	public static function from_params( array $params ): self {
		$query = new self();

		$query->page          = self::required_int( $params, 'page', self::max_int() );
		$query->per_page      = self::required_int( $params, 'per_page', self::MAX_PER_PAGE );
		$query->app_code      = self::app_code( $params );
		$query->integration   = $query->app_code;
		$query->inbox_status  = self::inbox_status( $params );
		$query->received_from = self::day_bound( $params, 'date_from', '00:00:00' );
		$query->received_to   = self::day_bound( $params, 'date_to', '23:59:59' );
		$query->search        = self::search( $params );
		$query->orderby       = self::one_of( $params, 'orderby', self::ORDERBY, 'date' );
		$query->order         = self::one_of( $params, 'order', array( 'asc', 'desc' ), 'desc' );
		$query->status_order  = App_Event_Status::inbox_statuses_by_label();

		return $query;
	}

	/**
	 * @return int 1-based page.
	 */
	public function get_page(): int {
		return $this->page;
	}

	/**
	 * @return int Entries per page.
	 */
	public function get_per_page(): int {
		return $this->per_page;
	}

	/**
	 * @return int Rows to skip.
	 */
	public function get_offset(): int {
		return ( $this->page - 1 ) * $this->per_page;
	}

	/**
	 * The same read, for the app the platform names with this slug.
	 *
	 * @since 8.1.0
	 *
	 * @param string $slug The platform's slug for the filtered app.
	 *
	 * @return self
	 */
	public function for_integration( string $slug ): self {
		$query              = clone $this;
		$query->integration = null !== $this->app_code ? $slug : null;

		return $query;
	}

	/**
	 * @return string|null The app filter: an integration code, as asked.
	 */
	public function get_app_code(): ?string {
		return $this->app_code;
	}

	/**
	 * @return string|null The platform's slug the inbox stores for the filtered app.
	 */
	public function get_integration(): ?string {
		return $this->integration;
	}

	/**
	 * @return string|null The inbox status to keep.
	 */
	public function get_inbox_status(): ?string {
		return $this->inbox_status;
	}

	/**
	 * @return int|null Earliest received time, Unix seconds.
	 */
	public function get_received_from(): ?int {
		return $this->received_from;
	}

	/**
	 * @return int|null Latest received time, Unix seconds.
	 */
	public function get_received_to(): ?int {
		return $this->received_to;
	}

	/**
	 * @return string|null Search text.
	 */
	public function get_search(): ?string {
		return $this->search;
	}

	/**
	 * @return string `date` or `status`.
	 */
	public function get_orderby(): string {
		return $this->orderby;
	}

	/**
	 * @return string `asc` or `desc`.
	 */
	public function get_order(): string {
		return $this->order;
	}

	/**
	 * @return string[] The inbox statuses in the order an ascending sort by status lists them: A to Z by the label the tab shows.
	 */
	public function get_status_order(): array {
		return $this->status_order;
	}

	/**
	 * Whether the read filters the list. Search and sort don't count: they
	 * work without Automator Pro, as on the other Logs lists.
	 *
	 * @return bool
	 */
	public function has_filters(): bool {
		return null !== $this->app_code || null !== $this->inbox_status || null !== $this->received_from || null !== $this->received_to;
	}

	/**
	 * Largest page number that keeps the offset a PHP integer.
	 *
	 * @return int
	 */
	private static function max_int(): int {
		return intdiv( PHP_INT_MAX, self::MAX_PER_PAGE );
	}

	/**
	 * A required whole number from 1 to $max.
	 *
	 * @param array<string, mixed> $params The parameters.
	 * @param string               $name   The parameter.
	 * @param int                  $max    Largest value.
	 *
	 * @return int
	 *
	 * @throws Invalid_Log_List_Param When missing or out of range.
	 */
	private static function required_int( array $params, string $name, int $max ): int {
		$raw = $params[ $name ] ?? null;

		if ( null === $raw || '' === $raw ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %s parameter is required.', esc_html( $name ) ) );
		}

		if ( ! is_scalar( $raw ) || 1 !== preg_match( '/^\d+$/', (string) $raw ) || (int) $raw < 1 ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %s parameter must be a whole number, 1 or more.', esc_html( $name ) ) );
		}

		if ( (int) $raw > $max ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %1$s parameter must be %2$d at most.', esc_html( $name ), esc_html( (string) $max ) ) );
		}

		return (int) $raw;
	}

	/**
	 * The app filter: an app's code, as GET logs/app-events lists it.
	 *
	 * @param array<string, mixed> $params The parameters.
	 *
	 * @return string|null
	 *
	 * @throws Invalid_Log_List_Param For a value that cannot be a code.
	 */
	private static function app_code( array $params ): ?string {
		$raw = $params['integration'] ?? null;

		if ( null === $raw ) {
			return null;
		}

		if ( ! is_string( $raw ) || 1 !== preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $raw ) ) {
			throw new Invalid_Log_List_Param( 'integration', 'The integration parameter must be an app code from the apps of GET logs/app-events.' );
		}

		return $raw;
	}

	/**
	 * The status filter: a wire code, read as the inbox status it stands for.
	 *
	 * @param array<string, mixed> $params The parameters.
	 *
	 * @return string|null
	 *
	 * @throws Invalid_Log_List_Param For an unknown code.
	 */
	private static function inbox_status( array $params ): ?string {
		$raw = $params['status'] ?? null;

		if ( null === $raw ) {
			return null;
		}

		$status = is_string( $raw ) ? App_Event_Status::inbox_status_for_code( $raw ) : null;

		if ( null === $status ) {
			throw new Invalid_Log_List_Param( 'status', sprintf( 'The status parameter must be one of: %s.', esc_html( implode( ', ', App_Event_Status::codes() ) ) ) );
		}

		return $status;
	}

	/**
	 * A calendar day in the site's time zone, as the Unix time of $time on it.
	 *
	 * @param array<string, mixed> $params The parameters.
	 * @param string               $name   The parameter.
	 * @param string               $time   `00:00:00` or `23:59:59`.
	 *
	 * @return int|null
	 *
	 * @throws Invalid_Log_List_Param For a value that is not a real date.
	 */
	private static function day_bound( array $params, string $name, string $time ): ?int {
		$raw = $params[ $name ] ?? null;

		if ( null === $raw ) {
			return null;
		}

		$valid = is_string( $raw ) && 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $parts ) && checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );

		if ( ! $valid ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %s parameter must be a date, YYYY-MM-DD.', esc_html( $name ) ) );
		}

		$moment = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $raw . ' ' . $time, wp_timezone() );

		if ( false === $moment ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %s parameter must be a date, YYYY-MM-DD.', esc_html( $name ) ) );
		}

		return $moment->getTimestamp();
	}

	/**
	 * Search text, trimmed.
	 *
	 * @param array<string, mixed> $params The parameters.
	 *
	 * @return string|null
	 *
	 * @throws Invalid_Log_List_Param For empty or overlong text.
	 */
	private static function search( array $params ): ?string {
		$raw = $params['search'] ?? null;

		if ( null === $raw ) {
			return null;
		}

		$text = is_string( $raw ) ? trim( $raw ) : '';

		if ( '' === $text ) {
			throw new Invalid_Log_List_Param( 'search', 'The search parameter must be non-empty text.' );
		}

		if ( mb_strlen( $text ) > self::MAX_SEARCH_LENGTH ) {
			throw new Invalid_Log_List_Param( 'search', sprintf( 'The search parameter must be %d characters at most.', esc_html( (string) self::MAX_SEARCH_LENGTH ) ) );
		}

		return $text;
	}

	/**
	 * One of a fixed set, or the fallback when absent.
	 *
	 * @param array<string, mixed> $params   The parameters.
	 * @param string               $name     The parameter.
	 * @param string[]             $allowed  The values it may take.
	 * @param string               $fallback The value when absent.
	 *
	 * @return string
	 *
	 * @throws Invalid_Log_List_Param For any other value.
	 */
	private static function one_of( array $params, string $name, array $allowed, string $fallback ): string {
		$raw = $params[ $name ] ?? null;

		if ( null === $raw ) {
			return $fallback;
		}

		if ( ! is_string( $raw ) || ! in_array( $raw, $allowed, true ) ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %1$s parameter must be one of: %2$s.', esc_html( $name ), esc_html( implode( ', ', $allowed ) ) ) );
		}

		return $raw;
	}
}
