<?php
/**
 * Targeted source edits, following Page Builder's search/replace patch pattern.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Content\Utilities;

/** Preserve all bytes outside each unique, literal source match. */
final class Post_Content_Patcher {

	/** Block attributes that hold a per-block id, which a copy must not duplicate. */
	private const ID_ATTRIBUTES = array( 'block_id' );

	/**
	 * Prepare all edits before any persistence. Searches match the evolving
	 * source; range offsets refer to the source that was read.
	 *
	 * @param string $source Current authored content, never rendered HTML.
	 * @param array  $edits Ordered edits.
	 * @return string|\WP_Error
	 */
	public function apply( string $source, array $edits ) {
		$result = $this->apply_with_changes( $source, $edits );
		return is_wp_error( $result ) ? $result : $result['content'];
	}

	/**
	 * Apply edits and report where each one landed in the final source.
	 *
	 * Each change is the edit index plus the byte offset and length of its result
	 * (the replacement, inserted, copied or moved source; zero length for a delete).
	 * Earlier spans shift when a later edit changes the length of source before them.
	 * Range offsets and to come from one read, so they shift past earlier edits too.
	 *
	 * @param string $source Current authored content, never rendered HTML.
	 * @param array  $edits Ordered edits.
	 * @return array{content: string, changes: array}|\WP_Error
	 */
	public function apply_with_changes( string $source, array $edits ) {
		$changes = array();
		$applied = array();
		if ( empty( $edits ) || count( $edits ) > 50 ) {
			return $this->error( 'Supply between 1 and 50 edits.' );
		}
		foreach ( $edits as $index => $edit ) {
			$edit = $this->shift_range( $edit, $applied, $index );
			if ( is_wp_error( $edit ) ) {
				return $edit;
			}
			$steps = $this->resolve( $source, $edit, $index );
			if ( is_wp_error( $steps ) ) {
				return $steps;
			}
			// A move is two steps: remove the range, then insert it at its new place.
			foreach ( $steps as $step ) {
				list( $offset, $removed, $replacement, $recorded ) = $step;

				$delta = strlen( $replacement ) - $removed;
				foreach ( $changes as &$change ) {
					if ( $change['offset'] >= $offset + $removed ) {
						$change['offset'] += $delta;
					}
				}
				unset( $change );
				$source    = substr_replace( $source, $replacement, $offset, $removed );
				$applied[] = array( $offset, $removed, strlen( $replacement ) );
				if ( $recorded ) {
					$changes[] = array(
						'index' => $index,
						'offset' => $offset,
						'length' => strlen( $replacement ),
					);
				}
			}
		}
		return array(
			'content' => $source,
			'changes' => $changes,
		);
	}

	/**
	 * Resolve one edit to splice steps: byte offset, removed length, replacement
	 * source, and whether the step is the edit's reported change.
	 *
	 * Search edits match a unique literal. Range edits use an offset and length from
	 * a bounded read of the same content_hash, for blocks too large to quote.
	 *
	 * @return array<int, array{0: int, 1: int, 2: string, 3: bool}>|\WP_Error
	 */
	private function resolve( string $source, $edit, int $index ) {
		if ( ! is_array( $edit ) || array_diff( array_keys( $edit ), array( 'action', 'search', 'replace', 'content', 'offset', 'length', 'to' ) ) ) {
			return $this->error( 'Each edit needs a supported action and its fields.', $index );
		}
		$action = $edit['action'] ?? 'replace';
		if ( in_array( $action, array( 'copy_range', 'move_range' ), true ) ) {
			return $this->resolve_copy( $source, $edit, $action, $index );
		}
		if ( isset( $edit['to'] ) ) {
			return $this->error( 'Only copy_range and move_range take to.', $index );
		}
		if ( in_array( $action, array( 'replace_range', 'delete_range' ), true ) ) {
			$range = $this->resolve_range( $source, $edit, $action, $index );
			return is_wp_error( $range ) ? $range : array( $range );
		}
		if ( ! in_array( $action, array( 'replace', 'insert_before', 'insert_after', 'delete' ), true ) ) {
			return $this->error( 'Unsupported edit action.', $index );
		}
		if ( ! is_string( $edit['search'] ?? null ) || '' === $edit['search'] || isset( $edit['offset'] ) || isset( $edit['length'] ) ) {
			return $this->error( 'Search edits need a non-empty search and no offset or length.', $index );
		}
		$offset = strpos( $source, $edit['search'] );
		if ( false === $offset ) {
			return $this->error( 'Search was not found. Read the current content again.', $index );
		}
		// Include overlapping matches: "aa" is ambiguous in "aaa".
		if ( false !== strpos( $source, $edit['search'], $offset + 1 ) ) {
			return $this->error( 'Search matched more than once. Use a more specific source fragment.', $index );
		}
		$key = 'replace' === $action ? 'replace' : 'content';
		if ( 'delete' !== $action && ! is_string( $edit[ $key ] ?? null ) ) {
			return $this->error( 'Replace needs replace; insertion needs content.', $index );
		}
		$replacement = 'delete' === $action ? '' : $edit[ $key ];
		// Insertions leave the searched source untouched: a later range may still name it.
		if ( 'insert_before' === $action ) {
			return array( array( $offset, 0, $replacement, true ) );
		}
		if ( 'insert_after' === $action ) {
			return array( array( $offset + strlen( $edit['search'] ), 0, $replacement, true ) );
		}
		return array( array( $offset, strlen( $edit['search'] ), $replacement, true ) );
	}

