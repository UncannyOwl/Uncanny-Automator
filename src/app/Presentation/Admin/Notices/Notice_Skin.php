<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

/**
 * A look for the notices: draws the entries it is given, most urgent first,
 * and says which it drew. A skin may draw fewer than it is given and count
 * the rest; only a drawn notice consumes its one-shot state.
 *
 * @since 8.0.0
 */
interface Notice_Skin {

	/**
	 * @param Notice_Entry[] $entries Most urgent first; never empty.
	 * @param Notice_Screen  $screen  The screen.
	 *
	 * @return string[] The ids it drew.
	 */
	public function render( array $entries, Notice_Screen $screen ): array;
}
