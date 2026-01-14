<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
/**
 * Context Builder Factory
 *
 * Fluent builder for constructing Execution_Context instances.
 * Validates required fields and allows extensible additional data.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Action\Factories;

use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Fields;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Execution_Context;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;

/**
 * Class Context_Builder
 *
 * @since 7.0.0
 */
class Context_Builder {

	/**
	 * User context.
	 *
	 * @since 7.0.0
	 * @var User_Context|null
	 */
	private $user_context = null;

	/**
	 * Action fields.
	 *
	 * @since 7.0.0
	 * @var Action_Fields|null
	 */
	private $fields = null;

	/**
	 * Additional context data.
	 *
	 * @since 7.0.0
	 * @var array
	 */
	private $additional = array();

	/**
	 * Set user context.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context User context.
	 * @return self
	 */
	public function with_user( User_Context $user_context ): self {
		$this->user_context = $user_context;
		return $this;
	}

	/**
	 * Set action fields.
	 *
	 * @since 7.0.0
	 * @param Action_Fields $fields Action fields.
	 * @return self
	 */
	public function with_fields( Action_Fields $fields ): self {
		$this->fields = $fields;
		return $this;
	}

	/**
	 * Add additional context data.
	 *
	 * @since 7.0.0
	 * @param string $key   Context key.
	 * @param mixed  $value Context value.
	 * @return self
	 */
	public function with( string $key, $value ): self {
		$this->additional[ $key ] = $value;
		return $this;
	}

	/**
	 * Build the Execution_Context.
	 *
	 * @since 7.0.0
	 * @return Execution_Context
	 * @throws \InvalidArgumentException If required fields are missing.
	 */
	public function build(): Execution_Context {
		if ( ! $this->user_context ) {
			throw new \InvalidArgumentException( 'User context is required to build Execution_Context' );
		}
		if ( ! $this->fields ) {
			throw new \InvalidArgumentException( 'Action fields are required to build Execution_Context' );
		}
		return new Execution_Context( $this->user_context, $this->fields, $this->additional );
	}
}
