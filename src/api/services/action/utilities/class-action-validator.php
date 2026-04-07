<?php
/**
 * Action Config Validator.
 *
 * @deprecated 7.1.0 Use {@see \Uncanny_Automator\Api\Services\Field\Utilities\Field_Validator} directly.
 *
 * Thin backwards-compatible wrapper that delegates to the unified Field_Validator.
 * Existing call-sites that instantiate Action_Validator continue to work unchanged.
 *
 * @since   7.0.0
 * @package Uncanny_Automator\Api\Services\Action\Utilities
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Action\Utilities;

use Uncanny_Automator\Api\Services\Field\Utilities\Field_Validator;

/**
 * Action Config Validator Class.
 *
 * @deprecated 7.1.0 Use Field_Validator instead.
 */
class Action_Validator extends Field_Validator {

	/**
	 * Validate action configuration against schema.
	 *
	 * @deprecated 7.1.0 Use Field_Validator::validate() with component_type='action'.
	 *
	 * @param string $action_code Action code.
	 * @param array  $config      Configuration to validate.
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	public function validate( string $action_code, array $config, string $component_type = 'action', bool $partial = false ) {
		return parent::validate( $action_code, $config, 'action', $partial );
	}
}
