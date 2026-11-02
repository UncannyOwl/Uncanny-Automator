<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store;

use Uncanny_Automator\App\Integration_Catalog\Services\Item_Ancestor_Rule_Provider;

/**
 * Stamps each action row with the placement rules its action declares, so the
 * builder never offers a placement the backend would reject.
 *
 * Runs on every build from the live registry, so a cached row can never carry
 * a stale rule. When the action is registered in this request the registry
 * wins — it reflects the installed plugins; when it is not, whatever the feed
 * row carries stands.
 */
class Item_Ancestor_Rule_Enricher {

	/**
	 * Placement-rule reader.
	 *
	 * @var Item_Ancestor_Rule_Provider
	 */
	private Item_Ancestor_Rule_Provider $provider;

	/**
	 * Constructor.
	 *
	 * @param Item_Ancestor_Rule_Provider|null $provider Optional provider (lazy default: singleton).
	 */
	public function __construct( ?Item_Ancestor_Rule_Provider $provider = null ) {
		$this->provider = $provider ?? Item_Ancestor_Rule_Provider::instance();
	}

	/**
	 * Create an instance with explicit dependencies (primarily for tests).
	 *
	 * @param Item_Ancestor_Rule_Provider|null $provider Custom provider.
	 *
	 * @return self
	 */
	public static function create_with_dependencies( ?Item_Ancestor_Rule_Provider $provider = null ): self {
		return new self( $provider );
	}

	/**
	 * Apply the registry's placement rules to normalized action rows.
	 *
	 * @param string              $integration_code Owning integration, for the build report.
	 * @param array<string,array> $actions          Normalized action rows keyed by code.
	 *
	 * @return array<string,array> Same rows, with required_block_ancestors set or cleared for registered actions.
	 */
	public function enrich( string $integration_code, array $actions ): array {
		$key = Item_Ancestor_Rule_Provider::PAYLOAD_KEY;

		foreach ( array_keys( $actions ) as $code ) {
			$code = (string) $code;
			if ( ! $this->provider->is_registered( $code ) ) {
				continue;
			}

			$rules = $this->provider->get_rules(
				$code,
				static function ( string $action_code, string $reason ) use ( $integration_code ) {
					Catalog_Build_Report::item_skipped( $integration_code, $action_code, 'placement rule dropped: ' . $reason );
				}
			);

			if ( empty( $rules ) ) {
				unset( $actions[ $code ][ $key ] );
				continue;
			}

			$actions[ $code ][ $key ] = $rules;
		}

		return $actions;
	}
}
