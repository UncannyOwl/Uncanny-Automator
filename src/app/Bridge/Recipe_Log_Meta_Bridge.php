<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy recipe-log meta writer.
 *
 * Wraps `Automator()->db->recipe->add_meta()` — the legacy logger's
 * `uap_recipe_log_meta` upsert — so run-scoped meta written from `src/app/`
 * stays in lockstep with the legacy logger machinery without a raw facade
 * call (Rule 1). Consumer: `Run_Log_Flow_Translator` (the `actions_flow`
 * payload the bundled run-details modal reads).
 */
interface Recipe_Log_Meta_Bridge {

	/**
	 * Upsert one meta row for a recipe run.
	 *
	 * @param int    $recipe_id     Recipe post ID.
	 * @param int    $recipe_log_id Recipe log ID.
	 * @param string $meta_key      Meta key (e.g. `actions_flow`).
	 * @param mixed  $meta_value    Meta value; the legacy writer `wp_json_encode`s
	 *                              arrays, so pass the array, not a pre-encoded
	 *                              JSON string, or it is encoded twice.
	 * @param int    $user_id       Run user id (0 for anonymous/system scope).
	 *
	 * @return int|false Rows affected — `add_meta()` returns `$wpdb->update()` /
	 *                   `$wpdb->insert()` directly, never an insert id — or false
	 *                   when the write failed. Returned rather than swallowed so a
	 *                   caller can tell a lost payload from a stored one.
	 */
	public function upsert( int $recipe_id, int $recipe_log_id, string $meta_key, $meta_value, int $user_id = 0 );
}
