<?php
/**
 * Services Bootstrap.
 *
 * Initializes API services and registers hooks.
 *
 * @since 7.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services;

use Uncanny_Automator\Api\Services\Field\Field_Sanitizer_Legacy_Hooks;

/**
 * Services Bootstrap Class.
 *
 * Central bootstrap class for all API services.
 * Initializes service hooks and legacy compatibility layers.
 *
 * @since 7.0
 */
class Services_Bootstrap {

	/**
	 * Initialize all API services.
	 *
	 * Registers hooks and initializes legacy compatibility layers.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_field_hooks();
	}

	/**
	 * Register field-related hooks.
	 *
	 * Initializes legacy field code compatibility hooks.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	private function register_field_hooks(): void {
		( new Field_Sanitizer_Legacy_Hooks() )->register();
	}
}
