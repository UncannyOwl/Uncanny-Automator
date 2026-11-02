<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;

/**
 * Integration Items Value Object.
 *
 * Two-bucket collection of trigger / action items. Filter
 * conditions and loop filters live in Integration_Block_Modifiers
 * (sibling slot under integration.block_modifiers on the wire).
 */
class Integration_Items {

	/**
	 * Trigger items, keyed by item code.
	 *
	 * @var array<string, Integration_Item>
	 */
	private array $trigger;

	/**
	 * Action items, keyed by item code.
	 *
	 * @var array<string, Integration_Item>
	 */
	private array $action;

	/**
	 * Sort the raw catalog payload into one bucket per item type.
	 *
	 * A type the integration does not register is simply absent from the
	 * payload and lands as an empty bucket.
	 *
	 * @param array<string, array> $items Raw items keyed by type, then by code.
	 *
	 * @throws InvalidArgumentException When a present type is not an array.
	 */
	public function __construct( array $items ) {
		$this->validate( $items );

		$this->trigger = $this->build_item_objects( $items['trigger'] ?? array() );
		$this->action  = $this->build_item_objects( $items['action'] ?? array() );
	}

	/**
	 * The triggers this integration contributes to the catalog.
	 *
	 * @return array<string, Integration_Item> Keyed by item code.
	 */
	public function get_triggers(): array {
		return $this->trigger;
	}

	/**
	 * The actions this integration contributes to the catalog.
	 *
	 * @return array<string, Integration_Item> Keyed by item code.
	 */
	public function get_actions(): array {
		return $this->action;
	}

	/**
	 * Whether this integration registers any triggers at all.
	 *
	 * @return bool
	 */
	public function has_triggers(): bool {
		return ! empty( $this->trigger );
	}

	/**
	 * Whether this integration registers any actions at all.
	 *
	 * @return bool
	 */
	public function has_actions(): bool {
		return ! empty( $this->action );
	}

	/**
	 * Flatten to plain arrays for internal use and storage.
	 *
	 * Unlike to_rest(), empty buckets stay as empty arrays — no stdClass
	 * coercion, because nothing here is going through json_encode().
	 *
	 * @return array<string, array> Two-key map of type => items.
	 */
	public function to_array(): array {
		return array(
			'trigger' => $this->items_to_array( $this->trigger ),
			'action'  => $this->items_to_array( $this->action ),
		);
	}

	/**
	 * Project to the two-key map the builder's integration schema expects.
	 *
	 * @return array<string, array|object> Empty buckets become stdClass so they
	 *                                     encode as `{}` rather than `[]`.
	 */
	public function to_rest(): array {
		return array(
			'trigger' => $this->items_to_rest( $this->trigger ),
			'action'  => $this->items_to_rest( $this->action ),
		);
	}

	/**
	 * Reject a payload where an item type is present but not a list.
	 *
	 * @param array<string, mixed> $items Raw items keyed by type.
	 *
	 * @throws InvalidArgumentException When a present type is not an array.
	 *
	 * @return void
	 */
	private function validate( array $items ): void {
		$item_types = Integration_Item_Types::get_all();
		foreach ( $item_types as $type ) {
			if ( isset( $items[ $type ] ) && ! is_array( $items[ $type ] ) ) {
				throw new InvalidArgumentException(
					sprintf( "Integration items['%s'] must be an array", esc_html( $type ) )
				);
			}
		}
	}

	/**
	 * Hydrate one bucket's raw rows into item objects.
	 *
	 * The array key is authoritative for the code: a row that omits its own
	 * `code` inherits the key it was stored under.
	 *
	 * @param array<string, array> $items Raw rows keyed by item code.
	 *
	 * @return array<string, Integration_Item> Keyed by item code.
	 */
	private function build_item_objects( array $items ): array {
		$objects = array();

		foreach ( $items as $code => $item_data ) {
			if ( ! isset( $item_data['code'] ) ) {
				$item_data['code'] = $code;
			}

			$objects[ $code ] = new Integration_Item( $item_data );
		}

		return $objects;
	}

	/**
	 * Flatten one bucket's item objects to plain arrays.
	 *
	 * @param array<string, Integration_Item> $items Keyed by item code.
	 *
	 * @return array<string, array> Keyed by item code.
	 */
	private function items_to_array( array $items ): array {
		$arrays = array();

		foreach ( $items as $code => $item ) {
			$arrays[ $code ] = $item->to_array();
		}

		return $arrays;
	}

	/**
	 * Project one bucket's item objects to their REST shape.
	 *
	 * @param array<string, Integration_Item> $items Keyed by item code.
	 *
	 * @return array|object Empty stdClass for empty buckets, keyed array otherwise.
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
