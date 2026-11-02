<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Bridge;

/**
 * Wraps the legacy trigger-token derivation (Rule 1). Coalesces null → array().
 *
 * trigger_tokens() is gated by automator_do_identify_tokens(); the GET recipe
 * route satisfies it, because that helper treats any Automator REST request as
 * a valid token endpoint. Callers outside REST/edit-page context derive empty.
 */
final class Automator_Trigger_Tokens_Bridge implements Trigger_Tokens_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_renderable_tokens( array $trigger_meta, int $recipe_id ): array {
		$tokens = \Automator()->tokens->trigger_tokens( $trigger_meta, $recipe_id );
		return is_array( $tokens ) ? $tokens : array();
	}
}
