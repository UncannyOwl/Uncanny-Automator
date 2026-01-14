<?php
/**
 * Action Token Dependency Tracker.
 *
 * Tracks action token dependencies for background processing.
 * When an action uses another action's token, the source action
 * must not run in background mode.
 *
 * Migrated from Recipe_Post_Rest_Api::has_action_token().
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Services\Action\Utilities
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Action\Utilities;

use Uncanny_Automator\Api\Components\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Code;
use Uncanny_Automator\Background_Actions;

/**
 * Action Token Dependency Tracker Class.
 *
 * Scans action config for action tokens and updates the source action's
 * metadata to prevent background processing when dependencies exist.
 */
class Action_Token_Dependency_Tracker {

	/**
	 * Regex pattern for action tokens.
	 *
	 * Matches: {{ACTION_META:74046:CODE:FIELD}} or {{ACTION_FIELD:74046:CODE:FIELD}}
	 *
	 * @var string
	 */
	private const ACTION_TOKEN_PATTERN = '/{{ACTION_(FIELD|META):(\d+):([^:}]+):[^}]+}}/';

	/**
	 * Action registry for checking background processing.
	 *
	 * @var WP_Action_Registry
	 */
	private WP_Action_Registry $registry;

	/**
	 * Constructor.
	 *
	 * @param WP_Action_Registry|null $registry Optional registry instance for testing.
	 */
	public function __construct( ?WP_Action_Registry $registry = null ) {
		$this->registry = $registry ?? new WP_Action_Registry();
	}

	/**
	 * Track action token dependencies after config save.
	 *
	 * Scans the config for action tokens and marks source actions
	 * as dependencies if they use background processing.
	 *
	 * @param int   $action_id The action being updated.
	 * @param array $config    The saved config array.
	 *
	 * @return void
	 */
	public function track_dependencies( int $action_id, array $config ): void {
		$references = $this->extract_action_token_references( $config );

		if ( empty( $references ) ) {
			return;
		}

		$updated = array();

		foreach ( $references as $reference ) {
			$source_action_id = $reference['action_id'];
			$action_code      = $reference['action_code'];

			// Skip if already processed this action.
			if ( in_array( $source_action_id, $updated, true ) ) {
				continue;
			}

			// Only mark if source action has background processing.
			if ( ! $this->has_background_processing( $action_code ) ) {
				continue;
			}

			// Mark source action as a token dependency.
			update_post_meta(
				$source_action_id,
				Background_Actions::IS_USED_FOR_ACTION_TOKEN,
				$action_id
			);

			$updated[] = $source_action_id;
		}
	}

	/**
	 * Extract action token references from config values.
	 *
	 * @param array $config Config array to scan.
	 *
	 * @return array Array of references: [['action_id' => int, 'action_code' => string], ...]
	 */
	private function extract_action_token_references( array $config ): array {
		$references = array();

		foreach ( $config as $value ) {
			if ( ! is_string( $value ) ) {
				continue;
			}

			preg_match_all( self::ACTION_TOKEN_PATTERN, $value, $matches, PREG_SET_ORDER );

			foreach ( $matches as $match ) {
				$references[] = array(
					'action_id'   => (int) $match[2],
					'action_code' => $match[3],
				);
			}
		}

		return $references;
	}

	/**
	 * Check if an action has background processing enabled.
	 *
	 * @param string $action_code Action code to check.
	 *
	 * @return bool True if background processing is enabled.
	 */
	private function has_background_processing( string $action_code ): bool {
		try {
			$definition = $this->registry->get_action_definition(
				new Action_Code( $action_code )
			);

			return ! empty( $definition['background_processing'] );
		} catch ( \Exception $e ) {
			return false;
		}
	}
}
