<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Integration Items Value Object.
 *
 * Contains the general definitions of the functional units (triggers, actions, etc.)
 * provided by this integration. Each category is an object where keys are item codes
 * (e.g., "WCPURCHASESPRODUCT") and values are Integration_Item objects.
 *
 * @since 7.0.0
 */
class Integration_Items {

	/**
	 * The triggers.
	 *
	 * @var array
	 */
	private array $trigger;

	/**
	 * The actions.
	 *
	 * @var array
	 */
	private array $action;

	/**
	 * The loop filters.
	 *
	 * @var array
	 */
	private array $loop_filter;

	/**
	 * The filter conditions.
	 *
	 * @var array
	 */
	private array $filter_condition;

	/**
	 * The closures.
	 *
	 * @var array
	 */
	private array $closure;

	/**
	 * Constructor.
	 *
	 * @param array $items Items array with :
	 *  @property array $trigger Array of Trigger objects.
	 *  @property array $action Array of Action objects.
	 *  @property array $loop_filter Array of Loop Filter objects.
	 *  @property array $filter_condition Array of Filter Condition objects.
	 *  @property array $closure Array of Closure objects.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid items.
	 */
	public function __construct( array $items ) {
		$this->validate( $items );

		$this->trigger          = $this->build_item_objects( $items['trigger'] ?? array() );
		$this->action           = $this->build_item_objects( $items['action'] ?? array() );
		$this->loop_filter      = $this->build_item_objects( $items['loop_filter'] ?? array() );
		$this->filter_condition = $this->build_item_objects( $items['filter_condition'] ?? array() );
		$this->closure          = $this->build_item_objects( $items['closure'] ?? array() );
	}

	/**
	 * Get triggers.
	 *
	 * @return array<string, Integration_Item> Array of Integration_Item objects keyed by code.
	 */
	public function get_triggers(): array {
		return $this->trigger;
	}

	/**
	 * Get actions.
	 *
	 * @return array<string, Integration_Item> Array of Integration_Item objects keyed by code.
	 */
	public function get_actions(): array {
		return $this->action;
	}

	/**
	 * Get loop filters.
	 *
	 * @return array<string, Integration_Item> Array of Integration_Item objects keyed by code.
	 */
	public function get_loop_filters(): array {
		return $this->loop_filter;
	}

	/**
	 * Get conditions.
	 *
	 * @return array<string, Integration_Item> Array of Integration_Item objects keyed by code.
	 */
	public function get_filter_conditions(): array {
		return $this->filter_condition;
	}

	/**
	 * Get closures.
	 *
	 * @return array<string, Integration_Item> Array of Integration_Item objects keyed by code.
	 */
	public function get_closures(): array {
		return $this->closure;
	}

	/**
	 * Check if integration has triggers.
	 *
	 * @return bool
	 */
	public function has_triggers(): bool {
		return ! empty( $this->trigger );
	}

	/**
	 * Check if integration has actions.
	 *
	 * @return bool
	 */
	public function has_actions(): bool {
		return ! empty( $this->action );
	}

	/**
	 * Check if integration has loop filters.
	 *
	 * @return bool
	 */
	public function has_loop_filters(): bool {
		return ! empty( $this->loop_filter );
	}

	/**
	 * Check if integration has conditions.
	 *
	 * @return bool
	 */
	public function has_filter_conditions(): bool {
		return ! empty( $this->filter_condition );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'trigger'          => $this->items_to_array( $this->trigger ),
			'action'           => $this->items_to_array( $this->action ),
			'loop_filter'      => $this->items_to_array( $this->loop_filter ),
			'filter_condition' => $this->items_to_array( $this->filter_condition ),
			'closure'          => $this->items_to_array( $this->closure ),
		);
	}

	/**
	 * Convert to REST format.
	 *
	 * Uses Integration_Item::to_rest() to exclude backend-only properties.
	 * Converts empty arrays to empty objects for JavaScript compatibility.
	 *
	 * @return array
	 */
	public function to_rest(): array {
		return array(
			'trigger'          => $this->items_to_rest( $this->trigger ),
			'action'           => $this->items_to_rest( $this->action ),
			'loop_filter'      => $this->items_to_rest( $this->loop_filter ),
			'filter_condition' => $this->items_to_rest( $this->filter_condition ),
			'closure'          => $this->items_to_rest( $this->closure ),
		);
	}

	/**
	 * Validate items.
	 *
	 * @param array $items Items to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $items ): void {
		// Each item type must be an array if provided
		$item_types = Integration_Item_Types::get_all();
		foreach ( $item_types as $type ) {
			if ( isset( $items[ $type ] ) && ! is_array( $items[ $type ] ) ) {
				throw new InvalidArgumentException(
					"Integration items['{$type}'] must be an array"
				);
			}
		}
	}

	/**
	 * Build Integration_Item objects from array data.
	 *
	 * @param array $items Array of item data keyed by code.
	 *
	 * @return array<string, Integration_Item> Array of Integration_Item objects keyed by code.
	 */
	private function build_item_objects( array $items ): array {
		$objects = array();

		foreach ( $items as $code => $item_data ) {
			// Ensure code is set in the item data
			if ( ! isset( $item_data['code'] ) ) {
				$item_data['code'] = $code;
			}

			$objects[ $code ] = new Integration_Item( $item_data );
		}

		return $objects;
	}

	/**
	 * Convert array of Integration_Item objects to arrays.
	 *
	 * @param array<string, Integration_Item> $items Array of Integration_Item objects.
	 *
	 * @return array Array of item data keyed by code.
	 */
	private function items_to_array( array $items ): array {
		$arrays = array();

		foreach ( $items as $code => $item ) {
			$arrays[ $code ] = $item->to_array();
		}

		return $arrays;
	}

	/**
	 * Convert array of Integration_Item objects to REST format.
	 *
	 * Uses Integration_Item::to_rest() to exclude backend-only properties.
	 * Returns empty object for JavaScript compatibility when no items exist.
	 *
	 * @param array<string, Integration_Item> $items Array of Integration_Item objects.
	 *
	 * @return array|object Array of item data keyed by code, or empty object.
	 */
	private function items_to_rest( array $items ) {
		if ( empty( $items ) ) {
			return (object) array();
		}

		$arrays = array();

		foreach ( $items as $code => $item ) {
			$arrays[ $code ] = $item->to_rest();
		}

		return $arrays;
	}
}
