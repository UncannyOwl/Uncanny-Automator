<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe_Post_Rest_Api as Recipe;
use WP_REST_Request;

/**
 * Class Populate_From_Query.
 *
 * @package Uncanny_Automator
 */
class Populate_From_Query {

	/**
	 * WP Post object.
	 *
	 * @var \WP_Post
	 */
	static $post;

	/**
	 * Recipe functions.
	 *
	 * @var Recipe
	 */
	static $recipe;


	/**
	 * Nonce name.
	 *
	 * @var string
	 */
	static $nonce = 'Uncanny Automator';

	/**
	 * Figure out if this post needs to be populated from the query params.
	 *
	 * @param $post_id
	 * @param \WP_Post $post
	 * @param $update
	 *
	 * @return bool
	 */
	public static function maybe_populate( $post_id, $post, $update ) {

		self::init( $post );

		try {
			if ( self::is_new_recipe() ) {
				if ( self::query_args_exist( array( 'action', 'nonce' ) ) ) {
					if ( self::is_authorized() ) {
						return self::populate();
					}
				}

				return false;
			}
		} catch ( \Exception $e ) {
			Automator()->error->add_error( 555, 'Uncanny_Automator\Populate_From_Query: ' . $e->getMessage() );
		}

		return true;
	}

	/**
	 * Initialize class, populate the variables from the hook call.
	 *
	 * @param \WP_Post $post
	 */
	public static function init( $post ) {

		self::$recipe = new Recipe();
		self::$post   = $post;
	}

	/**
	 * Check if the post is an Automator recipe and is newly created.
	 *
	 * @return bool
	 */
	protected static function is_new_recipe() {

		if ( 'uo-recipe' !== self::$post->post_type ) {

			return false;
		}

		if ( 'auto-draft' !== self::$post->post_status ) {

			return false;
		}

		return true;
	}

	/**
	 * Check if query arguments exist in the GET array.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected static function query_args_exist( $args ) {

		foreach ( $args as $arg ) {

			if ( ! automator_filter_has_var( $arg ) ) {

				return false;
			}
		}

		return true;
	}

	/**
	 * Checks the nonce and capabilities.
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	private static function is_authorized() {

		if ( ! wp_verify_nonce( automator_filter_input( 'nonce' ), self::$nonce ) ) {

			throw new Automator_Exception( 'Invalid nonce.' );

		}
		// To validate nonce
		$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_rest' );

		// Use the save_settings_permissions function from the recipe class to check required capabilities.
		return self::$recipe->save_settings_permissions();
	}

	/**
	 * Populates the recipe.
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	protected static function populate() {

		switch ( automator_filter_input( 'action' ) ) {

			case 'add-new-trigger':
				if ( self::query_args_exist( array( 'item_code' ) ) ) {

					return self::add_new_trigger();

				} else {

					throw new Automator_Exception( 'Missing item code.' );

				}

				break;

			default:
				throw new Automator_Exception( 'Unknown action.' );

				break;
		}
	}

	/**
	 * Creates a trigger.
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	public static function add_new_trigger() {

		self::change_recipe_type();

		$trigger_id = self::add_trigger();

		// Trigger was created, check if its meta needs to be set
		self::maybe_update_trigger( $trigger_id );

		// add do_action for external hooks
		do_action_deprecated(
			'uap_trigger_populated_from_query',
			array(
				self::$post,
				$trigger_id,
				$_GET, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			),
			'3.0',
			'automator_trigger_populated_from_query'
		);
		do_action( 'automator_trigger_populated_from_query', self::$post, $trigger_id, $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return true;
	}

	/**
	 * Changes recipe type to logged-in.
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	public static function change_recipe_type() {
		$request = new WP_REST_Request( 'POST', '', '' );
		$request->set_body_params(
			array(
				'post_ID'     => self::$post->ID,
				'recipe_type' => automator_filter_has_var( 'is_anon' ) ? 'anonymous' : 'user',
			)
		);

		$recipe_type_changed = self::$recipe->change_post_recipe_type( $request );

		if ( ! $recipe_type_changed->data['success'] ) {
			throw new Automator_Exception( "Recipe type couldn't be changed." );
		}

		return true;
	}

	/**
	 * Populates the POST array with the data and adds a trigger.
	 *
	 * @return string trigger ID
	 * @throws Automator_Exception
	 */
	public static function add_trigger() {
		$request = new WP_REST_Request( 'POST', '', '' );
		$request->set_body_params(
			array(
				'recipePostID' => self::$post->ID,
				'action'       => automator_filter_input( 'action' ),
				'item_code'    => automator_filter_input( 'item_code' ),
			)
		);

		$trigger_added = self::$recipe->add( $request );

		if ( ! $trigger_added->data['success'] ) {
			throw new Automator_Exception( "Trigger couldn't be added." );
		} else {

			return $trigger_added->data['post_ID'];
		}
	}

	/**
	 * Decides if trigger needs a value.
	 *
	 * @param $trigger_id
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	public static function maybe_update_trigger( $trigger_id ) {

		$trigger_value = self::query_args_exist( array( 'optionCode', 'optionValue', 'optionValue_readable' ) );

		if ( $trigger_value ) { // Check if trigger value needs to be populated

			self::add_trigger_value( $trigger_id );

			self::publish_trigger( $trigger_id );

		}

		return true;
	}

	/**
	 * Populates the POST array with the data and adds trigger value.
	 *
	 * @param $trigger_id
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	public static function add_trigger_value( $trigger_id ) {
		$option_code = automator_filter_input( 'optionCode' );
		$request     = new WP_REST_Request( 'POST', '', '' );
		$request->set_body_params(
			array(
				'itemId'      => $trigger_id,
				'optionCode'  => $option_code,
				'optionValue' => array(
					$option_code               => automator_filter_input( 'optionValue' ),
					$option_code . '_readable' => urldecode( automator_filter_input( 'optionValue_readable' ) ),
				),
			)
		);

		$trigger_value_added = self::$recipe->update( $request );

		if ( ! $trigger_value_added->data['success'] ) {
			throw new Automator_Exception( "Trigger value couldn't be set." );
		}

		return true;
	}

	/**
	 * Populates the POST array and publishes the trigger.
	 *
	 * @param $trigger_id
	 *
	 * @return bool
	 * @throws Automator_Exception
	 */
	public static function publish_trigger( $trigger_id ) {
		$request = new WP_REST_Request( 'POST', '', '' );
		$request->set_body_params(
			array(
				'post_ID'     => $trigger_id,
				'post_status' => 'publish',
			)
		);

		$trigger_status_changed = self::$recipe->change_post_status( $request );

		if ( ! $trigger_status_changed->data['success'] ) {
			throw new Automator_Exception( "Trigger couldn't be published." );
		}

		return true;
	}
}
