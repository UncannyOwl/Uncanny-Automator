<?php
/**
 * WordPress abilities available to expose through the MCP server.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;
use Uncanny_Automator\App\Transports\Abilities\Automator_Abilities;
use WP_Ability;

/**
 * Enumerates abilities registered with the WordPress Abilities API, decides
 * which may be offered, attributes each to the plugin that registered it,
 * and serves the switched-on ones to the MCP search, schema and run tools.
 */
final class Ability_Catalog {

	/**
	 * Site-owner switches.
	 *
	 * @var Ability_Availability_Settings
	 */
	private Ability_Availability_Settings $settings;

	/**
	 * Provider lookups memoised by callback file directory.
	 *
	 * @var array<string,array{slug:string,name:string}>
	 */
	private array $providers = array();

	/**
	 * @param Ability_Availability_Settings|null $settings Switches; defaults to the stored option.
	 */
	public function __construct( ?Ability_Availability_Settings $settings = null ) {
		$this->settings = $settings ?? new Ability_Availability_Settings();
	}

	/**
	 * Whether this WordPress has the Abilities API and it is ready to be read.
	 *
	 * Every lookup in this class sits behind this check, so it names each Abilities API
	 * function the class calls.
	 *
	 * @return bool
	 */
	public static function is_supported(): bool {
		return function_exists( 'wp_get_abilities' ) && function_exists( 'wp_get_ability' ) && function_exists( 'wp_has_ability' ) && did_action( 'init' ) > 0;
	}

	/**
	 * Every registered ability, as rows for the settings page.
	 *
	 * @return array<int,array<string,mixed>> Sorted by provider then label.
	 */
	public function get_abilities(): array {
		if ( ! self::is_supported() ) {
			return array();
		}

		$rows = array();

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! $ability instanceof WP_Ability || Automator_Abilities::is_operation_name( $ability->get_name() ) || self::is_adapter_ability( $ability->get_name() ) ) {
				// Automator operations use the existing operation switches. A second
				// ability switch would falsely present the native route as unavailable.
				continue;
			}

			$rows[] = $this->row_for( $ability );
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				$by_provider = strcasecmp( $a['provider']['name'], $b['provider']['name'] );

