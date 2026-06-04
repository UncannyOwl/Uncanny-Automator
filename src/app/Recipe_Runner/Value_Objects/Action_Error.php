<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Value_Objects;

use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;

/**
 * Immutable value object representing a structured action error.
 *
 * Replaces raw error message strings with typed, classifiable errors.
 * Legacy callers using add_log_error('string') get automatic conversion
 * via from_legacy_message().
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Value_Objects
 * @since   7.3
 */
class Action_Error {

	/**
	 * @var string Error code (Error_Code constant).
	 */
	private $code;

	/**
	 * @var string Human-readable error message.
	 */
	private $message;

	/**
	 * @var bool Whether this error should escalate to recipe status.
	 */
	private $is_actionable;

	/**
	 * @var array Optional context data (action_id, API response, etc.).
	 */
	private $context;

	/**
	 * @param string $code    Error code (Error_Code constant).
	 * @param string $message Human-readable error message.
	 * @param array  $context Optional context data.
	 */
	public function __construct( string $code, string $message, array $context = array() ) {
		$this->code          = $code;
		$this->message       = $message;
		$this->is_actionable = Error_Code::is_actionable( $code );
		$this->context       = $context;
	}

	/**
	 * Create from a legacy error message string.
	 *
	 * Infers the error code from message content. Used by add_log_error()
	 * bridge so existing integrations get structured errors automatically.
	 *
	 * @param string $message The legacy error message.
	 *
	 * @return self
	 */
	public static function from_legacy_message( string $message ): self {
		return new self( Error_Code::infer_from_message( $message ), $message );
	}

	/**
	 * @return string
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * @return string
	 */
	public function get_message(): string {
		return $this->message;
	}

	/**
	 * @return bool
	 */
	public function is_actionable(): bool {
		return $this->is_actionable;
	}

	/**
	 * @return array
	 */
	public function get_context(): array {
		return $this->context;
	}
}
