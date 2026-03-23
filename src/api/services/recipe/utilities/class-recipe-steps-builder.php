<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Recipe\Utilities;

/**
 * Recipe Steps Builder Service.
 *
 * Service for building recipe next steps for MCP responses.
 *
 * @since 7.0.0
 */
class Recipe_Steps_Builder {

	/**
	 * Build recipe next steps for the given recipe ID.
	 *
	 * @since 7.0.0
	 * @param int $recipe_id The recipe ID.
	 * @return array Array of recipe next steps.
	 */
	public function build_steps( int $recipe_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$edit_link = get_edit_post_link( $recipe_id, 'raw' );

		return array(
			'edit_recipe' => array(
				'admin_url' => is_string( $edit_link ) ? $edit_link : '',
				'hint'      => 'Open the recipe editor to manage triggers, actions, and conditions.',
			),
		);
	}
}
