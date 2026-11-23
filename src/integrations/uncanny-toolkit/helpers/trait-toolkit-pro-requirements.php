<?php

namespace Uncanny_Automator\Integrations\Uncanny_Toolkit;

/**
 * Trait Toolkit_Pro_Requirements
 *
 * What the Uncanny Toolkit items built on Toolkit Pro state: Toolkit Pro, which has no integration of its own,
 * and for the LearnDash items the LearnDash integration as well.
 *
 * @package Uncanny_Automator
 *
 * @since 8.1.0
 */
trait Toolkit_Pro_Requirements {

	/**
	 * The plugin an item that needs Toolkit Pro states through set_plugin_details().
	 *
	 * Only the literal, so the feed exporter can read it as text.
	 *
	 * @since 8.1.0
	 *
	 * @return array The plugin details.
	 */
	public static function toolkit_pro_plugin_details(): array {
		return array(
			'plugin_file'       => 'uncanny-toolkit-pro/uncanny-toolkit-pro.php',
			'plugin_name'       => 'Uncanny Toolkit Pro',
			'distribution_type' => 'commercial',
		);
	}

	/**
	 * The plugin and integration an item that needs Toolkit Pro and LearnDash states through set_plugin_details().
	 *
	 * Only the literal, so the feed exporter can read it as text.
	 *
	 * @since 8.1.0
	 *
	 * @return array The plugin details.
	 */
	public static function toolkit_pro_learndash_plugin_details(): array {
		return array(
			'plugin_file'          => 'uncanny-toolkit-pro/uncanny-toolkit-pro.php',
			'plugin_name'          => 'Uncanny Toolkit Pro',
			'distribution_type'    => 'commercial',
			'integration_required' => 'LD',
		);
	}

	/**
	 * Whether Toolkit Pro is loaded, from its own folder or a renamed one, and LearnDash too for an item that states
	 * the LearnDash integration.
	 *
	 * @since 8.1.0
	 *
	 * @return bool|null True when what the item needs is loaded, false when it is not.
	 */
	protected function is_plugin_compatible(): ?bool {
		return defined( 'UNCANNY_TOOLKIT_PRO_VERSION' )
			&& ( 'LD' !== $this->get_plugin_details()['integration_required'] || defined( 'LEARNDASH_VERSION' ) );
	}
}