	/**
	 * Move a range edit's offset and to from the source that was read to the
	 * source after the earlier steps. A range that overlaps an earlier step, or
	 * a to inside a replaced span, no longer names the bytes that were read.
	 * Positions at an earlier insertion point start after the inserted source.
	 *
	 * @param mixed $edit    One requested edit.
	 * @param array $applied Earlier steps: offset, removed length, inserted length.
	 * @return mixed|\WP_Error The edit with current offsets; other edits unchanged.
	 */
	private function shift_range( $edit, array $applied, int $index ) {
		if (
			array() === $applied
			|| ! is_array( $edit )
			|| ! in_array( $edit['action'] ?? 'replace', array( 'replace_range', 'delete_range', 'copy_range', 'move_range' ), true )
			|| ! is_int( $edit['offset'] ?? null )
			|| ! is_int( $edit['length'] ?? null )
		) {
			// resolve() reports invalid fields with their usual messages.
			return $edit;
		}
		$start = $edit['offset'];
		$end   = $start + $edit['length'];
		$to    = is_int( $edit['to'] ?? null ) ? $edit['to'] : null;
		foreach ( $applied as list( $at, $removed, $inserted ) ) {
			$overlaps = 0 < $removed ? $start < $at + $removed && $end > $at : $start < $at && $end > $at;
			if ( $overlaps || ( null !== $to && $to > $at && $to < $at + $removed ) ) {
				return $this->error( 'This range overlaps an earlier edit in the same request. Offsets refer to the content that was read; make overlapping changes in a later request.', $index );
			}
			$delta = $inserted - $removed;
			$start = $start > $at || ( $start === $at && 0 === $removed ) ? $start + $delta : $start;
			$end   = $end > $at ? $end + $delta : $end;
			if ( null !== $to ) {
				$to = $to > $at || ( $to === $at && 0 === $removed ) ? $to + $delta : $to;
			}
		}
		$edit['offset'] = $start;
		if ( null !== $to ) {
			$edit['to'] = $to;
		}
		return $edit;
	}

	/**
	 * A byte range inside the current source, never splitting a UTF-8 character.
	 *
	 * @return array{0: int, 1: int, 2: string}|\WP_Error
	 */
	private function resolve_range( string $source, array $edit, string $action, int $index ) {
		$offset = $edit['offset'] ?? null;
		$length = $edit['length'] ?? null;
		if ( ! is_int( $offset ) || ! is_int( $length ) || $offset < 0 || $length < 1 || isset( $edit['search'] ) || isset( $edit['content'] ) ) {
			return $this->error( 'Range edits need an integer offset and a positive length, and no search.', $index );
		}
		if ( $offset + $length > strlen( $source ) ) {
			return $this->error( 'The range ends past the content. Read the current content again.', $index );
		}
		if ( $this->inside_character( $source, $offset ) || $this->inside_character( $source, $offset + $length ) ) {
			return $this->error( 'The range splits a character. Use offsets from a content read.', $index );
		}
		if ( 'replace_range' === $action && ! is_string( $edit['replace'] ?? null ) ) {
			return $this->error( 'replace_range needs replace.', $index );
		}
		if ( 'delete_range' === $action && isset( $edit['replace'] ) ) {
			return $this->error( 'delete_range takes no replace.', $index );
		}
		return array( $offset, $length, 'replace_range' === $action ? $edit['replace'] : '', true );
	}

