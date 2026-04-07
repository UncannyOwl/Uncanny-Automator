<?php
namespace Uncanny_Automator\Settings;

use Exception;

/**
 * Trait for discovering available actions and triggers in premium integrations
 *
 * @package Uncanny_Automator\Settings
 */
trait Premium_Integration_Items {

	/**
	 * Get available actions by scanning directories and applying filters
	 *
	 * @return array
	 */
	protected function get_available_actions() {
		return $this->get_available_items( 'actions' );
	}

	/**
	 * Get available triggers by scanning directories and applying filters
	 *
	 * @return array
	 */
	protected function get_available_triggers() {
		return $this->get_available_items( 'triggers' );
	}

	/**
	 * Get available items (actions or triggers) by scanning directories and applying filters
	 *
	 * @param string $type Either 'actions' or 'triggers'
	 * @return array
	 * @throws Exception If invalid item type.
	 */
	protected function get_available_items( $type ) {

		// Validate type.
		$this->validate_item_type( $type );

		// Get trigger or action items from the integration.
		$items = $this->get_items_from_classes( $type );

		// Adjust the ID for the filter name.
		$integration_id = strtolower( $this->get_id() );

		/**
		 * Filter the available items
		 *
		 * @param array $items The current items
		 * @return array
		 */
		$items = apply_filters( "automator_{$integration_id}_{$type}", $items );

		// Remove duplicates and reindex array
		return array_values( array_unique( $items ) );
	}

	/**
	 * Get items (actions or triggers) for this integration from Automator's
	 * global registry.
	 *
	 * Previously this scanned the integration's `actions/`/`triggers/` directories
	 * and instantiated each class to read its readable sentence. That approach
	 * stopped working once `Abstract_Action::__construct()` and
	 * `Abstract_Trigger::__construct()` gained a static double-instantiation
	 * guard: by the time the settings page renders, every action/trigger class
	 * has already been instantiated during plugin boot, so re-instantiating from
	 * here short-circuits before `setup_action()`/`setup_trigger()` runs and
	 * `readable_sentence` is never set.
	 *
	 * Reading from the registry avoids that pitfall and is also independent of
	 * on-disk file layout.
	 *
	 * @param string $type Either 'actions' or 'triggers'
	 * @return array
	 * @throws Exception If invalid item type.
	 */
	protected function get_items_from_classes( $type ) {

		// Validate type.
		$this->validate_item_type( $type );

		$registered = 'actions' === $type
			? Automator()->get_actions()
			: Automator()->get_triggers();

		if ( empty( $registered ) ) {
			return array();
		}

		$integration_code = strtoupper( $this->get_id() );
		$items            = array();

		foreach ( $registered as $registered_item ) {
			if ( ! isset( $registered_item['integration'] ) ) {
				continue;
			}
			if ( strtoupper( $registered_item['integration'] ) !== $integration_code ) {
				continue;
			}
			$sentence = isset( $registered_item['select_option_name'] )
				? $registered_item['select_option_name']
				: '';
			if ( '' === $sentence ) {
				continue;
			}
			$items[] = $this->format_readable_sentence( $sentence );
		}

		return $items;
	}

	/**
	 * Format the readable sentence by replacing placeholders
	 *
	 * @param string $sentence
	 * @return string
	 */
	protected function format_readable_sentence( $sentence ) {
		// Replace {{text}} with "text"
		return preg_replace( '/\{\{([^}]+)\}\}/', '$1', $sentence );
	}

	/**
	 * Validate item type
	 *
	 * @param string $type
	 * @return void
	 * @throws Exception
	 */
	private function validate_item_type( $type ) {
		if ( 'actions' !== $type && 'triggers' !== $type ) {
			throw new Exception( 'Invalid item type' );
		}
	}
}
