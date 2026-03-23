<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\User\Value_Objects;

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
	 * Constructor.
	 *
	 * @param int|null $executor The user ID who runs the action.
	 * @param int|null $executee The user ID the action affects.
	 */
	public function __construct( ?int $executor, ?int $executee ) {
		$this->executor = $executor;
		$this->executee = $executee;
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
}
