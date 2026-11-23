<?php

namespace Uncanny_Automator\Integrations\Uncanny_Ceus;

/**
 * Trait Uncanny_Ceus_Requirements
 *
 * What the CEU award triggers state: Uncanny CEUs newer than 3.0.6, the first release that fires the award hook
 * they listen to.
 *
 * @package Uncanny_Automator
 *
 * @since 8.1.0
 */
trait Uncanny_Ceus_Requirements {

	/**
	 * The plugin a CEU award trigger states through set_plugin_details(): Uncanny CEUs 3.0.7 or newer.
	 *
	 * Only the literal, so the feed exporter can read it as text.
	 *
	 * @since 8.1.0
	 *
	 * @return array The plugin details.
	 */
	public static function award_hook_plugin_details(): array {
		return array(
			'plugin_file'       => 'uncanny-continuing-education-credits/uncanny-continuing-education-credits.php',
			'plugin_name'       => 'Uncanny CEUs',
			'distribution_type' => 'commercial',
			'min_version'       => '3.0.7',
		);
	}

	/**
	 * Whether the Uncanny CEUs that is loaded fires the award hook: newer than 3.0.6, from its own folder or a
	 * renamed one.
	 *
	 * @since 8.1.0
	 *
	 * @return bool|null True when a newer Uncanny CEUs than 3.0.6 is loaded, false when it is not.
	 */
	protected function is_plugin_compatible(): ?bool {
		return class_exists( '\\uncanny_ceu\\Utilities' ) && version_compare( \uncanny_ceu\Utilities::get_version(), '3.0.6', '>' );
	}
}
