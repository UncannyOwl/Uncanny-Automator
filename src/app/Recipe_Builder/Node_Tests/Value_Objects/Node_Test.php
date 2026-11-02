<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects;

use InvalidArgumentException;

/**
 * One node-test definition — the object the list endpoint serializes.
 *
 * Discriminated on run_mode: `request` resolves in one round trip and
 * never carries `poll`; `listen` arms a server-side listener and must
 * declare `poll` (interval_ms / timeout_ms).
 */
final class Node_Test {

	/**
	 * Unique id within the node's test list (e.g. "send-email").
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Display name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Optional backend-owned markdown description.
	 *
	 * @var string|null
	 */
	private ?string $description;

	/**
	 * Whether the test gates the primary Save.
	 *
	 * @var bool
	 */
	private bool $required;

	/**
	 * Execution mode: request | listen.
	 *
	 * @var string
	 */
	private string $run_mode;

	/**
	 * Label for the per-test run button.
	 *
	 * @var string
	 */
	private string $button_label;

	/**
	 * Ephemeral test-only fields in the fields-directory wire shape.
	 *
	 * @var array
	 */
	private array $fields;

	/**
	 * Listen polling config: { interval_ms, timeout_ms }. Null for request mode.
	 *
	 * @var array|null
	 */
	private ?array $poll;

	/**
	 * Validate and freeze the definition.
	 *
	 * @param array $config Definition config.
	 * @throws InvalidArgumentException When a required key is missing or malformed.
	 */
	public function __construct( array $config ) {
		foreach ( array( 'id', 'name', 'button_label' ) as $key ) {
			if ( ! isset( $config[ $key ] ) || ! is_string( $config[ $key ] ) || '' === $config[ $key ] ) {
				throw new InvalidArgumentException( sprintf( "Node test requires a non-empty string '%s'.", esc_html( $key ) ) );
			}
		}
		if ( isset( $config['description'] ) && ! is_string( $config['description'] ) ) {
			throw new InvalidArgumentException( "Node test 'description' must be a string." );
		}
		if ( isset( $config['fields'] ) && ! is_array( $config['fields'] ) ) {
			throw new InvalidArgumentException( "Node test 'fields' must be an array." );
		}
		if ( ! isset( $config['required'] ) || ! is_bool( $config['required'] ) ) {
			throw new InvalidArgumentException( "Node test requires a boolean 'required'." );
		}
		if ( ! in_array( $config['run_mode'] ?? '', array( 'request', 'listen' ), true ) ) {
			throw new InvalidArgumentException( "Node test run_mode must be 'request' or 'listen'." );
		}

		$poll = null;
		if ( 'listen' === $config['run_mode'] ) {
			$poll = $config['poll'] ?? null;
			if ( ! is_array( $poll ) || (int) ( $poll['interval_ms'] ?? 0 ) <= 0 || (int) ( $poll['timeout_ms'] ?? 0 ) <= 0 ) {
				throw new InvalidArgumentException( 'A listen-mode node test requires poll.interval_ms and poll.timeout_ms.' );
			}
			$poll = array(
				'interval_ms' => (int) $poll['interval_ms'],
				'timeout_ms'  => (int) $poll['timeout_ms'],
			);
		}

		$this->id           = $config['id'];
		$this->name         = $config['name'];
		$this->description  = isset( $config['description'] ) && '' !== $config['description'] ? $config['description'] : null;
		$this->required     = $config['required'];
		$this->run_mode     = $config['run_mode'];
		$this->button_label = $config['button_label'];
		$this->fields       = isset( $config['fields'] ) ? array_values( $config['fields'] ) : array();
		$this->poll         = $poll;
	}

	/**
	 * The test id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Whether the test gates the primary Save.
	 *
	 * @return bool
	 */
	public function is_required(): bool {
		return $this->required;
	}

	/**
	 * The execution mode.
	 *
	 * @return string
	 */
	public function get_run_mode(): string {
		return $this->run_mode;
	}

	/**
	 * The wire projection (§3 shape; description/poll omitted when absent).
	 *
	 * @return array
	 */
	public function to_wire(): array {
		$wire = array(
			'id'           => $this->id,
			'name'         => $this->name,
			'required'     => $this->required,
			'run_mode'     => $this->run_mode,
			'button_label' => $this->button_label,
			'fields'       => $this->fields,
		);

		if ( null !== $this->description ) {
			$wire['description'] = $this->description;
		}
		if ( null !== $this->poll ) {
			$wire['poll'] = $this->poll;
		}

		return $wire;
	}
}
