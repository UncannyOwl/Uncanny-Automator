<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Value_Objects;

use InvalidArgumentException;

/**
 * The license state the AddOns page renders, as one of ten scenarios.
 *
 * These ids are the frontend's LicenseScenarioID vocabulary and the key the
 * notice and CTA tables are looked up by, so the derivation lives here and
 * nowhere else. WordPress-free: callers pass the state in.
 */
final class Summary_Scenario {

	/**
	 * Pro is not on disk.
	 *
	 * @var string
	 */
	const PRO_NOT_INSTALLED = 'pro-not-installed';

	/**
	 * Pro is installed but not loaded.
	 *
	 * @var string
	 */
	const PRO_DEACTIVATED = 'pro-installed-but-deactivated';

	/**
	 * Valid license entitling the Basic tier.
	 *
	 * @var string
	 */
	const ACTIVE_BASIC = 'pro-installed-license-active-pro-basic';

	/**
	 * Valid license entitling the Plus tier.
	 *
	 * @var string
	 */
	const ACTIVE_PLUS = 'pro-installed-license-active-pro-plus';

	/**
	 * Valid license entitling the Elite tier.
	 *
	 * @var string
	 */
	const ACTIVE_ELITE = 'pro-installed-license-active-pro-elite';

	/**
	 * License key exists but was never activated.
	 *
	 * @var string
	 */
	const INACTIVE = 'pro-installed-license-inactive';

	/**
	 * License term has lapsed.
	 *
	 * @var string
	 */
	const EXPIRED = 'pro-installed-license-expired';

	/**
	 * License key was revoked.
	 *
	 * @var string
	 */
	const DISABLED = 'pro-installed-license-disabled';

	/**
	 * License is valid but not activated for this site.
	 *
	 * @var string
	 */
	const SITE_INACTIVE = 'pro-installed-license-site-inactive';

	/**
	 * Anything else the store reports.
	 *
	 * @var string
	 */
	const INVALID = 'pro-installed-license-invalid';

	/**
	 * The resolved scenario id.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Construct from a known id.
	 *
	 * @param string $id Scenario id.
	 */
	private function __construct( string $id ) {
		$this->id = $id;
	}

	/**
	 * Build from a known scenario id.
	 *
	 * @param string $id Scenario id.
	 *
	 * @return self
	 * @throws InvalidArgumentException When the id is not one of the ten.
	 */
	public static function from_id( string $id ): self {

		if ( ! in_array( $id, self::all(), true ) ) {
			throw new InvalidArgumentException( 'Unknown license summary scenario id.' );
		}

		return new self( $id );
	}

	/**
	 * Derive the scenario from the current license state.
	 *
	 * @param bool   $pro_installed Whether the Pro plugin is on disk.
	 * @param bool   $pro_active    Whether the Pro plugin is loaded.
	 * @param string $status        License status as reported by the store.
	 * @param string $entitled_tier Tier the license entitles (basic|plus|elite).
	 *
	 * @return self
	 */
	public static function from_state( bool $pro_installed, bool $pro_active, string $status, string $entitled_tier ): self {

		if ( ! $pro_installed ) {
			return new self( self::PRO_NOT_INSTALLED );
		}

		if ( ! $pro_active ) {
			return new self( self::PRO_DEACTIVATED );
		}

		if ( 'valid' === $status ) {
			switch ( $entitled_tier ) {
				case Addon::TIER_ELITE:
					return new self( self::ACTIVE_ELITE );
				case Addon::TIER_PLUS:
					return new self( self::ACTIVE_PLUS );
				default:
					return new self( self::ACTIVE_BASIC );
			}
		}

		switch ( $status ) {
			case 'inactive':
				return new self( self::INACTIVE );
			case 'expired':
				return new self( self::EXPIRED );
			case 'disabled':
				return new self( self::DISABLED );
			case 'site_inactive':
				return new self( self::SITE_INACTIVE );
			default:
				return new self( self::INVALID );
		}
	}

	/**
	 * The scenario id.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * Whether the license is valid and entitling a Pro tier.
	 *
	 * @return bool
	 */
	public function is_active_license(): bool {
		return in_array(
			$this->id,
			array( self::ACTIVE_BASIC, self::ACTIVE_PLUS, self::ACTIVE_ELITE ),
			true
		);
	}

	/**
	 * Whether the license itself is what needs attention.
	 *
	 * @return bool
	 */
	public function needs_license_fix(): bool {
		return in_array(
			$this->id,
			array( self::INACTIVE, self::EXPIRED, self::DISABLED, self::SITE_INACTIVE, self::INVALID ),
			true
		);
	}

	/**
	 * Every scenario id.
	 *
	 * @return array<int,string>
	 */
	public static function all(): array {
		return array(
			self::PRO_NOT_INSTALLED,
			self::PRO_DEACTIVATED,
			self::ACTIVE_BASIC,
			self::ACTIVE_PLUS,
			self::ACTIVE_ELITE,
			self::INACTIVE,
			self::EXPIRED,
			self::DISABLED,
			self::SITE_INACTIVE,
			self::INVALID,
		);
	}
}
