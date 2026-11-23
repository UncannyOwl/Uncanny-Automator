<?php

namespace Uncanny_Automator\Integrations\Wpjm;

/**
 * Trait Wpjm_Requirements
 *
 * What the WP Job Manager items built on Resume Manager state: the Resume Manager add-on, which has no integration
 * of its own.
 *
 * @package Uncanny_Automator
 *
 * @since 8.1.0
 */
trait Wpjm_Requirements {

	/**
	 * The plugin an item that needs Resume Manager states through set_plugin_details().
	 *
	 * Only the literal, so the feed exporter can read it as text.
	 *
	 * @since 8.1.0
	 *
	 * @return array The plugin details.
	 */
	public static function resume_manager_plugin_details(): array {
		return array(
			'plugin_file'       => 'wp-job-manager-resumes/wp-job-manager-resumes.php',
			'plugin_name'       => 'Resume Manager',
			'distribution_type' => 'commercial',
		);
	}

	/**
	 * Whether Resume Manager is loaded, from its own folder or a renamed one.
	 *
	 * @since 8.1.0
	 *
	 * @return bool|null True when Resume Manager is loaded, false when it is not.
	 */
	protected function is_plugin_compatible(): ?bool {
		return function_exists( 'get_resume_files' );
	}
}
