<?php
/**
 * Dependency Resolver
 *
 * Orchestrates dependency resolution for blocks, integrations, and their items.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Dependency
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Dependency;

use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\License\License_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Plugin\Plugin_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Account\Account_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Environment\Environment_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Setup\Setup_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Block\License\License_Resolver as Block_License_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Integration_Catalog\Services\Item_Availability_Provider;
use Uncanny_Automator\App\Recipe_Builder\Block\Block;

/**
 * Orchestrates dependency resolution for blocks, integrations, and their items.
 *
 * Coordinates five types of dependencies:
 * - license: Automator license/tier requirements
 * - plugin: Plugin installation/activation requirements (integrations only)
 * - account: App connection requirements (integrations only)
 * - setup: The setup steps an item (trigger, action, condition or loop filter) needs on the site, on plugin and app integrations (items only)
 * - environment: What the site itself must be, e.g. a multisite network (integrations only)
 *
 * @since 7.0.0
 */
class Dependencies_Resolver {

	/**
	 * Dependency context.
	 *
	 * @var Dependency_Context
	 */
	private $context;

	/**
	 * Registered integration dependency resolvers, keyed by dependency type.
	 *
	 * @var array<string,object>
	 */
	private $resolvers;

	/**
	 * Registered block dependency resolvers.
	 *
	 * @var array<object>
	 */
	private $block_resolvers;

	/**
	 * Constructor.
	 *
	 * @param Dependency_Context $context Dependency resolution context
	 *
	 * @return void
	 */
	public function __construct( Dependency_Context $context ) {
		$this->context         = $context;
		$this->resolvers       = $this->register_integration_resolvers();
		$this->block_resolvers = $this->register_block_resolvers();
	}

	/**
	 * Register integration dependency resolvers, keyed by dependency type.
	 *
	 * Insertion order (license, plugin, account, setup, environment) is preserved so
	 * `resolve_integration_dependencies()`'s merged output order is
	 * unchanged; the keys let `resolve_integration_setup_dependencies()`
	 * skip the license resolver before evaluation.
	 *
	 * @return array<string,object> Resolver instances keyed by type
	 */
	private function register_integration_resolvers() {
		return array(
			'license'     => new License_Resolver( $this->context ),
			'plugin'      => new Plugin_Resolver( $this->context ),
			'account'     => new Account_Resolver( $this->context ),
			'setup'       => new Setup_Resolver( $this->context ),
			'environment' => new Environment_Resolver( $this->context ),
		);
	}

	/**
	 * Register block dependency resolvers.
	 *
	 * Blocks only require license/tier validation.
	 *
	 * @return array<object> Array of resolver instances
	 */
	private function register_block_resolvers() {
		return array(
			new Block_License_Resolver( $this->context ),
		);
	}

	/**
	 * Resolve integration-level dependencies.
	 *
	 * @param Integration $integration Integration object
	 *
	 * @return array Dependencies array with 'all_met' and 'items'
	 */
	public function resolve_integration_dependencies( Integration $integration ) {
		$dependencies = array();

		// Loop through registered resolvers and evaluate if applicable.
		foreach ( $this->resolvers as $resolver ) {
			if ( $resolver->should_evaluate( $integration ) ) {
				$resolved_deps = $resolver->evaluate();
				$dependencies  = array_merge( $dependencies, $resolved_deps );
			}
		}

		return $this->format_dependencies_response( $dependencies );
	}

	/**
	 * Resolve only setup dependencies — plugin installation chains and
	 * app/account connection. The license resolver is skipped BEFORE
	 * evaluation: tier/credits entitlements are conveyed separately by
	 * the consumer (the template library routes them onto license tags),
	 * and skipping avoids computing credit state that would be thrown
	 * away.
	 *
	 * Response shape matches resolve_integration_dependencies():
	 * `{ all_met: bool, items: Dependency[] }`, where `all_met` here
	 * means "installed and connected on this site".
	 *
	 * @param Integration $integration Integration object
	 *
	 * @return array Dependencies array with 'all_met' and 'items'
	 */
	public function resolve_integration_setup_dependencies( Integration $integration ) {
		$dependencies = array();

		foreach ( $this->resolvers as $type => $resolver ) {
			if ( 'license' === $type ) {
				continue;
			}
			if ( $resolver->should_evaluate( $integration ) ) {
				$dependencies = array_merge( $dependencies, $resolver->evaluate() );
			}
		}

		return $this->format_dependencies_response( $dependencies );
	}

