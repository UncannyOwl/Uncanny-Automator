<?php

namespace Uncanny_Automator;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Recipe_Rest_Api
 *
 * @package Uncanny_Automator
 */
class Recipe_Post_Rest_Api {
	/**
	 * Recipe_Post_Rest_Api constructor.
	 */
	public function __construct() {

		// Register API class
		add_action( 'rest_api_init', array( $this, 'register_routes_for_recipes' ), 20 );
	}

	/**
	 * Rest API Custom Endpoints
	 *
	 * @since 1.0
	 */
	public function register_routes_for_recipes() {

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/create/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/add/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'add' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/delete/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/update/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/get_options/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/change_post_status/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_post_status' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/change_post_recipe_type/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_post_recipe_type' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/change_post_title/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_post_title' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/recipe_completions_allowed/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'recipe_completions_allowed' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		/**
		 * Maximum number of times a Recipe can run
		 */
		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/recipe_max_completions_allowed/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'recipe_max_completions_allowed' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/set_recipe_terms/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_recipe_terms' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		//Rest APIs for User Selector Automator v2.0
		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/user-selector/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'user_selector' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/trigger-options/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'trigger_options' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/schedule_action/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'schedule_action' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/remove_schedule/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'remove_schedule' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/set_recipe_requires_user/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_recipe_requires_user' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/actions_order/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_actions_order' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/set_any_or_all_trigger_option/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_any_or_all_trigger_option' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);
	}

	/**
	 * Checks the nonce of Rest API requests
	 *
	 * @return bool
	 */
	public function valid_nonce() {

		if ( empty( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			return false;
		}

		return wp_verify_nonce( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ), 'wp_rest' );
	}

	/**
	 * Permission callback function that let the rest API allow or disallow access
	 *
	 * @return bool|WP_Error
	 */
	public function save_settings_permissions() {

		if ( ! $this->valid_nonce() ) {
			return false;
		}

		$capability = 'manage_options';
		$capability = apply_filters_deprecated( 'uap_roles_modify_recipe', array( $capability ), '3.0', 'automator_capability_required' );
		$capability = apply_filters( 'automator_capability_required', $capability );

		// Restrict endpoint to only users who have the edit_posts capability.
		if ( ! current_user_can( $capability ) ) {
			return new WP_Error( 'rest_forbidden', 'You do not have the capability to save module settings.', array( 'status' => 403 ) );
		}

		// This is a black-listing approach. You could alternatively do this via white-listing, by returning false here and changing the permissions check.
		$setting = true;
		$setting = apply_filters_deprecated( 'uap_save_setting_permissions', array( $setting ), '3.0', 'automator_save_setting_permissions' );

		return apply_filters( 'automator_save_setting_permissions', $setting );
	}

