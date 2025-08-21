<?php
namespace Uncanny_Automator\Services\Admin_Post\Routes\Pro_Auto_Install;

/**
 * Upgrader Skin.
 *
 * Custom upgrader skin for the pro auto install.
 */
class Silent_Upgrader_Skin extends \WP_Upgrader_Skin {

	/**
	 * Feedback.
	 *
	 * @param mixed $feedback The feedback.
	 * @param mixed $args The arguments.
	 * @return mixed
	 */
	public function feedback( $feedback, ...$args ) {
		// Show no feedback.
		return '';
	}
}