	/**
	 * Resolve item-level dependencies.
	 *
	 * Items and block modifiers run through the same dependency checks as
	 * integrations, but use their own tier for license checks. The plugin and
	 * setup rows an item (trigger, action, condition or loop filter) needs beyond
	 * its integration are built from the answer the registration pass stored with
	 * it, which every caller hands over from the store entry
	 * (`get_requirements()`), so the scoped catalog and a saved recipe's nodes get
	 * the same rows. A requirements key on `$item` is never read.
	 * Filters out dependencies that are already present in the integration dependencies,
	 * as items inherit the parent integration's dependencies.
	 *
	 * @param array $item Item data with code, type, name, required_tier
	 * @param Integration $integration Integration object
	 * @param array $integration_dependencies Integration-level dependencies (optional)
	 * @param array|null $requirements The item's stored requirements; null when it has none
	 *
	 * @return array Dependencies array with 'all_met' and 'items'
	 */
	public function resolve_item_dependencies( array $item, Integration $integration, array $integration_dependencies = array(), ?array $requirements = null ) {
		$dependencies = array();

		$item['requirements'] = $requirements ?? array();

		// Loop through registered resolvers and evaluate if applicable.
		foreach ( $this->resolvers as $resolver ) {
			if ( $resolver->should_evaluate( $integration, $item ) ) {
				$resolved_deps = $resolver->evaluate();
				$dependencies  = array_merge( $dependencies, $resolved_deps );
			}
		}

		// Filter out dependencies that are already present in integration dependencies.
		$dependencies = $this->filter_inherited_dependencies(
			$dependencies,
			$integration_dependencies,
			$integration->get_entity_code(),
			(string) ( $item['code'] ?? '' ),
			(string) ( $item['requirements']['plugin_details']['integration_required'] ?? '' )
		);

		return $this->format_dependencies_response( $dependencies );
	}

	/**
	 * Whether a listed blocked item reads as one that declares nothing: it declares something
	 * (Item_Availability_Provider::declares_something()) and its stored answer is not met, yet neither it nor its
	 * integration has an unmet row (no row could be built for what it needs: a setup step with no page to link to, a
	 * plugin row without the integration's developer or icon, or an item kept out by a filter alone), so nothing would
	 * show it locked. The scoped catalog leaves such an item out and a saved recipe resolves it unavailable in its own
	 * sentence, as for a blocked item that declares nothing (which the catalog does not list, and which keeps its feed
	 * reading). An unmet integration row (an app that is not connected, whose setup rows are built only once it is)
	 * already locks the item and tells why, so the item stays.
	 *
	 * @since 8.1.0
	 *
	 * @param array|null $requirements             The item's stored requirements; null when it has none.
	 * @param array      $item_dependencies        What resolve_item_dependencies() returned for it.
	 * @param array      $integration_dependencies What resolve_integration_dependencies() returned for its integration.
	 *
	 * @return bool
	 */
	public function is_unmet_without_a_row( ?array $requirements, array $item_dependencies, array $integration_dependencies ): bool {
		return false === ( $requirements['is_met'] ?? null )
			&& Item_Availability_Provider::declares_something( $requirements )
			&& true === ( $item_dependencies['all_met'] ?? true )
			&& true === ( $integration_dependencies['all_met'] ?? true );
	}

	/**
	 * Resolve block-level dependencies.
	 *
	 * @param Block $block Block object
	 *
	 * @return array Dependencies array with 'all_met' and 'items'
	 */
	public function resolve_block_dependencies( Block $block ) {
		$dependencies = array();

		// Loop through registered block resolvers and evaluate if applicable.
		foreach ( $this->block_resolvers as $resolver ) {
			if ( $resolver->should_evaluate( $block ) ) {
				$resolved_deps = $resolver->evaluate();
				$dependencies  = array_merge( $dependencies, $resolved_deps );
			}
		}

		return $this->format_dependencies_response( $dependencies );
	}

