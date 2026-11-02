<?php
/**
 * Run recipe tool.
 *
 * Runs a published recipe that has a manual trigger. Split from the former
 * consolidated `execute` tool (target=recipe) so recipe_id is a schema-level
 * requirement instead of a conditional one.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Account_Access_Policy;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Execution_Service;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Account_Access_Guard;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Account_Access_Refused;

/**
 * Run Recipe Tool.
 *
 * Requires recipe_id. The recipe must be published and have a manual trigger.
 * The response confirms queue acceptance or initiation, never completion.
 */
class Run_Recipe_Tool extends Abstract_MCP_Tool {

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return 'run_recipe';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Request a run of a published recipe. Requires recipe_id. '
			. 'The recipe must have a manual trigger (trigger_code "RECIPE_MANUAL_TRIGGER_ANON", provided by Uncanny Automator Pro); add one with save_trigger if it is missing. '
			. 'A queued response includes a request-local queue_id; a synchronously initiated run includes recipe_log_id. Neither confirms completion or external delivery. Do not repeat an accepted request; use get_logs to check its outcome. '
			. 'To run a single action outside a recipe, use run_action. '
			. 'Use those names as root tools when listed. Otherwise use rest_discover and rest_request for the corresponding native operations: automator/save-trigger, automator/get-logs and automator/run-action.';
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
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'Recipe ID to run. The recipe must be published and have a manual trigger.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'     => array( 'type' => 'integer' ),
				'recipe_title'  => array( 'type' => 'string' ),
				'recipe_status' => array(
					'type' => 'string',
					'enum' => array( 'queued', 'initiated' ),
				),
				'recipe_log_id' => array(
					'type' => 'integer',
					'minimum' => 1,
					'description' => 'Confirmed log ID when the manual trigger has already run.',
				),
				'queue_id'      => array(
					'type' => 'string',
					'description' => 'Request-local acceptance receipt; not a recipe log ID or proof of completion.',
				),
				'warning'       => array( 'type' => 'string' ),
				'executor'      => array( 'type' => 'integer' ),
				'executee'      => array( 'type' => 'integer' ),
			),
			'required'   => array( 'recipe_id', 'recipe_status' ),
			'oneOf'      => array(
				array(
					'type' => 'object',
					'properties' => array(
						'recipe_status' => array(
							'type' => 'string',
							'enum' => array( 'queued' ),
						),
					),
					'required' => array( 'queue_id' ),
				),
				array(
					'type' => 'object',
					'properties' => array(
						'recipe_status' => array(
							'type' => 'string',
							'enum' => array( 'initiated' ),
						),
					),
					'required' => array( 'recipe_log_id' ),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$recipe_id = (int) ( $params['recipe_id'] ?? 0 );

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'recipe_id is required.' );
		}

		// Pre-check recipe state for clearer error messages.
		$recipe = get_post( $recipe_id );

		if ( ! $recipe || AUTOMATOR_POST_TYPE_RECIPE !== $recipe->post_type ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe %d not found.', $recipe_id )
			);
		}

		if ( 'trash' === $recipe->post_status ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe %d is trashed and cannot be executed.', $recipe_id )
			);
		}

		if ( 'publish' !== $recipe->post_status ) {
			return Json_Rpc_Response::create_error_response(
				sprintf( 'Recipe %d is in "%s" status. Only published recipes can be executed.', $recipe_id, $recipe->post_status )
			);
		}

		foreach ( Database::get_action_store()->get_recipe_action_codes( $recipe_id, array( 'publish' ) ) as $action_id => $code ) {
			$refusal = ( new Account_Access_Policy() )->recipe_action_refusal( $code, Database::get_action_store()->get_action_field_values( $action_id ) );
			if ( null !== $refusal ) {
				return Json_Rpc_Response::create_error_response(
					$refusal,
					array(
						'code'      => 'account_access_refused',
						'recipe_id' => $recipe_id,
					)
				);
			}
		}

		try {
			$result = Account_Access_Guard::during(
				static function () use ( $recipe_id ) {
					// Drain the whole trigger queue inside the guard, not at shutdown.
					// Manual_Recipe_Run::dispatch() explains why it is not narrowed.
					return Recipe_Execution_Service::instance()->execute_recipe( $recipe_id, true );
				}
			);
		} catch ( Account_Access_Refused $refused ) {
			return self::access_refused( $refused, array( 'recipe_id' => $recipe_id ) );
		}

		// A runner may catch the refusal; retain it alongside the receipt.
		if ( null !== Account_Access_Guard::refusal() ) {
			return self::access_refused( Account_Access_Guard::refusal(), is_array( $result ) ? $result : array( 'recipe_id' => $recipe_id ) );
		}

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_error_response( $this->error_with_reasons( $result ) );
		}

		$result['executor'] = $user_context->get_executor();
		$result['executee'] = $user_context->get_executor();

		$message = 'queued' === $result['recipe_status'] ? 'Recipe request queued; execution is not yet confirmed' : 'Recipe execution initiated';
		return Json_Rpc_Response::create_success_response( $message, $result );
	}

	/**
	 * An action of the run stopped where it would have given administrator-level access.
	 *
	 * @param Account_Access_Refused $refused The refusal.
	 * @param array $receipt Run identifiers and acceptance details captured before refusal.
	 * @return array
	 */
	private static function access_refused( Account_Access_Refused $refused, array $receipt ): array {
		return Json_Rpc_Response::create_error_response(
			$refused->getMessage(),
			array_merge(
				$receipt,
				array(
					'code'        => 'account_access_refused',
					'outcome'     => 'unconfirmed',
					'next_action' => 'The refused change was not saved. Other steps may have run, stopped or remain scheduled. Check this recipe\'s results with get_logs before running it again.',
				)
			)
		);
	}
}
