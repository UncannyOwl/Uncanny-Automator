<?php
/**
 * Integration Store
 *
 * Builds Integration domain objects from feed data.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Services
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Services;

use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store\Catalog_Build_Report;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store\Integration_Builder;
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
	 * Per-request memo of built Integration objects, keyed by integration code.
	 *
	 * Building an Integration normalizes every item/modifier VO it owns —
	 * repeat lookups within one request (suggestion eligibility, availability
	 * resolution) must not pay that cost per call.
	 *
	 * @var array<string, \Uncanny_Automator\App\Integration_Catalog\Integration\Integration|null>
	 */
	private array $built_memo = array();

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
		// Injected collaborators change what a build produces — drop memoized results.
		$this->built_memo = array();
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
		// Injected collaborators change what a build produces — drop memoized results.
		$this->built_memo = array();
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
	 * A record that cannot build is skipped rather than allowed to break the
	 * catalog. Catalog_Build_Report collects what was skipped - here and in the
	 * normalizer - and writes one snapshot per build when its gate is on.
	 *
	 * Performance: Feed data is static-cached in Feed_Service.
	 *
	 * @return array<Integration> Array of Integration objects
	 */
	private function build_integrations_list(): array {
		$this->init();

		$json_data    = $this->feed_service->get_processed();
		$integrations = array();

		Catalog_Build_Report::start();

		foreach ( $json_data as $item ) {
			$code = $item['integration_id'] ?? '';
			if ( empty( $code ) ) {
				Catalog_Build_Report::integration_skipped( 'post:' . ( $item['post_id'] ?? 'unknown' ), 'missing integration_id' );
				continue;
			}

			try {
				$integrations[] = $this->builder->build_from_json( $code, $item );
			} catch ( \Throwable $e ) {
				// A partially valid record must not reach the UI; the report says why it did not.
				Catalog_Build_Report::integration_skipped( $code, $e->getMessage() );
			}
		}

		Catalog_Build_Report::write( count( $json_data ), count( $integrations ), $this->feed_service->get_active_feed() );

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
		if ( array_key_exists( $code, $this->built_memo ) ) {
			return $this->built_memo[ $code ];
		}

		$this->init();

		$item = $this->feed_service->find_in_processed( 'integration_id', $code );

		if ( null === $item ) {
			$this->built_memo[ $code ] = null;
			return $this->built_memo[ $code ];
		}

		$integration_code = $item['integration_id'] ?? $code;

		try {
			$this->built_memo[ $code ] = $this->builder->build_from_json( $integration_code, $item );
			return $this->built_memo[ $code ];
		} catch ( \Throwable $e ) {
			$this->built_memo[ $code ] = null;
			return $this->built_memo[ $code ];
		}
	}
}
