<?php
/**
 * Per-tool availability for third-party MCP clients.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

/**
 * Stores which MCP tools a site owner has switched off for OAuth clients.
 *
 * Only the disabled set is stored, so every tool, including one registered
 * later, stays available unless it has been switched off. First-party Uncanny
 * Agent credentials never consult this setting; see Tool_Authorization_Policy.
 */
final class Tool_Availability_Settings {

	/**
	 * Option holding the sorted list of disabled tool names.
	 */
	public const OPTION_NAME = 'automator_mcp_disabled_tools';

	/**
	 * Retain saved denials when an operation is replaced.
	 *
	 * save_block replaces condition groups but also creates delay blocks. Mapping
	 * the old denial to it deliberately blocks both kinds of direct block saves
	 * rather than silently restoring condition-group access. save_action.delay
	 * stays governed by save_action because timing already belonged to that tool;
	 * disabling save_block is not a global ban on delay blocks.
	 *
	 * @var array<string, string>
	 */
	private const RENAMED_TOOLS = array(
		'save_condition'       => 'save_filter_condition',
		'save_condition_group' => 'save_block',
	);

	/**
	 * Disabled set memoised for the current request.
	 *
	 * @var string[]|null
	 */
	private ?array $disabled = null;

	/**
	 * Tool names switched off for third-party clients.
	 *
	 * @return string[] Sorted, unique tool names.
	 */
	public function get_disabled_tools(): array {
		if ( null === $this->disabled ) {
			$this->disabled = self::normalize( get_option( self::OPTION_NAME, array() ) );
		}

		return $this->disabled;
	}

	/**
	 * Whether a tool has been switched off for third-party clients.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	public function is_disabled( string $name ): bool {
		return in_array( $name, $this->get_disabled_tools(), true );
	}

	/**
	 * Switch one tool off or on for third-party clients.
	 *
	 * @param string $name     Tool name.
	 * @param bool   $disabled True to switch the tool off.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_tool_disabled( string $name, bool $disabled ): bool {
		$current = $this->get_disabled_tools();
		$next    = $disabled
			? array_merge( $current, array( $name ) )
			: array_diff( $current, array( $name ) );

		return $this->set_disabled_tools( $next );
	}

	/**
	 * Replace the disabled set.
	 *
	 * @param array $names Tool names to switch off.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_disabled_tools( array $names ): bool {
		$normalized = self::normalize( $names );

		if ( get_option( self::OPTION_NAME, array() ) !== $normalized && ! update_option( self::OPTION_NAME, $normalized ) ) {
			return false;
		}

		$this->disabled = $normalized;

		return true;
	}

	/**
	 * Reduce stored or submitted values to a sorted, unique list of names.
	 *
	 * @param mixed $value Raw option value or submitted list.
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
			$name = self::RENAMED_TOOLS[ $name ] ?? $name;

			if ( '' !== $name ) {
				$names[ $name ] = true;
			}
		}

		$names = array_keys( $names );
		sort( $names, SORT_STRING );

		return $names;
	}
}
