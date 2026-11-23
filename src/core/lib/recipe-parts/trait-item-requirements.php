<?php
/**
 * Item Requirements Trait
 *
 * What a trigger or action needs beyond its integration, and the answer worked out once, in the
 * registration pass, from what it declared.
 *
 * ## Usage
 *
 * Plugin details are data: one literal block passed to one setter in the item's setup, in the shape of
 * the integration's `plugin_details`:
 * ```php
 * protected function setup_action() {
 *     $this->set_plugin_details(
 *         array(
 *             'plugin_file'       => 'duplicator-pro/duplicator-pro.php',
 *             'plugin_name'       => 'Duplicator Pro',
 *             'distribution_type' => 'commercial',
 *         )
 *     );
 * }
 *
 * protected function is_plugin_compatible(): ?bool {
 *     return class_exists( 'DUP_PRO_Package' );
 * }
 * ```
 *
 * The plugin's presence is read from the site and the item's guard is kept apart from it:
 * - No guard (is_plugin_compatible() returns null): presence and version decide.
 * - A guard that passes: the plugin is loaded, even when its file is not the one named (a renamed folder).
 * - A guard that fails with the file active: our code does not match the installed version.
 *
 * An item that states a required integration keeps that integration's plugin in its guard, so the guard is the
 * item's whole check. A guard that passes then answers for the integration too, on a request that did not load it.
 *
 * Setup steps are code: the item overrides setup_requirements() and returns each step with its answer.
 *
 * Items we control do not override requirements_met(); it is true when the plugin details are met, the
 * required integration is met and every setup step is met. An item that overrides it is answered by
 * its own bool.
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */

namespace Uncanny_Automator;

use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Registry_Service;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Enums\Plugin_State;

