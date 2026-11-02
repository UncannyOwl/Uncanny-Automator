<?php
/**
 * Bounded views of authored post content: outline, head, tail, range and search.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Content\Utilities;

/**
 * Read large authored source in pieces so a client never needs the whole document.
 *
 * Works on raw post_content only. Nothing is rendered: shortcodes, block comments
 * and HTML stay literal, and no content filter, block render or shortcode runs.
 */
final class Post_Content_Reader {

	public const VIEWS           = array( 'full', 'outline', 'head', 'tail', 'range', 'search' );
	public const FULL_LIMIT      = 65536;
	public const MAX_SLICE       = 32768;
	public const DEFAULT_SLICE   = 8192;
	public const MAX_CONTEXT     = 2000;
	public const MAX_MATCHES     = 50;
	public const OUTLINE_LIMIT   = 200;
	private const SNIPPET_CHARS  = 80;
	private const ATTRIBUTE_KEYS = array( 'block_id', 'anchor', 'className', 'id', 'ref' );

	/**
	 * Same delimiter grammar as WP_Block_Parser, so offsets match core's parsing.
	 */
	public const DELIMITER = '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s';

	/** First bytes of the source. */
	public function head( string $source, int $bytes ): array {
		return $this->slice( $source, 0, $bytes );
	}

	/** Last bytes of the source. */
	public function tail( string $source, int $bytes ): array {
		return $this->slice( $source, max( 0, strlen( $source ) - $bytes ), $bytes );
	}

	/**
	 * Exact byte range, widened to whole UTF-8 characters.
	 *
	 * @return array|\WP_Error
	 */
	public function range( string $source, int $offset, int $length ) {
		if ( $offset > strlen( $source ) ) {
			return new \WP_Error( 'post_content_view_out_of_range', 'offset is past the end of the content. Use total_bytes.', array( 'status' => 400 ) );
		}
		return $this->slice( $source, $offset, $length );
	}

	/**
	 * Literal matches with surrounding source. Counting includes overlapping matches,
	 * the same rule the edit patcher uses to decide whether a search is unique.
	 */
	public function search( string $source, string $needle, int $context, int $max_matches ): array {
		$matches = array();
		$count   = 0;
		$offset  = strpos( $source, $needle );
		while ( false !== $offset ) {
			++$count;
			if ( count( $matches ) < $max_matches ) {
				$before    = $this->cut( $source, max( 0, $offset - $context ), $offset );
				$after_end = min( strlen( $source ), $offset + strlen( $needle ) + $context );
				$matches[] = array(
					'offset' => $offset,
					'line' => $this->line_at( $source, $offset ),
					'before' => $before,
					'match' => $needle,
					'after' => $this->cut( $source, $offset + strlen( $needle ), $after_end ),
				);
			}
			$offset = strpos( $source, $needle, $offset + 1 );
		}
		return array(
			'search' => $needle,
			'match_count' => $count,
			'unique' => 1 === $count,
			'matches' => $matches,
			'truncated' => $count > count( $matches ),
		);
	}

