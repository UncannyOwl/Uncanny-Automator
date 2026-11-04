<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Token\Domain;

/**
 * Tells a loopable token apart by its id.
 *
 * A loopable token holds a list a token loop iterates. Every kind carries the
 * same id prefix, which is also what the token parser dispatches its list on:
 * a trigger's (`TOKEN_EXTENDED:DATA_TOKEN_{id}:{trigger_id}:{code}:{id}`), an
 * action's (`...:ACTION_TOKEN:{action_id}:{code}:{id}`) and an integration's
 * universal one (`...:UNIVERSAL:{integration}:{id}`). The per-item tokens a loop
 * publishes for the item it is on share the prefix with `CHILDREN_` after it;
 * each holds one value, so none of them is loopable. A token typed as an array
 * without the prefix is not loopable either: no loopable class hydrates it.
 *
 * @since 8.0.1
 */
final class Loopable_Token_Id {

	/**
	 * Id prefix of every loopable token.
	 *
	 * @var string
	 */
	const PREFIX = 'TOKEN_EXTENDED:DATA_TOKEN_';

	/**
	 * Id prefix of the per-item tokens a loop publishes inside its body.
	 *
	 * @var string
	 */
	const CHILD_PREFIX = 'TOKEN_EXTENDED:DATA_TOKEN_CHILDREN_';

	/**
	 * Whether a token id, without braces, names a loopable token.
	 *
	 * @param string $token_id Token id, such as `TOKEN_EXTENDED:DATA_TOKEN_WP_POST_TAGS:UNIVERSAL:WP:WP_POST_TAGS`.
	 *
	 * @return bool
	 */
	public static function is_loopable( string $token_id ): bool {
		return 0 === strpos( $token_id, self::PREFIX ) && 0 !== strpos( $token_id, self::CHILD_PREFIX );
	}
}
