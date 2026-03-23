<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop;

use Uncanny_Automator\Api\Components\Loop\Enums\Loop_Status;
use Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Enums\Iteration_Type;

/**
 * Loop Configuration.
 *
 * Data transfer object for loop configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between raw data
 * and validated domain objects.
 *
 * @since 7.0.0
 */
class Loop_Config {

	/**
	 * Generic data storage.
	 *
	 * @var array
	 */
	private array $data = array();

	/**
	 * Loop ID.
	 *
	 * @var int|null
	 */
	private ?int $id = null;

	/**
	 * Recipe ID.
	 *
	 * @var int|null
	 */
	private ?int $recipe_id = null;

	/**
	 * Loop status.
	 *
	 * @var string|null
	 */
	private ?string $status = null;

	/**
	 * UI order.
	 *
	 * @var int|null
	 */
	private ?int $ui_order = null;

	/**
	 * Iterable expression.
	 *
	 * @var array
	 */
	private array $iterable_expression = array();

	/**
	 * Run on condition.
	 *
	 * @var mixed
	 */
	private $run_on = null;

	/**
	 * Loop filters.
	 *
	 * @var array
	 */
	private array $filters = array();

	/**
	 * Loop items (action IDs).
	 *
	 * @var array
	 */
	private array $items = array();

	/**
	 * Set a configuration value (generic approach).
	 *
	 * @param string $key   Configuration key.
	 * @param mixed  $value Configuration value.
	 * @return self
	 */
	public function set( string $key, $value ): self {
		$this->data[ $key ] = $value;
		return $this;
	}

	/**
	 * Get a configuration value (generic approach).
	 *
	 * @param string $key           Configuration key.
	 * @param mixed  $default_value Default value if key not found.
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		return $this->data[ $key ] ?? $default_value;
	}

	/**
	 * Set loop ID.
	 *
	 * @param int|null $id Loop ID.
	 * @return self
	 */
	public function id( $id ): self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Set recipe ID.
	 *
	 * @param int|null $recipe_id Recipe ID.
	 * @return self
	 */
	public function recipe_id( $recipe_id ): self {
		$this->recipe_id = $recipe_id;
		return $this;
	}

	/**
	 * Set loop status.
	 *
	 * @param string $status Loop status ('draft' or 'publish').
	 * @return self
	 */
	public function status( string $status ): self {
		$this->status = $status;
		return $this;
	}

	/**
	 * Set UI order.
	 *
	 * @param int $ui_order UI order value.
	 * @return self
	 */
	public function ui_order( int $ui_order ): self {
		$this->ui_order = $ui_order;
		return $this;
	}

	/**
	 * Set iterable expression.
	 *
	 * @param array $iterable_expression Iterable expression configuration.
	 * @return self
	 */
	public function iterable_expression( array $iterable_expression ): self {
		$this->iterable_expression = $iterable_expression;
		return $this;
	}

	/**
	 * Set run on condition.
	 *
	 * @param mixed $run_on Run on condition.
	 * @return self
	 */
	public function run_on( $run_on ): self {
		$this->run_on = $run_on;
		return $this;
	}

	/**
	 * Set loop filters.
	 *
	 * @param array $filters Loop filters.
	 * @return self
	 */
	public function filters( array $filters ): self {
		$this->filters = $filters;
		return $this;
	}

	/**
	 * Set loop items.
	 *
	 * @param array $items Loop items (action IDs).
	 * @return self
	 */
	public function items( array $items ): self {
		$this->items = $items;
		return $this;
	}

	/**
	 * Get loop ID.
	 *
	 * @return int|null
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get recipe ID.
	 *
	 * @return int|null
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Get loop status.
	 *
	 * @return string|null
	 */
	public function get_status(): ?string {
		return $this->status;
	}

	/**
	 * Get UI order.
	 *
	 * @return int|null
	 */
	public function get_ui_order(): ?int {
		return $this->ui_order;
	}

	/**
	 * Get iterable expression.
	 *
	 * @return array
	 */
	public function get_iterable_expression(): array {
		return $this->iterable_expression;
	}

	/**
	 * Get run on condition.
	 *
	 * @return mixed
	 */
	public function get_run_on() {
		return $this->run_on;
	}

	/**
	 * Get loop filters.
	 *
	 * @return array
	 */
	public function get_filters(): array {
		return $this->filters;
	}

	/**
	 * Get loop items.
	 *
	 * @return array
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Create from array.
	 *
	 * Validates input types to prevent runtime errors.
	 *
	 * @param array $data Array data.
	 * @return self
	 * @throws \InvalidArgumentException If data types are invalid.
	 */
	public static function from_array( array $data ): self {
		// Validate iterable_expression is array.
		if ( isset( $data['iterable_expression'] ) && ! is_array( $data['iterable_expression'] ) ) {
			throw new \InvalidArgumentException( 'iterable_expression must be an array' );
		}

		// Validate filters is array.
		if ( isset( $data['filters'] ) && ! is_array( $data['filters'] ) ) {
			throw new \InvalidArgumentException( 'filters must be an array' );
		}

		// Validate items is array.
		if ( isset( $data['items'] ) && ! is_array( $data['items'] ) ) {
			throw new \InvalidArgumentException( 'items must be an array' );
		}

		$config = ( new self() )
			->id( isset( $data['id'] ) ? (int) $data['id'] : null )
			->recipe_id( isset( $data['recipe_id'] ) ? (int) $data['recipe_id'] : ( isset( $data['post_parent'] ) ? (int) $data['post_parent'] : null ) )
			->status( $data['status'] ?? $data['post_status'] ?? Loop_Status::DRAFT )
			->ui_order( isset( $data['ui_order'] ) ? (int) $data['ui_order'] : ( isset( $data['_ui_order'] ) ? (int) $data['_ui_order'] : 2 ) )
			->iterable_expression( $data['iterable_expression'] ?? array( 'type' => Iteration_Type::USERS ) )
			->run_on( $data['run_on'] ?? null )
			->filters( $data['filters'] ?? array() )
			->items( $data['items'] ?? array() );

		return $config;
	}
}
