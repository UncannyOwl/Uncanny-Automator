<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Domain;

/** Immutable protocol outcome; WordPress response construction belongs to the controller. */
final class Token_Exchange_Failure {

	private string $error;
	private string $description;
	private int $status;
	private string $ref;
	private array $headers;

	/** Capture the protocol outcome without WordPress or credential state. */
	public function __construct( string $error, string $description, int $status, string $ref, array $headers = array() ) {
		$this->error       = $error;
		$this->description = $description;
		$this->status      = $status;
		$this->ref         = $ref;
		$this->headers     = $headers;
	}

	/** OAuth error identifier. */
	public function get_error(): string {
		return $this->error;
	}

	/** Safe client-visible remedy. */
	public function get_description(): string {
		return $this->description;
	}

	/** HTTP status for this outcome. */
	public function get_status(): int {
		return $this->status;
	}

	/** Support reference preserved by the response adapter. */
	public function get_ref(): string {
		return $this->ref;
	}

	/** Additional protocol headers. */
	public function get_headers(): array {
		return $this->headers;
	}
}
