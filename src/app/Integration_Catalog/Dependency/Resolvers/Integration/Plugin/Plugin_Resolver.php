<?php
/**
 * Plugin Resolver
 *
 * Resolves plugin installation/activation dependencies.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Plugin
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Plugin;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Distribution_Type;
use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Recipe_Builder\Shared\Polyfill\Str;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Registry_Service;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Abstract_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Store;
use Uncanny_Automator\App\Recipe_Builder\Services\Availability\Integration_Plugin_Presence;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Enums\Plugin_State;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Stated_Row_Id;

/**
 * Resolves plugin installation/activation dependencies.
 *
 * For an item, also the plugin it needs beyond its integration and the other integration it needs, built from the
 * requirements the registration pass stored with it (`$item['requirements']`); the item is never asked again.
 *
 * NOTE: This resolver is Integration-specific and will not evaluate for other entity types.
 *
 * @since 7.0.0
 *
 * @property Integration $entity
 * @property Plugin_Scenario $scenario
 */
class Plugin_Resolver extends Abstract_Resolver {

	/**
	 * The key in an item's answer naming the integration the plugin it needs belongs to, when that is not the item's
	 * own (Automator's, for an item that needs a newer Automator). Its row shows that integration's developer and icon.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	public const PLUGIN_OWNER = 'plugin_owner';

	/**
	 * Plugin details.
	 *
	 * @var Integration_Plugin_Details|null
	 */
	private $plugin;

	/**
	 * Track codes being resolved to prevent circular dependencies.
	 *
	 * @var array
	 */
	private static array $resolution_stack = array();

	/**
	 * Maximum recursion depth.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 10;

	/**
	 * Whether an integration's plugin is on disk, active or not.
	 *
	 * @var Integration_Plugin_Presence
	 */
	private Integration_Plugin_Presence $presence;

	/**
	 * Build the plugin lookup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->presence = new Integration_Plugin_Presence();
	}

	/**
	 * Store context for evaluation.
	 *
	 * @param Dependency_Evaluatable $entity Entity object
	 * @param array|null $item Item data
	 *
	 * @return void
	 */
	protected function set_properties( Dependency_Evaluatable $entity, $item = null ) {
		$this->entity = $entity;
		$this->item   = $item;

		// Plugin resolver only works with Integration entities
		if ( $this->is_integration() ) {
			$this->plugin = $this->entity->get_details()->get_plugin();
		}
	}

	/**
	 * Check if should be evaluated.
	 *
	 * Evaluates for plugin-type integrations, for any integration naming a
	 * parent it needs installed, and for an item that names a plugin or another
	 * integration it needs. Returns false for non-Integration entities.
	 *
	 * @param Dependency_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data (null for entity-level)
	 *
	 * @return bool True if should be evaluated
	 */
	public function should_evaluate( Dependency_Evaluatable $entity, $item = null ) {
		$this->set_properties( $entity, $item );

		if ( ! $this->is_integration() ) {
			return false;
		}

		// A plugin is itself installable; anything naming a parent needs that parent
		// installed. Apps take the second route — the Elite integrations are apps
		// whose code ships in a separate add-on — and is_plugin() is false for every
		// one of them, so type alone cannot be the question.
		return $this->entity->is_plugin()
			|| ( $this->plugin && '' !== $this->plugin->get_integration_required() )
			|| $this->item_declares_plugin_details();
	}

	/**
	 * Whether the item being resolved stored a plugin or another integration it needs.
	 *
	 * @since 8.1.0
	 *
	 * @return bool False at integration level.
	 */
	private function item_declares_plugin_details(): bool {
		$details = $this->item_plugin_details();

		return '' !== (string) ( $details['plugin_file'] ?? '' ) || '' !== (string) ( $details['integration_required'] ?? '' );
	}

	/**
	 * The requirements the registration pass stored with the item being resolved.
	 *
	 * @since 8.1.0
	 *
	 * @return array Empty at integration level, or for an item that stored none.
	 */
	private function item_requirements(): array {
		return null !== $this->item ? (array) ( $this->item['requirements'] ?? array() ) : array();
	}

	/**
	 * The plugin details stored with the item being resolved.
	 *
	 * @since 8.1.0
	 *
	 * @return array Empty at integration level, or for an item that stored none.
	 */
	private function item_plugin_details(): array {
		return (array) ( $this->item_requirements()['plugin_details'] ?? array() );
	}

