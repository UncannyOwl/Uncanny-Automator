<?php
/**
 * MCP catalog tool that creates or updates Automator recipes in one call.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Recipe\Recipe_Service;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Validator;
use Uncanny_Automator\Api\Services\User_Selector\User_Selector_Service;
use Uncanny_Automator\Api\Services\Token\Validation\Token_Validator;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\Api\Services\Recipe\Utilities\Recipe_Link_Builder;
use Uncanny_Automator\Api\Services\User_Selector\User_Selector_Advisor;
use WP_Error;

class Save_Recipe_Tool extends Abstract_MCP_Tool {

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'save_recipe';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Create a new recipe or update an existing recipe. Omit recipe_id to create a draft. Include recipe_id to update title, status, notes, throttling, or execution limits. '
			. 'Pass user_selector to configure which user actions execute on (required for anonymous recipes needing user context). '
			. 'ALWAYS send back the recipe link when creating or updating.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => false,
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
				'recipe_id'      => array(
					'type'        => 'integer',
					'description' => 'Existing recipe ID to update. Omit to create a new recipe.',
				),
				'title'          => array(
					'type'        => 'string',
					'description' => 'Recipe title.',
				),
				'status'         => array(
					'type'        => 'string',
					'description' => 'Recipe publication status.',
					'enum'        => array( Recipe_Status::DRAFT, Recipe_Status::PUBLISH, 'trash' ),
					'default'     => Recipe_Status::DRAFT,
				),
				'type'           => array(
					'type'        => 'string',
					'description' => 'Recipe execution type. "user" recipes run per-user, "anonymous" recipes run globally. WARNING: Type cannot be changed once recipe is created - only used for new recipes.',
					'enum'        => array( 'user', 'anonymous' ),
					'default'     => 'user',
				),
				'trigger_logic'  => array(
					'type'        => 'string',
					'description' => 'Trigger logic. "all" = all triggers must be true, "any" = any trigger may fire.',
					'enum'        => array( 'all', 'any' ),
					'default'     => 'all',
				),
				'notes'          => array(
					'type'        => 'string',
					'description' => 'Recipe notes or description.',
				),
				'categories'     => array(
					'type'        => 'array',
					'description' => 'Recipe categories (slugs). Use get_terms tool to discover available categories.',
					'items'       => array(
						'type' => 'string',
					),
				),
				'tags'           => array(
					'type'        => 'array',
					'description' => 'Recipe tags (slugs). Use get_terms tool to discover available tags.',
					'items'       => array(
						'type' => 'string',
					),
				),
				'throttle'       => array(
					'type'        => 'object',
					'description' => 'Recipe throttle settings.',
					'properties'  => array(
						'enabled'  => array(
							'type'        => 'boolean',
							'description' => 'Enable recipe throttling.',
						),
						'duration' => array(
							'type'        => 'integer',
							'description' => 'Throttle duration.',
							'minimum'     => 1,
						),
						'unit'     => array(
							'type'        => 'string',
							'description' => 'Duration unit.',
							'enum'        => array( 'minutes', 'hours', 'days' ),
						),
						'scope'    => array(
							'type'        => 'string',
							'description' => 'Throttle scope (only for "user" recipes).',
							'enum'        => array( 'recipe', 'user' ),
						),
					),
				),
				'times_per_user' => array(
					'type'        => 'integer',
					'description' => 'Number of times the recipe can run per user (user recipes).',
					'minimum'     => 0,
				),
				'total_times'    => array(
					'type'        => 'integer',
					'description' => 'Total runs allowed across all users (anonymous recipes).',
					'minimum'     => 0,
				),
				'redirect_url'   => array(
					'type'        => 'string',
					'description' => 'URL to redirect users to when the recipe completes. Leave empty to remove redirect. When provided, automatically creates or updates a redirect closure.',
					'format'      => 'uri',
				),
				'user_selector'  => array(
					'type'        => 'object',
					'description' => 'User selector configuration. Determines which user actions execute on. Required for anonymous recipes needing user context. Omit to leave user selector unchanged.',
					'properties'  => array(
						'source'             => array(
							'type'        => 'string',
							'description' => '"existingUser" (find by field) or "newUser" (create user).',
							'enum'        => array( 'existingUser', 'newUser' ),
						),
						'unique_field'       => array(
							'type'        => 'string',
							'description' => 'For existingUser: field to identify user.',
							'enum'        => array( 'email', 'id', 'username' ),
						),
						'unique_field_value' => array(
							'type'        => 'string',
							'description' => 'For existingUser: value to match. Supports tokens like {{user_email}}.',
						),
						'fallback'           => array(
							'type'        => 'string',
							'description' => 'Fallback behavior. existingUser: "create-new-user" or "do-nothing". newUser: "select-existing-user" or "do-nothing".',
							'enum'        => array( 'create-new-user', 'do-nothing', 'select-existing-user' ),
						),
						'prioritized_field'  => array(
							'type'        => 'string',
							'description' => 'For newUser with fallback=select-existing-user: which field to check first for duplicates.',
							'enum'        => array( 'email', 'username' ),
						),
						'user_data'          => array(
							'type'        => 'object',
							'description' => 'User data. Required for newUser, optional for existingUser with create-new-user fallback.',
							'properties'  => array(
								'email'       => array(
									'type' => 'string',
									'description' => 'User email. Supports tokens.',
								),
								'username'    => array(
									'type' => 'string',
									'description' => 'User login name. Supports tokens.',
								),
								'firstName'   => array(
									'type' => 'string',
									'description' => 'First name.',
								),
								'lastName'    => array(
									'type' => 'string',
									'description' => 'Last name.',
								),
								'displayName' => array(
									'type' => 'string',
									'description' => 'Display name.',
								),
								'password'    => array(
									'type' => 'string',
									'description' => 'Password. Empty = auto-generate.',
								),
								'role'        => array(
									'type' => 'string',
									'description' => 'WordPress role.',
									'default' => 'subscriber',
								),
								'logUserIn'   => array(
									'type' => 'boolean',
									'description' => 'Log user in after creation.',
									'default' => false,
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id'  => array( 'type' => 'integer' ),
				'recipe'     => array( 'type' => 'object' ),
				'links'      => array(
					'type'       => 'object',
					'properties' => array(
						'edit_recipe' => array( 'type' => 'string' ),
					),
				),
				'notes'      => array(
					'type' => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'   => array( 'recipe_id', 'recipe', 'links' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;

		// Extract taxonomy and user_selector params before passing to service.
		$categories    = $params['categories'] ?? null;
		$tags          = $params['tags'] ?? null;
		$user_selector = $params['user_selector'] ?? null;
		unset( $params['categories'], $params['tags'], $params['user_selector'] );

		// Validate redirect_url scheme if provided (transport-level input validation).
		if ( ! empty( $params['redirect_url'] ) ) {
			$parsed = wp_parse_url( $params['redirect_url'] );
			if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
				return Json_Rpc_Response::create_error_response( 'redirect_url must use http:// or https:// scheme.' );
			}
		}

		$service = Recipe_Service::instance();

		// Pre-publish validation (service-layer checks).
		$wants_publish = isset( $params['status'] ) && 'publish' === $params['status'];
		if ( $wants_publish && $recipe_id > 0 ) {
			$validator = new Recipe_Validator();
			$readiness = $validator->validate_publish_readiness( $recipe_id );
			if ( is_wp_error( $readiness ) ) {
				return Json_Rpc_Response::create_error_response( $readiness->get_error_message() );
			}

			// Block publish if anonymous recipe needs user selector.
			$advisor        = new User_Selector_Advisor();
			$selector_error = $advisor->check_on_publish( $recipe_id );
			if ( null !== $selector_error ) {
				return Json_Rpc_Response::create_error_response( $selector_error );
			}
		}

		try {
			if ( $recipe_id > 0 ) {
				// Update existing recipe.
				unset( $params['recipe_id'] );
				$params['id'] = $recipe_id;

				$result = $service->update_recipe( $recipe_id, $params );
				if ( is_wp_error( $result ) ) {
					return Json_Rpc_Response::create_error_response( $result->get_error_message() );
				}

				$recipe_data = isset( $result['recipe'] ) ? $result['recipe'] : $result;
				if ( isset( $recipe_data['id'] ) ) {
					$recipe_id = (int) $recipe_data['id'];
				}

				// Taxonomy assignment via service layer.
				$service->assign_taxonomies( $recipe_id, $categories, $tags );

				// Handle user_selector if provided — fail the operation on error.
				$us_result = $this->save_user_selector_if_present( $recipe_id, $user_selector );
				if ( is_wp_error( $us_result ) ) {
					return Json_Rpc_Response::create_error_response(
						'Recipe updated but user selector save failed: ' . $us_result->get_error_message()
					);
				}

				$payload = array(
					'recipe_id' => $recipe_id,
					'recipe'    => $recipe_data,
					'links'     => ( new Recipe_Link_Builder() )->build_links( $recipe_id ),
				);

				if ( isset( $result['message'] ) && '' !== $result['message'] ) {
					$payload['notes'] = array( $result['message'] );
				}

				return Json_Rpc_Response::create_success_response( 'Recipe updated successfully', $payload );
			} else {
				// Create new recipe.
				$result = $service->create_recipe( $params );
				if ( is_wp_error( $result ) ) {
					return Json_Rpc_Response::create_error_response( $result->get_error_message() );
				}

				$recipe_data = $result['recipe'] ?? array();
				$created_id  = isset( $result['recipe_id'] ) ? (int) $result['recipe_id'] : (int) ( $recipe_data['id'] ?? 0 );

				// Taxonomy assignment via service layer.
				$service->assign_taxonomies( $created_id, $categories, $tags );

				// Handle user_selector if provided — include created_id in error so caller can reconcile.
				$us_result = $this->save_user_selector_if_present( $created_id, $user_selector );
				if ( is_wp_error( $us_result ) ) {
					return Json_Rpc_Response::create_error_response(
						'Recipe created (recipe_id: ' . $created_id . ') but user selector save failed: ' . $us_result->get_error_message(),
						array(
							'recipe_id' => $created_id,
							'recipe'    => $recipe_data,
							'links'     => ( new Recipe_Link_Builder() )->build_links( $created_id ),
						)
					);
				}

				$payload = array(
					'recipe_id' => $created_id,
					'recipe'    => $recipe_data,
					'links'     => ( new Recipe_Link_Builder() )->build_links( $created_id ),
				);

				if ( isset( $result['message'] ) && '' !== $result['message'] ) {
					$payload['notes'] = array( $result['message'] );
				}

				return Json_Rpc_Response::create_success_response( 'Recipe created successfully', $payload );
			}
		} catch ( \InvalidArgumentException $e ) {
			return Json_Rpc_Response::create_error_response( $e->getMessage() );
		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response( 'Failed to process recipe: ' . $e->getMessage() );
		}
	}

	/**
	 * Save user selector if provided.
	 *
	 * Delegates token validation, admin check, and persistence to service-layer classes.
	 * Returns WP_Error on failure so the caller can decide whether to fail or warn.
	 *
	 * @param int        $recipe_id     Recipe ID.
	 * @param array|null $user_selector User selector data from params.
	 *
	 * @return true|\WP_Error True on success (or no-op), WP_Error on failure.
	 */
	private function save_user_selector_if_present( int $recipe_id, ?array $user_selector ) {
		if ( null === $user_selector || empty( $user_selector ) || $recipe_id <= 0 ) {
			return true;
		}

		// Validate tokens in user selector fields.
		$fields_to_validate = array();
		if ( isset( $user_selector['unique_field_value'] ) ) {
			$fields_to_validate['unique_field_value'] = $user_selector['unique_field_value'];
		}
		if ( isset( $user_selector['user_data'] ) && is_array( $user_selector['user_data'] ) ) {
			foreach ( $user_selector['user_data'] as $key => $value ) {
				if ( is_string( $value ) && ! empty( $value ) ) {
					$fields_to_validate[ 'user_data_' . $key ] = $value;
				}
			}
		}

		if ( ! empty( $fields_to_validate ) ) {
			$validation = Token_Validator::validate( $recipe_id, $fields_to_validate );
			if ( ! $validation['valid'] ) {
				return new WP_Error( 'user_selector_token_error', 'User selector token validation failed: ' . $validation['message'] );
			}
		}

		// Reject static (non-token) values that resolve to an administrator (service-layer check).
		$us_service  = User_Selector_Service::instance();
		$admin_error = $us_service->validate_user_selector_not_admin( $user_selector );
		if ( is_wp_error( $admin_error ) ) {
			return $admin_error;
		}

		$result = $us_service->save_user_selector( $recipe_id, $user_selector );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
