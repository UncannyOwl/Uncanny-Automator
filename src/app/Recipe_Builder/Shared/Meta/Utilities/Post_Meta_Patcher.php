<?php
/**
 * Path edits inside a decoded post meta array.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Utilities;

/**
 * Edits work on the decoded PHP array, never a JSON copy, so untouched entries
 * keep their PHP types, including whole-number floats and nested serialized text.
 */
final class Post_Meta_Patcher {

	public const MAX_EDITS = 50;
	public const MAX_PATH  = 32;

	/**
	 * Apply ordered edits; a later edit sees the result of earlier ones.
	 *
	 * @param array $value Decoded meta array.
	 * @param array $edits Each with path, and value or unset.
	 * @return array|\WP_Error
	 */
	public function apply( array $value, array $edits ) {
		if ( array() === $edits || count( $edits ) > self::MAX_EDITS || array_keys( $edits ) !== range( 0, count( $edits ) - 1 ) ) {
			return $this->error( 'Supply between 1 and ' . self::MAX_EDITS . ' edits.' );
		}
		foreach ( $edits as $index => $edit ) {
			$path = $this->path( $edit['path'] ?? null );
			if ( null === $path ) {
				return $this->error( 'Each edit needs a path of 1 to ' . self::MAX_PATH . ' keys: strings or integers.', $index );
			}
			$unset = true === ( $edit['unset'] ?? false );
			if ( array_key_exists( 'value', $edit ) === $unset ) {
				return $this->error( 'Each edit needs either value or unset: true.', $index );
			}
			$new = $unset ? null : $edit['value'];
			if ( ! $unset && null !== Post_Meta_Value::invalid_input( $new, count( $path ) ) ) {
				return $this->error( 'The value is not valid JSON data within ' . Post_Meta_Value::MAX_DEPTH . ' levels.', $index );
			}
			$value = $this->edit( $value, $path, $unset, $new, $index );
			if ( is_wp_error( $value ) ) {
				return $value;
			}
		}
		return $value;
	}

	/**
	 * Read the value at a path.
	 *
	 * @param array $value Decoded meta array.
	 * @param mixed $path  Requested path.
	 * @return array{0: mixed}|\WP_Error The found value, wrapped so null is distinct from absent.
	 */
	public function get( array $value, $path ) {
		$path = $this->path( $path );
		if ( null === $path ) {
			return $this->error( 'The path needs 1 to ' . self::MAX_PATH . ' keys: strings or integers.' );
		}
		foreach ( $path as $depth => $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $this->missing( array_slice( $path, 0, $depth + 1 ) );
			}
			$value = $value[ $segment ];
		}
		return array( $value );
	}

	/**
	 * Set or unset one path. Containers on the way must exist; the last key may be new.
	 *
	 * @param mixed $new New value.
	 * @return array|\WP_Error
	 */
	private function edit( array $value, array $path, bool $unset, $new, int $index, int $depth = 0 ) {
		$segment = $path[ $depth ];
		if ( count( $path ) - 1 === $depth ) {
			if ( $unset ) {
				if ( ! array_key_exists( $segment, $value ) ) {
					return $this->missing( $path, $index );
				}
				unset( $value[ $segment ] );
				return $value;
			}
			$value[ $segment ] = $new;
			return $value;
		}
		if ( ! array_key_exists( $segment, $value ) ) {
			return $this->missing( array_slice( $path, 0, $depth + 1 ), $index );
		}
		if ( ! is_array( $value[ $segment ] ) ) {
			return $this->error( 'Path ' . wp_json_encode( array_slice( $path, 0, $depth + 1 ) ) . ' is not an array or object, so it has no keys to edit.', $index );
		}
		$child = $this->edit( $value[ $segment ], $path, $unset, $new, $index, $depth + 1 );
		if ( is_wp_error( $child ) ) {
			return $child;
		}
		$value[ $segment ] = $child;
		return $value;
	}

	/**
	 * Validate a path of array keys.
	 *
	 * @param mixed $path Requested path.
	 * @return array<int, int|string>|null
	 */
	private function path( $path ): ?array {
		if ( ! is_array( $path ) || array() === $path || count( $path ) > self::MAX_PATH || array_keys( $path ) !== range( 0, count( $path ) - 1 ) ) {
			return null;
		}
		foreach ( $path as $segment ) {
			if ( ! is_int( $segment ) && ! ( is_string( $segment ) && 1 === preg_match( '//u', $segment ) ) ) {
				return null;
			}
		}
		return $path;
	}

	/** Name the first missing key so the caller can read the right level again. */
	private function missing( array $path, ?int $index = null ): \WP_Error {
		return $this->error( 'Path ' . wp_json_encode( $path ) . ' does not exist. Read the key again, or set a new key only at the last step of an existing path.', $index );
	}

	/** Keep patch failures distinct from stale or refused writes. */
	private function error( string $message, ?int $index = null ): \WP_Error {
		return new \WP_Error(
			'post_meta_edit_invalid',
			$message,
			array(
				'status' => 422,
				'edit_index' => $index,
			)
		);
	}
}
