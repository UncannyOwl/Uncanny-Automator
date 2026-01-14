<?php
/**
 * Agent Tools Interface
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\Api\Components\Action\Value_Objects\Execution_Context;

/**
 * Agent Tools Interface for AI-executable actions.
 *
 * Actions implementing this interface can be executed programmatically
 * by AI agents with deterministic success/failure results.
 *
 * @since 7.0.0
 */
interface Agent_Tools {

	/**
	 * Execute the action with given execution context.
	 *
	 * @since 7.0.0
	 *
	 * @param Execution_Context $context The execution context containing user, fields, and additional data.
	 * @return Agent_Response_Context Execution result with success/failure status.
	 */
	public function execute( Execution_Context $context ): Agent_Response_Context;
}
