<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Value_Objects;

/**
 * Value object representing the result of user resolution for Everyone recipes.
 *
 * Immutable after construction. Use named constructors for clarity.
 *
 * The recipe pipeline always continues after resolution — action skipping
 * is handled separately by Action_Failure_Handler via its own filter.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Value_Objects
 * @since   7.2
 */
final class User_Resolution {

	/**
	 * @var int
	 */
	private $user_id;

	/**
	 * @var string
	 */
	private $error_message;

	/**
	 * @var bool
	 */
	private $complete_with_errors;

	/**
	 * @var bool
	 */
	private $do_nothing;

	/**
	 * @var array
	 */
	private $parsed_data;

	/**
	 * @param int    $user_id              Resolved WordPress user ID.
	 * @param string $error_message        Human-readable message for logging.
	 * @param bool   $complete_with_errors Whether to mark recipe as completed-with-errors.
	 * @param bool   $do_nothing           Whether to mark recipe as do-nothing.
	 * @param array  $parsed_data          Parsed field data for trigger meta storage.
	 */
	private function __construct(
		int $user_id,
		string $error_message,
		bool $complete_with_errors,
		bool $do_nothing,
		array $parsed_data
	) {
		$this->user_id              = $user_id;
		$this->error_message        = $error_message;
		$this->complete_with_errors = $complete_with_errors;
		$this->do_nothing           = $do_nothing;
		$this->parsed_data          = $parsed_data;
	}

	/**
	 * User resolved successfully — recipe should continue.
	 *
	 * @param int    $user_id     The resolved user ID.
	 * @param string $message     Success message for logging.
	 * @param array  $parsed_data Parsed field data.
	 *
	 * @return self
	 */
	public static function success( int $user_id, string $message = '', array $parsed_data = array() ): self {
		return new self( $user_id, $message, false, false, $parsed_data );
	}

	/**
	 * Resolution decided to do nothing — recipe continues but actions are skipped.
	 *
	 * @param string $reason  Reason for doing nothing.
	 * @param int    $user_id Optional user ID if one was found.
	 *
	 * @return self
	 */
	public static function do_nothing( string $reason, int $user_id = 0 ): self {
		return new self( $user_id, $reason, false, true, array() );
	}

	/**
	 * Resolution failed — recipe continues but actions are skipped with error.
	 *
	 * @param string $message Error message.
	 * @param int    $user_id Optional user ID if one was partially resolved.
	 *
	 * @return self
	 */
	public static function error( string $message, int $user_id = 0 ): self {
		return new self( $user_id, $message, true, false, array() );
	}

	/**
	 * @return int
	 */
	public function get_user_id(): int {
		return $this->user_id;
	}

	/**
	 * @return string
	 */
	public function get_error_message(): string {
		return $this->error_message;
	}

	/**
	 * @return bool
	 */
	public function should_complete_with_errors(): bool {
		return $this->complete_with_errors;
	}

	/**
	 * @return bool
	 */
	public function is_do_nothing(): bool {
		return $this->do_nothing;
	}

	/**
	 * @return array
	 */
	public function get_parsed_data(): array {
		return $this->parsed_data;
	}

	/**
	 * Whether a valid user was resolved.
	 *
	 * @return bool
	 */
	public function has_user(): bool {
		return 0 !== $this->user_id;
	}

	/**
	 * Whether this resolution represents a failure state (error or do-nothing).
	 *
	 * @return bool
	 */
	public function is_failure(): bool {
		return $this->complete_with_errors || $this->do_nothing;
	}
}
