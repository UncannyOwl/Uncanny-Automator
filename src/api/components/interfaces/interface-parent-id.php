<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Interfaces;

/**
 * Parent ID Interface.
 *
 * Represents a parent identifier that can be either a Recipe_ID or a Loop_ID.
 * This interface allows Actions to reference their parent container in a type-safe way.
 *
 * Implementations:
 * - Recipe_ID: For actions directly under a recipe
 * - Loop_ID: For actions nested within a loop
 *
 * @since 7.0.0
 */
interface Parent_Id {

	/**
	 * Get the parent ID value.
	 *
	 * @return int|null The parent identifier, or null for new instances.
	 */
	public function get_value(): ?int;
}
