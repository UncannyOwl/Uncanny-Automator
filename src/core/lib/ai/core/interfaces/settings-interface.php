<?php

declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Core\Interfaces;

/**
 * Settings interface for AI integration configuration.
 *
 * Handles WordPress settings page creation and output.
 * Used by AI providers to create standardized settings pages.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Core\Interfaces
 * @since 5.6
 */
interface Settings_Interface {

	/**
	 * Set properties for the settings page.
	 *
	 * Initializes the settings page configuration including
	 * form fields, validation rules, and display options.
	 *
	 * @return void
	 */
	public function set_properties(): void;

	/**
	 * Generate settings page output.
	 *
	 * Renders the HTML output for the settings page.
	 * Should include form fields, validation, and user feedback.
	 *
	 * @return void
	 */
	public function output(): void;
}
