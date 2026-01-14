<?php
/**
 * Action Response Formatter.
 *
 * Handles formatting of action data for API responses.
 * Extracts response formatting logic from Action_Instance_Service for better separation of concerns.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services\Action\Helpers
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Action\Utilities;

/**
 * Action Response Formatter Class.
 *
 * Standardizes the structure of action data returned by the Action Service API.
 */
class Action_Response_Formatter {

	/**
	 * Format action response data.
	 *
	 * @param array $action_data Action data array.
	 * @return array Formatted response data.
	 */
	public function format( array $action_data ): array {
		return array(
			'action_id'                    => $action_data['action_id'],
			'action_code'                  => $action_data['action_code'],
			'integration'                  => $action_data['integration'],
			'user_type'                    => $action_data['user_type'],
			'recipe_id'                    => $action_data['recipe_id'],
			'sentence_human_readable'      => $action_data['sentence_human_readable'] ?? '',
			'sentence_human_readable_html' => $action_data['sentence_human_readable_html'] ?? '',
			'config'                       => $action_data['config'] ?? array(),
			'async'                        => $action_data['async'] ?? array(),
		);
	}
}