/**
 * Trait Item_Requirements
 *
 * The plugin state comes from {@see Plugin_State::work_out()}, the one rule for every item type.
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
trait Item_Requirements {

	/**
	 * The plugin this item needs beyond its integration, as the item declared it.
	 *
	 * @since 8.1.0
	 *
	 * @var array
	 */
	protected $plugin_details = array();

	/**
	 * The requirements evaluate_requirements() worked out, read by requirements_met() while that
	 * evaluation runs so the item's answer is not worked out a second time.
	 *
	 * @since 8.1.0
	 *
	 * @var array|null
	 */
	private $requirements_in_evaluation = null;

	/**
	 * Declare the plugin this item needs beyond its integration.
	 *
	 * Keys: `plugin_file`, `plugin_name`, `distribution_type`, `plugin_required`, `integration_required`,
	 * `min_version`. Any other key is dropped.
	 *
	 * `min_version` applies only together with `plugin_file`; an installed plugin whose header carries no
	 * Version is not treated as outdated. `integration_required` is the exact integration code.
	 *
	 * @since 8.1.0
	 *
	 * @param array $details The plugin details, as a literal array.
	 *
	 * @return void
	 */
	public function set_plugin_details( array $details ): void {
		$this->plugin_details = array_intersect_key( $details, $this->plugin_details_defaults() );
	}

	/**
	 * The plugin this item needs beyond its integration, every key present.
	 *
	 * @since 8.1.0
	 *
	 * @return array The declared details; a key the item left out reads as ''.
	 */
	public function get_plugin_details(): array {
		return array_merge( $this->plugin_details_defaults(), $this->plugin_details );
	}

	/**
	 * The setup steps this item needs on the site, each with its answer.
	 *
	 * Each step: `key`, `description`, `is_met` (bool), and optionally `name` and `url`. Without a `name`, the step's
	 * row is named "{Integration} settings". `url` is the absolute http or https URL of the page where the step is
	 * done (e.g. the plugin's own settings page); without a usable one, the step's row links to the integration's
	 * settings page. Asked only once the plugin and the required integration are met, so a step may rely on them
	 * being loaded.
	 *
	 * @since 8.1.0
	 *
	 * @return array The steps; empty when the item needs none.
	 */
	protected function setup_requirements(): array {
		return array();
	}

	/**
	 * Whether this item's code can run against the plugin that is loaded.
	 *
	 * An item overrides it with its class, function or constant check. With no guard, the plugin's
	 * presence and version alone decide. A guard that passes means the plugin is loaded even when its
	 * file is not the one named (a renamed folder). A guard that fails while the file is active means
	 * our code does not match the installed version.
	 *
	 * An item that states a required integration checks that integration's plugin here as well: a guard that
	 * passes answers for the integration on a request that did not load it.
	 *
	 * @since 8.1.0
	 *
	 * @return bool|null True when the check passes, false when it fails, null when the item has no guard.
	 */
	protected function is_plugin_compatible(): ?bool {
		return null;
	}

	/**
	 * Whether this item can be used on this site.
	 *
	 * True when the plugin state is none or met, the required integration is met and every setup step
	 * is met. Items we control do not override it.
	 *
	 * @since 8.1.0
	 *
	 * @return bool
	 */
	public function requirements_met() {
		$requirements = $this->requirements_in_evaluation ?? $this->work_out_requirements();

		return $requirements['is_met'];
	}

	/**
	 * Why this item's requirements are not met on this site, for an item that gives its own sentence.
	 *
	 * @since 8.1.0
	 *
	 * @return string The sentence; '' tells the generic one.
	 */
	public function requirements_unmet_message(): string {
		return '';
	}

	/**
	 * Work out this item's requirements once, for the registration pass to store with its definition.
	 *
	 * `is_met` is the answer requirements_met() gives, asked exactly once here.
	 *
	 * @since 8.1.0
	 *
	 * @return array{is_met: bool, plugin_details: array, plugin_state: string, integration_met: bool, setup: array, message: string}
	 */
	public function evaluate_requirements(): array {
		$this->requirements_in_evaluation = $this->work_out_requirements();

		$is_met = (bool) $this->requirements_met();

		$requirements                     = $this->requirements_in_evaluation;
		$this->requirements_in_evaluation = null;

		$requirements['is_met']  = $is_met;
		$requirements['message'] = $is_met ? '' : $this->requirements_unmet_message();

		return $requirements;
	}

	/**
	 * Work out the requirements from what the item declared.
	 *
	 * The guard is asked once. It judges the plugin only while the required integration is met, so a missing
	 * integration shows its own row and does not read the item's plugin as unsupported.
	 *
	 * Runs inside the registration filters, so an item's own guard or setup steps that throw never stop the pass for
	 * the others: a guard that throws reads as failing, setup steps that throw leave the item not met with no step to
	 * show, and each is logged once.
	 *
	 * A using class may call it to work out its requirements by this rule and build its own answer on the result.
	 *
	 * @since 8.1.0
	 *
	 * @return array{is_met: bool, plugin_details: array, plugin_state: string, integration_met: bool, setup: array, message: string}
	 */
	protected function work_out_requirements(): array {
		$details         = $this->get_plugin_details();
		$guard           = $this->ask_guard();
		$integration_met = $this->is_required_integration_met( (string) $details['integration_required'], $guard );
		$plugin_state    = Plugin_State::work_out( $details, $integration_met ? $guard : null );
		$plugin_met      = in_array(
			$plugin_state,
			array(
				Plugin_State::NONE,
				Plugin_State::MET,
			),
			true
		);
		$setup           = $plugin_met && $integration_met ? $this->ask_setup_steps() : array();

		return array(
			'is_met'          => $plugin_met && $integration_met && null !== $setup && $this->are_setup_steps_met( $setup ),
			'plugin_details'  => $details,
			'plugin_state'    => $plugin_state,
			'integration_met' => $integration_met,
			'setup'           => $setup ?? array(),
			'message'         => '',
		);
	}

	/**
	 * The item's compatibility guard; one that throws reads as failing, and is logged.
	 *
	 * @since 8.1.0
	 *
	 * @return bool|null
	 */
	private function ask_guard(): ?bool {
		try {
			return $this->is_plugin_compatible();
		} catch ( \Throwable $e ) {
			automator_log( sprintf( '%s: is_plugin_compatible() threw, read as failing: %s', static::class, $e->getMessage() ), 'Item_Requirements' );

			return false;
		}
	}

	/**
	 * The item's setup steps; null when they throw, which is logged.
	 *
	 * @since 8.1.0
	 *
	 * @return array|null
	 */
	private function ask_setup_steps(): ?array {
		try {
			return $this->setup_requirements();
		} catch ( \Throwable $e ) {
			automator_log( sprintf( '%s: setup_requirements() threw, the item reads as not met: %s', static::class, $e->getMessage() ), 'Item_Requirements' );

			return null;
		}
	}

	/**
	 * Whether the integration this item requires is met: registered on this request, or answered by a guard that
	 * passes, since the guard holds that integration's plugin check too.
	 *
	 * A using class may answer it with its own check instead.
	 *
	 * @since 8.1.0
	 *
	 * @param string    $integration_code The required integration's code; '' when none.
	 * @param bool|null $guard            The item's guard answer; null when it has none.
	 *
	 * @return bool True when none is required.
	 */
	protected function is_required_integration_met( string $integration_code, ?bool $guard ): bool {
		if ( '' === $integration_code || true === $guard ) {
			return true;
		}

		return Integration_Registry_Service::get_instance()->has_integration( $integration_code );
	}

	/**
	 * Whether every setup step is met.
	 *
	 * @since 8.1.0
	 *
	 * @param array $steps The setup steps.
	 *
	 * @return bool True when there are none.
	 */
	private function are_setup_steps_met( array $steps ): bool {
		foreach ( $steps as $step ) {
			if ( true !== ( $step['is_met'] ?? false ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The plugin details keys, each empty.
	 *
	 * @since 8.1.0
	 *
	 * @return array
	 */
	private function plugin_details_defaults(): array {
		return array(
			'plugin_file'          => '',
			'plugin_name'          => '',
			'distribution_type'    => '',
			'plugin_required'      => '',
			'integration_required' => '',
			'min_version'          => '',
		);
	}
}