	/**
	 * Block map with byte offsets, from the block comment delimiters alone.
	 *
	 * @param int $max_depth Deepest level listed; 1 lists only top-level blocks.
	 */
	public function outline( string $source, int $max_depth ): array {
		$blocks   = array();
		$stack    = array();
		$total    = 0;
		$listable = 0;
		if ( preg_match_all( self::DELIMITER, $source, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			foreach ( $tokens as $token ) {
				$start = $token[0][1];
				$end   = $start + strlen( $token[0][0] );
				$name  = ( '' !== $token['namespace'][0] ? $token['namespace'][0] : 'core/' ) . $token['name'][0];
				if ( '' !== $token['closer'][0] ) {
					// A stray closer is ignored, so it cannot unbalance the blocks around it.
					if ( empty( $stack ) || $stack[ count( $stack ) - 1 ]['name'] !== $name ) {
						continue;
					}
					$open = array_pop( $stack );
					if ( null !== $open['index'] ) {
						$blocks[ $open['index'] ]['length'] = $end - $open['offset'];
						if ( ! $open['has_children'] ) {
							$text = $this->snippet( substr( $source, $open['inner'], $start - $open['inner'] ) );
							if ( '' !== $text ) {
								$blocks[ $open['index'] ]['text'] = $text;
							}
						}
					}
					continue;
				}
				++$total;
				if ( ! empty( $stack ) ) {
					$stack[ count( $stack ) - 1 ]['has_children'] = true;
				}
				$depth = count( $stack ) + 1;
				$index = null;
				if ( $depth <= $max_depth ) {
					++$listable;
				}
				if ( $depth <= $max_depth && count( $blocks ) < self::OUTLINE_LIMIT ) {
					$entry      = array(
						'name' => $name,
						'depth' => $depth,
						'offset' => $start,
						'length' => $end - $start,
					);
					$attributes = $this->attributes( $token['attrs'][0] ?? '' );
					if ( ! empty( $attributes ) ) {
						$entry['attributes'] = $attributes;
					}
					$blocks[] = $entry;
					$index    = count( $blocks ) - 1;
				}
				if ( '' === ( $token['void'][0] ?? '' ) ) {
					$stack[] = array(
						'name' => $name,
						'offset' => $start,
						'inner' => $end,
						'index' => $index,
						'has_children' => false,
					);
				}
			}
		}
		return array(
			'blocks' => $blocks,
			'block_count' => $total,
			'listed' => count( $blocks ),
			'max_depth' => $max_depth,
			'truncated' => count( $blocks ) < $listable,
			'freeform' => 0 === $total,
		);
	}

	/**
	 * Whether source is one or more complete blocks: every opener closed inside it,
	 * no stray closer, and only whitespace between top-level blocks.
	 */
	public function whole_blocks( string $source ): bool {
		if ( ! preg_match_all( self::DELIMITER, $source, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return false;
		}
		$stack = array();
		$end   = 0;
		foreach ( $tokens as $token ) {
			if ( empty( $stack ) && '' !== trim( substr( $source, $end, $token[0][1] - $end ) ) ) {
				return false;
			}
			$end  = $token[0][1] + strlen( $token[0][0] );
			$name = $token['namespace'][0] . $token['name'][0];
			if ( '' !== $token['closer'][0] ) {
				if ( array_pop( $stack ) !== $name ) {
					return false;
				}
			} elseif ( '' === ( $token['void'][0] ?? '' ) ) {
				$stack[] = $name;
			}
		}
		return empty( $stack ) && '' === trim( substr( $source, $end ) );
	}

	/** Whether an offset is the start or end of a block delimiter, or an end of the source. */
	public function at_block_boundary( string $source, int $offset ): bool {
		if ( 0 === $offset || strlen( $source ) === $offset ) {
			return true;
		}
		if ( ! preg_match_all( self::DELIMITER, $source, $tokens, PREG_OFFSET_CAPTURE ) ) {
			return false;
		}
		foreach ( $tokens[0] as $token ) {
			if ( $token[1] === $offset || $token[1] + strlen( $token[0] ) === $offset ) {
				return true;
			}
		}
		return false;
	}

	/** Number of lines, counting a final line without a trailing newline. */
	public function line_count( string $source ): int {
		return '' === $source ? 0 : substr_count( $source, "\n" ) + 1;
	}

	/** Slice by bytes without splitting a multi-byte character. */
	private function slice( string $source, int $offset, int $length ): array {
		$offset = $this->boundary( $source, $offset );
		$text   = $this->cut( $source, $offset, min( strlen( $source ), $offset + $length ) );
		return array(
			'offset' => $offset,
			'length' => strlen( $text ),
			'line' => $this->line_at( $source, $offset ),
			'text' => $text,
			'more_after' => $offset + strlen( $text ) < strlen( $source ),
		);
	}

	/**
	 * Bytes between two offsets, kept to whole UTF-8 characters so JSON encoding succeeds.
	 * Both ends move back to a character boundary, never forward past requested bytes.
	 */
	private function cut( string $source, int $start, int $end ): string {
		$start = $this->boundary( $source, $start );
		$end   = $this->boundary( $source, $end );
		return $end > $start ? substr( $source, $start, $end - $start ) : '';
	}

	/** Move an offset back to the start of the UTF-8 character it falls inside. */
	private function boundary( string $source, int $offset ): int {
		$total  = strlen( $source );
		$offset = max( 0, min( $offset, $total ) );
		while ( $offset > 0 && $offset < $total && 0x80 === ( ord( $source[ $offset ] ) & 0xC0 ) ) {
			--$offset;
		}
		return $offset;
	}

	/** 1-based line number of a byte offset. */
	private function line_at( string $source, int $offset ): int {
		return substr_count( $source, "\n", 0, min( $offset, strlen( $source ) ) ) + 1;
	}

	/** A short plain-text hint of a leaf block, without rendering or running shortcodes. */
	private function snippet( string $inner ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( substr( $inner, 0, 4000 ) ) ) );
		if ( function_exists( 'mb_substr' ) && mb_strlen( $text, 'UTF-8' ) > self::SNIPPET_CHARS ) {
			return mb_substr( $text, 0, self::SNIPPET_CHARS, 'UTF-8' ) . '…';
		}
		return $text;
	}

	/** Identity attributes that help an AI target a block; never the full attribute set. */
	private function attributes( string $json ): array {
		if ( '' === trim( $json ) ) {
			return array();
		}
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$picked = array();
		foreach ( self::ATTRIBUTE_KEYS as $key ) {
			if ( isset( $decoded[ $key ] ) && is_scalar( $decoded[ $key ] ) ) {
				$picked[ $key ] = $decoded[ $key ];
			}
		}
		return $picked;
	}
}
