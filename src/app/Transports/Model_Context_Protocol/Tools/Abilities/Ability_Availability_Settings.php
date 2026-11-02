<?php
/**
 * Which WordPress abilities the site owner has switched off for Uncanny Agent and connected MCP clients.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities;

/**
 * Stores the abilities a site owner has switched off.
 *
 * Abilities registered by other plugins start switched ON, as they do for
 * every other WordPress caller. A plugin that does not want an ability used
 * by AI apps opts out with `mcp.public = false`; WordPress permission
 * callbacks and credential scopes still apply to each run. Only the
 * disabled set is stored.
 */
final class Ability_Availability_Settings {

	/**
	 * Option holding the sorted list of disabled ability names.
	 */
	public const OPTION_NAME = 'automator_mcp_disabled_abilities';

	/**
	 * Normalized names, cached until the WordPress option changes.
	 *
	 * @var string[]|null
	 */
	private ?array $disabled = null;

	/**
	 * Raw option value that produced the normalized cache.
	 *
	 * @var mixed
	 */
	private $cached_option = null;

	/**
	 * Ability names switched off.
	 *
	 * @return string[] Sorted, unique names such as "woocommerce/products-query".
	 */
	public function get_disabled_abilities(): array {
		// WordPress owns option-cache eviction on add/update/delete. Compare its
		// cached value so another settings instance or native option write cannot
		// leave this normalized cache stale. Unchanged reads do not re-normalize.
		$stored = get_option( self::OPTION_NAME, array() );
		if ( null === $this->disabled || $stored !== $this->cached_option ) {
			$this->disabled      = self::normalize( $stored );
			$this->cached_option = $stored;
		}

		return $this->disabled;
	}

	/**
	 * Whether an ability has been switched on.
	 *
	 * @param string $name Ability name.
	 * @return bool
	 */
	public function is_enabled( string $name ): bool {
		return ! in_array( $name, $this->get_disabled_abilities(), true );
	}

	/**
	 * Switch one ability on or off.
	 *
	 * @param string $name    Ability name.
	 * @param bool   $enabled True to expose it.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_enabled( string $name, bool $enabled ): bool {
		$current = $this->get_disabled_abilities();
		$next    = $enabled
			? array_diff( $current, array( $name ) )
			: array_merge( $current, array( $name ) );

		return $this->set_disabled_abilities( $next );
	}

	/**
	 * Replace the disabled set.
	 *
	 * @param array $names Ability names to withhold.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_disabled_abilities( array $names ): bool {
		$normalized = self::normalize( $names );

		if ( get_option( self::OPTION_NAME, array() ) !== $normalized && ! update_option( self::OPTION_NAME, $normalized ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Reduce stored or submitted values to a sorted, unique list of names.
	 *
	 * @param mixed $value Raw list.
	 * @return string[]
	 */
	private static function normalize( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$names = array();

		foreach ( $value as $name ) {
			if ( ! is_string( $name ) ) {
				continue;
			}

			$name = trim( $name );

			if ( '' !== $name ) {
				$names[ $name ] = true;
			}
		}

		$names = array_keys( $names );
		sort( $names, SORT_STRING );

		return $names;
	}
}
