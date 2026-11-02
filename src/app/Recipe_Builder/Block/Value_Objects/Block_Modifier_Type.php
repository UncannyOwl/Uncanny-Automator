<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

use InvalidArgumentException;

/**
 * Block Modifier Type Value Object.
 *
 * One accepted modifier kind on a block's modifier slot. Carries the wire `id` (the `block_modifier_type` code) and a display `name` contextual to the parent block: inside Filter the name is typically "Condition"; inside Loop, "Filter".
 *
 * Per-block phrasing is intentional: each block decides what to call its modifier slot. The wizard's "you're picking one of these" breadcrumb renders this name when the user is mid-pick, so the block context is implicit and the name doesn't need to repeat the block's own name.
 *
 * Shape (REST projection):
 * - `id` — the `block_modifier_type` code (e.g. `'filter_condition'`, `'loop_filter'`).
 * - `name` — display name, contextual to the parent block.
 *
 * @since 7.x
 */
class Block_Modifier_Type {

	/**
	 * The wire `block_modifier_type` code.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * The display name of this modifier kind, contextual to the parent block.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Constructor.
	 *
	 * @param array $data Modifier-type data.
	 *   @property string $id   The `block_modifier_type` code.
	 *   @property string $name Display name (contextual to the block).
	 *
	 * @throws InvalidArgumentException If invalid data.
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->id   = $data['id'];
		$this->name = $data['name'];
	}

	/**
	 * Get the wire `block_modifier_type` code.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Get the display name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Convert to array (REST shape).
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'id'   => $this->id,
			'name' => $this->name,
		);
	}

	/**
	 * Validate modifier-type data.
	 *
	 * @param array $data Data to validate.
	 *
	 * @throws InvalidArgumentException If invalid.
	 *
	 * @return void
	 */
	private function validate( array $data ): void {
		if ( empty( $data['id'] ) || ! is_string( $data['id'] ) ) {
			throw new InvalidArgumentException( 'Block modifier type `id` must be a non-empty string' );
		}

		if ( empty( $data['name'] ) || ! is_string( $data['name'] ) ) {
			throw new InvalidArgumentException( 'Block modifier type `name` must be a non-empty string' );
		}
	}
}