	/**
	 * Filter out dependencies that are already present in integration dependencies.
	 *
	 * Items inherit their parent integration's dependencies, so we only need to
	 * include dependencies that are unique to the item (different type). The rows
	 * built from what the item stated it needs beyond its integration are its own
	 * and always kept, beside an integration row of the same type: its plugin row,
	 * its setup rows, and the install rows of the integration it requires, except
	 * one the integration's own rows already carry (never shown twice).
	 *
	 * @param array  $item_dependencies Array of Dependency objects from item
	 * @param array  $integration_dependencies Integration dependencies array with 'items' key (Dependency objects)
	 * @param string $integration_code The item's integration code
	 * @param string $item_code The item code
	 * @param string $required_code The integration the item requires; '' when none
	 *
	 * @return array Filtered array of Dependency objects
	 */
	private function filter_inherited_dependencies( array $item_dependencies, array $integration_dependencies, string $integration_code, string $item_code, string $required_code = '' ) {
		// If no integration dependencies provided, return all item dependencies.
		if ( empty( $integration_dependencies['items'] ) ) {
			return $item_dependencies;
		}

		// Extract dependency types and ids from integration dependencies.
		$integration_types = array();
		$integration_ids   = array();
		foreach ( $integration_dependencies['items'] as $integration_dep ) {
			$integration_types[ $integration_dep->get_type() ] = true;
			$integration_ids[]                                 = $integration_dep->get_id();
		}

		$required_ids = array_diff( Stated_Row_Id::required_integration( $required_code, $item_dependencies ), $integration_ids );

		// Filter out item dependencies that have the same type as integration dependencies,
		// except the rows the item stated. Re-indexed so a stated row after a dropped one keeps the list a list.
		return array_values(
			array_filter(
				$item_dependencies,
				static function ( $item_dep ) use ( $integration_types, $integration_code, $item_code, $required_ids ) {
					return Stated_Row_Id::is_stated( $item_dep->get_id(), $integration_code, $item_code )
						|| in_array( $item_dep->get_id(), $required_ids, true )
						|| ! isset( $integration_types[ $item_dep->get_type() ] );
				}
			)
		);
	}

	/**
	 * Format dependencies response with 'all_met' flag and Dependency objects.
	 *
	 * @param array $dependencies Array of Dependency objects
	 *
	 * @return array Dependencies array with 'all_met' and 'items' (Dependency objects)
	 */
	private function format_dependencies_response( array $dependencies ) {
		// Check if all dependencies are met.
		$all_met = true;
		foreach ( $dependencies as $dep ) {
			if ( ! $dep->is_met() ) {
				$all_met = false;
				break;
			}
		}

		return array(
			'all_met' => $all_met,
			'items'   => $dependencies,
		);
	}

	/**
	 * Convert dependencies response to array format.
	 *
	 * Converts Dependency objects to arrays for JSON serialization.
	 *
	 * @param array $dependencies_response Dependencies response with 'all_met' and 'items' (Dependency objects)
	 *
	 * @return array Dependencies array with 'all_met' and 'items' (arrays)
	 */
	public function to_array( array $dependencies_response ) {
		$dependency_arrays = array_map(
			function ( $dep ) {
				return $dep->to_array();
			},
			$dependencies_response['items']
		);

		return array(
			'all_met' => $dependencies_response['all_met'],
			'items'   => $dependency_arrays,
		);
	}

	/**
	 * Convert integration items to array format.
	 *
	 * @param array $dependencies_response Dependencies response with 'all_met' and 'items' (Dependency objects)
	 * @param array $integration_dependencies Integration dependencies array with 'all_met' key
	 *
	 * @return array Dependencies array with 'all_met' and 'items' (arrays)
	 */
	public function integration_items_to_array( array $dependencies_response, array $integration_dependencies ) {
		$response = $this->to_array( $dependencies_response );
		// If the integration dependencies are not met, override the response all_met to false.
		if ( true === $response['all_met'] && false === $integration_dependencies['all_met'] ) {
			return array(
				'all_met' => false,
				'items'   => $response['items'],
			);
		}

		return $response;
	}
}