	/**
	 * Evaluate plugin dependency.
	 *
	 * Returns flat array with parent dependencies first, then self, then, for an
	 * item, the rows for what the item needs beyond its integration.
	 *
	 * @return array Array of Dependency objects
	 */
	public function evaluate() {
		$code = $this->get_code();

		// Circular dependency check.
		if ( in_array( $code, self::$resolution_stack, true ) ) {
			return array();
		}

		// Depth limit check.
		if ( count( self::$resolution_stack ) >= self::MAX_DEPTH ) {
			return $this->entity->is_plugin() ? array( $this->resolve_self() ) : array();
		}

		// Push current to stack.
		self::$resolution_stack[] = $code;

		try {
			$dependencies = array();

			// 1. Recursively resolve parent dependencies FIRST.
			if ( $this->plugin && ! empty( $this->plugin->get_integration_required() ) ) {
				$parent_code         = $this->plugin->get_integration_required();
				$parent_dependencies = $this->resolve_parent_integration( $parent_code );
				$dependencies        = array_merge( $dependencies, $parent_dependencies );
			}

			// 2. Then add self LAST — only a plugin is something to install. An app is
			// carried by one, so the row it needs is the parent's, already added above.
			if ( $this->entity->is_plugin() ) {
				$dependencies[] = $this->resolve_self();
			}

			// 3. An item's own needs, from what it stored.
			return array_merge( $dependencies, $this->resolve_item() );
		} finally {
			// Always pop from stack.
			array_pop( self::$resolution_stack );
		}
	}

	/**
	 * Resolve this plugin's own dependency. A plugin that is on disk but not active is
	 * offered for activation, not for installation.
	 *
	 * @return Dependency
	 */
	private function resolve_self() {
		$is_active        = $this->is_active_integration( $this->get_code() );
		$is_inactive      = ! $is_active && $this->presence->is_installed( $this->get_code() );
		$developer        = $this->entity->get_details()->get_developer();
		$integration_name = $this->get_name();
		$scenario_id      = $this->get_scenario_id();
		$blocked_by       = $this->blocking_parent_name();

		// Check for sub-dependencies (e.g., WooCommerce Subscriptions requires WooCommerce).
		// Only a parent the catalog knows holds the plugin back: one it does not know has no
		// row to install and no name to give, and a disabled button with no reason reads as broken.
		$dependency_ids = array();
		if ( null !== $this->catalog_parent() ) {
			$dependency_ids[] = Stated_Row_Id::integration( $this->plugin->get_integration_required() );
		}

		return $this->create_dependency(
			array(
				'type'         => 'installable',
				'id'           => Stated_Row_Id::integration( $this->get_code() ),
				'name'         => $integration_name,
				'description'  => $is_inactive
					? $this->scenario->get_inactive_description( $integration_name, $blocked_by )
					: $this->scenario->get_description( $scenario_id, $integration_name, $blocked_by ),
				'is_met'       => $is_active,
				'is_disabled'  => $this->calculate_is_disabled( $dependency_ids ),
				'dependencies' => $dependency_ids,
				'cta'          => $is_inactive
					? $this->scenario->create_activate_cta( $integration_name )
					: $this->scenario->create_cta( $scenario_id, $integration_name, $developer->get_site() ),
				'scenario_id'  => $scenario_id,
				'icon'         => $this->entity->get_details()->get_icon(),
				'developer'    => $developer->to_array(),
				'tags'         => $this->get_tags(),
			)
		);
	}

