<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store;

use Uncanny_Automator\App\Integration_Catalog\Dependency\Enums\Plugin_State;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Plugin\Plugin_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Services\Item_Availability_Provider;
use Uncanny_Automator\Services\Plugin\Info;

/**
 * Attaches to each trigger, action, condition and loop filter row the requirements the registration pass stored with
 * it, so every reader takes the answer from the store entry and nothing looks it up again.
 *
 * Runs on every build, after the discovery cache and the feed are read, so the answer is this request's and never
 * reaches a cache that outlives it. The stored answer is the only source for an item the site has code for: a row
 * whose item stored nothing carries no requirements, whatever the feed row held.
 *
 * An item only the feed lists has no stored answer. Its feed row names the Automator plugin it ships in and the
 * version that first carried it (`since`); when that plugin is active below that version, the row carries an
 * outdated answer for it, so the item reads locked with the Update row. Any other feed item carries none.
 *
 * Unlike {@see Plugin_State::work_out()}, which judges a third-party plugin against an item's own minimum, this
 * comparison reads an installed prerelease or build as its release (8.1.0-beta1 as 8.1.0): Automator's betas
 * carry the items of the release they lead to. Each plugin's header is read once per enricher, which the store
 * builds once per request.
 *
 * @since 8.1.0
 */
class Item_Requirements_Enricher {

	/**
	 * The row key the answer is attached under, as the registration pass stores it on a definition.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	private const PAYLOAD_KEY = 'requirements';

	/**
	 * The feed row key naming the Automator plugin the item ships in and the version that first carried it.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	private const SINCE_KEY = 'since';

	/**
	 * The keys a `since` block must carry, each a non-empty string.
	 *
	 * @since 8.1.0
	 *
	 * @var string[]
	 */
	private const SINCE_FIELDS = array( 'plugin_file', 'plugin_name', 'min_version' );

	/**
	 * A release version, as a plugin header carries it: dot-separated numbers.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	private const VERSION_PATTERN = '/^\d+(\.\d+)*$/';

	/**
	 * What starts a version's prerelease (`-beta1`) or build (`+20261114`) suffix.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	private const SUFFIX_SEPARATOR = '/[-+]/';

	/**
	 * Automator's own integration: the plugin a `since` names is an Automator plugin, so its row shows this
	 * integration's developer and icon.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	private const AUTOMATOR_INTEGRATION = 'UOA';

	/**
	 * Every plugin details key an answer carries, each empty, in the shape the registration pass stores.
	 *
	 * @since 8.1.0
	 *
	 * @var array<string,string>
	 */
	private const PLUGIN_DETAILS_DEFAULTS = array(
		'plugin_file'          => '',
		'plugin_name'          => '',
		'distribution_type'    => '',
		'plugin_required'      => '',
		'integration_required' => '',
		'min_version'          => '',
	);

	/**
	 * Reads the requirements stored with an item.
	 *
	 * @since 8.1.0
	 *
	 * @var Item_Availability_Provider
	 */
	private Item_Availability_Provider $provider;

	/**
	 * Reads an installed plugin's header version: `( string $plugin_file ): string`, '' when it carries none.
	 *
	 * @since 8.1.0
	 *
	 * @var \Closure
	 */
	private \Closure $installed_version;

	/**
	 * The release version of each plugin a `since` named, as installed and active; '' when it is not active or its
	 * header carries no version. Keyed by plugin file, for this enricher's life only, never a cache.
	 *
	 * @since 8.1.0
	 *
	 * @var array<string,string>
	 */
	private array $release_versions = array();

	/**
	 * Constructor.
	 *
	 * @since 8.1.0
	 *
	 * @param Item_Availability_Provider|null $provider          Optional provider (lazy default: singleton).
	 * @param callable|null                   $installed_version Optional header version reader,
	 *                                                           `( string $plugin_file ): string` (lazy default:
	 *                                                           the plugin header's Version).
	 */
	public function __construct( ?Item_Availability_Provider $provider = null, ?callable $installed_version = null ) {
		$this->provider          = $provider ?? Item_Availability_Provider::instance();
		$this->installed_version = null !== $installed_version
			? \Closure::fromCallable( $installed_version )
			: static function ( string $plugin_file ): string {
				return (string) ( Info::get_plugin_data( $plugin_file )['Version'] ?? '' );
			};
	}

