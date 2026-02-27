<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop;

use Uncanny_Automator\Api\Components\Interfaces\Parent_Id;
use Uncanny_Automator\Api\Components\Loop\Value_Objects\Loop_Id;
use Uncanny_Automator\Api\Components\Loop\Value_Objects\Loop_Recipe_Id;
use Uncanny_Automator\Api\Components\Loop\Value_Objects\Loop_Status_Value;
use Uncanny_Automator\Api\Components\Loop\Value_Objects\Loop_Ui_Order;
use Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Expression;
use Uncanny_Automator\Api\Components\Loop\Filter\Filter;
use Uncanny_Automator\Api\Components\Loop\Enums\Loop_Status;

/**
 * Loop Aggregate.
 *
 * Root aggregate of the Loop domain.
 * Pure domain object representing a loop within a recipe.
 * Contains zero WordPress dependencies - pure PHP business logic only.
 *
 * Loops iterate over a set of entities (users, posts, or tokens) and execute
 * contained actions for each entity in the set.
 *
 * Implements Parent_Id interface to allow it to be used as a parent reference for actions.
 *
 * This aggregate coordinates two bounded contexts:
 * - Iterable_Expression: Defines what the loop iterates over
 * - Filter: Defines how to filter the iteration set
 *
 * @since 7.0.0
 */
class Loop implements Parent_Id {

	/**
	 * Loop ID.
	 *
	 * @var Loop_Id
	 */
	private Loop_Id $loop_id;

	/**
	 * Recipe ID.
	 *
	 * @var Loop_Recipe_Id
	 */
	private Loop_Recipe_Id $recipe_id;

	/**
	 * Loop status.
	 *
	 * @var Loop_Status_Value|null
	 */
	private ?Loop_Status_Value $status = null;

	/**
	 * UI order.
	 *
	 * @var Loop_Ui_Order
	 */
	private Loop_Ui_Order $ui_order;

	/**
	 * Iterable expression (from Iterable_Expression bounded context).
	 *
	 * @var Expression
	 */
	private Expression $expression;

	/**
	 * Run on condition.
	 *
	 * @var mixed
	 */
	private $run_on = null;

	/**
	 * Loop filters (from Filter bounded context).
	 *
	 * @var Filter[]
	 */
	private array $filters = array();

	/**
	 * Loop items (action IDs within this loop).
	 *
	 * @var array
	 */
	private array $items = array();

	/**
	 * Constructor.
	 *
	 * @param Loop_Config $config Loop configuration object.
	 */
	public function __construct( Loop_Config $config ) {
		$this->loop_id   = new Loop_Id( $config->get_id() );
		$this->recipe_id = new Loop_Recipe_Id( $config->get_recipe_id() );
		$this->ui_order  = new Loop_Ui_Order( $config->get_ui_order() );

		// Set iterable expression (from Iterable_Expression bounded context)
		$expression_data  = $config->get_iterable_expression();
		$this->expression = Expression::from_array( $expression_data );

		// Set status if provided
		if ( null !== $config->get_status() ) {
			$this->status = new Loop_Status_Value( $config->get_status() );
		}

		// Set run_on condition
		$this->run_on = $config->get_run_on();

		// Build Filter entities from config filters (from Filter bounded context)
		$this->build_filters( $config->get_filters() );

		// Store item references (action IDs)
		$this->items = $config->get_items();

		$this->validate_business_rules();
	}

	/**
	 * Get loop ID value.
	 *
	 * Implements Parent_Id interface.
	 *
	 * @return int|null The loop identifier, or null for new loops.
	 */
	public function get_value(): ?int {
		return $this->loop_id->get_value();
	}

	/**
	 * Get loop ID.
	 *
	 * @return Loop_Id
	 */
	public function get_loop_id(): Loop_Id {
		return $this->loop_id;
	}

	/**
	 * Get recipe ID.
	 *
	 * @return Loop_Recipe_Id
	 */
	public function get_recipe_id(): Loop_Recipe_Id {
		return $this->recipe_id;
	}