	/**
	 * Create a recipe
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function create( WP_REST_Request $request ) {
		$return['success'] = false;
		$return['data']    = $request;

		$recipe_post = array(
			'post_type'   => 'uo-recipe',
			'post_author' => get_current_user_id(),
		);

		if ( $request->has_param( 'recipeTitle' ) ) {
			$recipe_post['title'] = wp_strip_all_tags( $request->get_param( 'recipeTitle' ) );
		}

		$post_id = wp_insert_post( $recipe_post );

		if ( is_wp_error( $post_id ) ) {
			$return['message'] = sprintf( '%s:%s', __( 'The action failed to create the post. The response was', 'uncanny-automator' ), $post_id );

			return new WP_REST_Response( $return, 400 );
		}

		$return                   = array();
		$return['success']        = true;
		$return['post_ID']        = $post_id;
		$return['action']         = 'create';
		$return['recipes_object'] = Automator()->get_recipes_data( true, $post_id );

		return new WP_REST_Response( $return, 200 );

	}

	/**
	 * Add trigger or action to recipe
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function add( WP_REST_Request $request ) {

		$return['message'] = __( 'The data that was sent was malformed. Please reload the page and try again.', 'uncanny-automator' );
		$return['success'] = false;
		$return['data']    = $request;
		$return['post']    = '';

		// Make sure we have a parent post ID
		if ( ! $request->has_param( 'recipePostID' ) || ! is_numeric( $request->get_param( 'recipePostID' ) ) ) {
			$return['message'] = __( 'Recipe ID is missing.', 'uncanny-automator' );

			return new WP_REST_Response( $return, 400 );
		}
		if ( $request->has_param( 'trigger_code' ) && $request->has_param( 'item_code' ) ) {
			$return['message'] = __( 'Trigger code or Item code is missing.', 'uncanny-automator' );

			return new WP_REST_Response( $return, 400 );
		}

		// Make sure the parent post exists
		$recipe = get_post( absint( $request->get_param( 'recipePostID' ) ) );
		if ( ! $recipe instanceof WP_Post ) {
			$return['message'] = __( 'Post ID sent is not a recipe post', 'uncanny-automator' );

			return new WP_REST_Response( $return, 400 );
		}

		$post_type       = false;
		$sentence        = '';
		$action          = '';
		$post_action     = sanitize_text_field( $request->get_param( 'action' ) );
		$allowed_actions = array(
			'add-new-trigger',
			'add-new-action',
			'add-new-closure',
		);
		// Make sure we have the post type ( trigger OR action )
		if ( ! $request->has_param( 'action' ) ) {
			$return['message'] = 'Action is missing as parameter.';

			return new WP_REST_Response( $return, 400 );
		}
		if ( ! in_array( (string) $post_action, $allowed_actions, true ) ) {
			$return['message'] = 'Action is not an allowed action.';

			return new WP_REST_Response( $return, 400 );
		}

		if ( 'add-new-trigger' === (string) $post_action ) {
			$post_type = 'uo-trigger';
			$action    = 'create_trigger';
			$sentence  = Automator()->get->trigger_title_from_trigger_code( sanitize_text_field( $request->get_param( 'item_code' ) ) );
		}

		if ( 'add-new-action' === (string) $post_action ) {
			$post_type = 'uo-action';
			$action    = 'create_action';
			$sentence  = Automator()->get->action_title_from_action_code( sanitize_text_field( $request->get_param( 'item_code' ) ) );
		}

		if ( 'add-new-closure' === (string) $post_action ) {
			$post_type = 'uo-closure';
			$action    = 'create_closure';

		}

		if ( ! $post_type ) {
			$return['message'] = __( 'Post type is not defined.', 'uncanny-automator' );

			return new WP_REST_Response( $return, 400 );
		}

		$create_post = apply_filters( 'automator_add_recipe_child', true, $post_type, $action, $recipe );

		if ( true !== $create_post ) {
			return $create_post;
		}

		// Create post object
		$post = array(
			'post_title'        => $sentence,
			'post_content'      => '',
			'post_status'       => 'draft',
			'post_type'         => $post_type,
			'post_date'         => $recipe->post_date,
			'post_date_gmt'     => $recipe->post_date_gmt,
			'post_modified'     => $recipe->post_modified,
			'post_modified_gmt' => $recipe->post_modified_gmt,
			'post_parent'       => $recipe->ID,

		);

		// Insert the post into the database
		$post_id = wp_insert_post( $post );

		if ( is_wp_error( $post_id ) ) {
			$return['message'] = sprintf( '%s:%s', __( 'The action failed to create the post. The response was', 'uncanny-automator' ), $post_id );

			return new WP_REST_Response( $return, 400 );
		}

		/** Sanitize @var $item_code */
		$item_code = Automator()->utilities->automator_sanitize( $request->get_param( 'item_code' ) );

		if ( 'create_trigger' === $action ) {
			update_post_meta( $post_id, 'code', $item_code );
			$trigger_integration = Automator()->get->trigger_integration_from_trigger_code( $item_code );
			update_post_meta( $post_id, 'integration', $trigger_integration );
			update_post_meta( $post_id, 'uap_trigger_version', Utilities::automator_get_version() );
			$add_action_hook = Automator()->get->trigger_actions_from_trigger_code( $item_code );
			update_post_meta( $post_id, 'add_action', $add_action_hook );
			/**
			 * @param int $post_id Trigger ID
			 * @param string $item_code Trigger item code
			 * @param WP_REST_Request $request
			 *
			 * @since 3.0
			 * @package Uncanny_Automator
			 */
			do_action( 'automator_recipe_trigger_created', $post_id, $item_code, $request );
		}

