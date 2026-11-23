<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\App\Recipe_Logs\Lists\Enums\Log_List_Kind;
use Uncanny_Automator\App\Recipe_Logs\Lists\Invalid_Log_List_Param;
use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status_Registry;

/**
 * One read of a Logs page list: which list, which page, and the filters,
 * search and sort it applies.
 *
 * Built from the request's parameters and valid once built. Dates are the
 * site's own calendar days, inclusive, because log rows store the site's
 * local time.
 *
 * @since 8.1.0
 */
final class Log_List_Query {

	/**
	 * The most entries one page holds.
	 *
	 * @var int
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * The sort columns a list takes.
	 *
	 * @var string[]
	 */
	const ORDER_BY = array( 'date', 'recipe', 'user', 'status' );

	/**
	 * The longest search text taken.
	 *
	 * @var int
	 */
	const MAX_SEARCH_LENGTH = 200;

	/** @var string */
	private $kind;

	/** @var int */
	private $page;

	/** @var int */
	private $per_page;

	/** @var int|null */
	private $recipe_id;

	/** @var int|null */
	private $user_id;

	/** @var int[]|null */
	private $status_ints;

	/** @var string|null */
	private $integration;

	/** @var string|null */
	private $endpoint;

	/** @var string|null */
	private $item_code;

	/** @var int|null */
	private $item_log_id;

	/** @var string|null */
	private $date_from;

	/** @var string|null */
	private $date_to;

	/** @var string|null */
	private $search;

	/** @var string */
	private $orderby;

	/** @var string */
	private $order;

	/**
	 * Use {@see self::from_params()}.
	 */
	private function __construct() {
	}

