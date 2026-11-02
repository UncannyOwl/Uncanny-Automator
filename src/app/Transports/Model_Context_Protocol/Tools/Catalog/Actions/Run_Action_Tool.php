<?php
/**
 * Run action tool.
 *
 * Runs a single action outside any recipe through Action_Executor. Split from
 * the former consolidated `execute` tool (target=action) so action_code is a
 * schema-level requirement instead of a conditional one.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Actions;

use Uncanny_Automator\App\Application\Sub_Tooling\Action_Executor;
use Uncanny_Automator\App\Recipe_Builder\Shared\Security\Security;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Account_Access_Guard;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Account_Access_Refused;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Action_Availability_Policy;

/**
 * Run Action Tool.
 *
 * Requires action_code. Runs synchronously and returns the action's own result.
 */
class Run_Action_Tool extends Abstract_MCP_Tool {

	/**
	 * Site-owner switches and the banned list.
	 *
	 * @var Action_Availability_Policy
	 */
	private Action_Availability_Policy $availability;

	/**
	 * Runs the action outside a recipe.
	 *
	 * @var Action_Executor
	 */
	private Action_Executor $executor;

	/**
	 * @param Action_Availability_Policy|null $availability Availability policy; defaults to the stored switches.
	 * @param Action_Executor|null            $executor     Action runner; defaults to the registry-backed executor.
	 */
	public function __construct( ?Action_Availability_Policy $availability = null, ?Action_Executor $executor = null ) {
		$this->availability = $availability ?? new Action_Availability_Policy();
		$this->executor     = $executor ?? new Action_Executor();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'run_action';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Run a single action immediately, outside any recipe. Requires action_code. '
			. 'Use search to find action codes and get_component_schema for the required fields. '
			. 'Runs synchronously and returns the action result. '
			. 'To run a whole recipe, use run_recipe.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'idempotentHint'  => false,
			'openWorldHint'   => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'action_code' => array(
					'type'        => 'string',
					'description' => 'Action code to run. Use search to find codes.',
				),
				'user_id'     => array(
					'type'        => 'integer',
					'description' => 'User ID the action runs for. Defaults to the current authenticated user.',
					'minimum'     => 0,
				),
				'fields'      => array(
					'type'                 => 'object',
					'description'          => 'Field values for the action. Keys must match option_code from get_component_schema.',
					'additionalProperties' => true,
				),
			),
			'required'   => array( 'action_code' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		// Standalone actions are supplied by WordPress integrations and may return
		// any JSON-compatible shape. Advertising a narrower output contract causes
		// conforming MCP clients to reject successful side-effecting executions.
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$action_code = trim( (string) ( $params['action_code'] ?? '' ) );
		$fields      = $params['fields'] ?? array();
		$user_id     = intval( $params['user_id'] ?? $user_context->get_executee() ?? $user_context->get_executor() );

		if ( '' === $action_code ) {
			return self::refused( 'action_code is required.' );
		}

		if ( ! $this->availability->is_available( $action_code, $user_context ) ) {
			return self::refused( $this->availability->get_refusal_message( $action_code ) );
		}

		if ( ! is_array( $fields ) ) {
			return self::refused( 'fields must be an object.' );
		}

		$refusal = $this->availability->field_refusal( $action_code, $fields );
		if ( null !== $refusal ) {
			return self::refused( $refusal );
		}

		if ( ! empty( $fields ) ) {
			$fields = Security::sanitize( $fields, Security::PRESERVE_RAW );
		}

		try {
			$result = Account_Access_Guard::during(
				function () use ( $action_code, $fields, $user_id ) {
					return $this->executor->run( $action_code, $fields, $user_id );
				}
			);
		} catch ( Account_Access_Refused $refused ) {
			return self::access_refused( $refused );
		}

		// The action's own error handling may have caught the refusal.
		if ( null !== Account_Access_Guard::refusal() ) {
			return self::access_refused( Account_Access_Guard::refusal() );
		}

		// The executor returns an error only before the action runs.
		if ( is_wp_error( $result ) ) {
			return self::refused( $result->get_error_message() );
		}

		if ( ! empty( $result['success'] ) ) {
			$response_data = array(
				'success'     => true,
				'action_code' => $action_code,
				'executor'    => $user_context->get_executor(),
				'executee'    => $user_id,
				'data'        => $this->ensure_object( $result['data'] ?? array() ),
			);

			if ( ! empty( $result['tokens'] ) ) {
				$response_data['tokens'] = $result['tokens'];
			}

			return Json_Rpc_Response::create_success_response( 'Action executed successfully', $response_data );
		}

		$fallback_error = sprintf(
			"Action '%s' failed. Check that the integration is active and any required apps are connected.",
			$action_code
		);

		if ( empty( $result['error'] ) ) {
			return Json_Rpc_Response::create_error_response( $fallback_error );
		}

		// Name the action: a terse reason such as "Not Found" otherwise reads as a bad request.
		return Json_Rpc_Response::create_error_response( sprintf( "Action '%s' failed: %s", $action_code, $result['error'] ) );
	}

	/**
	 * The action stopped where it would have given administrator-level access.
	 *
	 * @param Account_Access_Refused $refused The refusal.
	 * @return array
	 */
	private static function access_refused( Account_Access_Refused $refused ): array {
		return Json_Rpc_Response::create_error_response(
			$refused->getMessage(),
			array(
				'code'        => 'account_access_refused',
				'outcome'     => 'unconfirmed',
				'next_action' => 'The refused change was not saved, and the action stopped there. Any other changes it makes first may already be saved, so check them before running it again without the refused change.',
			)
		);
	}

	/**
	 * An error from before the action ran, so nothing changed.
	 *
	 * @param string $message Error message.
	 * @return array
	 */
	private static function refused( string $message ): array {
		return Json_Rpc_Response::create_error_response( $message, array( 'outcome' => 'refused_before_running' ) );
	}
}
