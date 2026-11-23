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
 * Projects the upstream template feed (v2 cards) into the strict FE contract.
 *
 * A card already names its tier, integrations and block types; this
 * service turns the tier into a license tag (License_Tag_Catalog), collapses
 * per-site usability into the single `dependencies_met` boolean via the
 * catalog's setup-dependency resolution (plugin chains installed + apps
 * connected; tier/credits entitlements ride on `tags`, never here), and hides
 * cards whose `min_version` is above this site's Automator. Categories come
 * from the feed index, headed by the "All templates" entry the library's menu
 * expects, each listing the shown templates it holds.
 */
final class Template_Feed_Service {

	/**
	 * Slug of the "All templates" category the FE's menu and filter look for.
	 *
	 * @var string
	 */
	const ALL_CATEGORY_SLUG = 'all';

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
	 * This site's Automator version, compared with each card's min_version.
	 *
	 * @var string
	 */
	private $plugin_version;

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
	 * @param string|null                    $plugin_version        This site's Automator version.
	 */
	public function __construct(
		?Template_Library_Client $client = null,
		?Integration_Query_Service $query_service = null,
		?Dependencies_Resolver $dependencies_resolver = null,
		?string $plugin_version = null
	) {
		$this->plugin_version        = $plugin_version ?? (string) AUTOMATOR_PLUGIN_VERSION;
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

		$templates   = array();
		$in_category = array();
		foreach ( $feed['cards'] as $card ) {
			if ( ! $this->is_for_this_version( $card ) ) {
				continue;
			}
			$templates[] = $this->project_template( $card );
			foreach ( (array) ( $card['categories'] ?? array() ) as $category_id ) {
				$in_category[ (int) $category_id ][] = (int) ( $card['id'] ?? 0 );
			}
		}

		$categories = array(
			array(
				'id'           => -1,
				'name'         => esc_html_x( 'All templates', 'Template library', 'uncanny-automator' ),
				'slug'         => self::ALL_CATEGORY_SLUG,
				'count'        => count( $templates ),
				'template_ids' => array(),
			),
		);
		foreach ( $feed['categories'] as $category ) {
			$categories[] = $this->project_category( $category, $in_category[ (int) ( $category['id'] ?? 0 ) ] ?? array() );
		}

		return array(
			'templates'  => $templates,
			'categories' => $categories,
		);
	}

	/**
	 * Whether a card's template works with this site's Automator (no min_version, or one at or below it).
	 *
	 * @param array<string,mixed> $card Feed card.
	 * @return bool
	 */
	private function is_for_this_version( array $card ): bool {
		$min_version = (string) ( $card['min_version'] ?? '' );

		return '' === $min_version || version_compare( $this->plugin_version, $min_version, '>=' );
	}

	/**
	 * Project one card to the template contract.
	 *
	 * @param array<string,mixed> $card Feed card.
	 * @return array<string,mixed>
	 */
	private function project_template( array $card ): array {
		$codes = array_values( array_map( 'strval', (array) ( $card['integrations'] ?? array() ) ) );

		return array(
			'id'                => (int) ( $card['id'] ?? 0 ),
			'title'             => (string) ( $card['title'] ?? '' ),
			'description'       => (string) ( $card['description'] ?? '' ),
			'tags'              => $this->license_tags( (string) ( $card['tier'] ?? 'lite' ) ),
			'integration_codes' => $codes,
			'dependencies_met'  => $this->dependencies_met( $codes ),
			'priority'          => (int) ( $card['priority'] ?? 0 ),
			'blocks'            => array_values( array_map( 'strval', (array) ( $card['blocks'] ?? array() ) ) ),
		);
	}

	/**
	 * License display tags for a card's tier. Free (`lite`) templates get [].
	 *
	 * @param string $tier lite, pro-basic, pro-plus or pro-elite.
	 * @return array<int,array<string,string>>
	 */
	private function license_tags( string $tier ): array {
		$tag = License_Tag_Catalog::for_tier( $tier );

		return null === $tag ? array() : array( $tag );
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
	 * Project one index category to the five-key contract, with the shown templates it holds.
	 *
	 * @param array<string,mixed> $category     Index category.
	 * @param array<int,int>      $template_ids Shown templates in it.
	 * @return array<string,mixed>
	 */
	private function project_category( array $category, array $template_ids ): array {
		return array(
			'id'           => (int) ( $category['id'] ?? 0 ),
			'name'         => (string) ( $category['name'] ?? '' ),
			'slug'         => (string) ( $category['slug'] ?? '' ),
			'count'        => count( $template_ids ),
			'template_ids' => $template_ids,
		);
	}
}