	/**
	 * The rows for what the item being resolved needs beyond its integration, from the requirements it stored: the
	 * other integration it needs while that one is not active (that integration's own install rows), then the
	 * plugin it names.
	 *
	 * @since 8.1.0
	 *
	 * @return Dependency[] Empty at integration level, or when the item stored nothing it needs.
	 */
	private function resolve_item(): array {
		$requirements = $this->item_requirements();
		$details      = $this->item_plugin_details();
		$rows         = array();

		$required = (string) ( $details['integration_required'] ?? '' );
		if ( '' !== $required && false === ( $requirements['integration_met'] ?? true ) ) {
			$rows = $this->resolve_parent_integration( $required );
		}

		$state = (string) ( $requirements['plugin_state'] ?? Plugin_State::NONE );
		if ( '' !== (string) ( $details['plugin_file'] ?? '' ) && Plugin_State::NONE !== $state ) {
			$row = $this->resolve_item_plugin( $details, $state );
			if ( null !== $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * The row for the plugin an item needs, in the state the registration pass stored. Missing and met read as the
	 * integration's own install row does for that distribution type; inactive offers activation; outdated and
	 * unsupported point to the WordPress updates screen.
	 *
	 * The developer and icon are those of the integration the plugin belongs to ({@see self::plugin_owner()}). A row
	 * needs a name, an icon, and a developer name and site; without any of them, or without that integration in the
	 * catalog, it cannot be built (and the builder could not read it), so it is left out and logged instead, and the
	 * item's other rows still show.
	 *
	 * @since 8.1.0
	 *
	 * @param array  $details The plugin details the item stored.
	 * @param string $state   The plugin state, a {@see Plugin_State} value.
	 *
	 * @return Dependency|null Null when the row cannot be built.
	 */
	private function resolve_item_plugin( array $details, string $state ): ?Dependency {
		$id    = Stated_Row_Id::plugin( $this->get_code(), (string) ( $this->item['code'] ?? '' ) );
		$owner = $this->plugin_owner();

		if ( null === $owner ) {
			automator_log( sprintf( 'Item plugin row %s left out: the catalog has no %s integration to take the plugin\'s developer and icon from.', $id, (string) ( $this->item_requirements()[ self::PLUGIN_OWNER ] ?? '' ) ), 'Plugin_Resolver' );

			return null;
		}

		$name      = (string) ( $details['plugin_name'] ?? '' );
		$developer = $owner->get_details()->get_developer();
		$site      = (string) $developer->get_site();
		$icon      = $owner->get_details()->get_icon();

		if ( '' === $name || '' === $site || '' === $developer->get_name() || '' === $icon ) {
			automator_log( sprintf( 'Item plugin row %s left out: it needs a plugin name, an icon, and the developer name and site of the integration the plugin belongs to.', $id ), 'Plugin_Resolver' );

			return null;
		}

		$distribution_type = (string) ( $details['distribution_type'] ?? '' );
		$scenario_id       = $this->scenario->get_scenario_id( $distribution_type );

		switch ( $state ) {
			case Plugin_State::INACTIVE:
				$description = $this->scenario->get_inactive_description( $name );
				$cta         = $this->scenario->create_activate_cta( $name );
				break;
			case Plugin_State::OUTDATED:
				$description = $this->scenario->get_outdated_description( $name, (string) ( $details['min_version'] ?? '' ) );
				$cta         = $this->scenario->create_update_cta( $name );
				break;
			case Plugin_State::UNSUPPORTED:
				$description = $this->scenario->get_unsupported_description( $name );
				$cta         = $this->scenario->create_check_updates_cta();
				break;
			default:
				$description = $this->scenario->get_description( $scenario_id, $name );
				$cta         = $this->scenario->create_cta( $scenario_id, $name, $site );
				break;
		}

		return $this->create_dependency(
			array(
				'type'        => 'installable',
				'id'          => $id,
				'name'        => $name,
				'description' => $description,
				'is_met'      => Plugin_State::MET === $state,
				'cta'         => $cta,
				'scenario_id' => $scenario_id,
				'icon'        => $icon,
				'developer'   => $developer->to_array(),
				'tags'        => $this->get_tags( $distribution_type ),
			)
		);
	}

	/**
	 * The integration the plugin an item needs belongs to, whose developer and icon its row shows: the one the
	 * item's answer names under {@see self::PLUGIN_OWNER}, else the item's own integration.
	 *
	 * @since 8.1.0
	 *
	 * @return Integration|null Null when the answer names an integration the catalog does not know.
	 */
	private function plugin_owner(): ?Integration {
		$owner = (string) ( $this->item_requirements()[ self::PLUGIN_OWNER ] ?? '' );

		if ( '' === $owner ) {
			return $this->entity;
		}

		return Integration_Store::get_instance()->get_by_code( $owner );
	}

	/**
	 * Get tags for a plugin dependency.
	 *
	 * @param string|null $distribution_type The plugin's distribution type; null for this integration's own plugin.
	 *
	 * @return array Array of tag arrays.
	 */
	private function get_tags( ?string $distribution_type = null ): array {
		if ( null === $distribution_type ) {
			$distribution_type = $this->plugin
				? $this->plugin->get_distribution_type()
				: Distribution_Type::COMMERCIAL;
		}

		// Base tag for all installable dependencies.
		$tags = array(
			array(
				'scenario_id' => 'installable',
				'label'       => esc_html_x( 'Plugin', 'Dependency', 'uncanny-automator' ),
				'icon'        => 'puzzle-piece',
			),
		);

		// Add distribution-specific tag.
		switch ( $distribution_type ) {
			case Distribution_Type::COMMERCIAL:
				$tags[] = array(
					'scenario_id' => 'installable-' . Distribution_Type::SLUG_COMMERCIAL,
					'label'       => esc_html_x( 'Commercial', 'Dependency', 'uncanny-automator' ),
					'icon'        => 'dollar-sign',
				);
				break;
			case Distribution_Type::OPEN_SOURCE:
				$tags[] = array(
					'scenario_id' => 'installable-' . Distribution_Type::SLUG_OPEN_SOURCE,
					'label'       => esc_html_x( 'Open source', 'Dependency', 'uncanny-automator' ),
					'icon'        => 'earth-americas',
				);
				break;
			case Distribution_Type::WP_ORG:
				$tags[] = array(
					'scenario_id' => 'installable-' . Distribution_Type::SLUG_WP_ORG,
					'label'       => esc_html_x( 'Open source', 'Dependency', 'uncanny-automator' ),
					'icon'        => 'earth-americas',
				);
				break;
			// Undeclared distribution carries no second pill — claiming either
			// "Commercial" or "Open source" here would be a guess.
			default:
				break;
		}

		return $tags;
	}

	/**
	 * Display name of the integration that must be installed first, if any.
	 *
	 * Only names a parent we can actually confirm: the plugin details have to
	 * declare one, it has to be inactive, and it has to resolve to a known
	 * integration. A disabled button with no stated reason reads as a broken
	 * one, but naming a blocker we cannot verify would be worse than silence.
	 *
	 * @return string Parent integration name, empty when nothing blocks installing
	 */
	private function blocking_parent_name(): string {
		$parent = $this->catalog_parent();

		if ( null === $parent || $this->is_active_integration( $this->plugin->get_integration_required() ) ) {
			return '';
		}

		return $parent->get_entity_name();
	}

	/**
	 * The parent integration the plugin details declare, when the catalog knows it.
	 *
	 * @since 8.0.0
	 *
	 * @return Integration|null The parent, or null when none is declared or the catalog does not know it.
	 */
	private function catalog_parent(): ?Integration {
		if ( ! $this->plugin ) {
			return null;
		}

		$parent_code = $this->plugin->get_integration_required();

		if ( '' === $parent_code ) {
			return null;
		}

		return Integration_Store::get_instance()->get_by_code( $parent_code );
	}

	/**
	 * Calculate if dependency is disabled by unmet sub-dependencies.
	 *
	 * For example, WooCommerce Subscriptions can't be installed if WooCommerce isn't active.
	 *
	 * @param array $dependency_ids Array of dependency IDs this depends on
	 *
	 * @return bool True if dependency is blocked by unmet sub-dependencies
	 */
	private function calculate_is_disabled( array $dependency_ids ) {
		if ( empty( $dependency_ids ) ) {
			return false;
		}

		foreach ( $dependency_ids as $dep_id ) {
			// Check if installable dependency is met.
			if ( Str::starts_with( $dep_id, 'installable-' ) ) {
				$required_code = str_replace( 'installable-', '', $dep_id );
				if ( ! $this->is_active_integration( $required_code ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if integration is active.
	 *
	 * @param string $code Integration code
	 *
	 * @return bool True if integration is active
	 */
	private function is_active_integration( string $code ) {
		return Integration_Registry_Service::get_instance()->has_integration( $code );
	}

	/**
	 * Get plugin scenario ID.
	 *
	 * Determines scenario based on plugin distribution type.
	 *
	 * @return string Scenario ID matching frontend schema
	 */
	private function get_scenario_id() {
		// Default for null plugin details.
		if ( ! $this->plugin ) {
			return $this->scenario->get_scenario_id( Distribution_Type::COMMERCIAL );
		}

		// Determine distribution type.
		$distribution_type = $this->plugin->get_distribution_type();

		// Let scenario service determine the scenario ID.
		return $this->scenario->get_scenario_id( $distribution_type );
	}

	/**
	 * Resolve a parent integration and its dependencies recursively.
	 *
	 * @param string $parent_code Integration code (e.g., 'WC')
	 *
	 * @return array Array of Dependency objects (parent's parents + parent)
	 */
	private function resolve_parent_integration( string $parent_code ): array {
		// Get parent Integration from store singleton.
		$parent_integration = Integration_Store::get_instance()->get_by_code( $parent_code );

		if ( null === $parent_integration ) {
			return array();
		}

		// Create a new resolver instance for the parent.
		$parent_resolver = new self( $this->context, $this->scenario );

		// Check if should evaluate (is it a plugin-type integration?).
		if ( ! $parent_resolver->should_evaluate( $parent_integration ) ) {
			return array();
		}

		// Recursively evaluate parent (returns flat array: grandparents + parent).
		return $parent_resolver->evaluate();
	}
}
