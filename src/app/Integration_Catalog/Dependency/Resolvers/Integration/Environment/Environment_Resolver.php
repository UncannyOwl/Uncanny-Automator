<?php
/**
 * Environment Resolver
 *
 * Resolves site-environment requirements: what the site itself must be for an
 * integration to run, as opposed to anything a user can install or connect.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Environment
 * @since 8.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Environment;

use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Context;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Abstract_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Value_Objects\Dependency_Cta;
use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;

/**
 * Marks an integration unavailable when the site itself cannot run it.
 *
 * WordPress Multisite is the only such integration: Pro loads it only on a
 * network (its plugin_active() is is_multisite()), so on a single site the
 * catalog would otherwise list it as ready while none of its items exist. It
 * stays listed, with one unmet row that says why.
 *
 * The row is typed installable because the builder's dependency union has no
 * environment type yet; its CTA is a docs link, never an install, and its tag
 * names the Environment category. Change the type here once the builder has one.
 *
 * Integration-specific: never evaluates for other entity types.
 *
 * @since 8.0.0
 *
 * @property Integration $entity
 */
class Environment_Resolver extends Abstract_Resolver {

	/**
	 * Code of the WordPress Multisite integration.
	 *
	 * @since 8.0.0
	 * @var string
	 */
	const MULTISITE_INTEGRATION = 'WPMU';

	/**
	 * WordPress docs page on turning a site into a network.
	 *
	 * @since 8.0.0
	 * @var string
	 */
	const MULTISITE_DOCS_URL = 'https://developer.wordpress.org/advanced-administration/multisite/create-network/';

	/**
	 * Reports whether this site is a multisite network.
	 *
	 * @since 8.0.0
	 * @var callable
	 */
	private $is_multisite;

	/**
	 * Constructor.
	 *
	 * @since 8.0.0
	 *
	 * @param Dependency_Context $context      Dependency resolution context.
	 * @param callable|null      $is_multisite Optional multisite check (default: is_multisite).
	 */
	public function __construct( Dependency_Context $context, ?callable $is_multisite = null ) {
		$this->is_multisite = $is_multisite ?? 'is_multisite';
		parent::__construct( $context );
	}

	/**
	 * Evaluate for WordPress Multisite when this site is not a network.
	 *
	 * @since 8.0.0
	 *
	 * @param Dependency_Evaluatable $entity Entity object (Integration, Block, etc).
	 * @param array|null             $item   Item data (null for entity-level).
	 *
	 * @return bool True if should be evaluated.
	 */
	public function should_evaluate( Dependency_Evaluatable $entity, $item = null ) {
		$this->set_properties( $entity, $item );

		return $this->is_integration()
			&& self::MULTISITE_INTEGRATION === $this->get_code()
			&& ! call_user_func( $this->is_multisite );
	}

	/**
	 * The unmet multisite requirement.
	 *
	 * @since 8.0.0
	 *
	 * @return array Array of Dependency objects.
	 */
	public function evaluate() {
		return array(
			$this->create_dependency(
				array(
					'type'        => 'installable',
					'id'          => sprintf( 'environment-%s', $this->get_code() ),
					'name'        => $this->get_name(),
					'description' => esc_html__( 'This integration needs a WordPress Multisite network. This site is a single site, so it cannot be used here.', 'uncanny-automator' ),
					'is_met'      => false,
					'cta'         => new Dependency_Cta(
						array(
							'type'  => 'link-external',
							'label' => esc_html_x( 'Learn about Multisite', 'Dependency CTA', 'uncanny-automator' ),
							'url'   => self::MULTISITE_DOCS_URL,
						)
					),
					'scenario_id' => 'installable-open-source',
					'icon'        => $this->entity->get_details()->get_icon(),
					'developer'   => array(
						'name' => 'WordPress',
						'site' => 'https://wordpress.org',
					),
					'tags'        => array(
						array(
							'scenario_id' => 'installable',
							'label'       => esc_html_x( 'Environment', 'Dependency', 'uncanny-automator' ),
							'icon'        => 'server',
						),
					),
				)
			),
		);
	}
}