		if ( 'create_action' === $action ) {
			update_post_meta( $post_id, 'code', $item_code );
			$action_integration = Automator()->get->action_integration_from_action_code( $item_code );
			update_post_meta( $post_id, 'integration', $action_integration );
			update_post_meta( $post_id, 'uap_action_version', Utilities::automator_get_version() );
			/**
			 * @param int $post_id Action ID
			 * @param string $item_code Action item code
			 * @param WP_REST_Request $request
			 *
			 * @since 3.0
			 * @package Uncanny_Automator
			 */
			do_action( 'automator_recipe_action_created', $post_id, $item_code, $request );
		}

		if ( 'create_closure' === $action ) {
			update_post_meta( $post_id, 'code', $item_code );
			$closure_integration = Automator()->get->closure_integration_from_closure_code( $item_code );
			update_post_meta( $post_id, 'integration', $closure_integration );
			update_post_meta( $post_id, 'uap_closure_version', Utilities::automator_get_version() );
			/**
			 * @param int $post_id Closure ID
			 * @param string $item_code Closure item code
			 * @param WP_REST_Request $request
			 *
			 * @since 3.0
			 * @package Uncanny_Automator
			 */
			do_action( 'automator_recipe_closure_created', $post_id, $item_code, $request );
		}
		
		if ( $request->has_param( 'default_meta' ) ) {
			if ( is_array( $request->get_param( 'default_meta' ) ) ) {
				$meta_values = (array) Automator()->utilities->automator_sanitize( $request->get_param( 'default_meta' ), 'mixed' );
				foreach ( $meta_values as $meta_key => $meta_value ) {
					update_post_meta( $post_id, Automator()->utilities->automator_sanitize( $meta_key ), Automator()->utilities->automator_sanitize( $meta_value ) );
				}
			}
		}
		Automator()->cache->clear_automator_recipe_part_cache( $recipe->ID );

