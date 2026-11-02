<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Block\Migration;

/**
 * A legacy recipe could not be migrated to block storage in this request.
 * The vintage was not bumped, so the next request retries; callers fail closed.
 */
final class Legacy_Storage_Migration_Exception extends \RuntimeException {

	/**
	 * The one sentence a reader reports when this failure stops it reading a recipe.
	 *
	 * get_recipe, get_tokens and token validation must give the same diagnosis
	 * for the same failure, so the wording lives with the failure.
	 *
	 * @param int $recipe_id The recipe whose read failed.
	 * @return string
	 */
	public function user_message( int $recipe_id ): string {
		return sprintf( 'Recipe %d could not be read: its legacy storage migration failed (%s). Retry, or open the recipe in the builder once.', $recipe_id, $this->getMessage() );
	}
}
