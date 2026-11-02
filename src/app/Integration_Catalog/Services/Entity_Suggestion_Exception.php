<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Services;

use Exception;

/**
 * Carries a REST error code + HTTP status for suggestion validation failures.
 */
class Entity_Suggestion_Exception extends Exception {

	/**
	 * Machine-readable REST error code.
	 *
	 * @var string
	 */
	private string $error_code;

	/**
	 * HTTP status the controller should map this failure to.
	 *
	 * @var int
	 */
	private int $status;

	/**
	 * Build the exception; the error code doubles as the message.
	 *
	 * @param string $error_code Machine-readable REST error code.
	 * @param int    $status     HTTP status (400/403/404).
	 */
	public function __construct( string $error_code, int $status ) {
		parent::__construct( $error_code );
		$this->error_code = $error_code;
		$this->status     = $status;
	}

	/**
	 * Machine-readable REST error code.
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/**
	 * HTTP status the controller should map this failure to.
	 *
	 * @return int
	 */
	public function get_status(): int {
		return $this->status;
	}
}
