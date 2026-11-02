<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Value_Objects;

use InvalidArgumentException;

/**
 * A single addon product as published by the upstream addons feed.
 *
 * Immutable and WordPress-free: the feed row is validated once at
 * construction and every consumer reads the getters. Request-time state
 * (installed / active / update available) is not held here; it belongs to
 * Addon_Status_Service, which resolves it per request.
 */
final class Addon {

	/**
	 * Basic tier slug.
	 *
	 * @var string
	 */
	const TIER_BASIC = 'basic';

	/**
	 * Plus tier slug.
	 *
	 * @var string
	 */
	const TIER_PLUS = 'plus';

	/**
	 * Elite tier slug.
	 *
	 * @var string
	 */
	const TIER_ELITE = 'elite';

	/**
	 * Upstream product id.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Constant prefix the addon plugin ships (e.g. UAEI).
	 *
	 * @var string
	 */
	private string $constant_key;

	/**
	 * Product name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Icon code shown on the card.
	 *
	 * @var string
	 */
	private string $icon;

	/**
	 * Marketing blurb shown on the card.
	 *
	 * @var string
	 */
	private string $short_description;

	/**
	 * Plan tiers this addon is included in (cumulative).
	 *
	 * @var array<int,string>
	 */
	private array $plans;

	/**
	 * Latest published version.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Minimum WordPress version.
	 *
	 * @var string
	 */
	private string $requires_wp;

	/**
	 * Upstream last-updated timestamp, as published.
	 *
	 * @var string
	 */
	private string $last_updated;

	/**
	 * Changelog URL.
	 *
	 * @var string
	 */
	private string $changelog_url;

	/**
	 * Product page URL.
	 *
	 * @var string
	 */
	private string $product_page;

	/**
	 * Automator settings tab the addon registers, when it has one.
	 *
	 * @var string
	 */
	private string $settings_tab;

	/**
	 * Construct from validated feed values.
	 *
	 * @param int               $id                Upstream product id.
	 * @param string            $constant_key      Constant prefix.
	 * @param string            $name              Product name.
	 * @param string            $icon              Icon code.
	 * @param string            $short_description Marketing blurb.
	 * @param array<int,string> $plans             Included tiers.
	 * @param string            $version           Latest version.
	 * @param string            $requires_wp       Minimum WordPress version.
	 * @param string            $last_updated      Upstream last-updated timestamp.
	 * @param string            $changelog_url     Changelog URL.
	 * @param string            $product_page      Product page URL.
	 * @param string            $settings_tab      Settings tab slug.
	 */
	private function __construct(
		int $id,
		string $constant_key,
		string $name,
		string $icon,
		string $short_description,
		array $plans,
		string $version,
		string $requires_wp,
		string $last_updated,
		string $changelog_url,
		string $product_page,
		string $settings_tab
	) {
		$this->id                = $id;
		$this->constant_key      = $constant_key;
		$this->name              = $name;
		$this->icon              = $icon;
		$this->short_description = $short_description;
		$this->plans             = $plans;
		$this->version           = $version;
		$this->requires_wp       = $requires_wp;
		$this->last_updated      = $last_updated;
		$this->changelog_url     = $changelog_url;
		$this->product_page      = $product_page;
		$this->settings_tab      = $settings_tab;
	}

