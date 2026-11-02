<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy action-tokens hydrator.
 *
 * Exposes one intent-named method (`hydrate_action_tokens`) that
 * encapsulates the full configure-then-hydrate sequence. The legacy
 * hydrator object (`Automator()->action_tokens()->hydrator()`) never
 * leaks through this interface.
 *
 * @since 7.4.0
 */
interface Action_Tokens_Bridge {

	/**
	 * Hydrate action tokens for a completed action.
	 *
	 * Configures the legacy hydrator with the action context and stores
	 * the token value. Replaces the leaked `get_hydrator()` pattern.
	 *
	 * @param int   $user_id       The user ID.
	 * @param int   $action_id     The action post ID.
	 * @param int   $action_log_id The action log ID.
	 * @param array $process_args  The process arguments.
	 * @param mixed $value         The token value to hydrate.
	 * @param bool  $encode        Whether to JSON-encode the value before storing.
	 *
	 * @return bool|int Result of the store operation, or false on empty value.
	 */
	public function hydrate_action_tokens( int $user_id, int $action_id, int $action_log_id, array $process_args, $value, bool $encode = true );

	/**
	 * Resolve the renderable token list for an action — what the
	 * bundled recipe-builder shows in the token dropdown for downstream
	 * items.
	 *
	 * Wraps `Automator()->tokens->get_action_tokens_renderable()`. That
	 * call fires `automator_action_{CODE}_tokens_renderable` so action
	 * classes (e.g. DROPBOX_CREATE_FOLDER declaring FOLDER_PATH via
	 * `set_action_tokens()`) can register what they emit.
	 *
	 * Used by {@see \Uncanny_Automator\App\Recipe_Builder\Block\Resolvers\Block_Resolver::resolve_child}
	 * to populate `$action['tokens']` for actions inside `uo-block`
	 * posts. Without this the stamping `Automator_Functions::get_recipe_data`
	 * performs never fires for wrapped actions, and the dropdown loses every
	 * token they declare.
	 *
	 * @param array $action_meta Flat post-meta map for the action (must
	 *                           include `code`).
	 * @param int   $action_id   Action post id.
	 * @param int   $recipe_id   Owning recipe id.
	 *
	 * @return array Renderable token list in the legacy
	 *               `[ {tokenId, tokenType, tokenName, tokenParent} ]` shape.
	 */
	public function get_renderable_tokens( array $action_meta, int $action_id, int $recipe_id ): array;
}
