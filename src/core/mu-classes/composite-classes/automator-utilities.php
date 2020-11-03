<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Utilities
 * @package Uncanny_Automator
 */
class Automator_Utilities {

	public function __construct() {
	}

	/**
	 * Convert options array (for selects) from associative array to array of associative arrays
	 * We need this to keep the order of the options in the JS
	 *
	 * @param $item
	 *
	 * @return mixed $item;
	 */
	public function keep_order_of_options( $item ) {
		// Check if it has options
		if ( isset( $item['options'] ) ) {

			// Iterate each option
			foreach ( $item['options'] as $option_key => $option ) {

				// Check if it's a select and has options in the select
				if ( in_array( $option['input_type'], [
						'select',
						'radio'
					] ) && ( isset( $option['options'] ) && ! empty( $option['options'] ) ) ) {

					// Create array that will be used to create the new array of options
					$select_options = [];
					// Iterate each option
					foreach ( $option['options'] as $select_option_value => $select_option_text ) {
						$select_options[] = array(
							'value' => $select_option_value,
							'text'  => $select_option_text,
						);
					}

					// Replace old array for new one
					$item['options'][ $option_key ]['options'] = $select_options;
				}
			}
		}

		// Check if it has group of options
		if ( isset( $item['options_group'] ) ) {

			// Iterate each group of options
			foreach ( $item['options_group'] as $option_key => $fields ) {

				// Iterate each option inside a group of options
				foreach ( $fields as $field_index => $option ) {

					// Check if it's a select and has options in the select
					if ( in_array( $option['input_type'], [ 'select', 'radio' ] ) && isset( $option['options'] ) ) {

						// Create array that will be used to create the new array of options
						$select_options = [];

						// Iterate each option
						foreach ( $option['options'] as $select_option_value => $select_option_text ) {
							$select_options[] = array(
								'value' => $select_option_value,
								'text'  => $select_option_text,
							);
						}

						// Replace old array for new one
						$item['options_group'][ $option_key ][ $field_index ]['options'] = $select_options;
					}
				}
			}
		}

		return $item;
	}

	/**
	 * Sort integrations alphabetically
	 *
	 * @return null
	 */
	public function sort_integrations_alphabetically() {

		global $uncanny_automator;
		if ( ! $uncanny_automator->integrations ) {
			return null;
		}

		// Save integrations here
		$integrations = [];

		// Create an array with a list of integrations name
		$list_of_names = [];
		foreach ( $uncanny_automator->integrations as $integration_id => $integration ) {
			$list_of_names[ $integration_id ] = strtolower( $integration['name'] );
		}

		// Sort list of names alphabetically
		asort( $list_of_names );

		// Create a new integrations array with the correct order
		foreach ( $list_of_names as $integration_id => $integration_name ) {
			$integrations[ $integration_id ] = $uncanny_automator->integrations[ $integration_id ];
		}

		// Replace old array with new one
		$uncanny_automator->integrations = $integrations;

		return null;
	}

	/**
	 * @param null $recipe_id
	 * @param int $completed_times
	 *
	 * @return bool
	 */
	public function recipe_number_times_completed( $recipe_id = null, $completed_times = 0 ) {
		if ( is_null( $recipe_id ) ) {
			return false;
		}

		$post_meta = get_post_meta( $recipe_id, 'recipe_completions_allowed', true );
		if ( empty( $post_meta ) ) {
			$completions_allowed = 1;
			// Make sure that the recipe has recipe_completions_allowed saved. @version 2.9
			update_post_meta( $recipe_id, 'recipe_completions_allowed', 1 );
		} else {
			$completions_allowed = $post_meta;
		}


		$return = false;

		if ( intval( '-1' ) === intval( $completions_allowed ) ) {
			$return = false;
		} elseif ( (int) $completed_times >= (int) $completions_allowed ) {
			$return = true;
		}

		return $return;
	}