		$return                   = array();
		$return['success']        = true;
		$return['post_ID']        = $post_id;
		$return['action']         = $action;
		$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe->ID );

		return new WP_REST_Response( $return, 200 );
	}


	/**
	 * Delete trigger or action to recipe
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function delete( WP_REST_Request $request ) {

		// Make sure we have a parent post ID
		if ( $request->has_param( 'ID' ) && is_numeric( $request->get_param( 'ID' ) ) ) {

			// Delete the post
			$delete_posts = wp_delete_post( absint( $request->get_param( 'ID' ) ), true );

			if ( $delete_posts ) {
				Automator()->cache->clear_automator_recipe_part_cache( $request->get_param( 'ID' ) );

				$return['message']        = 'Deleted!';
				$return['success']        = true;
				$return['delete_posts']   = $delete_posts;
				$return['action']         = 'deleted-' . $delete_posts->post_type;
				$return['recipes_object'] = Automator()->get_recipes_data( true );

				return new WP_REST_Response( $return, 200 );
			}
		}

		$return['message'] = 'The data that was sent was malformed. Please reload the page and trying again.';
		$return['success'] = false;
		$return['data']    = $request;
		$return['post']    = '';
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * Add trigger or action to recipe
	 *
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function update( WP_REST_Request $request ) {

		if ( $request->has_param( 'itemId' ) && is_numeric( $request->get_param( 'itemId' ) ) && $request->has_param( 'optionCode' ) && $request->has_param( 'optionValue' ) ) {

			$item_id    = absint( $request->get_param( 'itemId' ) );
			$recipe_id  = Automator()->get->maybe_get_recipe_id( $item_id );
			$meta_key   = (string) Automator()->utilities->automator_sanitize( $request->get_param( 'optionCode' ) );
			$meta_value = Automator()->utilities->automator_sanitize( $request->get_param( 'optionValue' ), 'mixed' );

			/*
			 * Save human readable sentence that will be stored as trigger and action meta.
			 * Once a trigger is completed, the human readable post meta value will be saved as trigger or action log
			 * meta fr the user to have more detail about it in the logs.
			 */
			if ( $request->has_param( 'sentence_human_readable' ) ) {
				$human_readable = sanitize_text_field( $request->get_param( 'sentence_human_readable' ) );
				update_post_meta( $item_id, 'sentence_human_readable', $human_readable );
			}

			if ( $request->has_param( 'sentence_human_readable_html' ) ) {
				$human_readable = $request->get_param( 'sentence_human_readable_html' );
				update_post_meta( $item_id, 'sentence_human_readable_html', $human_readable );
			}

			// Make sure the parent post exists
			$item = get_post( $item_id );

			if ( $item ) {
				if ( is_array( $meta_value ) ) {
					foreach ( $meta_value as $meta_key => $meta_val ) {
						update_post_meta( $item_id, $meta_key, $meta_val );
					}
				} else {
					update_post_meta( $item_id, $meta_key, $meta_value );
				}
				Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

				$return['message']        = 'Option updated!';
				$return['success']        = true;
				$return['action']         = 'updated_option';
				$return['data']           = array( $item, $meta_key, $meta_value );
				$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );

				$return = apply_filters( 'automator_option_updated', $return, $item, $meta_key, $meta_value );

				return new WP_REST_Response( $return, 200 );
			} else {
				$return['message'] = 'You are trying to update trigger meta for a trigger that does not exist. Please reload the page and trying again.';
				$return['success'] = false;
				$return['data']    = $request;
				$return['post']    = '';

				return new WP_REST_Response( $return, 200 );
			}
		}

		$return['message'] = 'The data that was sent was malformed. Please reload the page and trying again.';
		$return['success'] = false;
		$return['data']    = $request;
		$return['post']    = '';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * Get Option for trigger
	 *
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_options( WP_REST_Request $request ) {

		$options = array();

		// Make sure we have a trigger code
		if ( $request->has_param( 'triggerCode' ) ) {

			$trigger_code = sanitize_text_field( $request->get_param( 'triggerCode' ) );

			$triggers = Automator()->get_triggers();

			// Loop through all trigger
			foreach ( $triggers as $trigger ) {

				// Locate the trigger the our trigger code
				if ( isset( $trigger['code'] ) && $trigger_code === $trigger['code'] ) {

					$options = $trigger['options'];

					$return['message'] = 'Success!';
					$return['success'] = true;
					$return['options'] = $options;
					$return['action']  = 'show_success';

					return new WP_REST_Response( $return, 200 );
				}
			}

			$return['message'] = 'No trigger code match';
			$return['success'] = false;
			$return['options'] = $options;
			$return['action']  = 'show_error';

			return new WP_REST_Response( $return, 200 );

		} elseif ( $request->has_param( 'actionCode' ) ) {

			$trigger_code = sanitize_text_field( $request->get_param( 'actionCode' ) );

			$actions = Automator()->get_actions();

			// Loop through all trigger
			foreach ( $actions as $action ) {

				// Locate the trigger the our trigger code
				if ( isset( $action['code'] ) && $trigger_code === $action['code'] ) {

					$options = $action['options'];

					$return['message'] = 'Success!';
					$return['success'] = true;
					$return['options'] = $options;
					$return['action']  = 'show_success';

					return new WP_REST_Response( $return, 200 );
				}
			}

			$return['message'] = 'No action code match';
			$return['success'] = false;
			$return['options'] = $options;
			$return['action']  = 'show_error';

			return new WP_REST_Response( $return, 200 );
		}

		$return['message'] = 'The data that was sent was malformed. Please reload the page and trying again.';
		$return['success'] = false;
		$return['options'] = $options;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function change_post_status( WP_REST_Request $request ) {

		// Make sure we have a post ID and a post status
		if ( $request->has_param( 'post_ID' ) && $request->has_param( 'post_status' ) ) {

			$status_types = array( 'draft', 'publish' );

			$post_status = sanitize_text_field( $request->get_param( 'post_status' ) );
			$post_id     = absint( $request->get_param( 'post_ID' ) );

			if ( in_array( $post_status, $status_types, true ) && $post_id ) {

				/*
				 * Save human readable sentence that will be stored as trigger and action meta.
				 * Once a trigger is completed, the human readable post meta value will be saved as trigger or action log
				 * meta fr the user to have more detail about it in the logs.
				 */
				if ( $request->has_param( 'sentence_human_readable' ) ) {
					$human_readable = sanitize_text_field( $request->get_param( 'sentence_human_readable' ) );
					update_post_meta( $post_id, 'sentence_human_readable', $human_readable );
				}

				$post = array(
					'ID'          => $post_id,
					'post_status' => $post_status,
				);

				$updated = wp_update_post( $post );

				if ( $updated ) {
					$return['message'] = 'Updated!';
					$return['success'] = true;
					$return['action']  = 'updated_post';
					Automator()->cache->clear_automator_recipe_part_cache( $post_id );

					$return['recipes_object'] = Automator()->get_recipes_data( true );

					return new WP_REST_Response( $return, 200 );
				}
			}
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function change_post_recipe_type( WP_REST_Request $request ) {

		// Make sure we have a post ID and a post status
		if ( $request->has_param( 'post_ID' ) && $request->has_param( 'recipe_type' ) ) {

			$recipe_types = apply_filters_deprecated( 'uap_recipe_types', array( Automator()->get_recipe_types() ), '3.0', 'automator_recipe_types' );
			$recipe_types = apply_filters( 'automator_recipe_types', $recipe_types );

			$recipe_type = sanitize_text_field( $request->get_param( 'recipe_type' ) );
			$post_id     = absint( $request->get_param( 'post_ID' ) );

			if ( in_array( $recipe_type, $recipe_types, true ) && $post_id ) {

				$updated = Automator()->utilities->set_recipe_type( $post_id, $recipe_type );

				if ( false !== $updated ) {
					$return['message']        = 'Updated!';
					$return['success']        = true;
					$return['action']         = 'updated_post';
					$return['recipes_object'] = Automator()->get_recipes_data( true, $post_id );

					return new WP_REST_Response( $return, 200 );
				}
			}
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function change_post_title( WP_REST_Request $request ) {

		// Make sure we have a post ID and a post status
		if ( $request->has_param( 'post_ID' ) && $request->has_param( 'post_title' ) ) {

			$post_title = sanitize_text_field( $request->get_param( 'post_title' ) );
			$post_id    = absint( $request->get_param( 'post_ID' ) );

			if ( $post_id ) {

				$post = array(
					'ID'         => $post_id,
					'post_title' => $post_title,
				);

				$updated = wp_update_post( $post );

				if ( $updated ) {
					Automator()->cache->clear_automator_recipe_part_cache( $post_id );
					$return['message']        = 'Updated!';
					$return['success']        = true;
					$return['action']         = 'updated_post';
					$return['recipes_object'] = Automator()->get_recipes_data( true, $post_id );

					return new WP_REST_Response( $return, 200 );
				}
			}
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * Add trigger or action to recipe
	 *
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function recipe_completions_allowed( WP_REST_Request $request ) {

		// Make sure we have a post ID and a post status
		if ( $request->has_param( 'post_ID' ) && absint( $request->get_param( 'post_ID' ) ) && $request->has_param( 'recipe_completions_allowed' ) ) {

			$recipe_completions_allowed = sanitize_text_field( $request->get_param( 'recipe_completions_allowed' ) );
			$post_id                    = absint( $request->get_param( 'post_ID' ) );

			if ( '-1' === $recipe_completions_allowed ) {
				$recipe_completions_allowed = - 1;
			} elseif ( is_numeric( $recipe_completions_allowed ) ) {
				$recipe_completions_allowed = absint( $recipe_completions_allowed );
			} else {
				$recipe_completions_allowed = 1;
			}

			update_post_meta( $post_id, 'recipe_completions_allowed', $recipe_completions_allowed );
			Automator()->cache->clear_automator_recipe_part_cache( $post_id );

			$return['message']        = 'Updated!';
			$return['success']        = true;
			$return['action']         = 'updated_recipe_completions_allowed';
			$return['recipes_object'] = Automator()->get_recipes_data( true, $post_id );

			return new WP_REST_Response( $return, 200 );
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function recipe_max_completions_allowed( WP_REST_Request $request ) {

		// Make sure we have a post ID and a post status
		if ( $request->has_param( 'post_ID' ) && absint( $request->get_param( 'post_ID' ) ) && $request->has_param( 'recipe_completions_allowed' ) ) {

			$recipe_completions_allowed = sanitize_text_field( $request->get_param( 'recipe_completions_allowed' ) );
			$post_id                    = absint( $request->get_param( 'post_ID' ) );

			if ( '-1' === $recipe_completions_allowed ) {
				$recipe_completions_allowed = - 1;
			} elseif ( is_numeric( $recipe_completions_allowed ) ) {
				$recipe_completions_allowed = absint( $recipe_completions_allowed );
			} else {
				$recipe_completions_allowed = 1;
			}

			update_post_meta( $post_id, 'recipe_max_completions_allowed', $recipe_completions_allowed );

			Automator()->cache->clear_automator_recipe_part_cache( $post_id );

			$return['message'] = 'Updated!';
			$return['success'] = true;
			$return['action']  = 'updated_recipe_max_completions_allowed';

			$return['recipes_object'] = Automator()->get_recipes_data( true, $post_id );

			return new WP_REST_Response( $return, 200 );
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * Set recipe terms & tags
	 *
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_recipe_terms( WP_REST_Request $request ) {
		// Make sure we have a post ID and a post status
		$params = $request->get_body_params();
		if ( isset( $params['recipe_id'] ) && isset( $params['term_id'] ) ) {
			$update_count = false;
			$recipe_id    = absint( $params['recipe_id'] );
			$taxonomy     = (string) sanitize_text_field( $params['term_id'] );
			if ( 'recipe_category' === $taxonomy && isset( $params['category_id'] ) && ! empty( $params['category_id'] ) ) {
				$term_id = absint( $params['category_id'] );
				$set_cat = 'true' === sanitize_text_field( $params['set_category'] ) ? true : false;
				if ( true === $set_cat ) {
					wp_add_object_terms( $recipe_id, $term_id, $taxonomy );
				} elseif ( ! $set_cat ) {
					wp_remove_object_terms( $recipe_id, $term_id, $taxonomy );
				}
			} elseif ( 'recipe_tag' === $taxonomy && isset( $params['tags']['commaSeparated'] ) && ! empty( $params['tags']['commaSeparated'] ) ) {
				$tags_sanitized = sanitize_text_field( $params['tags']['commaSeparated'] );
				$tags           = explode( ',', $tags_sanitized );
				wp_set_object_terms( $recipe_id, $tags, $taxonomy );
			}

			if ( $update_count ) {
				$all_terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
					)
				);
				if ( $all_terms ) {
					$term_ids = array_column( $all_terms, 'term_id' );
					wp_update_term_count_now( $term_ids, $taxonomy );
				}
			}
			Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

			$return['message'] = 'Updated!';
			$return['success'] = true;
			$return['action']  = 'set_recipe_terms';

			return new WP_REST_Response( $return, 200 );
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function user_selector( WP_REST_Request $request ) {

		// Make sure we have a post ID and a post status
		if ( $request->has_param( 'source' ) && $request->has_param( 'source' ) ) {
			$source    = Automator()->utilities->automator_sanitize( $request->get_param( 'source' ) );
			$fields    = Automator()->utilities->automator_sanitize( $request->get_param( 'data' ), 'mixed' );
			$recipe_id = (int) $request->get_param( 'recipeId' );
			//get recipe post id or action post id
			update_post_meta( $recipe_id, 'source', $source );
			update_post_meta( $recipe_id, 'fields', $fields );

			Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

			$return['message']        = 'Updated!';
			$return['success']        = true;
			$return['action']         = 'user_selector';
			$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );

			return new WP_REST_Response( $return, 200 );
		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function trigger_options( WP_REST_Request $request ) {
		$recipe_id = (int) $request->get_param( 'recipeId' );

		$return['message']        = 'Updated!';
		$return['success']        = true;
		$return['action']         = 'trigger_options';
		$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function schedule_action( WP_REST_Request $request ) {

		// Make sure we have all the data
		if ( $request->get_param( 'recipeId' ) && $request->has_param( 'actionId' ) && $request->has_param( 'asyncMode' ) ) {

			$post_id   = (int) $request->get_param( 'actionId' );
			$recipe_id = (int) $request->get_param( 'recipeId' );

			$return = array();

			update_post_meta( $post_id, 'async_mode', $request->get_param( 'asyncMode' ) );

			if ( $request->has_param( 'delayNumber' ) && $request->has_param( 'delayUnit' ) ) {

				update_post_meta( $post_id, 'async_delay_number', $request->get_param( 'delayNumber' ) );
				update_post_meta( $post_id, 'async_delay_unit', $request->get_param( 'delayUnit' ) );

				$return['success'] = true;

			}

			if ( $request->has_param( 'scheduleDate' ) && $request->has_param( 'scheduleTime' ) ) {

				update_post_meta( $post_id, 'async_schedule_time', $request->get_param( 'scheduleTime' ) );
				update_post_meta( $post_id, 'async_schedule_date', $request->get_param( 'scheduleDate' ) );

				$return['success'] = true;

			}

			if ( $request->has_param( 'scheduleSentence' ) ) {
				update_post_meta( $post_id, 'async_sentence', $request->get_param( 'scheduleSentence' ) );
			}

			if ( $return['success'] ) {
				Automator()->cache->remove( Automator()->cache->recipes_data );

				$return['post_ID']        = $post_id;
				$return['action']         = 'schedule_action';
				$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );

				return new WP_REST_Response( $return, 200 );
			}
		}

		$return['success'] = false;
		$return['message'] = 'Failed to schedule action';
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function remove_schedule( WP_REST_Request $request ) {

		// Make sure we have all the data
		if ( $request->get_param( 'recipeId' ) && $request->has_param( 'actionId' ) ) {

			Utilities::log( 'Removing schedule $request: ' . var_export( $request, true ) );

			$post_id   = (int) $request->get_param( 'actionId' );
			$recipe_id = (int) $request->get_param( 'recipeId' );

			$return = array();

			delete_post_meta( $post_id, 'async_mode' );
			delete_post_meta( $post_id, 'async_delay_unit' );
			delete_post_meta( $post_id, 'async_delay_number' );
			delete_post_meta( $post_id, 'async_schedule_time' );
			delete_post_meta( $post_id, 'async_schedule_date' );
			delete_post_meta( $post_id, 'async_sentence' );

			$return['success']        = true;
			$return['post_ID']        = $post_id;
			$return['action']         = 'remove_schedule';
			$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );

			return new WP_REST_Response( $return, 200 );

		}

		$return['success'] = false;
		$return['message'] = 'Failed to remove schedule';
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_recipe_requires_user( WP_REST_Request $request ) {

		// Make sure we have a post ID and a post status
		if ( $request->has_param( 'recipePostID' ) && $request->has_param( 'requiresUser' ) ) {

			$recipe_id     = absint( $request->get_param( 'recipePostID' ) );
			$requires_user = $request->get_param( 'requiresUser' );
			update_post_meta( $recipe_id, 'recipe_requires_user', $requires_user );

			$return['message'] = 'Updated!';
			$return['success'] = true;
			$return['action']  = 'updated_recipe';
			Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

			$return['recipes_object'] = Automator()->get_recipes_data( true );

			return new WP_REST_Response( $return, 200 );

		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * Function to update the menu_order of the actions
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function update_actions_order( WP_REST_Request $request ) {

		// Make sure we have a recipe ID and the newOrder
		if ( $request->has_param( 'recipeID' ) && $request->has_param( 'newOrder' ) ) {

			$recipe_id = absint( $request->get_param( 'recipeID' ) );
			$new_order = $request->get_param( 'newOrder' );

			// Update the actions menu_order here
			foreach ( $new_order as $index => $action_id ) {
				Automator()->db->action->update_menu_order( $action_id, ( $index + 1 ) * 10 );
			}

			$return['message'] = 'Updated!';
			$return['success'] = true;
			$return['action']  = 'update_actions_order';

			Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

			$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );

			return new WP_REST_Response( $return, 200 );

		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function set_any_or_all_trigger_option( WP_REST_Request $request ) {

		// Make sure we have a recipe ID and the newOrder
		if ( $request->has_param( 'recipeID' ) ) {

			$recipe_id  = absint( $request->get_param( 'recipeID' ) );
			$all_or_any = $request->get_param( 'allOrAnyOption' );

			update_post_meta( $recipe_id, 'run_when_any_trigger_complete', $all_or_any );

			$return['message'] = 'Updated!';
			$return['success'] = true;
			$return['action']  = 'set_any_trigger_option';

			Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

			$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );

			return new WP_REST_Response( $return, 200 );

		}

		$return['message'] = 'Failed to update';
		$return['success'] = false;
		$return['action']  = 'show_error';

		return new WP_REST_Response( $return, 200 );
	}
}
