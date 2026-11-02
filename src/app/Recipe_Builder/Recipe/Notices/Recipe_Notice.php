<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Notices;

/**
 * One notice about a recipe, sent to the builder as an entry of the recipe
 * wire's `notices` list. Every recipe notice extends this and is held by
 * Recipe_Notice_Center.
 *
 * `message()` only reads. It runs on every read of the recipe wire (the
 * builder's REST responses, MCP `get_recipe`, token validation), and the state
 * it reads is written and cleared by its owner.
 *
 * @since 8.0.0
 */
abstract class Recipe_Notice {

	/**
	 * The notice's stable code, lower-case snake_case (`recipe_imported`).
	 *
	 * Sent as the wire `code`. The builder never branches on it.
	 *
	 * @return string
	 */
	abstract public function code(): string;

	/**
	 * What the notice says about the recipe right now.
	 *
	 * Reads only: no write, no remote call, no state consumed.
	 *
	 * @param int $recipe_id The recipe being read.
	 *
	 * @return Recipe_Notice_Message|null Null when the notice does not apply to the recipe.
	 */
	abstract public function message( int $recipe_id ): ?Recipe_Notice_Message;
}
