<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Enums;

/**
 * Block Layout Enum.
 *
 * How a block ENCLOSES the recipe items placed inside it, declared by the block
 * definition itself rather than inferred by whatever renders it.
 *
 * This is a statement about shape, not about type: a definition says which of
 * the three it draws, and every surface that lays a recipe out - the vertical
 * builder, the canvas projection, anything after them - reads the same answer.
 * Before this existed the canvas guessed, from the number of paths a block
 * declared and from whether it happened to accept modifiers, which meant Delay
 * enclosed nothing because nobody had ever called `modifier_types()` on it.
 */
class Block_Layout {

	/**
	 * The block is a container: one lane of items, drawn inside a box the flow
	 * passes through. Loop.
	 *
	 * @var string
	 */
	const FRAME = 'frame';

	/**
	 * The block branches: two or more lanes, drawn side by side, splitting above
	 * and coming back together below. Filter.
	 *
	 * @var string
	 */
	const FAN_OUT = 'fan_out';

	/**
	 * The block's contents run later, so the flow does not pass through it: it
	 * is drawn apart from the flow, and its position among the recipe's other
	 * deferred blocks rides on each block node as `deferred_order`. Delay.
	 *
	 * @since 8.0.0
	 * @var string
	 */
	const DEFERRED = 'deferred';

	/**
	 * The layout a block that declares none draws in.
	 *
	 * A container, because a block that says nothing about its shape still has a
	 * body items are placed in, and a box around that body is what a body means.
	 * Fanning out is the deliberate case, and a block that fans out says so.
	 *
	 * @var string
	 */
	const DEFAULT_LAYOUT = self::FRAME;

	/**
	 * Validate block layout value.
	 *
	 * @param string $value Block layout value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $value ): bool {
		return in_array( $value, self::get_all(), true );
	}

	/**
	 * Get all valid block layout values.
	 *
	 * @return array<string> Array of valid block layout values.
	 */
	public static function get_all(): array {
		return array(
			self::FRAME,
			self::FAN_OUT,
			self::DEFERRED,
		);
	}
}