				return 0 !== $by_provider ? $by_provider : strcasecmp( $a['label'], $b['label'] );
			}
		);

		return $rows;
	}

	/**
	 * Whether an ability may be switched on: registered, marked public, and nameable as a tool.
	 *
	 * @param string $name Ability name.
	 * @return bool
	 */
	public function can_toggle( string $name ): bool {
		// The name comes from the request. WordPress reports looking up an
		// unregistered name as incorrect usage, so ask whether it is registered.
		if ( ! self::is_supported() || ! wp_has_ability( $name ) ) {
			return false;
		}

		$ability = wp_get_ability( $name );

		return $ability instanceof WP_Ability && $this->is_eligible( $ability );
	}

	/**
	 * Switch one ability on or off for Uncanny Agent and connected AI apps.
	 *
	 * @param string $name    Ability name.
	 * @param bool   $enabled True to expose it.
	 * @return bool Whether the requested state is saved.
	 */
	public function set_enabled( string $name, bool $enabled ): bool {
		return $this->settings->set_enabled( $name, $enabled );
	}

	/**
	 * Every registered, eligible ability the owner has not switched off.
	 *
	 * @return array<string,WP_Ability> Keyed by ability name, sorted by name.
	 */
	public function get_enabled(): array {
		if ( ! self::is_supported() ) {
			return array();
		}

		$abilities = array();

		foreach ( wp_get_abilities() as $ability ) {
			if ( ! $ability instanceof WP_Ability ) {
				continue;
			}

			$name = $ability->get_name();

			if ( null !== $this->find_enabled( $name ) ) {
				$abilities[ $name ] = $ability;
			}
		}

		ksort( $abilities, SORT_STRING );

		return $abilities;
	}

	/**
	 * One switched-on ability by name; null when it is off, gone, or opted out.
	 *
	 * @param string $name Ability name.
	 * @return WP_Ability|null
	 */
	public function find_enabled( string $name ): ?WP_Ability {
		if ( ! self::is_supported() || ! $this->settings->is_enabled( $name ) || ! wp_has_ability( $name ) ) {
			return null;
		}

		$ability = wp_get_ability( $name );

		return $ability instanceof WP_Ability && $this->is_eligible( $ability ) ? $ability : null;
	}

	/**
	 * Whether the ability only reads: Automator's own policy for its operations,
	 * the provider's declaration for every other ability.
	 *
	 * @param WP_Ability $ability Ability.
	 * @return bool
	 */
	public function is_read_only( WP_Ability $ability ): bool {
		$policy = Automator_Abilities::policy_for( $ability );
		if ( null !== $policy ) {
			return true === $policy['read_only'];
		}
		if ( Automator_Abilities::is_operation_name( $ability->get_name() ) || null !== $ability->get_meta_item( 'automator_operation' ) ) {
			return false;
		}
		$annotations = $ability->get_meta_item( 'annotations', array() );

		return is_array( $annotations ) && true === ( $annotations['readonly'] ?? false );
	}

	/**
	 * Abilities for the MCP search tool: the switched-on ones, ready to run, and
	 * those their plugin keeps off for AI apps, marked off and naming the plugin
	 * that controls them. Abilities the owner switched off stay out.
	 *
	 * "list" returns every one. "search" keeps those matching at least one
	 * word of the query in name, label, description, plugin or category, best
	 * match first. Both run locally: the set is small and owner-curated.
	 *
	 * @param string      $mode     "search" or "list".
	 * @param string      $query    Words to match; ignored in list mode.
	 * @param string|null $provider Plugin slug to keep, e.g. "woocommerce".
	 * @param int         $limit    Page size.
	 * @param int         $offset   Rows to skip.
	 * @return array{items:array<int,array<string,mixed>>,total:int}
	 */
	public function search( string $mode, string $query, ?string $provider, int $limit, int $offset ): array {
		$scored = array();

		foreach ( $this->searchable() as $ability ) {
			$row = $this->summary_for( $ability );

			if ( null !== $provider && '' !== $provider && 0 !== strcasecmp( $row['provider']['slug'], $provider ) ) {
				continue;
			}

			$score = 'search' === $mode ? $this->match_score( $row, $query ) : 1;

			if ( $score > 0 ) {
				$row['availability'] = null === $this->find_enabled( $ability->get_name() )
					? array(
						'available' => false,
						'message'   => 'This ability is off. ' . $this->plugin_off_message( $row['provider']['name'] ),
						'blockers'  => array( $this->plugin_off_message( $row['provider']['name'] ) ),
					)
					: array(
						'available' => true,
						'message'   => 'Ready to use.',
						'blockers'  => array(),
					);
				$scored[]            = array( $score, $row );
			}
		}

		// Search ranks by relevance, so the closest ability shows even when it is
		// off; a list shows the ready ones first.
		usort(
			$scored,
			static function ( array $a, array $b ) use ( $mode ): int {
				$by_score        = 'search' === $mode ? $b[0] <=> $a[0] : 0;
				$by_availability = (int) $b[1]['availability']['available'] <=> (int) $a[1]['availability']['available'];

				return 0 !== $by_score ? $by_score : $by_availability;
			}
		);

		$rows = array_column( $scored, 1 );

		return array(
			'items' => array_values( array_slice( $rows, max( 0, $offset ), max( 1, $limit ) ) ),
			'total' => count( $rows ),
		);
	}

	/**
	 * Why an AI app cannot use this ability, as the refusal it is told.
	 *
	 * @param string $name Ability name.
	 * @return string
	 */
	public function refusal_for( string $name ): string {
		$ability = self::is_supported() && wp_has_ability( $name ) ? wp_get_ability( $name ) : null;

		if ( self::is_adapter_ability( $name ) ) {
			return sprintf( 'The ability "%s" belongs to the WordPress MCP Adapter\'s own MCP server and cannot run here. Run the ability you need with run_ability directly.', $name );
		}

		if ( $ability instanceof WP_Ability && $this->is_plugin_off( $ability ) ) {
			return sprintf( 'The ability "%1$s" is off. %2$s', $name, $this->plugin_off_message( $this->provider_for( $ability )['name'] ) );
		}

		return sprintf( 'The ability "%s" is not switched on for AI apps on this site.', $name );
	}

	/**
	 * Switched-on abilities, then those their plugin keeps off for AI apps.
	 *
	 * @return WP_Ability[]
	 */
	private function searchable(): array {
		if ( ! self::is_supported() ) {
			return array();
		}

		$plugin_off = array();
		foreach ( wp_get_abilities() as $ability ) {
			if ( $ability instanceof WP_Ability && $this->is_plugin_off( $ability ) ) {
				$plugin_off[ $ability->get_name() ] = $ability;
			}
		}
		ksort( $plugin_off, SORT_STRING );

		return array_merge( array_values( $this->get_enabled() ), array_values( $plugin_off ) );
	}

	/**
	 * Whether the registering plugin keeps this ability from AI apps. Automator
	 * cannot turn it on: the plugin's own setting does.
	 *
	 * @param WP_Ability $ability Ability.
	 * @return bool
	 */
	private function is_plugin_off( WP_Ability $ability ): bool {
		return ! Automator_Abilities::is_operation_name( $ability->get_name() ) && ! self::is_adapter_ability( $ability->get_name() ) && 'opted_out' === $this->sharing_state( $ability );
	}

	/**
	 * Whether the WordPress MCP Adapter registered this ability for its own MCP server.
	 *
	 * Its permission check refuses every other caller, and execute-ability would
	 * run abilities a plugin keeps from AI apps, so it is never offered here.
	 *
	 * @param string $name Ability name.
	 * @return bool
	 */
	private static function is_adapter_ability( string $name ): bool {
		return 0 === strpos( $name, 'mcp-adapter/' );
	}

	/**
	 * What the AI app is told about an ability its plugin keeps off.
	 *
	 * @param string $plugin Name of the plugin that registered it.
	 * @return string
	 */
	private function plugin_off_message( string $plugin ): string {
		return sprintf( '%1$s controls whether AI apps can use this ability and has it turned off. Once %1$s turns it on, the site owner can switch it on or off in Uncanny Automator\'s MCP settings.', $plugin );
	}

	/**
	 * What search returns for an ability: enough to choose it, without schemas.
	 *
	 * @param WP_Ability $ability Ability.
	 * @return array<string,mixed>
	 */
	public function summary_for( WP_Ability $ability ): array {
		$row = $this->row_for( $ability );

		return array(
			'name'        => $row['name'],
			'label'       => $row['label'],
			'description' => $row['description'],
			'provider'    => $row['provider'],
			'category'    => $row['category'],
			'annotations' => $row['annotations'],
		);
	}

	/**
	 * What get_component_schema returns: the summary plus both schemas.
	 *
	 * @param WP_Ability $ability Ability.
	 * @return array<string,mixed>
	 */
	public function schema_for( WP_Ability $ability ): array {
		$definition                 = $this->summary_for( $ability );
		$definition['inputSchema']  = $this->strip_schema_marker( $ability->get_input_schema() ) ?? array(
			'type'       => 'object',
			'properties' => (object) array(),
		);
		$definition['outputSchema'] = $this->strip_schema_marker( $ability->get_output_schema() );

		return $definition;
	}

	/**
	 * A JSON schema without its "$schema" marker, or null when none is declared.
	 *
	 * @param mixed $schema Schema from the ability.
	 * @return array<string,mixed>|null
	 */
	private function strip_schema_marker( $schema ): ?array {
		if ( ! is_array( $schema ) || empty( $schema ) ) {
			return null;
		}

		unset( $schema['$schema'] );

		return $schema;
	}

	/**
	 * How well a summary row matches a query: 2 per word found in the label or
	 * name, 1 per word found elsewhere, 0 when nothing matches. Words under
	 * three letters are ignored; an empty query matches everything.
	 *
	 * @param array<string,mixed> $row   Summary row.
	 * @param string              $query Query.
	 * @return int
	 */
	private function match_score( array $row, string $query ): int {
		$words = array_filter(
			(array) preg_split( '/[^a-z0-9]+/', strtolower( $query ) ),
			static function ( $word ): bool {
				return is_string( $word ) && strlen( $word ) >= 3;
			}
		);

		if ( empty( $words ) ) {
			return 1;
		}

		$label    = strtolower( $row['label'] . ' ' . $row['name'] );
		$haystack = strtolower( implode( ' ', array( $label, $row['description'], $row['provider']['name'], $row['category']['label'] ) ) );
		$score    = 0;

		foreach ( $words as $word ) {
			if ( false !== strpos( $label, $word ) ) {
				$score += 2;
			} elseif ( false !== strpos( $haystack, $word ) ) {
				++$score;
			}
		}

		return $score;
	}

	/**
	 * What the registering plugin said about sharing the ability with AI apps.
	 *
	 * "offered": marked public, or public for MCP. "opted_out": the plugin
	 * explicitly set mcp.public to false. "unknown": the plugin said nothing;
	 * core fills `public` with false when unset, so only the mcp block tells
	 * a decision from an omission.
	 *
	 * @param WP_Ability $ability Ability.
	 * @return string offered|opted_out|unknown
	 */
	public function sharing_state( WP_Ability $ability ): string {
		$meta = $ability->get_meta();

		if ( isset( $meta['mcp'] ) && is_array( $meta['mcp'] ) && false === ( $meta['mcp']['public'] ?? null ) ) {
			return 'opted_out';
		}

		if ( true === ( $meta['public'] ?? false ) || true === ( $meta['mcp']['public'] ?? false ) ) {
			return 'offered';
		}

		return 'unknown';
	}

	/**
	 * Eligibility: the plugin did not opt out.
	 *
	 * An ability the plugin said nothing about is on, as it is for any other
	 * WordPress caller, until the site owner switches it off.
	 *
	 * @param WP_Ability $ability Ability.
	 * @return bool
	 */
	private function is_eligible( WP_Ability $ability ): bool {
		// Owner opt-in cannot grant a replaced Automator name the authority of
		// its original operation. Both native REST and run_ability reserve it.
		return ! Automator_Abilities::is_operation_name( $ability->get_name() ) && ! self::is_adapter_ability( $ability->get_name() ) && 'opted_out' !== $this->sharing_state( $ability );
	}

	/**
	 * A settings-page row for one ability.
	 *
	 * @param WP_Ability $ability Ability.
	 * @return array<string,mixed>
	 */
	private function row_for( WP_Ability $ability ): array {
		$name        = $ability->get_name();
		$annotations = $ability->get_meta_item( 'annotations', array() );
		$annotations = is_array( $annotations ) ? $annotations : array();
		// A category can be unregistered after its abilities were; the row then shows its slug.
		$category = function_exists( 'wp_has_ability_category' ) && function_exists( 'wp_get_ability_category' ) && wp_has_ability_category( $ability->get_category() )
			? wp_get_ability_category( $ability->get_category() )
			: null;

		return array(
			'name'        => $name,
			'label'       => $ability->get_label(),
			'description' => $ability->get_description(),
			'category'    => array(
				'slug'  => $ability->get_category(),
				'label' => is_object( $category ) && method_exists( $category, 'get_label' ) ? (string) $category->get_label() : $ability->get_category(),
			),
			'provider'    => $this->provider_for( $ability ),
			'annotations' => array(
				'readonly'      => is_bool( $annotations['readonly'] ?? null ) ? $annotations['readonly'] : null,
				'destructive'   => is_bool( $annotations['destructive'] ?? null ) ? $annotations['destructive'] : null,
				'idempotent'    => is_bool( $annotations['idempotent'] ?? null ) ? $annotations['idempotent'] : null,
				'openWorldHint' => is_bool( $annotations['openWorldHint'] ?? null ) ? $annotations['openWorldHint'] : null,
			),
			'sharing'     => $this->sharing_state( $ability ),
			'eligible'    => $this->is_eligible( $ability ),
			'enabled'     => $this->settings->is_enabled( $name ),
		);
	}

	/**
	 * Who registered the ability: WordPress, a plugin, a must-use plugin, or a theme.
	 *
	 * Reflects the execute callback to its file. No registry maps an ability
	 * to a plugin, so the file is the only reliable signal; the name's
	 * namespace is the fallback.
	 *
	 * @param WP_Ability $ability Ability.
	 * @return array{slug:string,name:string}
	 */
	private function provider_for( WP_Ability $ability ): array {
		$file = $this->callback_file( $ability );
		$key  = '' === $file ? 'ns:' . $ability->get_name() : dirname( $file );

		if ( isset( $this->providers[ $key ] ) ) {
			return $this->providers[ $key ];
		}

		$this->providers[ $key ] = $this->resolve_provider( $file, $ability->get_name() );

		return $this->providers[ $key ];
	}

	/**
	 * Resolve a provider from a callback file path.
	 *
	 * @param string $file         Callback file, or empty.
	 * @param string $ability_name Ability name, for the fallback.
	 * @return array{slug:string,name:string}
	 */
	private function resolve_provider( string $file, string $ability_name ): array {
		$file = wp_normalize_path( $file );

		if ( '' !== $file ) {
			if ( 0 === strpos( $file, wp_normalize_path( ABSPATH . WPINC ) ) || 0 === strpos( $file, wp_normalize_path( ABSPATH . 'wp-admin' ) ) ) {
				return array(
					'slug' => 'wordpress',
					'name' => 'WordPress',
				);
			}

			foreach ( array( WP_PLUGIN_DIR, WPMU_PLUGIN_DIR ) as $plugins_dir ) {
				$plugins_dir = trailingslashit( wp_normalize_path( $plugins_dir ) );

				if ( 0 === strpos( $file, $plugins_dir ) ) {
					$relative = substr( $file, strlen( $plugins_dir ) );
					$slug     = strtok( $relative, '/' );

					return array(
						'slug' => (string) $slug,
						'name' => $this->plugin_name( (string) $slug, trailingslashit( wp_normalize_path( WPMU_PLUGIN_DIR ) ) === $plugins_dir ),
					);
				}
			}

			$theme_root = trailingslashit( wp_normalize_path( get_theme_root() ) );

			if ( 0 === strpos( $file, $theme_root ) ) {
				$slug  = (string) strtok( substr( $file, strlen( $theme_root ) ), '/' );
				$theme = wp_get_theme( $slug );

				return array(
					'slug' => $slug,
					'name' => $theme->exists() ? (string) $theme->get( 'Name' ) : $slug,
				);
			}
		}

		$namespace = (string) strtok( $ability_name, '/' );

		return array(
			'slug' => $namespace,
			'name' => ucwords( str_replace( array( '-', '_' ), ' ', $namespace ) ),
		);
	}

	/**
	 * Display name of a plugin directory, from its header.
	 *
	 * @param string $slug   Plugin directory (or single-file plugin name).
	 * @param bool   $mu     Whether it lives in the must-use directory.
	 * @return string
	 */
	private function plugin_name( string $slug, bool $mu ): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = $mu ? get_mu_plugins() : get_plugins();

		foreach ( $plugins as $basename => $data ) {
			if ( $basename === $slug || 0 === strpos( $basename, $slug . '/' ) ) {
				$name = (string) ( $data['Name'] ?? '' );

				if ( '' !== $name ) {
					return $name;
				}
			}
		}

		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * File that declares the ability's execute callback, or empty when unknown.
	 *
	 * @param WP_Ability $ability Ability.
	 * @return string
	 */
	private function callback_file( WP_Ability $ability ): string {
		try {
			$property = new ReflectionProperty( $ability, 'execute_callback' );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$callback = $property->getValue( $ability );

			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new ReflectionMethod( $callback[0], (string) $callback[1] );
			} elseif ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$reflection = new ReflectionMethod( ...explode( '::', $callback, 2 ) );
			} elseif ( is_callable( $callback ) ) {
				$reflection = new ReflectionFunction( $callback );
			} else {
				return '';
			}

			return (string) $reflection->getFileName();
		} catch ( Throwable $throwable ) {
			unset( $throwable );
			return '';
		}
	}
}