	/**
	 * Copy or move whole blocks to a block boundary without resending their source.
	 *
	 * A copy gets fresh per-block ids, so the original and the copy keep separate
	 * styles; anchors and other HTML ids are copied as they are.
	 *
	 * @return array<int, array{0: int, 1: int, 2: string, 3: bool}>|\WP_Error
	 */
	private function resolve_copy( string $source, array $edit, string $action, int $index ) {
		$offset = $edit['offset'] ?? null;
		$length = $edit['length'] ?? null;
		$to     = $edit['to'] ?? null;
		if ( ! is_int( $offset ) || ! is_int( $length ) || ! is_int( $to ) || $offset < 0 || $length < 1 || $to < 0 || isset( $edit['search'] ) || isset( $edit['replace'] ) || isset( $edit['content'] ) ) {
			return $this->error( $action . ' needs integer offset, length and to, and no search, replace or content.', $index );
		}
		if ( $offset + $length > strlen( $source ) || $to > strlen( $source ) ) {
			return $this->error( 'The range or destination is past the content. Read the current content again.', $index );
		}
		$reader = new Post_Content_Reader();
		$blocks = substr( $source, $offset, $length );
		if ( ! $reader->whole_blocks( $blocks ) ) {
			return $this->error( 'The range must hold whole blocks: use an offset and length from an outline.', $index );
		}
		if ( ! $reader->at_block_boundary( $source, $to ) ) {
			return $this->error( 'to must be a block boundary: the offset of a block, or a block offset plus its length.', $index );
		}
		if ( 'copy_range' === $action ) {
			return array( array( $to, 0, $this->renew_ids( $blocks, $source ), true ) );
		}
		if ( $to > $offset && $to < $offset + $length ) {
			return $this->error( 'A range cannot move inside itself.', $index );
		}
		return array(
			array( $offset, $length, '', false ),
			array( $to > $offset ? $to - $length : $to, 0, $blocks, true ),
		);
	}

	/** Give each per-block id in copied source a new value that is unused in the document. */
	private function renew_ids( string $blocks, string $source ): string {
		$pattern = '/"(?:' . implode( '|', self::ID_ATTRIBUTES ) . ')":"([A-Za-z0-9_-]{4,64})"/';
		if ( ! preg_match_all( $pattern, $blocks, $found ) ) {
			return $blocks;
		}
		$taken = $source;
		foreach ( array_unique( $found[1] ) as $old ) {
			do {
				$new = substr( bin2hex( random_bytes( 32 ) ), 0, ctype_xdigit( $old ) ? strlen( $old ) : 8 );
			} while ( false !== strpos( $taken, $new ) );
			$taken .= ' ' . $new;
			// The id also appears in classes such as uagb-block-<id>; match it as a whole token.
			$blocks = (string) preg_replace( '/(?<![A-Za-z0-9])' . preg_quote( $old, '/' ) . '(?![A-Za-z0-9])/', $new, $blocks );
		}
		return $blocks;
	}

	/** Whether a byte offset falls inside a multi-byte UTF-8 character. */
	private function inside_character( string $source, int $offset ): bool {
		return $offset > 0 && $offset < strlen( $source ) && 0x80 === ( ord( $source[ $offset ] ) & 0xC0 );
	}

	/** Return a patch failure without including private source text. */
	private function error( string $message, ?int $index = null ): \WP_Error {
		return new \WP_Error(
			'post_content_edit_invalid',
			$message,
			array(
				'status' => 422,
				'edit_index' => $index,
			)
		);
	}
}
