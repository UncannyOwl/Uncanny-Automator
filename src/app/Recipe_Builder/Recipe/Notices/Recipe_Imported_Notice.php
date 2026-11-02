<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Notices;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Severity;
use Uncanny_Automator\Import_Recipe;

/**
 * "Recipe imported on 2026-08-12. Please make sure to set the correct values
 * before you take this recipe live.": a warning, with no heading, from the
 * moment a recipe is imported until its first change.
 *
 * `Import_Recipe` owns the state. It writes the dated, localized sentence to
 * `uap_recipe_imported` on every import (JSON upload, bulk import, template
 * library) and deletes it on the recipe's first `automator_event_recipe_changed`.
 * This notice sends that sentence as it is.
 *
 * @since 8.0.0
 */
final class Recipe_Imported_Notice extends Recipe_Notice {

	/**
	 * The notice's code: `recipe_imported`.
	 *
	 * @return string
	 */
	public function code(): string {
		return 'recipe_imported';
	}

	/**
	 * The stored import sentence as a warning, while the recipe carries it.
	 *
	 * @param int $recipe_id The recipe being read.
	 *
	 * @return Recipe_Notice_Message|null Null when the recipe was not imported, or has changed since.
	 */
	public function message( int $recipe_id ): ?Recipe_Notice_Message {
		$sentence = Database::get_recipe_store()->get_meta( $recipe_id, Import_Recipe::IMPORTED_RECIPE_WARNING_META, '' );

		if ( ! is_string( $sentence ) || '' === trim( $sentence ) ) {
			return null;
		}

		return new Recipe_Notice_Message( Notice_Severity::WARNING, $sentence );
	}
}