	/**
	 * Get loop status.
	 *
	 * @return Loop_Status_Value|null
	 */
	public function get_status(): ?Loop_Status_Value {
		return $this->status;
	}

	/**
	 * Get UI order.
	 *
	 * @return Loop_Ui_Order
	 */
	public function get_ui_order(): Loop_Ui_Order {
		return $this->ui_order;
	}

	/**
	 * Get iterable expression.
	 *
	 * @return Expression
	 */
	public function get_expression(): Expression {
		return $this->expression;
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
	 * @return Filter[]
	 */
	public function get_filters(): array {
		return $this->filters;
	}

	/**
	 * Get loop items (action IDs).
	 *
	 * @return array
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Check if this is a users loop.
	 *
	 * @return bool
	 */
	public function is_user_loop(): bool {
		return $this->expression->is_users();
	}

	/**
	 * Check if this is a posts loop.
	 *
	 * @return bool
	 */
	public function is_post_loop(): bool {
		return $this->expression->is_posts();
	}

	/**
	 * Check if this is a token loop.
	 *
	 * @return bool
	 */
	public function is_token_loop(): bool {
		return $this->expression->is_token();
	}

	/**
	 * Check if loop is persisted.
	 *
	 * @return bool True if loop has been saved to database.
	 */
	public function is_persisted(): bool {
		return null !== $this->loop_id->get_value() && $this->loop_id->get_value() > 0;
	}

	/**
	 * Check if loop is published.
	 *
	 * @return bool
	 */
	public function is_published(): bool {
		return null !== $this->status && $this->status->is_published();
	}

	/**
	 * Check if loop is draft.
	 *
	 * @return bool
	 */
	public function is_draft(): bool {
		return null === $this->status || $this->status->is_draft();
	}

	/**
	 * Check if loop has filters.
	 *
	 * @return bool
	 */
	public function has_filters(): bool {
		return ! empty( $this->filters );
	}

	/**
	 * Check if loop has items.
	 *
	 * @return bool
	 */
	public function has_items(): bool {
		return ! empty( $this->items );
	}

	/**
	 * Get filter count.
	 *
	 * @return int
	 */
	public function get_filter_count(): int {
		return count( $this->filters );
	}

	/**
	 * Get item count.
	 *
	 * @return int
	 */
	public function get_item_count(): int {
		return count( $this->items );
	}

	/**
	 * Convert to array.
	 *
	 * @return array Loop data as array.
	 */
	public function to_array(): array {
		$filters_array = array();
		foreach ( $this->filters as $filter ) {
			$filters_array[] = $filter->to_array();
		}

		return array(
			'type'                => 'loop',
			'id'                  => $this->loop_id->get_value(),
			'recipe_id'           => $this->recipe_id->get_value(),
			'status'              => null !== $this->status ? $this->status->get_value() : Loop_Status::DRAFT,
			'_ui_order'           => $this->ui_order->get_value(),
			'iterable_expression' => $this->expression->to_array(),
			'run_on'              => $this->run_on,
			'filters'             => $filters_array,
			'items'               => $this->items,
		);
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Array data.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		return new self( Loop_Config::from_array( $data ) );
	}

	/**
	 * Build Filter entities from filter data.
	 *
	 * @param array $filters_data Raw filter data.
	 */
	private function build_filters( array $filters_data ): void {
		foreach ( $filters_data as $filter_data ) {
			if ( is_array( $filter_data ) ) {
				$this->filters[] = Filter::from_array( $filter_data );
			} elseif ( $filter_data instanceof Filter ) {
				$this->filters[] = $filter_data;
			}
		}
	}

	/**
	 * Validate business rules.
	 *
	 * @throws \InvalidArgumentException If business rules are violated.
	 */
	private function validate_business_rules(): void {
		// Business rule: Loops must belong to a recipe
		if ( $this->recipe_id->is_null() ) {
			throw new \InvalidArgumentException( 'Loop must belong to a recipe (recipe_id required)' );
		}
	}
}
