<?php

namespace Uncanny_Automator\Integrations\Beaver_Builder;

/**
 * Trait Beaver_Builder_Requirements
 *
 * What the Beaver Builder form items state: Beaver Builder Pro, the edition that ships the Contact Form and
 * Subscribe Form modules. Each item's guard checks for its own module.
 *
 * @package Uncanny_Automator
 *
 * @since 8.1.0
 */
trait Beaver_Builder_Requirements {

	/**
	 * The plugin a form item states through set_plugin_details().
	 *
	 * Only the literal, so the feed exporter can read it as text.
	 *
	 * @since 8.1.0
	 *
	 * @return array The plugin details.
	 */
	public static function beaver_builder_pro_plugin_details(): array {
		return array(
			'plugin_file'       => 'bb-plugin/fl-builder.php',
			'plugin_name'       => 'Beaver Builder Pro',
			'distribution_type' => 'commercial',
		);
	}
}
