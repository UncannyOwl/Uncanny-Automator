<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for the legacy token-parser facade.
 *
 * Wraps `Automator()->parse->text()`. Named *Token_Parse* (not just
 * *Parse*) so a future facade for non-token parsing can coexist without
 * naming overlap.
 *
 * @since 7.4.0
 */
interface Token_Parse_Bridge {

	/**
	 * Resolve `{{token}}` placeholders inside a string against a recipe run.
	 *
	 * Wraps `Automator()->parse->text( $text, $recipe_id, $user_id, $args )`.
	 *
	 * @param string $text      Text containing token placeholders.
	 * @param int    $recipe_id Recipe post ID providing the parsing context.
	 * @param int    $user_id   User context for the run.
	 * @param array  $args      Additional trigger args (carries trigger log ids, etc.).
	 * @return string Parsed text with tokens substituted.
	 */
	public function parse_tokens( string $text, int $recipe_id, int $user_id, array $args ): string;
}
