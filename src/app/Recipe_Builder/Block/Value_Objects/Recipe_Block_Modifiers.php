<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

/**
 * Ordered, type-narrowed collection of `Recipe_Block_Modifier` VOs.
 *
 * Order matches storage `menu_order`. No `get_by_code` lookup — modifier
 * codes aren't unique per block (multiple `WP_USER_HAS_ROLE` rows on one
 * filter block are valid; each can carry different fields).
 *
 * Constructor throws on any non-`Recipe_Block_Modifier` member rather
 * than silently filtering — caller misuse is louder that way.
 */
final class Recipe_Block_Modifiers {

	/**
	 * Ordered list of modifier VOs.
	 *
	 * @var array<int, Recipe_Block_Modifier>
	 */
	private $modifiers;

	/**
	 * Take the block's modifiers in storage order, rejecting anything that is not a modifier.
	 *
	 * @param array<int, Recipe_Block_Modifier> $modifiers Ordered modifier VOs; reindexed to zero-based.
	 *
	 * @throws \InvalidArgumentException When any member is not a `Recipe_Block_Modifier`.
	 */
	public function __construct( array $modifiers ) {
		foreach ( $modifiers as $modifier ) {
			if ( ! $modifier instanceof Recipe_Block_Modifier ) {
				throw new \InvalidArgumentException(
					'Recipe_Block_Modifiers members must be Recipe_Block_Modifier instances'
				);
			}
		}
		$this->modifiers = array_values( $modifiers );
	}

	/**
	 * How many modifiers this collection holds.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->modifiers );
	}

	/**
	 * Every modifier, in the order storage holds them.
	 *
	 * @return array<int, Recipe_Block_Modifier>
	 */
	public function all(): array {
		return $this->modifiers;
	}
}