	/**
	 * Build from a raw upstream feed row.
	 *
	 * @param array<string,mixed> $row Raw feed row.
	 *
	 * @return self
	 * @throws InvalidArgumentException When the row cannot describe an addon.
	 */
	public static function from_feed_array( array $row ): self {

		$id    = isset( $row['id'] ) ? (int) $row['id'] : 0;
		$name  = trim( self::string_value( $row, 'name' ) );
		$plans = isset( $row['plans'] ) && is_array( $row['plans'] ) ? array_values( $row['plans'] ) : array();

		if ( $id < 1 ) {
			throw new InvalidArgumentException( 'Addon id must be a positive integer.' );
		}

		if ( '' === $name ) {
			throw new InvalidArgumentException( 'Addon name is required.' );
		}

		// A tier this release does not know is dropped rather than fatal: it
		// matches no list, so the addon still renders in the lists it does
		// belong to.
		$plans = array_values(
			array_intersect(
				array_map( 'strval', array_filter( $plans, 'is_scalar' ) ),
				array( self::TIER_BASIC, self::TIER_PLUS, self::TIER_ELITE )
			)
		);

		if ( empty( $plans ) ) {
			throw new InvalidArgumentException( 'Addon plans must include at least one of basic/plus/elite.' );
		}

		return new self(
			$id,
			self::string_value( $row, 'key' ),
			$name,
			self::string_value( $row, 'icon' ),
			self::string_value( $row, 'short_description' ),
			$plans,
			self::string_value( $row, 'stable_tag' ),
			self::string_value( $row, 'requires' ),
			self::string_value( $row, 'last_updated' ),
			self::string_value( $row, 'changelog_url' ),
			self::string_value( $row, 'product_page' ),
			self::string_value( $row, 'tab' )
		);
	}

	/**
	 * Read an optional scalar feed key as a string.
	 *
	 * @param array<string,mixed> $row Raw feed row.
	 * @param string              $key Feed key.
	 *
	 * @return string
	 */
	private static function string_value( array $row, string $key ): string {
		return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? (string) $row[ $key ] : '';
	}

	/**
	 * Upstream product id.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * Constant prefix the addon plugin ships.
	 *
	 * @return string
	 */
	public function constant_key(): string {
		return $this->constant_key;
	}

	/**
	 * Product name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Icon code shown on the card.
	 *
	 * @return string
	 */
	public function icon(): string {
		return $this->icon;
	}

	/**
	 * Marketing blurb shown on the card.
	 *
	 * @return string
	 */
	public function short_description(): string {
		return $this->short_description;
	}

	/**
	 * Plan tiers this addon is included in.
	 *
	 * @return array<int,string>
	 */
	public function plans(): array {
		return $this->plans;
	}

	/**
	 * Latest published version.
	 *
	 * @return string
	 */
	public function version(): string {
		return $this->version;
	}

	/**
	 * Minimum WordPress version.
	 *
	 * @return string
	 */
	public function requires_wp(): string {
		return $this->requires_wp;
	}

	/**
	 * Upstream last-updated timestamp, as published.
	 *
	 * @return string
	 */
	public function last_updated(): string {
		return $this->last_updated;
	}

	/**
	 * Changelog URL.
	 *
	 * @return string
	 */
	public function changelog_url(): string {
		return $this->changelog_url;
	}

	/**
	 * Product page URL.
	 *
	 * @return string
	 */
	public function product_page(): string {
		return $this->product_page;
	}

	/**
	 * Automator settings tab the addon registers.
	 *
	 * @return string
	 */
	public function settings_tab(): string {
		return $this->settings_tab;
	}

	/**
	 * Whether only the Elite tier includes this addon.
	 *
	 * @return bool
	 */
	public function requires_elite(): bool {
		return 1 === count( $this->plans ) && in_array( self::TIER_ELITE, $this->plans, true );
	}

	/**
	 * The tier a site must hold to install this addon.
	 *
	 * @return string
	 */
	public function required_tier(): string {

		// The lowest tier that includes it, so the gate agrees with
		// includes_plan(): a row listing basic is available on basic.
		if ( in_array( self::TIER_BASIC, $this->plans, true ) ) {
			return self::TIER_BASIC;
		}

		if ( in_array( self::TIER_PLUS, $this->plans, true ) ) {
			return self::TIER_PLUS;
		}

		return self::TIER_ELITE;
	}

	/**
	 * Whether the given tier includes this addon.
	 *
	 * @param string $tier Tier slug.
	 *
	 * @return bool
	 */
	public function includes_plan( string $tier ): bool {
		return in_array( $tier, $this->plans, true );
	}
}
