<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;

/**
 * Integration Block Modifiers Value Object.
 *
 * Sibling of Integration_Items. Two private slots — one per modifier kind.
 * to_rest() emits the 2-key map { filter_condition: {...}, loop_filter: {...} }
 * required by frontend ModelIntegrationBlockModifiersSchema. Empty buckets
 * become stdClass `{}` objects for JSON object-vs-array compatibility.
 */
class Integration_Block_Modifiers {

	/**
	 * Filter-condition modifiers, keyed by modifier code.
	 *
	 * @var array<string, Integration_Block_Modifier>
	 */
	private array $filter_condition;

	/**
	 * Loop-filter modifiers, keyed by modifier code.
	 *
	 * @var array<string, Integration_Block_Modifier>
	 */
	private array $loop_filter;

	/**
	 * Sort the raw catalog payload into one slot per modifier kind.
	 *
	 * A kind the integration does not register is simply absent from the
	 * payload and lands as an empty slot.
	 *
	 * @param array<string, array> $modifiers Raw modifiers keyed by kind, then by code.
	 *
	 * @throws InvalidArgumentException When a present kind is not an array.
	 */
	public function __construct( array $modifiers ) {
		$this->validate( $modifiers );

		$this->filter_condition = $this->build_modifier_objects(
			$modifiers[ Block_Modifier_Types::FILTER_CONDITION ] ?? array(),
			Block_Modifier_Types::FILTER_CONDITION
		);
		$this->loop_filter      = $this->build_modifier_objects(
			$modifiers[ Block_Modifier_Types::LOOP_FILTER ] ?? array(),
			Block_Modifier_Types::LOOP_FILTER
		);
	}

	/**
	 * The filter conditions this integration contributes to the catalog.
	 *
	 * @return array<string, Integration_Block_Modifier> Keyed by modifier code.
	 */
	public function get_filter_conditions(): array {
		return $this->filter_condition;
	}

	/**
	 * The loop filters this integration contributes to the catalog.
	 *
	 * @return array<string, Integration_Block_Modifier> Keyed by modifier code.
	 */
	public function get_loop_filters(): array {
		return $this->loop_filter;
	}

	/**
	 * Whether this integration registers any filter conditions at all.
	 *
	 * @return bool
	 */
	public function has_filter_conditions(): bool {
		return ! empty( $this->filter_condition );
	}

	/**
	 * Whether this integration registers any loop filters at all.
	 *
	 * @return bool
	 */
	public function has_loop_filters(): bool {
		return ! empty( $this->loop_filter );
	}

	/**
	 * Flatten to plain arrays for internal use and storage.
	 *
	 * Unlike to_rest(), empty slots stay as empty arrays — no stdClass
	 * coercion, because nothing here is going through json_encode().
	 *
	 * @return array<string, array> Two-key map of kind => modifiers.
	 */
	public function to_array(): array {
		return array(
			'filter_condition' => $this->modifiers_to_array( $this->filter_condition ),
			'loop_filter'      => $this->modifiers_to_array( $this->loop_filter ),
		);
	}

	/**
	 * Project to the two-key map the builder's ModelIntegrationBlockModifiers
	 * schema expects.
	 *
	 * @return array<string, array|object> Empty kinds become stdClass so they
	 *                                     encode as `{}` rather than `[]`.
	 */
	public function to_rest(): array {
		return array(
			'filter_condition' => $this->modifiers_to_rest( $this->filter_condition ),
			'loop_filter'      => $this->modifiers_to_rest( $this->loop_filter ),
		);
	}

	/**
	 * Reject a payload where a modifier kind is present but not a list.
	 *
	 * @param array<string, mixed> $modifiers Raw modifiers keyed by kind.
	 *
	 * @throws InvalidArgumentException When a present kind is not an array.
	 *
	 * @return void
	 */
	private function validate( array $modifiers ): void {
		$modifier_types = Block_Modifier_Types::get_all();
		foreach ( $modifier_types as $type ) {
			if ( isset( $modifiers[ $type ] ) && ! is_array( $modifiers[ $type ] ) ) {
				throw new InvalidArgumentException(
					sprintf( "Integration block modifiers['%s'] must be an array", esc_html( $type ) )
				);
			}
		}
	}

	/**
	 * Hydrate one slot's raw rows into modifier objects.
	 *
	 * The array key is authoritative for the code: a row that omits its own
	 * `code` inherits the key it was stored under. The bucket is likewise
	 * authoritative for the type — a row declaring a `type` that contradicts
	 * the slot it was stored under is rejected rather than shipped, since the
	 * wire would then carry a modifier whose own type disagrees with the
	 * bucket the frontend read it from.
	 *
	 * @param array<string, array> $modifiers Raw rows keyed by modifier code.
	 * @param string               $slot_type The bucket these rows came from.
	 *
	 * @throws InvalidArgumentException When a row's type contradicts its bucket.
	 *
	 * @return array<string, Integration_Block_Modifier> Keyed by modifier code.
	 */
	private function build_modifier_objects( array $modifiers, string $slot_type ): array {
		$objects = array();
		foreach ( $modifiers as $code => $modifier_data ) {
			if ( ! isset( $modifier_data['code'] ) ) {
				$modifier_data['code'] = $code;
			}

			if ( isset( $modifier_data['type'] ) && $slot_type !== $modifier_data['type'] ) {
				throw new InvalidArgumentException(
					sprintf(
						'Integration block modifier "%s" declares type "%s" but is stored under "%s"',
						esc_html( (string) $code ),
						esc_html( (string) $modifier_data['type'] ),
						esc_html( $slot_type )
					)
				);
			}

			$modifier_data['type'] = $slot_type;

			$objects[ $code ] = new Integration_Block_Modifier( $modifier_data );
		}
		return $objects;
	}

	/**
	 * Flatten one slot's modifier objects to plain arrays.
	 *
	 * @param array<string, Integration_Block_Modifier> $modifiers Keyed by modifier code.
	 *
	 * @return array<string, array> Keyed by modifier code.
	 */
	private function modifiers_to_array( array $modifiers ): array {
		$arrays = array();
		foreach ( $modifiers as $code => $modifier ) {
			$arrays[ $code ] = $modifier->to_array();
		}
		return $arrays;
	}

	/**
	 * Project one slot's modifier objects to their REST shape.
	 *
	 * @param array<string, Integration_Block_Modifier> $modifiers Keyed by modifier code.
	 *
	 * @return array|object Empty stdClass object when no modifiers, keyed array otherwise.
	 */
	private function modifiers_to_rest( array $modifiers ) {
		if ( empty( $modifiers ) ) {
			return (object) array();
		}
		$arrays = array();
		foreach ( $modifiers as $code => $modifier ) {
			$arrays[ $code ] = $modifier->to_rest();
		}
		return $arrays;
	}
}
