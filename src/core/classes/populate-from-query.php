<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe_Post_Type as Recipe;

/**
 * Class Populate_From_Query.
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
	 * Initialize class, populate the variables from the hook call.
	 *
	 * @param \WP_Post
	 */
	public static function init( $post ) {

		self::$recipe = new Recipe();
		self::$post   = $post;

	}

	/**
	 * Figure out if this post needs to be populated from the query params.
	 *
	 * @param $post_ID
	 * @param \WP_Post
	 * @param $update
	 */
	public static function maybe_populate( $post_ID, $post, $update ) {

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
		} catch ( \Throwable $e ) {

			error_log( 'Uncanny_Automator\Populate_From_Query: ' . $e->getMessage() );

		}

	}

	/**
	 * Check if query arguments exist in the GET array.
	 *
	 * @param $args
	 * @return bool
	 */
	protected static function query_args_exist( $args ) {

		foreach ( $args as $arg ) {

			if ( ! isset( $_GET[ $arg ] ) ) {

				return false;

			}
		}

		return true;
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
	 * Populates the recipe.
	 *
	 * @return bool
	 */
	protected static function populate() {

		switch ( $_GET['action'] ) {

			case 'add-new-trigger':
				if ( self::query_args_exist( array( 'item_code' ) ) ) {

					return self::add_new_trigger();

				} else {

					throw new \Exception( 'Missing item code.' );

				}

				break;

			default:
				throw new \Exception( 'Unknown action.' );

				break;
		}

		return true;

	}

	/**
	 * Creates a trigger.
	 *
	 * @return bool
	 */
	public static function add_new_trigger() {

		self::change_recipe_type();

		$trigger_id = self::add_trigger();

		// Trigger was created, check if its meta needs to be set
		self::maybe_update_trigger( $trigger_id );

		// add do_action for external hooks
		do_action( 'uap_trigger_populated_from_query', self::$post, $trigger_id, $_GET );

		return true;

	}

	/**
	 * Decides if trigger needs a value.
	 *
	 * @return bool
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
	 * Checks the nonce and capabilities.
	 *
	 * @return bool
	 */
	private static function is_authorized() {

		if ( ! wp_verify_nonce( $_GET['nonce'], self::$nonce ) ) {

			throw new \Exception( 'Invalid nonce.' );

		}

		// Use the save_setting_permissings function from the recipe class to check required capabilities.
		return self::$recipe->save_settings_permissions();

	}

	/**
	 * Populates the POST array with the data and adds a trigger.
	 *
	 * @return string trigger ID
	 */
	public static function add_trigger() {

		$_POST['recipePostID'] = self::$post->ID;
		$_POST['action']       = $_GET['action'];
		$_POST['item_code']    = $_GET['item_code'];

		$trigger_added = self::$recipe->add( '' );

		if ( ! $trigger_added->data['success'] ) {

			throw new \Exception( "Trigger couldn't be added." );

		} else {

			return $trigger_added->data['post_ID'];

		}

	}

	/**
	 * Populates the POST array with the data and adds trigger value.
	 *
	 * @return bool
	 */
	public static function add_trigger_value( $trigger_id ) {

		$_POST['itemId']      = $trigger_id;
		$_POST['optionCode']  = $_GET['optionCode'];
		$_POST['optionValue'] = array(
			$_POST['optionCode']               => $_GET['optionValue'],
			$_POST['optionCode'] . '_readable' => urldecode( $_GET['optionValue_readable'] ),
		);

		$trigger_value_added = self::$recipe->update( '' );

		if ( ! $trigger_value_added->data['success'] ) {

			throw new \Exception( "Trigger value couldn't be set." );

		}

		return true;

	}

	/**
	 * Changes recipe type to logged-in.
	 *
	 * @return bool
	 */
	public static function change_recipe_type() {

		$_POST['post_ID']     = self::$post->ID;
		$_POST['recipe_type'] = 'user';

		$recipe_type_changed = self::$recipe->change_post_recipe_type( '' );

		if ( ! $recipe_type_changed->data['success'] ) {

			throw new \Exception( "Recipe type couldn't be changed." );

		}

		return true;

	}

	/**
	 * Populates the POST array and publishes the trigger.
	 *
	 * @return bool
	 */
	public static function publish_trigger( $trigger_id ) {

		$_POST['post_ID']     = $trigger_id;
		$_POST['post_status'] = 'publish';

		$trigger_status_changed = self::$recipe->change_post_status( '' );

		if ( ! $trigger_status_changed->data['success'] ) {

			throw new \Exception( "Trigger couldn't be published." );

		}

		return true;

	}

}