	/**
	 * @param null $recipe_ids
	 * @param int $recipes_completed_times
	 *
	 * @return array
	 */
	public function recipes_number_times_completed( $recipe_ids = null, $recipes_completed_times = 0 ) {
		global $wpdb;
		$times_to_complete = [];
		$post_metas        = $wpdb->get_results( "SELECT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key = 'recipe_completions_allowed' LIMIT 0, 99999" );
		if ( $post_metas && is_array( $recipe_ids ) ) {
			foreach ( $recipe_ids as $recipe_id ) {
				$complete = 1;
				$found    = false;
				foreach ( $post_metas as $p ) {
					if ( (int) $recipe_id === (int) $p->post_id ) {
						$found    = true;
						$complete = $p->meta_value;
						break;
					} else {
						$found = false;
					}
				}

				if ( $found ) {
					$times_to_complete[ $recipe_id ] = $complete;
				} else {
					$times_to_complete[ $recipe_id ] = 1; //Complete recipe once
				}
			}
		} elseif ( is_array( $recipe_ids ) ) {
			//Fallback to mark each recipe to be completed only once
			foreach ( $recipe_ids as $recipe_id ) {
				$times_to_complete[ $recipe_id ] = 1;
			}
		}

		$results = [];
		foreach ( $times_to_complete as $recipe_id => $recipe_completions_allowed ) {
			$time_to_complete = false;
			//Only added condition that changes value to true.
			if ( is_array( $recipes_completed_times ) && key_exists( $recipe_id, $recipes_completed_times ) && (int) $recipes_completed_times[ $recipe_id ] === (int) $recipe_completions_allowed ) {
				$time_to_complete = true;
			}
			$results[ $recipe_id ] = $time_to_complete;
		}

		return $results;
	}

	/**
	 * @param $post
	 */
	public function ajax_auth_check( $post ) {

		$capability = apply_filters( 'modify_recipe', 'edit_posts' );

		if ( ! current_user_can( $capability ) ) {
			$return['status'] = 'auth-failed';
			$return['error']  = 'You do not have permission to update options.';
			echo wp_json_encode( $return );
			die();
		}

		if ( empty( $post ) ) {
			$return['status'] = 'auth-failed';
			$return['error']  = '$_POST object is empty.';
			echo wp_json_encode( $return );
			die();
		}

		if ( ! isset( $post['nonce'] ) ) {
			$return['status'] = 'auth-failed';
			$return['error']  = 'nonce was not received.';
			echo wp_json_encode( $return );
			die();
		}

		if ( ! wp_verify_nonce( $post['nonce'], 'wp_rest' ) ) {
			$return['status'] = 'auth-failed';
			$return['error']  = 'nonce did not validate.';
			echo wp_json_encode( $return );
			die();
		}
	}

	/**
	 * @param null $condition
	 * @param int $number_to_match
	 * @param int $number_to_compare
	 *
	 * @return bool
	 */
	public function match_condition_vs_number( $condition = null, $number_to_compare = 0, $number_to_match = 0 ) { // TODO
		if ( null === $condition ) {
			return false;
		}

		$number_to_compare = number_format( $number_to_compare, 2, '.', '' );
		$number_to_match   = number_format( $number_to_match, 2, '.', '' );

		switch ( $condition ) {
			case '<':
				if ( $number_to_match < $number_to_compare ) {
					return true;
				} else {
					return false;
				}
				break;
			case '>':
				if ( $number_to_match > $number_to_compare ) {
					return true;
				} else {
					return false;
				}
				break;
			case '=':
				if ( $number_to_match === $number_to_compare ) {
					return true;
				} else {
					return false;
				}
				break;
			case '!=':
				if ( $number_to_match !== $number_to_compare ) {
					return true;
				} else {
					return false;
				}
				break;
			case '<=':
				if ( $number_to_match <= $number_to_compare ) {
					return true;
				} else {
					return false;
				}
				break;
			case '>=':
				if ( $number_to_match >= $number_to_compare ) {
					return true;
				} else {
					return false;
				}
				break;
			default:
				return false;
		}
	}

	/**
	 * Get the recipe type
	 *
	 * @param int $recipe_id
	 *
	 * @return bool|mixed|string
	 */
	public function get_recipe_type( $recipe_id = 0 ) {

		if ( ! absint( $recipe_id ) ) {
			return false;
		}

		$recipe_type = get_post_meta( $recipe_id, 'uap_recipe_type', true );

		if ( empty( $recipe_type ) ) {
			return 'user';
		}

		return $recipe_type;
	}

	/**
	 * Set the recipe type
	 *
	 * @param int $recipe_id
	 * @param null $recipce_type
	 *
	 * @return bool|int
	 */
	public function set_recipe_type( $recipe_id = 0, $recipce_type = null ) {

		if ( ! absint( $recipe_id ) ) {
			return false;
		}

		if ( ! is_string( $recipce_type ) ) {
			return false;
		}

		return update_post_meta( $recipe_id, 'uap_recipe_type', $recipce_type );
	}
}
