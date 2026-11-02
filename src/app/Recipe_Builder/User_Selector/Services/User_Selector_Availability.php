<?php
/**
 * User selector availability.
 *
 * @since 8.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\User_Selector\Services;

use Uncanny_Automator\App\Bridge\Automator_Recipe_Runner_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Runner_Bridge;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Config;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Context;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\License\License_Scenario;
use WP_Error;

/**
 * Whether this site can use an Everyone recipe's run-as setting.
 *
 * The setting picks the WordPress user an Everyone recipe's actions run on.
 * Only a registered user resolver acts on it, and Pro registers one; without
 * it the runner ignores the setting. So the setting is usable exactly when a
 * resolver is registered, and every write path that authors one asks
 * `refusal()` first: the shared save, so REST and MCP refuse the same way.
 *
 * @since 8.0.0
 */
final class User_Selector_Availability {

	/**
	 * WP_Error code for a run-as write refused because no user resolver is registered.
	 *
	 * @var string
	 */
	public const REQUIRES_PRO = 'run_as_requires_pro';

	/**
	 * The legacy recipe runner, which holds the user resolver.
	 *
	 * @var Recipe_Runner_Bridge
	 */
	private Recipe_Runner_Bridge $runner;

	/**
	 * License and plugin state, for the unmet requirement's wording.
	 *
	 * @var Dependency_Context
	 */
	private Dependency_Context $context;

	/**
	 * Lazy-default DI: production reads the live runner and license state.
	 *
	 * @param Recipe_Runner_Bridge|null $runner  The recipe runner.
	 * @param Dependency_Context|null   $context License and plugin state.
	 */
	public function __construct( ?Recipe_Runner_Bridge $runner = null, ?Dependency_Context $context = null ) {
		$this->runner  = $runner ?? new Automator_Recipe_Runner_Bridge();
		$this->context = $context ?? new Dependency_Context();
	}

	/**
	 * Whether a run-as setting picks the user an Everyone recipe's actions run on.
	 *
	 * @return bool
	 */
	public function is_usable(): bool {
		return $this->runner->has_user_resolver();
	}

	/**
	 * The refusal a run-as write gets on this site, or null when it may be written.
	 *
	 * @return WP_Error|null
	 */
	public function refusal(): ?WP_Error {
		if ( $this->is_usable() ) {
			return null;
		}

		return new WP_Error(
			self::REQUIRES_PRO,
			esc_html_x( 'Choosing which user an Everyone recipe runs its actions on requires Uncanny Automator Pro.', 'Run-as error', 'uncanny-automator' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * The unmet requirement behind an unusable run-as setting: Uncanny
	 * Automator Pro, worded and linked for this site's license and plugin
	 * state the way a step that needs Pro is.
	 *
	 * @return Dependency
	 */
	public function requirement(): Dependency {
		$scenario    = new License_Scenario( $this->context );
		$scenario_id = $scenario->get_scenario_id( 'pro-basic', 'tier' );
		$name        = $scenario->get_name( $scenario_id );

		return new Dependency(
			Dependency_Config::from_array(
				array(
					'type'        => 'license',
					'id'          => 'license-run-as',
					'name'        => $name,
					'description' => $scenario->get_description( $scenario_id, $name ),
					'is_met'      => $this->is_usable(),
					'cta'         => $scenario->create_cta( $scenario_id, $name ),
					'scenario_id' => $scenario_id,
				)
			)
		);
	}
}