	/**
	 * Reads the request's parameters for one list.
	 *
	 * @param string               $kind   One of {@see Log_List_Kind::all()}.
	 * @param array<string, mixed> $params The request's parameters.
	 *
	 * @return self
	 *
	 * @throws InvalidArgumentException For an unknown list.
	 * @throws Invalid_Log_List_Param   For a parameter the list cannot take.
	 */
	public static function from_params( string $kind, array $params ): self {
		if ( ! in_array( $kind, Log_List_Kind::all(), true ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown log list "%s".', esc_html( $kind ) ) );
		}

		$query       = new self();
		$query->kind = $kind;

		$query->page     = self::required_int( $params, 'page', 1, PHP_INT_MAX );
		$query->per_page = self::required_int( $params, 'per_page', 1, self::MAX_PER_PAGE );

		$query->recipe_id   = self::optional_int( $params, 'recipe_id', 1 );
		$query->user_id     = self::optional_int( $params, 'user_id', 0 );
		$query->status_ints = self::status_ints( $params );
		$query->integration = self::optional_text( $params, 'integration' );
		$query->endpoint    = self::only_for( $kind, array( Log_List_Kind::FAILED_ACTIONS ), 'endpoint', self::optional_text( $params, 'endpoint' ) );
		$query->item_code   = self::only_for( $kind, array( Log_List_Kind::TRIGGERS, Log_List_Kind::ACTIONS ), 'item_code', self::optional_text( $params, 'item_code' ) );
		$query->item_log_id = self::only_for( $kind, array( Log_List_Kind::FAILED_ACTIONS ), 'item_log_id', self::optional_int( $params, 'item_log_id', 1 ) );
		$query->date_from   = self::optional_date( $params, 'date_from', '00:00:00' );
		$query->date_to     = self::optional_date( $params, 'date_to', '23:59:59' );
		$query->search      = self::search( $params );
		$query->orderby     = self::one_of( $params, 'orderby', self::ORDER_BY, 'date' );
		$query->order       = self::one_of( $params, 'order', array( 'asc', 'desc' ), 'desc' );

		return $query;
	}

	/**
	 * The list read.
	 *
	 * @return string One of {@see Log_List_Kind::all()}.
	 */
	public function get_kind(): string {
		return $this->kind;
	}

	/**
	 * The page, 1 or more.
	 *
	 * @return int
	 */
	public function get_page(): int {
		return $this->page;
	}

	/**
	 * Entries per page, 1 to {@see self::MAX_PER_PAGE}.
	 *
	 * @return int
	 */
	public function get_per_page(): int {
		return $this->per_page;
	}

	/**
	 * How many entries come before the page.
	 *
	 * @return int
	 */
	public function get_offset(): int {
		return ( $this->page - 1 ) * $this->per_page;
	}

	/**
	 * The recipe the list is narrowed to.
	 *
	 * @return int|null
	 */
	public function get_recipe_id(): ?int {
		return $this->recipe_id;
	}

	/**
	 * The user the list is narrowed to, 0 for guests.
	 *
	 * @return int|null
	 */
	public function get_user_id(): ?int {
		return $this->user_id;
	}

	/**
	 * The stored status integers the status filter matches.
	 *
	 * @return int[]|null
	 */
	public function get_status_ints(): ?array {
		return $this->status_ints;
	}

	/**
	 * The integration code a run must have run.
	 *
	 * @return string|null
	 */
	public function get_integration(): ?string {
		return $this->integration;
	}

	/**
	 * The API endpoint a failed action called.
	 *
	 * @return string|null
	 */
	public function get_endpoint(): ?string {
		return $this->endpoint;
	}

	/**
	 * The trigger or action code an entry's item has.
	 *
	 * @return string|null
	 */
	public function get_item_code(): ?string {
		return $this->item_code;
	}

	/**
	 * The one failed action a link names, by its action log id.
	 *
	 * @return int|null
	 */
	public function get_item_log_id(): ?int {
		return $this->item_log_id;
	}

	/**
	 * The first moment included, `Y-m-d 00:00:00` in the site's time.
	 *
	 * @return string|null
	 */
	public function get_date_from(): ?string {
		return $this->date_from;
	}

	/**
	 * The last moment included, `Y-m-d 23:59:59` in the site's time.
	 *
	 * @return string|null
	 */
	public function get_date_to(): ?string {
		return $this->date_to;
	}

	/**
	 * The search text, trimmed.
	 *
	 * @return string|null
	 */
	public function get_search(): ?string {
		return $this->search;
	}

	/**
	 * The sort column.
	 *
	 * @return string One of {@see self::ORDER_BY}.
	 */
	public function get_orderby(): string {
		return $this->orderby;
	}

	/**
	 * The sort direction.
	 *
	 * @return string `asc` or `desc`.
	 */
	public function get_order(): string {
		return $this->order;
	}

	/**
	 * Whether the read narrows the list with a filter. Search, sort and a
	 * link's item_log_id are not filters: every site has them.
	 *
	 * @return bool
	 */
	public function has_filters(): bool {
		foreach ( array( $this->recipe_id, $this->user_id, $this->status_ints, $this->integration, $this->endpoint, $this->item_code, $this->date_from, $this->date_to ) as $value ) {
			if ( null !== $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A required whole number within a range.
	 *
	 * @param array<string, mixed> $params The parameters.
	 * @param string               $name   The parameter.
	 * @param int                  $min    The smallest value.
	 * @param int                  $max    The largest value.
	 *
	 * @return int
	 */
	private static function required_int( array $params, string $name, int $min, int $max ): int {
		$value = self::optional_int( $params, $name, $min );

		if ( null === $value ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %s parameter is required.', esc_html( (string) $name ) ) );
		}

		if ( $value > $max ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %1$s parameter must be %2$d at most.', esc_html( (string) $name ), esc_html( (string) $max ) ) );
		}

		return $value;
	}

	/**
	 * An optional whole number, written in digits, no smaller than a minimum.
	 *
	 * @param array<string, mixed> $params The parameters.
	 * @param string               $name   The parameter.
	 * @param int                  $min    The smallest value.
	 *
	 * @return int|null Null when the parameter is absent.
	 */
	private static function optional_int( array $params, string $name, int $min ): ?int {
		if ( ! array_key_exists( $name, $params ) || null === $params[ $name ] ) {
			return null;
		}

		$raw = $params[ $name ];

		if ( is_int( $raw ) ) {
			$value = $raw;
		} elseif ( is_string( $raw ) && 1 === preg_match( '/^-?\d{1,18}$/', $raw ) ) {
			$value = (int) $raw;
		} else {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %s parameter must be a whole number.', esc_html( (string) $name ) ) );
		}

		if ( $value < $min ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %1$s parameter must be %2$d or more.', esc_html( (string) $name ), esc_html( (string) $min ) ) );
		}

		return $value;
	}

	/**
	 * An optional non-empty text.
	 *
	 * @param array<string, mixed> $params The parameters.
	 * @param string               $name   The parameter.
	 *
	 * @return string|null Null when the parameter is absent.
	 */
	private static function optional_text( array $params, string $name ): ?string {
		if ( ! array_key_exists( $name, $params ) || null === $params[ $name ] ) {
			return null;
		}

		if ( ! is_string( $params[ $name ] ) || '' === $params[ $name ] ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %s parameter must be non-empty text.', esc_html( (string) $name ) ) );
		}

		return $params[ $name ];
	}

	/**
	 * Refuses a parameter the list does not take.
	 *
	 * @param string     $kind  The list.
	 * @param string[]   $kinds The lists that take the parameter.
	 * @param string     $name  The parameter.
	 * @param mixed|null $value The parameter's value, null when absent.
	 *
	 * @return mixed|null The value.
	 */
	private static function only_for( string $kind, array $kinds, string $name, $value ) {
		if ( null !== $value && ! in_array( $kind, $kinds, true ) ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %1$s list does not take the %2$s parameter.', esc_html( (string) $kind ), esc_html( (string) $name ) ) );
		}

		return $value;
	}

	/**
	 * The stored status integers a status code stands for.
	 *
	 * @param array<string, mixed> $params The parameters.
	 *
	 * @return int[]|null Null when the parameter is absent.
	 */
	private static function status_ints( array $params ): ?array {
		$code = self::optional_text( $params, 'status' );

		if ( null === $code ) {
			return null;
		}

		$ints = Log_Status_Registry::legacy_ints_for_code( $code );

		if ( array() === $ints ) {
			throw new Invalid_Log_List_Param( 'status', 'The status parameter must be a value from the statuses of GET logs/filters.' );
		}

		return $ints;
	}

	/**
	 * An optional calendar day, `Y-m-d`, as a moment of that day.
	 *
	 * @param array<string, mixed> $params The parameters.
	 * @param string               $name   The parameter.
	 * @param string               $time   The time of day to add.
	 *
	 * @return string|null Null when the parameter is absent.
	 */
	private static function optional_date( array $params, string $name, string $time ): ?string {
		$value = self::optional_text( $params, $name );

		if ( null === $value ) {
			return null;
		}

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts ) || ! checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %s parameter must be a date, YYYY-MM-DD.', esc_html( (string) $name ) ) );
		}

		return $value . ' ' . $time;
	}

	/**
	 * The search text, trimmed.
	 *
	 * @param array<string, mixed> $params The parameters.
	 *
	 * @return string|null Null when the parameter is absent.
	 */
	private static function search( array $params ): ?string {
		$value = self::optional_text( $params, 'search' );

		if ( null === $value ) {
			return null;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			throw new Invalid_Log_List_Param( 'search', 'The search parameter must be non-empty text.' );
		}

		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::MAX_SEARCH_LENGTH ) : substr( $value, 0, self::MAX_SEARCH_LENGTH );
	}

	/**
	 * One of a set of values, or a default when absent.
	 *
	 * @param array<string, mixed> $params  The parameters.
	 * @param string               $name    The parameter.
	 * @param string[]             $allowed The values taken.
	 * @param string               $fallback The value when the parameter is absent.
	 *
	 * @return string
	 */
	private static function one_of( array $params, string $name, array $allowed, string $fallback ): string {
		$value = self::optional_text( $params, $name );

		if ( null === $value ) {
			return $fallback;
		}

		if ( ! in_array( $value, $allowed, true ) ) {
			throw new Invalid_Log_List_Param( esc_html( $name ), sprintf( 'The %1$s parameter must be one of: %2$s.', esc_html( (string) $name ), esc_html( (string) implode( ', ', $allowed ) ) ) );
		}

		return $value;
	}
}
