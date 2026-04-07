<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Recipe\Utilities;

use Uncanny_Automator\Api\Components\Shared\Traits\Empty_Array_To_Object;

/**
 * Recipe Link Builder Service.
 *
 * Service for building recipe-related links for MCP responses.
 *
 * @since 7.0.0
 */
class Recipe_Link_Builder {

	use Empty_Array_To_Object;

	/**
	 * Build recipe links for the given recipe ID.
	 *
	 * @since 7.0.0
	 * @param int $recipe_id The recipe ID.
	 * @return array|\stdClass Array of recipe links, or empty object.
	 */
	public function build_links( int $recipe_id ) {
		if ( $recipe_id <= 0 ) {
			return $this->empty_object();
		}

		$edit_link = get_edit_post_link( $recipe_id, 'raw' );
		if ( ! is_string( $edit_link ) || '' === $edit_link ) {
			return $this->empty_object();
		}

		return array( 'edit_recipe' => $edit_link );
	}
}
