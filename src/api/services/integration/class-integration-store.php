<?php
/**
 * Integration Store
 *
 * Builds Integration domain objects from feed data.
 *
 * @package Uncanny_Automator\Api\Services\Integration
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Services\Integration\Utilities\Store\Integration_Builder;
use Uncanny_Automator\Traits\Singleton;

/**
 * Store for building Integration objects from processed feed data.
 *
 * @since 7.0.0
 */
class Integration_Store {

	use Singleton;

	/**
	 * Feed service for data operations.
	 *
	 * @var Integration_Feed_Service|null
	 */
	private $feed_service = null;

	/**
	 * Builder utility for creating Integration objects.
	 *
	 * @var Integration_Builder|null
	 */
	private $builder = null;

	/**
	 * Initialize dependencies lazily.
	 *
	 * Performance: Only instantiates services when first needed.
	 *
	 * @return void
	 */
	private function init(): void {
		if ( null === $this->feed_service ) {
			$this->feed_service = Integration_Feed_Service::get_instance();
			$this->builder      = new Integration_Builder();
		}
	}

	/**
	 * Set feed service.
	 *
	 * @param Integration_Feed_Service $feed_service Feed service instance
	 *
	 * @return void
	 */
	public function set_feed_service( Integration_Feed_Service $feed_service ): void {
		$this->feed_service = $feed_service;
	}

	/**
	 * Set builder.
	 *
	 * @param Integration_Builder $builder Builder instance
	 *
	 * @return void
	 */
	public function set_builder( Integration_Builder $builder ): void {
		$this->builder = $builder;
	}

	/**
	 * Get all integration data.
	 *
	 * @return array<Integration> Array of Integration objects
	 */
	public function get_all(): array {
		return $this->build_integrations_list();
	}

	/**
	 * Get all integration data formatted for REST API response.
	 *
	 * @return array<string, array> Integration data arrays keyed by code (without plugin_details)
	 */
	public function get_all_to_rest(): array {
		$integrations = $this->build_integrations_list();
		$result       = array();

		foreach ( $integrations as $integration ) {
			$code            = $integration->get_code()->get_value();
			$result[ $code ] = $integration->to_rest();
		}

		return $result;
	}

	/**
	 * Build integrations list from processed feed data.
	 *
	 * Performance: Feed data is static-cached in Feed_Service.
	 *
	 * @return array<Integration> Array of Integration objects
	 */
	private function build_integrations_list(): array {
		$this->init();

		$json_data    = $this->feed_service->get_processed();
		$integrations = array();

		foreach ( $json_data as $item ) {
			$code = $item['integration_id'] ?? '';
			if ( empty( $code ) ) {
				continue;
			}

			try {
				$integration    = $this->builder->build_from_json( $code, $item );
				$integrations[] = $integration;
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Silently skip invalid integrations.
			}
		}

		return $integrations;
	}

	/**
	 * Get a single integration by code.
	 *
	 * More efficient than get_all() when you only need one integration.
	 *
	 * @param string $code Integration code (e.g., 'ACTIVE_CAMPAIGN', 'WC')
	 *
	 * @return Integration|null Integration object or null if not found
	 */
	public function get_by_code( string $code ): ?Integration {
		$this->init();

		$item = $this->feed_service->find_in_processed( 'integration_id', $code );

		if ( null === $item ) {
			return null;
		}

		$integration_code = $item['integration_id'] ?? $code;

		try {
			return $this->builder->build_from_json( $integration_code, $item );
		} catch ( \Exception $e ) {
			return null;
		}
	}
}
