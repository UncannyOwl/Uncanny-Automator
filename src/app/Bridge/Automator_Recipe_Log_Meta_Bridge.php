<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Recipe_Log_Meta_Bridge}.
 *
 * The only place in `src/app/` permitted to call
 * `Automator()->db->recipe->add_meta()`.
 */
final class Automator_Recipe_Log_Meta_Bridge implements Recipe_Log_Meta_Bridge {

	/**
	 * @inheritDoc
	 */
	public function upsert( int $recipe_id, int $recipe_log_id, string $meta_key, $meta_value, int $user_id = 0 ) {
		return \Automator()->db->recipe->add_meta(
			$meta_key,
			$meta_value,
			array(
				'recipe_id'     => $recipe_id,
				'recipe_log_id' => $recipe_log_id,
				'user_id'       => $user_id,
			),
			true
		);
	}
}