	/**
	 * Attach each item's stored requirements to its normalized row, or, for an item only the feed lists, the
	 * outdated answer its `since` gives.
	 *
	 * @since 8.1.0
	 *
	 * @param string              $integration_code The owning integration code.
	 * @param string              $type             The item or block modifier type of every row.
	 * @param array<string,array> $items            Normalized rows keyed by item code.
	 *
	 * @return array<string,array> Same rows, each carrying its item's requirements when it has them.
	 */
	public function enrich( string $integration_code, string $type, array $items ): array {
		foreach ( array_keys( $items ) as $code ) {
			$requirements = $this->provider->requirements( $type, $integration_code, (string) $code )
				?? $this->feed_only_answer( $type, $integration_code, (string) $code, $items[ $code ][ self::SINCE_KEY ] ?? null );

			if ( null === $requirements ) {
				unset( $items[ $code ][ self::PAYLOAD_KEY ] );
				continue;
			}

			$items[ $code ][ self::PAYLOAD_KEY ] = $requirements;
		}

		return $items;
	}

	/**
	 * The answer for an item only the feed lists whose Automator plugin is active below the version that first
	 * carried it: not met, that plugin outdated, owned by Automator's own integration. The cheap checks run first:
	 * the `since` block, then whether the site has the item's code.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item or block modifier type.
	 * @param string $integration_code The owning integration code.
	 * @param string $code             The item code.
	 * @param mixed  $since            The feed row's `since`, as the feed sent it.
	 *
	 * @return array|null Null when `since` is not a valid block, the site has the item's code, or its plugin is not
	 *                    active or is new enough.
	 */
	private function feed_only_answer( string $type, string $integration_code, string $code, $since ): ?array {
		if ( ! $this->is_valid_since( $since ) || $this->provider->is_on_site( $type, $integration_code, $code ) ) {
			return null;
		}

		$release = $this->release_version( $since['plugin_file'] );

		if ( '' === $release || ! version_compare( $release, $since['min_version'], '<' ) ) {
			return null;
		}

		return array(
			'is_met'                      => false,
			'plugin_details'              => array_merge( self::PLUGIN_DETAILS_DEFAULTS, array_intersect_key( $since, array_flip( self::SINCE_FIELDS ) ) ),
			'plugin_state'                => Plugin_State::OUTDATED,
			'integration_met'             => true,
			'setup'                       => array(),
			'message'                     => '',
			Plugin_Resolver::PLUGIN_OWNER => self::AUTOMATOR_INTEGRATION,
		);
	}

	/**
	 * The release version of an active plugin: its header version without a prerelease or build suffix, read once
	 * per plugin file.
	 *
	 * @since 8.1.0
	 *
	 * @param string $plugin_file The plugin file.
	 *
	 * @return string '' when the plugin is not active or its header carries no version.
	 */
	private function release_version( string $plugin_file ): string {
		if ( ! isset( $this->release_versions[ $plugin_file ] ) ) {
			$installed = Info::is_plugin_active( $plugin_file ) ? (string) ( $this->installed_version )( $plugin_file ) : '';

			$this->release_versions[ $plugin_file ] = (string) preg_split( self::SUFFIX_SEPARATOR, $installed, 2 )[0];
		}

		return $this->release_versions[ $plugin_file ];
	}

	/**
	 * Whether a feed `since` is a block naming a plugin file, a plugin name and a release version.
	 *
	 * @since 8.1.0
	 *
	 * @param mixed $since The feed row's `since`.
	 *
	 * @return bool
	 */
	private function is_valid_since( $since ): bool {
		if ( ! is_array( $since ) ) {
			return false;
		}

		foreach ( self::SINCE_FIELDS as $field ) {
			if ( ! is_string( $since[ $field ] ?? null ) || '' === $since[ $field ] ) {
				return false;
			}
		}

		return 1 === preg_match( self::VERSION_PATTERN, $since['min_version'] );
	}
}
