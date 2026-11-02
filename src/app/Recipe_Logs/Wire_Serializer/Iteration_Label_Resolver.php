<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

/**
 * Resolves the optional human-readable iteration label for one loop entity.
 * Users and posts loops mirror the legacy `#%d name` format (deleted →
 * `#%d (deleted)`); token loops read the parent loop row's entity_ids JSON
 * at the entity_id index (a positional index for token loops, pinned
 * 2026-07-15). Pure lookups — no store access.
 */
final class Iteration_Label_Resolver {

	/**
	 * Resolve a label for one entity, or null when unresolvable (the wire
	 * key is optional; omission is always legal).
	 *
	 * @param int                 $entity_id  Entity id (user id, post id, or token-loop index).
	 * @param string              $loop_type  Loop kind: users|posts|token|''.
	 * @param array<string,mixed> $parent_row The uap_loop_entries parent row (token loops read entity_ids from it).
	 * @return string|null The label, or null.
	 */
	public function resolve( int $entity_id, string $loop_type, array $parent_row ): ?string {
		if ( 'users' === $loop_type ) {
			$user = get_userdata( $entity_id );
			return sprintf( '#%d %s', $entity_id, false !== $user ? $user->display_name : '(deleted)' );
		}
		if ( 'posts' === $loop_type ) {
			$post = get_post( $entity_id );
			return sprintf( '#%d %s', $entity_id, null !== $post ? get_the_title( $post ) : '(deleted)' );
		}
		if ( 'token' === $loop_type ) {
			return $this->token_label( $entity_id, (string) ( $parent_row['entity_ids'] ?? '' ) );
		}
		return null;
	}

	/**
	 * Best-effort token-loop label: the entity_ids JSON element at the
	 * entity_id index, stringified (scalar) or JSON-encoded (array).
	 *
	 * @param int    $entity_id   Positional index into the entity list.
	 * @param string $entity_ids  The parent row's raw entity_ids JSON.
	 * @return string|null The label, or null when the index is unresolvable.
	 */
	private function token_label( int $entity_id, string $entity_ids ): ?string {
		$entities = json_decode( $entity_ids, true );
		if ( ! is_array( $entities ) || ! isset( $entities[ $entity_id ] ) ) {
			return null;
		}
		$entity = $entities[ $entity_id ];
		if ( is_scalar( $entity ) ) {
			return (string) $entity;
		}
		$encoded = wp_json_encode( $entity );
		return is_string( $encoded ) ? $encoded : null;
	}
}
