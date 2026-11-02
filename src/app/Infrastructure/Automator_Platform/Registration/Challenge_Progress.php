<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration;

/**
 * How the platform's host challenge of an attempt is going, as the `425
 * TOO_EARLY` of register/complete tells it (`error.challenge`, platform
 * flows §4.5): which address the tries contact, how many there were of how
 * many, what the latest came to, and in how many seconds the next starts.
 *
 * A value. The outcome is a code from the platform's closed set; one this
 * plugin does not know is read as "not proven yet", never as a reason to
 * stop. Kept on the attempt ({@see to_array()}) so the registration notice
 * can say what the last check got.
 *
 * @since 8.0.0
 */
final class Challenge_Progress {

	/** The tries contact this site's own address. */
	public const CHECKING_THIS_ADDRESS = 'this_address';

	/**
	 * The challenge URL answered a status that is not the plugin's answer,
	 * kept as the last HTTP status.
	 */
	public const OUTCOME_HTTP_STATUS = 'http_status';

	/**
	 * @var string
	 */
	private $checking;

	/**
	 * @var int
	 */
	private $tries;

	/**
	 * @var int
	 */
	private $max_tries;

	/**
	 * @var string
	 */
	private $last_outcome;

	/**
	 * @var int
	 */
	private $last_http_status;

	/**
	 * @var int|null
	 */
	private $next_try_in;

	/**
	 * @param string   $checking         Which address the tries contact.
	 * @param int      $tries            Tries so far.
	 * @param int      $max_tries        Tries the attempt has.
	 * @param string   $last_outcome     What the latest try came to; '' before one ended.
	 * @param int      $last_http_status Its HTTP status for {@see OUTCOME_HTTP_STATUS}; 0 otherwise.
	 * @param int|null $next_try_in      Seconds until the next try; null when none is coming.
	 */
	private function __construct( string $checking, int $tries, int $max_tries, string $last_outcome, int $last_http_status, ?int $next_try_in ) {

		$this->checking         = $checking;
		$this->tries            = $tries;
		$this->max_tries        = $max_tries;
		$this->last_outcome     = $last_outcome;
		$this->last_http_status = $last_http_status;
		$this->next_try_in      = $next_try_in;
	}

	/**
	 * Read from the platform's `error.challenge` block, or from what
	 * {@see to_array()} kept; null when there is none.
	 *
	 * @param mixed $block The block.
	 *
	 * @return self|null
	 */
	public static function from_block( $block ): ?self {

		if ( ! is_array( $block ) || ! isset( $block['checking'] ) || ! is_string( $block['checking'] ) ) {
			return null;
		}

		return new self(
			$block['checking'],
			(int) ( $block['tries'] ?? 0 ),
			(int) ( $block['max_tries'] ?? 0 ),
			is_string( $block['last_outcome'] ?? null ) ? $block['last_outcome'] : '',
			(int) ( $block['last_http_status'] ?? 0 ),
			isset( $block['next_try_in'] ) ? (int) $block['next_try_in'] : null
		);
	}

	/**
	 * Whether the challenge of this site's own address is over without
	 * proving it: no try is coming, so the attempt can never become a 200. A
	 * move's old address is never over here; the platform ends those checks
	 * with an answer of its own.
	 *
	 * @return bool
	 */
	public function is_over(): bool {

		return null === $this->next_try_in && self::CHECKING_THIS_ADDRESS === $this->checking;
	}

	/**
	 * Whether a try of this site's own address ended without proving it.
	 *
	 * @return bool
	 */
	public function has_failed_check(): bool {

		return '' !== $this->last_outcome && self::CHECKING_THIS_ADDRESS === $this->checking;
	}

	/**
	 * @return string What the latest try came to; '' before one ended.
	 */
	public function last_outcome(): string {

		return $this->last_outcome;
	}

	/**
	 * @return int The latest try's HTTP status for {@see OUTCOME_HTTP_STATUS}; 0 otherwise.
	 */
	public function last_http_status(): int {

		return $this->last_http_status;
	}

	/**
	 * The form kept on the attempt; {@see from_block()} reads it back.
	 *
	 * @return array{checking: string, tries: int, max_tries: int, last_outcome: string, last_http_status: int, next_try_in: int|null}
	 */
	public function to_array(): array {

		return array(
			'checking'         => $this->checking,
			'tries'            => $this->tries,
			'max_tries'        => $this->max_tries,
			'last_outcome'     => $this->last_outcome,
			'last_http_status' => $this->last_http_status,
			'next_try_in'      => $this->next_try_in,
		);
	}
}
