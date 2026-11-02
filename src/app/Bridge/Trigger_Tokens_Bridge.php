<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Bridge;

/**
 * Derives a trigger's published token definitions from its current config,
 * wrapping the legacy Automator()->tokens->trigger_tokens() seam.
 */
interface Trigger_Tokens_Bridge {

	/**
	 * Derived trigger tokens for the given trigger meta (legacy token-array shape).
	 *
	 * @param array<string,mixed> $trigger_meta Flat trigger post-meta (needs 'integration','code').
	 * @param int                 $recipe_id    Recipe id.
	 * @return array<int,array<string,mixed>> Empty when the derivation yields nothing.
	 */
	public function get_renderable_tokens( array $trigger_meta, int $recipe_id ): array;
}
