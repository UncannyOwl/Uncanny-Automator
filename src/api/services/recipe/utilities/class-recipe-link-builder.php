<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Recipe\Utilities;

/**
 * Recipe Link Builder Service.
 *
 * Service for building recipe-related links for MCP responses.
 *
 * @since 7.0.0
 */
class Recipe_Link_Builder {

	/**
	 * Build recipe links for the given recipe ID.
	 *
	 * @since 7.0.0
	 * @param int $recipe_id The recipe ID.
	 * @return array Array of recipe links.
	 */
	public function build_links( int $recipe_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$edit_link = get_edit_post_link( $recipe_id, 'raw' );
		if ( ! is_string( $edit_link ) || '' === $edit_link ) {
			return array();
		}

		return array( 'edit_recipe' => $edit_link );
	}
}
