<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

/**
 * Whether Uncanny Automator Pro is loaded on this request, and which version.
 *
 * Pro defines AUTOMATOR_PRO_PLUGIN_VERSION when it loads, whatever its version; the runner records both when a block
 * finds no processor, because Pro 7.x loads on an 8.0 Free but registers none.
 *
 * @since 8.0.0
 */
final class Pro_Plugin_Presence {

	/**
	 * Whether Pro is loaded.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' );
	}

	/**
	 * Pro's version, or an empty string when Pro is not loaded.
	 *
	 * @return string
	 */
	public function version(): string {
		return $this->is_active() ? (string) constant( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) : '';
	}
}
