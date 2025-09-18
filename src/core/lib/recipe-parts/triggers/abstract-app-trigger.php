<?php
/**
 * Abstract App Trigger
 *
 * Extends the base Trigger class to provide clean access to app integration dependencies.
 * This eliminates the need for array_shift and manual property setting in extending classes.
 *
 * @package Uncanny_Automator\Recipe
 * @since 4.14
 */

namespace Uncanny_Automator\Recipe;

/**
 * Abstract App Trigger
 *
 * Provides clean access to app integration dependencies:
 * - $this->helpers - Access to the integration's helper methods
 * - $this->api - Access to the integration's API methods
 * - $this->webhooks - Access to the integration's webhook methods (if available)
 *
 * @package Uncanny_Automator\Recipe
 */
abstract class App_Trigger extends Trigger {

	/**
	 * Integration helpers instance.
	 *
	 * @var \Uncanny_Automator\App_Integrations\App_Helpers|null
	 */
	protected $helpers;

	/**
	 * Integration API instance.
	 *
	 * @var \Uncanny_Automator\App_Integrations\Api_Caller|null
	 */
	protected $api;

	/**
	 * Integration webhooks instance.
	 *
	 * @var \Uncanny_Automator\App_Integrations\App_Webhooks|null
	 */
	protected $webhooks;

	/**
	 * Override the parent method to set all dependencies from the dependencies object.
	 * This provides clean access to helpers, api, and webhooks without manual property setting.
	 *
	 * @param array $dependencies Array of dependency objects.
	 * @return void
	 */
	protected function set_helpers_from_dependencies( $dependencies ) {
		if ( empty( $dependencies ) || ! isset( $dependencies[0] ) ) {
			return;
		}

		// Use existing available methods.
		$this->set_item_helpers( $dependencies[0] );
		$this->set_helpers( $this->item_helpers->helpers ?? null );

		// Set API if available.
		$this->set_api( $this->item_helpers->api ?? null );

		// Set webhooks if available (optional).
		$this->set_webhooks( $this->item_helpers->webhooks ?? null );
	}

	/**
	 * Set helpers.
	 *
	 * @param \Uncanny_Automator\App_Integrations\App_Helpers|null $helpers helpers instance.
	 * @return void
	 */
	protected function set_helpers( $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * Set API helpers.
	 *
	 * @param \Uncanny_Automator\App_Integrations\Api_Caller|null $api API instance.
	 *
	 * @return void
	 */
	protected function set_api( $api ) {
		$this->api = $api;
	}

	/**
	 * Get the API instance.
	 *
	 * @return \Uncanny_Automator\App_Integrations\Api_Caller|null The API instance or null if not available
	 */
	protected function get_api() {
		return $this->api;
	}

	/**
	 * Set webhooks helpers.
	 *
	 * @param \Uncanny_Automator\App_Integrations\App_Webhooks|null $webhooks Webhooks instance.
	 *
	 * @return void
	 */
	protected function set_webhooks( $webhooks ) {
		$this->webhooks = $webhooks;
	}

	/**
	 * Get the webhooks instance.
	 *
	 * @return \Uncanny_Automator\App_Integrations\App_Webhooks|null The webhooks instance or null if not available
	 */
	protected function get_webhooks() {
		return $this->webhooks;
	}
}
