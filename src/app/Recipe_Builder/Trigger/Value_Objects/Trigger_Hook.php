<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Trigger\Value_Objects;

/**
 * Trigger Hook Value Object.
 *
 * Represents hook configuration for trigger execution. A trigger may listen on
 * one OR several WP hooks — integrations declare the set via
 * `add_action( array( 'hook_a', 'hook_b' ), $priority, $args )`. The names share
 * one priority + args_count, so this VO holds a non-empty list of hook names
 * plus the shared priority and argument count.
 *
 * @since 7.0.0
 */
class Trigger_Hook {

	/**
	 * The WordPress hooks that fire this trigger; always at least one.
	 *
	 * @var string[] Non-empty list of WP hook names.
	 */
	private array $names;
	private int $priority;
	private int $args_count;

	/**
	 * Constructor.
	 *
	 * Accepts the single-hook associative shape (`array( 'name' => 'hook' )`),
	 * the multi-hook associative shape (`array( 'name' => array( 'a', 'b' ) )`),
	 * and the bare list the trigger registry hands over for
	 * `add_action( array( 'a', 'b' ) )` (`array( 'a', 'b' )`).
	 *
	 * @param array $hook_data Hook configuration array.
	 * @throws \InvalidArgumentException If invalid hook data.
	 */
	public function __construct( array $hook_data ) {
		$names = $this->extract_names( $hook_data );
		$this->validate( $names, $hook_data );

		$this->names      = array_values( $names );
		$this->priority   = $hook_data['priority'] ?? 10;
		$this->args_count = $hook_data['args_count'] ?? 1;
	}

	/**
	 * Normalize the supported input shapes to a list of names.
	 *
	 * @param array $hook_data Hook configuration array.
	 * @return array
	 */
	private function extract_names( array $hook_data ): array {
		if ( array_key_exists( 'name', $hook_data ) ) {
			return is_array( $hook_data['name'] ) ? $hook_data['name'] : array( $hook_data['name'] );
		}

		// Bare list of hook names — the `add_action( array( ... ) )` registry shape.
		return $hook_data;
	}

	/**
	 * Get the first (primary) hook name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->names[0];
	}

	/**
	 * Get all hook names this trigger listens on.
	 *
	 * @return string[]
	 */
	public function get_names(): array {
		return $this->names;
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
	 * `name` mirrors the legacy `add_action` postmeta shape: a string for a
	 * single hook, a list for multiple. This keeps persistence + the
	 * response `hook` key identical to what the rest of the system already
	 * reads.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'name'       => 1 === count( $this->names ) ? $this->names[0] : $this->names,
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
	 * @param array $names     Normalized list of hook names.
	 * @param array $hook_data Original hook configuration (for priority / args_count).
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( array $names, array $hook_data ): void {
		if ( empty( $names ) ) {
			throw new \InvalidArgumentException( 'Hook name is required and must be a non-empty string' );
		}

		foreach ( $names as $name ) {
			if ( ! is_string( $name ) || '' === $name ) {
				throw new \InvalidArgumentException( 'Hook name is required and must be a non-empty string' );
			}
		}

		if ( isset( $hook_data['priority'] ) && ( ! is_int( $hook_data['priority'] ) || $hook_data['priority'] < 0 ) ) {
			throw new \InvalidArgumentException( 'Hook priority must be a non-negative integer' );
		}

		if ( isset( $hook_data['args_count'] ) && ( ! is_int( $hook_data['args_count'] ) || $hook_data['args_count'] < 1 ) ) {
			throw new \InvalidArgumentException( 'Hook args_count must be a positive integer' );
		}
	}
}
