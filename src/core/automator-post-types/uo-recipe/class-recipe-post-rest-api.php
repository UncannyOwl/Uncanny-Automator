<?php

namespace Uncanny_Automator;

use Exception;
use Uncanny_Automator\Webhooks\Response_Validator;
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
			'/set_walkthrough_progress/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_walkthrough_progress' ),
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
			'/change_recipe_notes/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'change_recipe_notes' ),
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

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/duplicate_action/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'duplicate_action' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/resend_api_request/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'resend_api_request' ),
				'permission_callback' => array( $this, 'save_settings_permissions' ),
			)
		);

		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/triggers_change_logic/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'triggers_change_logic' ),
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
			$return['message'] = sprintf( '%s:%s', esc_html__( 'The action failed to create the post. The response was', 'uncanny-automator' ), $post_id );

			return new WP_REST_Response( $return, 400 );
		}

		$return                   = array();
		$return['success']        = true;
		$return['post_ID']        = $post_id;
		$return['action']         = 'create';
		$return['recipes_object'] = Automator()->get_recipes_data( true, $post_id );

		/**
		 * Fires when a recipe is created.
		 *
		 * @since 5.7
		 */
		do_action( 'automator_recipe_created', $post_id, $return );

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

		$return['message'] = esc_html__( 'The data that was sent was malformed. Please reload the page and try again.', 'uncanny-automator' );
		$return['success'] = false;
		$return['data']    = $request;
		$return['post']    = '';

		// Make sure we have a parent post ID
		if ( ! $request->has_param( 'recipePostID' ) || ! is_numeric( $request->get_param( 'recipePostID' ) ) ) {
			$return['message'] = esc_html__( 'Recipe ID is missing.', 'uncanny-automator' );

			return new WP_REST_Response( $return, 400 );
		}
		if ( $request->has_param( 'trigger_code' ) && $request->has_param( 'item_code' ) ) {
			$return['message'] = esc_html__( 'Trigger code or Item code is missing.', 'uncanny-automator' );

			return new WP_REST_Response( $return, 400 );
		}

		// Make sure the parent post exists
		$recipe = get_post( absint( $request->get_param( 'recipePostID' ) ) );
		if ( ! $recipe instanceof WP_Post ) {
			$return['message'] = esc_html__( 'Post ID sent is not a recipe post', 'uncanny-automator' );

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
			$return['message'] = esc_html__( 'Post type is not defined.', 'uncanny-automator' );

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

		if ( ! empty( $request->get_param( 'parent_id' ) ) ) {
			$post['post_parent'] = absint( $request->get_param( 'parent_id' ) );
		}

		// Insert the post into the database
		$post_id = wp_insert_post( $post );

		if ( is_wp_error( $post_id ) ) {
			$return['message'] = sprintf( '%s:%s', esc_html__( 'The action failed to create the post. The response was', 'uncanny-automator' ), $post_id );

			return new WP_REST_Response( $return, 400 );
		}

		/** Sanitize @var $item_code */
		$item_code = Automator()->utilities->automator_sanitize( $request->get_param( 'item_code' ) );

		// Check defaults
		$default_meta     = $request->has_param( 'default_meta' ) ? $request->get_param( 'default_meta' ) : array();
		$default_meta     = ! empty( $default_meta ) && is_array( $default_meta ) ? (array) Automator()->utilities->automator_sanitize( $default_meta, 'mixed' ) : false;
		$integration_code = '';
		if ( isset( $default_meta['integration'] ) ) {
			$integration_code = $default_meta['integration'];
			unset( $default_meta['integration'] );
		}

		if ( 'create_trigger' === $action ) {
			Automator()->set_recipe_part_meta( $post_id, $item_code, $integration_code, $post_type, $default_meta );

			update_post_meta( $post_id, 'sentence_human_readable', $sentence );
			$add_action_hook = Automator()->get->trigger_actions_from_trigger_code( $item_code );
			update_post_meta( $post_id, 'add_action', $add_action_hook );

			// Added NUMTIMES as a default to fix missing meta
			update_post_meta( $post_id, 'NUMTIMES', 1 );

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
			Automator()->set_recipe_part_meta( $post_id, $item_code, $integration_code, $post_type, $default_meta );

			/**
			 * @since 4.5
			 */
			$actions_order = $request->has_param( 'actions_order' ) ? $request->get_param( 'actions_order' ) : array();
			if ( ! empty( $actions_order ) ) {
				foreach ( $actions_order as $index => $__action_id ) {
					if ( 'new_action' === $__action_id ) {
						$__action_id             = $post_id;
						$actions_order[ $index ] = $post_id;
					}
					Automator()->db->action->update_menu_order( $__action_id, ( $index + 1 ) * 10 );
				}
			}
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
			Automator()->set_recipe_part_meta( $post_id, $item_code, $integration_code, $post_type, $default_meta );

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

		if ( ! empty( $default_meta ) && is_array( $default_meta ) ) {
			foreach ( $default_meta as $meta_key => $meta_value ) {
				if (
					true === apply_filters( 'automator_sanitize_input_fields', true, $meta_key, $meta_value, $recipe->ID ) &&
					true === apply_filters( 'automator_sanitize_input_fields_' . $recipe->ID, true, $meta_key, $meta_value )
				) {
					$meta_value = Automator()->utilities->automator_sanitize( $meta_value );
					$meta_key   = Automator()->utilities->automator_sanitize( $meta_key );
				}
				update_post_meta( $post_id, $meta_key, $meta_value );
			}
		}
		Automator()->cache->clear_automator_recipe_part_cache( $recipe->ID );

		$return                   = array();
		$return['success']        = true;
		$return['post_ID']        = $post_id;
		$return['action']         = $action;
		$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe->ID );
		$return['_integrations']  = Automator()->get_recipe_integrations( $recipe->ID );
		$return['_recipe']        = Automator()->get_recipe_object( $recipe->ID );

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
				$actions_order = $request->has_param( 'actions_order' ) ? $request->get_param( 'actions_order' ) : array();
				if ( ! empty( $actions_order ) ) {
					foreach ( $actions_order as $index => $__action_id ) {
						Automator()->db->action->update_menu_order( $__action_id, ( $index + 1 ) * 10 );
					}
				}
				Automator()->cache->clear_automator_recipe_part_cache( $request->get_param( 'ID' ) );

				$recipe_id = absint( $request->get_param( 'recipe_id' ) );

				if ( 'uo-recipe' !== get_post_type( $recipe_id ) ) {
					$ancestors = get_post_ancestors( $recipe_id );
					$recipe_id = array_pop( $ancestors );
				}

				$return['message']        = 'Deleted!';
				$return['success']        = true;
				$return['delete_posts']   = $delete_posts;
				$return['action']         = 'deleted-' . $delete_posts->post_type;
				$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );
				$return['_recipe']        = Automator()->get_recipe_object( absint( $request->get_param( 'recipe_id' ) ) );

				/**
				 * Fires when a recipe item is deleted.
				 *
				 * @since 5.7
				 */
				do_action( 'automator_recipe_item_deleted', $request->get_param( 'ID' ), $recipe_id, $return );

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

		do_action( 'automator_recipe_before_options_update', $request );

		if ( $request->has_param( 'itemId' ) && is_numeric( $request->get_param( 'itemId' ) ) && $request->has_param( 'optionCode' ) && $request->has_param( 'optionValue' ) ) {
			$item_id    = absint( $request->get_param( 'itemId' ) );
			$recipe_id  = absint( $request->get_param( 'recipe_id' ) );
			$meta_key   = (string) Automator()->utilities->automator_sanitize( $request->get_param( 'optionCode' ) );
			$meta_value = $request->get_param( 'optionValue' );
			$meta_value = Automator()->utilities->automator_sanitize( $meta_value, 'mixed', $meta_key, $request->get_param( 'options' ) );

			/*
			 * Save human readable sentence that will be stored as trigger and action meta.
			 * Once a trigger is completed, the human readable post meta value will be saved as trigger or action log
			 * meta fr the user to have more detail about it in the logs.
			 */
			if ( $request->has_param( 'sentence_human_readable' ) ) {
				$human_readable = sanitize_text_field( $request->get_param( 'sentence_human_readable' ) );
				// Fix for 4.2.1.2 where token is erroneously parsed because of four brackets.
				$human_readable = strtr(
					$human_readable,
					array(
						'{{{{' => '{{',
						'}}}}' => '}}',
					)
				);
				update_post_meta( $item_id, 'sentence_human_readable', $human_readable );
			}

			if ( $request->has_param( 'sentence_human_readable_html' ) ) {
				$human_readable = $request->get_param( 'sentence_human_readable_html' );
				update_post_meta( $item_id, 'sentence_human_readable_html', $human_readable );
			}

			// Update trigger 'add_action' meta.
			if ( $request->has_param( 'trigger_item_code' ) ) {
				$trigger_item_code = sanitize_text_field( $request->get_param( 'trigger_item_code' ) );
				$add_action_hook   = Automator()->get->trigger_actions_from_trigger_code( $trigger_item_code );
				update_post_meta( $item_id, 'add_action', $add_action_hook );
			}

			// Make sure the parent post exists
			$item = get_post( $item_id );

			if ( $item ) {

				// @since 6.0
				do_action( 'automator_recipe_before_update', $item, $request );

				$before_update_value = get_post_meta( $item_id, $meta_key, true );

				if ( is_array( $meta_value ) ) {
					// Allow integrations to hook into the filter.
					$meta_value = apply_filters( 'automator_field_values_before_save', $meta_value, $item );

					foreach ( $meta_value as $meta_key => $meta_val ) {
						$meta_val = Automator()->utilities->maybe_slash_json_value( $meta_val, true );
						update_post_meta( $item_id, $meta_key, $meta_val );

						/**
						 * Added in case the action uses another action's token,
						 * and it happens to use Background processing
						 *
						 * @since 4.6
						 */
						$this->has_action_token( $item, $meta_val );
					}
				} else {
					$meta_value = Automator()->utilities->maybe_slash_json_value( $meta_value, true );
					update_post_meta( $item_id, $meta_key, $meta_value );

					/**
					 * Added in case the action uses another action's token,
					 * and it happens to use Background processing
					 *
					 * @since 4.6
					 */
					$this->has_action_token( $item, $meta_value );
				}

				do_action( 'automator_recipe_option_updated_before_cache_is_cleared', $item, $recipe_id );

				Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

				$return['message']        = 'Option updated!';
				$return['success']        = true;
				$return['action']         = 'updated_option';
				$return['data']           = array( $item, $meta_key, $meta_value );
				$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );
				$return['_recipe']        = Automator()->get_recipe_object( $recipe_id );

				/**
				 * Fires when a recipe option is updated.
				 *
				 * @since 5.7
				 */
				do_action( 'automator_recipe_option_updated', $item, $meta_key, $meta_value, $before_update_value, $recipe_id, $return );

				$return = apply_filters( 'automator_option_updated', $return, $item, $meta_key, $meta_value );

				return new WP_REST_Response( $return, 200 );
			}
			$return['message'] = 'You are trying to update trigger meta for a trigger that does not exist. Please reload the page and trying again.';
			$return['success'] = false;
			$return['data']    = $request;
			$return['post']    = '';

			return new WP_REST_Response( $return, 200 );
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

				// Fallback code to add add_action meta in < 3.0 triggers.
				$this->process_post_migratable( $post_id );

				if ( $updated ) {
					$return['message'] = 'Updated!';
					$return['success'] = true;
					$return['action']  = 'updated_post';
					Automator()->cache->clear_automator_recipe_part_cache( $post_id );

					if ( ! $request->has_param( 'recipe_id' ) ) {
						$recipe_id = $post_id;
						if ( 'uo-recipe' !== get_post_type( $post_id ) ) {
							$ancestors = get_post_ancestors( $post_id );
							$recipe_id = array_pop( $ancestors );
						}
					} else {
						$recipe_id = absint( $request->get_param( 'recipe_id' ) );
					}

					$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );

					$recipe_object = Automator()->get_recipe_object( $recipe_id );

					$return['_recipe'] = $recipe_object;

					/**
					 * Fires when a recipe status is changed. Added for Scheduled and Delayed actions.
					 *
					 * @since 5.7
					 */
					do_action( 'automator_recipe_status_updated', $post_id, $recipe_id, $post_status, $return );

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
					$return['_recipe']        = Automator()->get_recipe_object( $post_id );

					/**
					 * Fires when a recipe type is updated.
					 *
					 * @since 5.7
					 */
					do_action( 'automator_recipe_type_updated', $post_id, $recipe_type, $return );

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
	public function set_walkthrough_progress( WP_REST_Request $request ) {

		if ( $request->has_param( 'id' ) && $request->has_param( 'progress_percentage' ) && $request->has_param( 'step_id' ) && $request->has_param( 'close_requested' ) ) {

			$walkthrough_id  = sanitize_text_field( $request->get_param( 'id' ) );
			$step            = sanitize_text_field( $request->get_param( 'step_id' ) );
			$percent         = $request->has_param( 'progress_percentage' ) ? absint( $request->get_param( 'progress_percentage' ) ) : 0;
			$close_requested = $request->has_param( 'close_requested' ) ? filter_var( $request->get_param( 'close_requested' ), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : false;
			$show            = ! $close_requested && $percent < 100;

			$updated = Automator()->utilities->set_user_walkthrough_progress(
				get_current_user_id(),
				$walkthrough_id,
				array(
					'show'      => $show ? 1 : 0,
					'step'      => $step,
					'progress'  => $percent,
					'dismissed' => $close_requested,
				)
			);

			if ( false !== $updated ) {
				$return = array(
					'message' => 'Updated!',
					'success' => true,
					'action'  => 'updated_option',
				);

				/**
				 * Fires when a walkthrough mode is updated.
				 *
				 * @since 5.8
				 */
				do_action( 'automator_user_walkthrough_progress_updated', $updated, $return );

				return new WP_REST_Response( $return, 200 );
			}
		}

		$return = array(
			'message' => 'Failed to update',
			'success' => false,
			'action'  => 'show_error',
		);

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
					$return['_recipe']        = Automator()->get_recipe_object( $post_id, 'JSON' );

					/**
					 * Fires when a recipe title is updated.
					 *
					 * @since 5.7
					 */
					do_action( 'automator_recipe_title_updated', $post_id, $post_title, $return );

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
	public function change_recipe_notes( WP_REST_Request $request ) {

		// Validate we have a post ID and notes is set.
		$post_id = $request->has_param( 'post_ID' ) ? absint( $request->get_param( 'post_ID' ) ) : 0;
		if ( empty( $post_id ) || ! $request->has_param( 'notes' ) ) {
			return new WP_REST_Response(
				array(
					'message' => 'Failed to update',
					'success' => false,
					'action'  => 'show_error',
				),
				200
			);
		}

		// Sanitize the notes.
		$notes = sanitize_textarea_field( trim( $request->get_param( 'notes' ) ) );

		// If the notes are empty, delete the post meta.
		if ( empty( $notes ) ) {
			delete_post_meta( $post_id, 'uap_recipe_notes' );
		} else {
			// Update the post meta.
			update_post_meta( $post_id, 'uap_recipe_notes', $notes );
		}

		$return = array(
			'message'        => 'Updated!',
			'success'        => true,
			'action'         => 'updated_post',
			'recipes_object' => Automator()->get_recipes_data( true, $post_id ),
			'_recipe'        => Automator()->get_recipe_object( $post_id ),
		);

		/**
		 * Fires when recipe notes are updated.
		 *
		 * @since 5.8
		*/
		do_action( 'automator_recipe_notes_updated', $post_id, $notes, $return );

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
			$return['_recipe']        = Automator()->get_recipe_object( $post_id );

			/**
			 * Fires when a recipe completions allowed is updated.
			 *
			 * @since 5.7
			 */
			do_action( 'automator_recipe_completions_allowed_updated', $post_id, $recipe_completions_allowed, $return );

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
			$return['_recipe']        = Automator()->get_recipe_object( $post_id );

			/**
			 * Fires when a recipe max completions allowed is updated.
			 *
			 * @since 5.7
			 */
			do_action( 'automator_recipe_max_completions_allowed_updated', $post_id, $recipe_completions_allowed, $return );

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
			$term_ids     = array();
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

			/**
			 * Fires when a recipe terms are updated.
			 *
			 * @since 5.7
			 */
			do_action( 'automator_recipe_terms_updated', $recipe_id, $taxonomy, $term_ids, $return );

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
			$return['_recipe']        = Automator()->get_recipe_object( $recipe_id );

			/**
			 * Fires when a recipe user selector is updated.
			 *
			 * @since 5.7
			 */
			do_action( 'automator_recipe_user_selector_updated', $recipe_id, $source, $fields, $return );

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

			if ( $request->has_param( 'delayNumber' ) && ! empty( $request->get_param( 'delayNumber' ) ) && $request->has_param( 'delayUnit' ) ) {

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

			if ( $request->has_param( 'customValue' ) ) {

				update_post_meta( $post_id, 'async_custom', $request->get_param( 'customValue' ) );

				$return['success'] = true;

			}

			if ( $return['success'] ) {
				Automator()->cache->remove( Automator()->cache->recipes_data );

				$return['post_ID']        = $post_id;
				$return['action']         = 'schedule_action';
				$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );
				$return['_recipe']        = Automator()->get_recipe_object( $recipe_id );

				/**
				 * Fires when a recipe schedule action is scheduled.
				 *
				 * @since 5.7
				 */
				do_action( 'automator_recipe_schedule_action_scheduled', $post_id, $recipe_id, $return );

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

			Utilities::log( 'Removing schedule $request: ' . var_export( $request, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

			$post_id   = (int) $request->get_param( 'actionId' );
			$recipe_id = (int) $request->get_param( 'recipeId' );

			$return = array();

			delete_post_meta( $post_id, 'async_mode' );
			delete_post_meta( $post_id, 'async_delay_unit' );
			delete_post_meta( $post_id, 'async_delay_number' );
			delete_post_meta( $post_id, 'async_schedule_time' );
			delete_post_meta( $post_id, 'async_schedule_date' );
			delete_post_meta( $post_id, 'async_sentence' );
			delete_post_meta( $post_id, 'async_custom' );

			$return['success']        = true;
			$return['post_ID']        = $post_id;
			$return['action']         = 'remove_schedule';
			$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );
			$return['_recipe']        = Automator()->get_recipe_object( $recipe_id );

			/**
			 * Fires when a recipe schedule action is removed.
			 *
			 * @since 5.7
			 */
			do_action( 'automator_recipe_schedule_remove_from_action', $post_id, $recipe_id, $return );

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
			// Adding user selector
			update_post_meta( $recipe_id, 'recipe_requires_user', $requires_user );
			// User selector is removed
			if ( ! $requires_user ) {
				delete_post_meta( $recipe_id, 'source' );
				delete_post_meta( $recipe_id, 'fields' );
			}

			$return['message'] = 'Updated!';
			$return['success'] = true;
			$return['action']  = 'updated_recipe';
			Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

			$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );
			$return['_recipe']        = Automator()->get_recipe_object( $recipe_id );

			/**
			 * Fires when a recipe requires user is updated.
			 *
			 * @since 5.7
			 */
			do_action( 'automator_recipe_requires_user_updated', $recipe_id, $requires_user, $return );

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
		if ( $request->has_param( 'recipe_id' ) && $request->has_param( 'actions_order' ) ) {

			$recipe_id         = absint( $request->get_param( 'recipe_id' ) );
			$actions_order     = $request->has_param( 'actions_order' ) ? $request->get_param( 'actions_order' ) : array();
			$return['message'] = 'The action order array is empty.';
			$return['success'] = false;
			$return['action']  = 'update_actions_order';
			if ( ! empty( $actions_order ) ) {
				// Update the actions menu_order here
				foreach ( $actions_order as $index => $action_id ) {
					Automator()->db->action->update_menu_order( $action_id, ( $index + 1 ) * 10 );
				}
				$return['message'] = 'Updated!';
				$return['success'] = true;
			}

			Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

			$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );
			$return['_recipe']        = Automator()->get_recipe_object( $recipe_id );

			/**
			 * Fires when a recipe actions order is updated.
			 *
			 * @since 5.7
			 */
			do_action( 'automator_recipe_actions_order_updated', $recipe_id, $actions_order, $return );

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

			/**
			 * Fires when a recipe all or any trigger option is updated.
			 *
			 * @since 5.7
			 */
			do_action( 'automator_recipe_all_or_any_trigger_option_updated', $recipe_id, $all_or_any, $return );

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
	public function duplicate_action( WP_REST_Request $request ) {

		// Make sure we have a recipe ID and the newOrder
		if ( ! $request->has_param( 'recipe_id' ) || ! $request->has_param( 'action_id' ) ) {
			$return['message'] = 'Recipe or Action ID is empty';
			$return['success'] = false;
			$return['action']  = 'show_error';

			return new WP_REST_Response( $return, 400 );
		}

		$recipe_id   = absint( $request->get_param( 'recipe_id' ) );
		$action_id   = $request->get_param( 'action_id' );
		$action_post = get_post( $action_id );
		if ( ! $action_post instanceof \WP_Post ) {
			$return['message'] = 'Action does not exist';
			$return['success'] = false;
			$return['action']  = 'show_error';

			return new WP_REST_Response( $return, 400 );
		}

		if ( 'uo-action' !== $action_post->post_type ) {
			$return['message'] = 'Action is not of the correct post type';
			$return['success'] = false;
			$return['action']  = 'show_error';

			return new WP_REST_Response( $return, 400 );
		}

		if ( ! automator_duplicate_recipe_part( $action_id, $recipe_id, 'draft' ) ) {
			$return['message'] = 'Something went wrong';
			$return['success'] = false;
			$return['action']  = 'show_error';

			return new WP_REST_Response( $return, 400 );
		}
		Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

		$return                   = array();
		$return['success']        = true;
		$return['post_ID']        = $recipe_id;
		$return['action']         = 'add-new-action';
		$return['recipes_object'] = Automator()->get_recipes_data( true, $recipe_id );
		$return['_recipe']        = Automator()->get_recipe_object( $recipe_id );

		/**
		 * Fires when a recipe action is duplicated.
		 *
		 * @since 5.7
		 */
		do_action( 'automator_recipe_action_duplicated', $recipe_id, $return );

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function resend_api_request( WP_REST_Request $request ) {

		// Make sure we have a recipe ID and the newOrder
		if ( ! $request->has_param( 'item_log_id' ) ) {
			$return['success'] = false;
			$return['message'] = esc_html__( 'Action Log ID is empty', 'uncanny-automator' );

			return new WP_REST_Response( $return, 400 );
		}

		$item_log_id = absint( $request->get_param( 'item_log_id' ) );

		$api_request = Automator()->db->api->get_by_log_id( 'action', $item_log_id );

		if ( empty( $api_request->params ) ) {
			$return['success'] = false;
			$return['message'] = esc_html__( 'Missing action params', 'uncanny-automator' );
		}

		$params = maybe_unserialize( $api_request->params );

		$params['resend'] = true;

		if ( 'internal:webhook' === $api_request->endpoint ) {
			return $this->replay_as_webhook( $api_request );
		}

		try {
			$response = Api_Server::api_call( $params );
		} catch ( Exception $e ) {
			$return['success'] = false;
			$return['message'] = $e->getMessage();
			automator_log( $e->getMessage() );

			// Log the response for retries.
			if ( true === $params['resend'] ) {
				$this->log_api_retry_response(
					$item_log_id,
					Automator_Status::get_class_name( Automator_Status::COMPLETED_WITH_ERRORS ),
					$return['message']
				);
			}

			return new WP_REST_Response( $return, $e->getCode() );
		}

		$return['message'] = esc_html__( 'The request has been successfully resent', 'uncanny-automator' );
		$return['success'] = true;

		// Log the success response for retries.
		if ( true === $params['resend'] ) {
			$this->log_api_retry_response(
				$item_log_id,
				Automator_Status::get_class_name( Automator_Status::COMPLETED ),
				$return['message']
			);
		}

		/**
		 * Fires after a successful API request.
		 *
		 * @since 5.7
		 */
		do_action( 'automator_recipe_app_request_resent', $item_log_id, $return );

		return new WP_REST_Response( $return, 200 );
	}

	/**
	 * Replay the action as webhook.
	 *
	 * @param mixed $api_request
	 *
	 * @return WP_REST_Response
	 */
	protected function replay_as_webhook( $api_request ) {

		$success = true;
		$message = _x( 'The request has been successfully resent', 'Webhooks', 'uncanny-automator' );

		if ( ! isset( $api_request->request ) || ! isset( $api_request->params ) ) {
			$success = false;
			$message = _x( 'Invalid data. Property "request" or "params" is missing.', 'Webhooks', 'uncanny-automator' );
		}

		$params  = (array) maybe_unserialize( $api_request->params );
		$request = (array) maybe_unserialize( $api_request->request );

		try {

			if ( ! isset( $request['http_url'] ) || ! isset( $params['method'] ) ) {
				throw new Exception( 'Invalid data. Cannot find "http_url" or "method".', 400 );
			}

			Response_Validator::validate_webhook_response(
				Automator_Send_Webhook::call_webhook( $request['http_url'], $params, $params['method'] )
			);

		} catch ( Exception $e ) {

			$this->log_api_retry_response(
				$api_request->item_log_id,
				Automator_Status::get_class_name( Automator_Status::COMPLETED_WITH_ERRORS ),
				$e->getMessage()
			);

			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $e->getMessage(),
				),
				200
			);

		}

		$response = array(
			'success' => $success,
			'message' => $message,
		);

		$this->log_api_retry_response(
			$api_request->item_log_id,
			Automator_Status::get_class_name( Automator_Status::COMPLETED ),
			$message
		);

		/**
		 * Fires when a webhook request is replayed.
		 *
		 * @since 5.7
		 */
		do_action( 'automator_recipe_webhook_request_replayed', $api_request->item_log_id, $response );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Log API retry responses.
	 *
	 * @param int $item_log_id The item log id.
	 * @param string $result The result.
	 * @param string $message The message.
	 *
	 * @return int|false The ID of the last inserted log response. Returns false otherwise.
	 */
	protected function log_api_retry_response( $item_log_id = 0, $result = '', $message = '' ) {

		global $wpdb;

		// Figure out the last insert ID since we cannot directly access it.
		$last_insert_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(id) as last_insert_id FROM {$wpdb->prefix}uap_api_log WHERE 1=%d",
				1
			)
		);

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'uap_api_log_response',
			array(
				'api_log_id'  => $last_insert_id,
				'item_log_id' => $item_log_id,
				'result'      => $result,
				'message'     => $message,
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
			)
		);

		if ( false !== $inserted ) {
			return $wpdb->insert_id;
		}

		return false;

	}

	/**
	 * @param $item
	 * @param $meta_value
	 *
	 * @return void
	 */
	public function has_action_token( $item, $meta_value ) {
		if ( 'uo-action' !== $item->post_type ) {
			return;
		}

		// Match action tokens
		preg_match_all( '/{{ACTION\_(FIELD|META)\:(.*?)}}/', $meta_value, $matches );
		// Nothing matched
		if ( empty( $matches ) || ! array_key_exists( 0, $matches ) || empty( $matches[0] ) ) {
			return;
		}
		$already_updated = array();
		foreach ( $matches[0] as $action_token ) {
			/**
			 * SAMPLE: {{ACTION_META:74046:FACEBOOK_GROUPS_PUBLISH_POST:POST_LINK}}
			 * 0 = ACTION_META
			 * 1 = 74046
			 * 2 = FACEBOOK_GROUPS_PUBLISH_POST
			 * 3 = POST_LINK
			 */
			$raw = explode( ':', str_replace( array( '{', '}' ), '', $action_token ) );
			// Get action ID
			$action_id = isset( $raw[1] ) ? absint( $raw[1] ) : null;
			// Get action code
			$action_code = isset( $raw[2] ) ? sanitize_text_field( $raw[2] ) : null;
			// Check if the action has background processing set to true
			$has_background_processing = Automator()->get->action_has_background_processing( $action_code );
			if ( ! $has_background_processing ) {
				continue;
			}
			if ( null !== $action_id && ! in_array( $action_id, $already_updated, true ) ) {
				// Update that action's postmeta to skip background processing
				update_post_meta( $action_id, Background_Actions::IS_USED_FOR_ACTION_TOKEN, $item->ID );
				$already_updated[] = $action_id;
			}
		}
	}

	/**
	 * Processes migratable posts (e.g. Triggers that do not have `add_action` (pre 3.0)).
	 *
	 * @param integer $post_id The post ID.
	 *
	 * @return boolean True, always.
	 */
	private function process_post_migratable( $post_id ) {

		// Check the current post type.
		$object = get_post( $post_id );

		if ( 'uo-action' === $object->post_type || 'uo-trigger' === $object->post_type ) {

			$post_id = wp_get_post_parent_id( $post_id );

		}

		// Otherwise, assume $post_id is a recipe.
		$triggers = $this->fetch_all_triggers_with_missing_hook_from_recipe( $post_id );

		if ( ! empty( $triggers ) ) {
			// If there are any missing `add_action` in the current recipe triggers, do migrate.
			( new \Uncanny_Automator\Migrations\Migrate_Triggers() )->migrate();
		}

		return true;

	}

	/**
	 * Fetches all triggers with a missing hook from a given recipe.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return array The triggers that are missing `add_action` post_meta.
	 */
	private function fetch_all_triggers_with_missing_hook_from_recipe( $recipe_id ) {

		global $wpdb;

		// Retrieve all triggers first from current recipe.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT posts.ID , pm.meta_value as trigger_code
				FROM $wpdb->posts as posts
				JOIN $wpdb->postmeta pm
				ON posts.ID = pm.post_id and pm.meta_key = 'code'
				WHERE posts.post_type = %s
				AND posts.post_parent = %d
				AND posts.post_type = 'uo-trigger'
				AND NOT EXISTS (
						SELECT * FROM $wpdb->postmeta
						WHERE $wpdb->postmeta.meta_key = 'add_action'
						AND $wpdb->postmeta.post_id=posts.ID
					)
				",
				'uo-trigger',
				$recipe_id
			),
			ARRAY_A
		);

	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function triggers_change_logic( WP_REST_Request $request ) {

		// Make sure we have a recipe ID and the newOrder
		if ( ! $request->has_param( 'recipe_id' ) || ! $request->has_param( 'trigger_logic' ) ) {
			$return['message'] = 'Recipe or Trigger logic is not available';
			$return['success'] = false;
			$return['action']  = 'show_error';

			return new WP_REST_Response( $return, 400 );
		}

		$recipe_id     = absint( $request->get_param( 'recipe_id' ) );
		$trigger_logic = sanitize_text_field( $request->get_param( 'trigger_logic' ) );
		update_post_meta( $recipe_id, 'automator_trigger_logic', $trigger_logic );
		Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );

		$return            = array();
		$return['success'] = true;
		$return['_recipe'] = Automator()->get_recipe_object( absint( $request->get_param( 'recipe_id' ) ) );

		return new WP_REST_Response( $return, 200 );
	}
}
