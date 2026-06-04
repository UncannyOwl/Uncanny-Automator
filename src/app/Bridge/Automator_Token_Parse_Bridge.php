<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Token_Parse_Bridge}.
 *
 * @since 7.4.0
 */
final class Automator_Token_Parse_Bridge implements Token_Parse_Bridge {

	/**
	 * @inheritDoc
	 */
	public function parse_tokens( string $text, int $recipe_id, int $user_id, array $args ): string {
		return (string) \Automator()->parse->text( $text, $recipe_id, $user_id, $args );
	}
}
