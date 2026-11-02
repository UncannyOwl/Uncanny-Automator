<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library;

use Uncanny_Automator\App\Infrastructure\Template_Library\Template_Library_Client;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependencies_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Context;
use Uncanny_Automator\App\Integration_Catalog\Scope_Tag\License_Tag_Catalog;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use WP_Error;

/**
 * Projects the upstream template feed into the strict FE contract.
 *
 * The upstream entry carries trigger/action trees and license booleans;
 * this service strips everything down to the seven-key template shape:
 * license tags resolved via License_Tag_Catalog, integration codes
 * extracted from the item trees, and per-site usability collapsed into
 * the single `dependencies_met` boolean via the catalog's
 * setup-dependency resolution (plugin chains installed + apps
 * connected; tier/credits entitlements ride on `tags`, never here).
 */
final class Template_Feed_Service {

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Upstream client.
	 *
	 * @var Template_Library_Client
	 */
	private $client;

	/**
	 * Integration catalog query service.
	 *
	 * @var Integration_Query_Service
	 */
	private $query_service;

	/**
	 * Catalog dependency resolver (plugin-chain + account + license state).
	 *
	 * @var Dependencies_Resolver
	 */
	private $dependencies_resolver;

	/**
	 * Per-request memo of code => usable checks.
	 *
	 * @var array<string,bool>
	 */
	private $usable_memo = array();

	/**
	 * Lazy-default constructor injection (Rule 9c). The resolver default
	 * mirrors Integration_Scope_Resolver's own construction.
	 *
	 * @param Template_Library_Client|null   $client                Upstream client.
	 * @param Integration_Query_Service|null $query_service         Catalog query service.
	 * @param Dependencies_Resolver|null     $dependencies_resolver Catalog dependency resolver.
	 */
	public function __construct(
		?Template_Library_Client $client = null,
		?Integration_Query_Service $query_service = null,
		?Dependencies_Resolver $dependencies_resolver = null
	) {
		$this->client                = $client ?? Template_Library_Client::instance();
		$this->query_service         = $query_service ?? Integration_Query_Service::get_instance();
		$this->dependencies_resolver = $dependencies_resolver ?? new Dependencies_Resolver( new Dependency_Context() );
	}

	/**
	 * Shared-instance accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The enriched feed in the exact FE contract shape.
	 *
	 * @return array{templates: array<int,array<string,mixed>>, categories: array<int,array<string,mixed>>}|WP_Error
	 */
	public function get_feed() {
		$feed = $this->client->get_feed();
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		$templates = array();
		foreach ( (array) ( $feed['templates'] ?? array() ) as $entry ) {
			if ( is_array( $entry ) ) {
				$templates[] = $this->project_template( $entry );
			}
		}

		$categories = array();
		foreach ( (array) ( $feed['categories'] ?? array() ) as $category ) {
			if ( is_array( $category ) ) {
				$categories[] = $this->project_category( $category );
			}
		}

		return array(
			'templates'  => $templates,
			'categories' => $categories,
		);
	}

	/**
	 * Project one upstream entry to the seven-key template contract.
	 *
	 * @param array<string,mixed> $entry Upstream feed entry.
	 * @return array<string,mixed>
	 */
	private function project_template( array $entry ): array {
		$codes = $this->integration_codes( $entry );

		return array(
			'id'                => (int) ( $entry['id'] ?? 0 ),
			'title'             => (string) ( $entry['title'] ?? '' ),
			'description'       => (string) ( $entry['description'] ?? '' ),
			'tags'              => $this->license_tags( $entry ),
			'integration_codes' => $codes,
			'dependencies_met'  => $this->dependencies_met( $codes ),
			'priority'          => (int) ( $entry['priority'] ?? 0 ),
		);
	}

	/**
	 * License display tags for an upstream entry. Free templates get [].
	 *
	 * @param array<string,mixed> $entry Upstream feed entry.
	 * @return array<int,array<string,string>>
	 */
	private function license_tags( array $entry ): array {
		if ( ! empty( $entry['requires_elite'] ) ) {
			return array( License_Tag_Catalog::for_tier( 'pro-elite' ) );
		}
		if ( ! empty( $entry['requires_pro'] ) ) {
			return array( License_Tag_Catalog::for_tier( 'pro-basic' ) );
		}

		return array();
	}

	/**
	 * Unique integration codes from the entry's trigger + action items,
	 * first-seen order (triggers before actions).
	 *
	 * @param array<string,mixed> $entry Upstream feed entry.
	 * @return array<int,string>
	 */
	private function integration_codes( array $entry ): array {
		$codes = array();
		foreach ( array( 'triggers', 'actions' ) as $branch ) {
			$items = $entry[ $branch ]['items'] ?? array();
			foreach ( (array) $items as $item ) {
				$code = isset( $item['integration_code'] ) ? (string) $item['integration_code'] : '';
				if ( '' !== $code && ! in_array( $code, $codes, true ) ) {
					$codes[] = $code;
				}
			}
		}

		return $codes;
	}

	/**
	 * True when every integration code is usable on this site.
	 *
	 * @param array<int,string> $codes Integration codes.
	 * @return bool
	 */
	private function dependencies_met( array $codes ): bool {
		foreach ( $codes as $code ) {
			if ( ! $this->is_integration_usable( $code ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether one integration is usable on this site, per the catalog's
	 * setup-dependency resolution (plugin installation chains + app
	 * connection; license entitlements excluded — they ride on `tags`).
	 * Codes the catalog does not know are never usable.
	 *
	 * @param string $code Integration code (e.g. "WC").
	 * @return bool
	 */
	private function is_integration_usable( string $code ): bool {
		if ( array_key_exists( $code, $this->usable_memo ) ) {
			return $this->usable_memo[ $code ];
		}

		$integration = $this->query_service->get_integration( $code );
		if ( null === $integration ) {
			$this->usable_memo[ $code ] = false;
			return $this->usable_memo[ $code ];
		}

		$dependencies = $this->dependencies_resolver->resolve_integration_setup_dependencies( $integration );

		$this->usable_memo[ $code ] = ! empty( $dependencies['all_met'] );
		return $this->usable_memo[ $code ];
	}

	/**
	 * Project one upstream category to the five-key contract
	 * (`templates` renamed to `template_ids`, ints enforced).
	 *
	 * @param array<string,mixed> $category Upstream category entry.
	 * @return array<string,mixed>
	 */
	private function project_category( array $category ): array {
		return array(
			'id'           => (int) ( $category['id'] ?? 0 ),
			'name'         => (string) ( $category['name'] ?? '' ),
			'slug'         => (string) ( $category['slug'] ?? '' ),
			'count'        => (int) ( $category['count'] ?? 0 ),
			'template_ids' => array_map( 'intval', (array) ( $category['templates'] ?? array() ) ),
		);
	}
}
