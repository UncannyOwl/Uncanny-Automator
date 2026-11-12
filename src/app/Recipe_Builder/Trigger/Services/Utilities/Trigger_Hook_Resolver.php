<?php
/**
 * Trigger Hook Resolver.
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Utilities
 * @since 8.0.4
 */

declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Utilities;

use Uncanny_Automator\App\Bridge\Automator_Trigger_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Definition_Bridge;
use Uncanny_Automator\Recipe\Trigger_Metadata_Loader;

/**
 * The hooks a trigger code listens on.
 *
 * Three sources, first non-empty wins:
 *
 * 1. The hooks the code declares in `definition()` (the build-time trigger metadata).
 * 2. The `action` the code registers with the legacy trigger registry.
 * 3. The hooks the trigger being saved carries, for a code this request does not know.
 *
 * Declared hooks come first because a trigger that declares several hooks registers only one of
 * them as its `action`.
 *
 * @since 8.0.4
 */
final class Trigger_Hook_Resolver {

	/**
	 * Registered trigger definitions.
	 *
	 * @since 8.0.4
	 *
	 * @var Trigger_Definition_Bridge
	 */
	private Trigger_Definition_Bridge $definitions;

	/**
	 * Build-time trigger metadata, keyed by trigger code.
	 *
	 * @since 8.0.4
	 *
	 * @var Trigger_Metadata_Loader
	 */
	private Trigger_Metadata_Loader $metadata;

	/**
	 * Constructor.
	 *
	 * @since 8.0.4
	 *
	 * @param Trigger_Definition_Bridge|null $definitions Registered trigger definitions.
	 * @param Trigger_Metadata_Loader|null   $metadata    Build-time trigger metadata.
	 */
	public function __construct( ?Trigger_Definition_Bridge $definitions = null, ?Trigger_Metadata_Loader $metadata = null ) {
		$this->definitions = $definitions ?? new Automator_Trigger_Definition_Bridge();
		$this->metadata    = $metadata ?? new Trigger_Metadata_Loader();
	}

	/**
	 * The hooks a trigger code listens on.
	 *
	 * @since 8.0.4
	 *
	 * @param string $trigger_code Trigger code.
	 * @param mixed  $carried      The hook name or names the trigger being saved carries.
	 *
	 * @return string[] Non-empty hook names in source order, without repeats; empty when no source has one.
	 */
	public function resolve( string $trigger_code, $carried ): array {

		$declared = $this->declared( $trigger_code );

		if ( ! empty( $declared ) ) {
			return $declared;
		}

		$registered = $this->registered( $trigger_code );

		if ( ! empty( $registered ) ) {
			return $registered;
		}

		return $this->names( $carried );
	}

	/**
	 * The hooks the code declares in `definition()`.
	 *
	 * @since 8.0.4
	 *
	 * @param string $trigger_code Trigger code.
	 *
	 * @return string[] Empty when the code has no metadata entry or the entry declares no hooks.
	 */
	private function declared( string $trigger_code ): array {

		$entry = $this->metadata->load_metadata()[ $trigger_code ] ?? null;

		if ( ! is_array( $entry ) || ! isset( $entry['hooks'] ) || ! is_array( $entry['hooks'] ) ) {
			return array();
		}

		$names = array_map(
			static function ( $hook ) {
				return is_array( $hook ) ? ( $hook[0] ?? null ) : null;
			},
			$entry['hooks']
		);

		return $this->names( $names );
	}

	/**
	 * The hooks the code registers as its `action`.
	 *
	 * @since 8.0.4
	 *
	 * @param string $trigger_code Trigger code.
	 *
	 * @return string[] Empty when the code is not registered on this request.
	 */
	private function registered( string $trigger_code ): array {

		$definition = $this->definitions->get_trigger_definition( $trigger_code );

		if ( null === $definition ) {
			return array();
		}

		return $this->names( $definition['action'] ?? null );
	}

	/**
	 * The usable hook names in a value: non-empty strings, in order, without repeats.
	 *
	 * @since 8.0.4
	 *
	 * @param mixed $value One hook name or a list of them.
	 *
	 * @return string[]
	 */
	private function names( $value ): array {

		$names = array_filter(
			(array) $value,
			static function ( $name ) {
				return is_string( $name ) && '' !== $name;
			}
		);

		return array_values( array_unique( $names ) );
	}
}
