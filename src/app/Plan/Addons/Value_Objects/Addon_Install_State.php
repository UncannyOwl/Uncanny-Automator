<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Value_Objects;

/**
 * What this site currently has of an addon, and what that means for its card.
 *
 * Always built for the request that renders it — never cached, never stored
 * alongside feed data.
 */
final class Addon_Install_State {

	/**
	 * The license does not entitle this addon.
	 *
	 * @var string
	 */
	const SCENARIO_NO_ACCESS = 'no-access';

	/**
	 * Entitled but the plugin is not on disk.
	 *
	 * @var string
	 */
	const SCENARIO_NOT_INSTALLED = 'has-access-addon-not-installed';

	/**
	 * Installed but not activated.
	 *
	 * @var string
	 */
	const SCENARIO_NOT_ACTIVE = 'has-access-addon-not-active';

	/**
	 * Active with a newer version published.
	 *
	 * @var string
	 */
	const SCENARIO_UPDATE_AVAILABLE = 'has-access-addon-update-available';

	/**
	 * Active and current.
	 *
	 * @var string
	 */
	const SCENARIO_ACTIVE = 'has-access-addon-active';

	/**
	 * Whether the plugin is on disk.
	 *
	 * @var bool
	 */
	private bool $is_installed;

	/**
	 * Whether the plugin is active.
	 *
	 * @var bool
	 */
	private bool $is_active;

	/**
	 * Whether a newer version is published.
	 *
	 * @var bool
	 */
	private bool $is_update_available;

	/**
	 * Construct from resolved state.
	 *
	 * @param bool $is_installed       Whether the plugin is on disk.
	 * @param bool $is_active          Whether the plugin is active.
	 * @param bool $is_update_available Whether a newer version is published.
	 */
	public function __construct( bool $is_installed, bool $is_active, bool $is_update_available ) {
		$this->is_installed        = $is_installed;
		$this->is_active           = $is_active;
		$this->is_update_available = $is_update_available;
	}

	/**
	 * Whether the plugin is on disk.
	 *
	 * @return bool
	 */
	public function is_installed(): bool {
		return $this->is_installed;
	}

	/**
	 * Whether the plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->is_active;
	}

	/**
	 * Whether a newer version is published.
	 *
	 * @return bool
	 */
	public function is_update_available(): bool {
		return $this->is_update_available;
	}

	/**
	 * The scenario the addon card renders for.
	 *
	 * @param bool $has_access Whether the license entitles this addon.
	 *
	 * @return string
	 */
	public function card_scenario( bool $has_access ): string {

		if ( ! $has_access ) {
			return self::SCENARIO_NO_ACCESS;
		}

		if ( ! $this->is_installed ) {
			return self::SCENARIO_NOT_INSTALLED;
		}

		if ( ! $this->is_active ) {
			return self::SCENARIO_NOT_ACTIVE;
		}

		if ( $this->is_update_available ) {
			return self::SCENARIO_UPDATE_AVAILABLE;
		}

		return self::SCENARIO_ACTIVE;
	}
}
