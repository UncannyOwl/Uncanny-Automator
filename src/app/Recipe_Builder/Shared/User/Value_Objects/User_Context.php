<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects;

/**
 * User Context.
 *
 * Represents the dual-user context in action execution:
 * - Executor: The user who initiates/runs the action (nullable for anonymous)
 * - Executee: The user the action affects (nullable for non-user actions)
 *
 * @since 7.0.0
 */
class User_Context {

	/**
	 * Anonymous user constant.
	 */
	const ANONYMOUS = null;

	/**
	 * The executor user ID.
	 *
	 * @var int|null
	 */
	private ?int $executor;

	/**
	 * The executee user ID.
	 *
	 * @var int|null
	 */
	private ?int $executee;

	/**
	 * Whether the caller is a first-party Uncanny Agent credential.
	 *
	 * Transports set this from the authenticated credential. Policies that let
	 * the Agent bypass site-owner switches read it; nothing else should.
	 *
	 * @var bool
	 */
	private bool $first_party_caller;

	/**
	 * Whether the caller's credential may make changes. Internal callers always may.
	 *
	 * @var bool
	 */
	private bool $can_write;

	/** Verified client identity, supplied only by the transport. */
	private string $client_id;

	/**
	 * Constructor.
	 *
	 * @param int|null $executor The user ID who runs the action.
	 * @param int|null $executee The user ID the action affects.
	 */
	public function __construct( ?int $executor, ?int $executee, bool $first_party_caller = false, bool $can_write = true, string $client_id = '' ) {
		$this->executor           = $executor;
		$this->executee           = $executee;
		$this->first_party_caller = $first_party_caller;
		$this->can_write          = $can_write;
		$this->client_id          = $client_id;
	}

	/**
	 * Get the executor user ID.
	 *
	 * @return int|null
	 */
	public function get_executor(): ?int {
		return $this->executor;
	}

	/**
	 * Get the executee user ID.
	 *
	 * @return int|null
	 */
	public function get_executee(): ?int {
		return $this->executee;
	}

	/**
	 * Whether the caller is a first-party Uncanny Agent credential.
	 *
	 * @return bool
	 */
	public function is_first_party_caller(): bool {
		return $this->first_party_caller;
	}

	/** Client identity cannot be supplied by a model argument. */
	public function get_client_id(): string {
		return $this->client_id;
	}

	/**
	 * Whether the caller's credential may make changes.
	 *
	 * Internal callers always may. An MCP credential that carries only the read
	 * scope may not; the router checks scope per tool, so a tool whose effect
	 * depends on its input (run_ability) asks here.
	 *
	 * @return bool
	 */
	public function can_write(): bool {
		return $this->can_write;
	}
}
