<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Trigger\Value_Objects;

/**
 * Trigger Hook Value Object.
 *
 * Represents hook configuration for trigger execution.
 * Encapsulates hook name, priority, and argument count.
 *
 * @since 7.0.0
 */
class Trigger_Hook {

	private string $name;
	private int $priority;
	private int $args_count;

	/**
	 * Constructor.
	 *
	 * @param array $hook_data Hook configuration array.
	 * @throws \InvalidArgumentException If invalid hook data.
	 */
	public function __construct( array $hook_data ) {
		$this->validate( $hook_data );

		$this->name       = $hook_data['name'];
		$this->priority   = $hook_data['priority'] ?? 10;
		$this->args_count = $hook_data['args_count'] ?? 1;
	}

	/**
	 * Get hook name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get hook priority.
	 *
	 * @return int
	 */
	public function get_priority(): int {
		return $this->priority;
	}

	/**
	 * Get argument count.
	 *
	 * @return int
	 */
	public function get_args_count(): int {
		return $this->args_count;
	}

	/**
	 * To array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'name'       => $this->name,
			'priority'   => $this->priority,
			'args_count' => $this->args_count,
		);
	}

	/**
	 * Create from WordPress action hook format.
	 *
	 * @param string $name Hook name.
	 * @param int    $priority Hook priority.
	 * @param int    $args_count Number of arguments.
	 * @return self
	 */
	public static function from_wp_hook( string $name, int $priority = 10, int $args_count = 1 ): self {
		return new self(
			array(
				'name'       => $name,
				'priority'   => $priority,
				'args_count' => $args_count,
			)
		);
	}

	/**
	 * Validate hook data.
	 *
	 * @param array $hook_data Hook configuration to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( array $hook_data ): void {
		if ( empty( $hook_data['name'] ) || ! is_string( $hook_data['name'] ) ) {
			throw new \InvalidArgumentException( 'Hook name is required and must be a non-empty string' );
		}

		if ( isset( $hook_data['priority'] ) && ( ! is_int( $hook_data['priority'] ) || $hook_data['priority'] < 0 ) ) {
			throw new \InvalidArgumentException( 'Hook priority must be a non-negative integer' );
		}

		if ( isset( $hook_data['args_count'] ) && ( ! is_int( $hook_data['args_count'] ) || $hook_data['args_count'] < 1 ) ) {
			throw new \InvalidArgumentException( 'Hook args_count must be a positive integer' );
		}
	}
}
