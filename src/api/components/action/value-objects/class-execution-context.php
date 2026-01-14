<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
/**
 * Execution Context Value Object
 *
 * Container for action execution context including user, fields, and
 * extensible additional data. Single parameter pattern prevents future
 * signature changes when adding new context data.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Action\Value_Objects;

use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Class Execution_Context
 *
 * @since 7.0.0
 */
class Execution_Context {

	/**
	 * User context.
	 *
	 * @since 7.0.0
	 * @var User_Context
	 */
	private $user_context;

	/**
	 * Action fields.
	 *
	 * @since 7.0.0
	 * @var Action_Fields
	 */
	private $fields;

	/**
	 * Additional context data for future extensibility.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private $additional;

	/**
	 * Constructor.
	 *
	 * @since 7.0.0
	 * @param User_Context  $user_context User context.
	 * @param Action_Fields $fields       Action fields.
	 * @param array         $additional   Additional context data.
	 */
	public function __construct(
		User_Context $user_context,
		Action_Fields $fields,
		array $additional = array()
	) {
		$this->user_context = $user_context;
		$this->fields       = $fields;
		$this->additional   = $additional;
	}

	/**
	 * Get user context.
	 *
	 * @since 7.0.0
	 * @return User_Context User context.
	 */
	public function user(): User_Context {
		return $this->user_context;
	}

	/**
	 * Get action fields.
	 *
	 * @since 7.0.0
	 * @return Action_Fields Action fields.
	 */
	public function fields(): Action_Fields {
		return $this->fields;
	}

	/**
	 * Get additional context value by key.
	 *
	 * @since 7.0.0
	 * @param string $key     Context key.
	 * @param mixed  $default Default value if key doesn't exist.
	 * @return mixed Context value or default.
	 */
	public function get( string $key, $default_value = null ) {
		return $this->additional[ $key ] ?? $default_value;
	}

	/**
	 * Check if additional context key exists.
	 *
	 * @since 7.0.0
	 * @param string $key Context key.
	 * @return bool True if key exists.
	 */
	public function has( string $key ): bool {
		return isset( $this->additional[ $key ] );
	}
}
