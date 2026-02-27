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
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;
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
		return 'Create a new recipe or update an existing recipe. Omit recipe_id to create a draft. Include recipe_id to update title, status, notes, throttling, or execution limits. ALWAYS send back the recipe link when createing or updating.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		// Get existing categories for enum.
		$category_terms = get_terms(
			array(
				'taxonomy'   => 'recipe_category',
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);
		$category_slugs = is_array( $category_terms ) ? array_values( $category_terms ) : array();

		// Get existing tags for enum.
		$tag_terms = get_terms(
			array(
				'taxonomy'   => 'recipe_tag',
				'hide_empty' => false,
				'fields'     => 'slugs',
			)
		);
		$tag_slugs = is_array( $tag_terms ) ? array_values( $tag_terms ) : array();

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
					'description' => 'Recipe categories (slugs). Use existing categories from the enum. Categories help organize recipes.',
					'items'       => array(
						'type' => 'string',
						'enum' => $category_slugs,
					),
				),
				'tags'           => array(
					'type'        => 'array',
					'description' => 'Recipe tags (slugs). Use existing tags from the enum. Tags provide additional labeling.',
					'items'       => array(
						'type' => 'string',
						'enum' => $tag_slugs,
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
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		$this->require_authenticated_executor( $user_context );

		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;
		if ( ! $recipe_id && isset( $params['id'] ) ) {
			$recipe_id = (int) $params['id'];
		}

		// Extract taxonomy params before passing to service (service doesn't handle taxonomies).
		$categories = $params['categories'] ?? null;
		$tags       = $params['tags'] ?? null;
		unset( $params['categories'], $params['tags'] );

		// Validate redirect_url scheme if provided.
		if ( ! empty( $params['redirect_url'] ) ) {
			$parsed = wp_parse_url( $params['redirect_url'] );
			if ( ! isset( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], array( 'http', 'https' ), true ) ) {
				return Json_Rpc_Response::create_error_response( 'redirect_url must use http:// or https:// scheme.' );
			}
		}

		$service = Recipe_Service::instance();

		try {
			if ( $recipe_id > 0 ) {
				// Update existing recipe
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

				// Handle taxonomy assignment after successful update.
				$this->assign_taxonomies( $recipe_id, $categories, $tags );

				$payload = array(
					'recipe_id'  => $recipe_id,
					'recipe'     => $recipe_data,
					'links'      => $this->build_recipe_links( $recipe_id ),
					'next_steps' => $this->build_recipe_next_steps( $recipe_id ),
				);

				if ( isset( $result['message'] ) && '' !== $result['message'] ) {
					$payload['notes'] = array( $result['message'] );
				}

				return Json_Rpc_Response::create_success_response( 'Recipe updated successfully', $payload );
			} else {
				// Create new recipe
				$result = $service->create_recipe( $params );
				if ( is_wp_error( $result ) ) {
					return Json_Rpc_Response::create_error_response( $result->get_error_message() );
				}

				$recipe_data = $result['recipe'] ?? array();
				$created_id  = isset( $result['recipe_id'] ) ? (int) $result['recipe_id'] : (int) ( $recipe_data['id'] ?? 0 );

				// Handle taxonomy assignment after successful creation.
				$this->assign_taxonomies( $created_id, $categories, $tags );

				$payload = array(
					'recipe_id'  => $created_id,
					'recipe'     => $recipe_data,
					'links'      => $this->build_recipe_links( $created_id ),
					'next_steps' => $this->build_recipe_next_steps( $created_id ),
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
	 * Assign categories and tags to a recipe.
	 *
	 * @param int        $recipe_id  Recipe post ID.
	 * @param array|null $categories Category slugs to assign.
	 * @param array|null $tags       Tag slugs to assign.
	 * @return void
	 */
	private function assign_taxonomies( int $recipe_id, ?array $categories, ?array $tags ): void {
		if ( $recipe_id <= 0 ) {
			return;
		}

		// Assign categories if provided (replaces existing).
		if ( is_array( $categories ) ) {
			$sanitized_cats = array_map( 'sanitize_title', $categories );
			wp_set_object_terms( $recipe_id, $sanitized_cats, 'recipe_category' );
		}

		// Assign tags if provided (replaces existing).
		if ( is_array( $tags ) ) {
			$sanitized_tags = array_map( 'sanitize_title', $tags );
			wp_set_object_terms( $recipe_id, $sanitized_tags, 'recipe_tag' );
		}
	}

	/**
	 * Build useful admin/front-end links for a recipe.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return array Links keyed by purpose.
	 */
	private function build_recipe_links( int $recipe_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		$edit_link = get_edit_post_link( $recipe_id, 'raw' );
		if ( ! is_string( $edit_link ) || '' === $edit_link ) {
			return array();
		}

		return array(
			'edit_recipe' => $edit_link,
		);
	}

	/**
	 * Suggest sensible follow-up calls for agents after creating/updating a recipe.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return array Structured follow-up suggestions.
	 */
	private function build_recipe_next_steps( int $recipe_id ): array {
		if ( $recipe_id <= 0 ) {
			return array();
		}

		return array(
			'add_trigger' => array(
				'tool'   => 'add_trigger',
				'params' => array(
					'recipe_id' => $recipe_id,
				),
				'hint'   => 'Recipes need at least one trigger before they can run.',
			),
			'add_action'  => array(
				'tool'   => 'add_action',
				'params' => array(
					'recipe_id' => $recipe_id,
				),
				'hint'   => 'Add at least one action so the recipe does something useful.',
			),
			'list_tokens' => array(
				'tool'   => 'get_recipe_tokens',
				'params' => array(
					'recipe_id' => $recipe_id,
				),
				'hint'   => 'Use recipe tokens to personalise action fields.',
			),
		);
	}
}
