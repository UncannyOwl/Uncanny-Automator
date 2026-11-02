<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Notices;

use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Severity;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Loop_Fold_Bulk_Migration;

/**
 * "A loop in this recipe is waiting to be upgraded": a warning, from the moment
 * the upgrade could not fold one of the recipe's loops until a later pass folds it.
 *
 * `Loop_Fold_Bulk_Migration` owns the state. It records the recipes it could not
 * fold when a pass fails and forgets them when a pass completes. A loop that has
 * not folded is not in the recipe tree, so the builder does not show it and the
 * runner does not run it, and nothing else tells the site owner.
 *
 * @since 8.0.0
 */
final class Loop_Not_Migrated_Notice extends Recipe_Notice {

	/**
	 * The notice's code: `loop_not_migrated`.
	 *
	 * @return string
	 */
	public function code(): string {
		return 'loop_not_migrated';
	}

	/**
	 * The warning, while the loop fold has recorded the recipe as one it could not fold.
	 *
	 * @param int $recipe_id The recipe being read.
	 *
	 * @return Recipe_Notice_Message|null Null when the fold has not failed on the recipe, or has folded it since.
	 */
	public function message( int $recipe_id ): ?Recipe_Notice_Message {
		if ( ! in_array( $recipe_id, Loop_Fold_Bulk_Migration::failed_recipe_ids(), true ) ) {
			return null;
		}

		return new Recipe_Notice_Message(
			Notice_Severity::WARNING,
			esc_html_x( 'Uncanny Automator could not finish upgrading a loop in this recipe to version 8.0. Until it does, the loop and the actions inside it are not shown in the builder and do not run. Automator tries again automatically. If this message is still here after a few hours, contact support.', 'Recipe notice message', 'uncanny-automator' ),
			esc_html_x( 'A loop in this recipe is waiting to be upgraded', 'Recipe notice heading', 'uncanny-automator' )
		);
	}
}
