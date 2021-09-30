<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe_Post_Rest_Api as Recipe_Api;

/**
 * Class Populate_From_Query.
 * @package Uncanny_Automator
 */
class Import_Recipe {

	/**
	 * Recipe functions.
	 *
	 * @var Recipe_Post_Rest_Api
	 */
	public $recipe_api;

	/**
	 * Initialize class, populate the variables from the hook call.
	 *
	 * @param \WP_Post
	 */
	public function __construct() {

		$this->recipe_api = new Recipe_Api();

	}

	public function import_from_file( $path ) {

		$recipe = $this->load_file( $path );

		$recipe_id = $this->import_from_array( $recipe );

		return $recipe_id;
	}

	public function load_file( $path ) {

		$string = file_get_contents( $path );

		$json_array = json_decode( $string, true );

		return $json_array;
	}

	public function import_from_array( $recipe ) {

		$recipe['ID'] = $this->create_recipe_post( $recipe );

		if ( ! empty( $recipe['triggers'] ) ) {
			foreach ( $recipe['triggers'] as &$trigger ) {
				$trigger['ID'] = $this->add_trigger( $recipe['ID'], $trigger );
				if ( $trigger['ID'] ) {
					$this->set_values( $trigger );
					$this->set_status( $trigger );
				}
			}
		}

		if ( ! empty( $recipe['actions'] ) ) {
			foreach ( $recipe['actions'] as $action ) {
				$action['ID'] = $this->add_action( $recipe['ID'], $action );
				if ( $action['ID'] ) {
					$this->set_values( $action, $recipe );
					$this->set_status( $action );
				}
			}
		}


		$this->set_status( $recipe );

		return $recipe['ID'];

	}

	public function create_recipe_post( $recipe ) {

		$recipe_title = $recipe['title'] ? $recipe['title'] : 'Imported recipe';

		$recipe_post = array(
			'post_type'   => 'uo-recipe',
			'post_title'  => wp_strip_all_tags( $recipe_title ),
			'post_author' => get_current_user_id(),
		);

		return wp_insert_post( $recipe_post );
	}

	public function add_trigger( $recipe_id, $trigger ) {

		$request = new \WP_REST_Request( 'POST' );

		$request->set_param( 'recipePostID', $recipe_id );
		$request->set_param( 'action', 'add-new-trigger' );
		$request->set_param( 'item_code', $trigger['code'] );

		$trigger_added = $this->recipe_api->add( $request );


		if ( ! $trigger_added->data['success'] ) {

			throw new \Exception( "Trigger couldn't be added." );

		} else {

			return $trigger_added->data['post_ID'];

		}

		return;
	}


	public function add_action( $recipe_id, $action ) {

		$request = new \WP_REST_Request( 'POST' );

		$request->set_param( 'recipePostID', $recipe_id );
		$request->set_param( 'action', 'add-new-action' );
		$request->set_param( 'item_code', $action['code'] );

		$action_added = $this->recipe_api->add( $request );

		if ( ! $action_added->data['success'] ) {

			throw new \Exception( "Action couldn't be added." );

		} else {

			return $action_added->data['post_ID'];

		}

		return;
	}

	public function set_values( $item, $recipe = null ) {

		$request = new \WP_REST_Request( 'POST' );

		$request->set_param( 'itemId', $item['ID'] );

		$options = array();

		if ( empty( $item['fields'] ) ) {
			return true;
		}

		foreach ( $item['fields'] as $option ) {

			$request = new \WP_REST_Request( 'POST' );

			$request->set_param( 'itemId', $item['ID'] );
			$request->set_param( 'optionCode', $option['meta'] );
			$option_value = array();
			if ( is_array( $option['value'] ) ) {
				$option_value = json_encode( $option['value'] );
			} else {
				$option_value = $option['value'];
			}

			$request->set_param( 'optionValue', $this->parse_tokens( $option_value, $recipe ) );

			$trigger_value_added = $this->recipe_api->update( $request );

			if ( ! $trigger_value_added->data['success'] ) {

				throw new \Exception( "Trigger value couldn't be set." );

			}
		}

		return true;
	}

	public function set_status( $item ) {
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param( 'post_ID', $item['ID'] );
		$request->set_param( 'post_status', $item['status'] );

		$item_status_changed = $this->recipe_api->change_post_status( $request );

		if ( ! $item_status_changed->data['success'] ) {

			throw new \Exception( "Item couldn't be published." );

		}

		return true;
	}

	public function parse_tokens( $text, $recipe ) {

		if ( ! $recipe || empty( $recipe['triggers'] ) ) {
			return $text;
		}

		foreach ( $recipe['triggers'] as $trigger ) {
			if ( ! empty( $trigger['name'] ) ) {
				$text = str_replace( '%' . $trigger['name'] . '%', $trigger['ID'], $text );
			}
		}

		return $text;
	}


}
