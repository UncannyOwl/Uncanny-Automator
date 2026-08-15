<?php
/**
 * Automator feature states.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Domain;

/**
 * Stores the visibility of each feature in the feature policy.
 */
final class Feature_State {

	public const AGENT_SETTINGS_TAB          = 'agent_settings_tab';
	public const PAGE_BUILDER_SETTINGS_TAB   = 'page_builder_settings_tab';
	public const PAGE_BUILDER_MENU           = 'page_builder_menu';
	public const AGENT_LAUNCHER_TAB          = 'agent_launcher_tab';
	public const AGENT_LAUNCHER_TOP_BAR_LINK = 'agent_launcher_top_bar_link';
	public const SETUP_WIZARD                = 'setup_wizard';

	public const VISIBLE = 'visible';
	public const HIDDEN  = 'hidden';

	/**
	 * Feature visibility values.
	 *
	 * @var array<string,string>
	 */
	private array $visibility;

	/**
	 * Create the feature state from its visible features.
	 *
	 * @param string[] $visible_features Visible features.
	 */
	private function __construct( array $visible_features ) {
		$unknown_features = array_diff( $visible_features, self::features() );

		if ( ! empty( $unknown_features ) ) {
			throw new \InvalidArgumentException( 'Unknown feature in Automator feature state.' );
		}

		$this->visibility = array();

		foreach ( self::features() as $feature ) {
			$this->visibility[ $feature ] = in_array( $feature, $visible_features, true )
				? self::VISIBLE
				: self::HIDDEN;
		}
	}

	/**
	 * Create the feature state from its visible features.
	 *
	 * @param string[] $visible_features Visible features.
	 *
	 * @return self
	 */
	public static function from_visible_features( array $visible_features ): self {
		return new self( $visible_features );
	}

	/**
	 * Create a fail-closed state where every feature is hidden.
	 *
	 * @return self
	 */
	public static function all_hidden(): self {
		return new self( array() );
	}

	/**
	 * Get all features in the release matrix.
	 *
	 * @return string[]
	 */
	public static function features(): array {
		return array(
			self::AGENT_SETTINGS_TAB,
			self::PAGE_BUILDER_SETTINGS_TAB,
			self::PAGE_BUILDER_MENU,
			self::AGENT_LAUNCHER_TAB,
			self::AGENT_LAUNCHER_TOP_BAR_LINK,
			self::SETUP_WIZARD,
		);
	}

	/**
	 * Get the visibility of one feature.
	 *
	 * @param string $feature Feature from the release matrix.
	 *
	 * @return string
	 */
	public function visibility_of( string $feature ): string {
		if ( ! array_key_exists( $feature, $this->visibility ) ) {
			throw new \InvalidArgumentException( 'Unknown feature in Automator feature state.' );
		}

		return $this->visibility[ $feature ];
	}

	/**
	 * Determine if one feature is visible.
	 *
	 * @param string $feature Feature from the release matrix.
	 *
	 * @return bool
	 */
	public function is_visible( string $feature ): bool {
		return self::VISIBLE === $this->visibility_of( $feature );
	}

	/**
	 * Get all feature visibility values.
	 *
	 * @return array<string,string>
	 */
	public function to_array(): array {
		return $this->visibility;
	}
}
