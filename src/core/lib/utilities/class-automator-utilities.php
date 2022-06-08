<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Utilities
 *
 * @package Uncanny_Automator
 */
class Automator_Utilities {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @var
	 */
	public $recipe_types;

	/**
	 * @return Automator_Utilities
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Convert options array (for selects) from associative array to array of associative arrays
	 * We need this to keep the order of the options in the JS
	 *
	 * @param $item
	 *
	 * @return array;
	 */
	public function keep_order_of_options( $item ) {
		// Check if it has options
		if ( isset( $item['options'] ) ) {

			// Iterate each option
			foreach ( $item['options'] as $option_key => $option ) {

				// Check if it's a select and has options in the select
				if (
					in_array( $option['input_type'], array( 'select', 'radio' ), true )
					&& ( isset( $option['options'] ) && ! empty( $option['options'] ) )
				) {

					// Create array that will be used to create the new array of options
					$select_options = array();
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
					if (
						in_array( $option['input_type'], array( 'select', 'radio' ), true )
						&& isset( $option['options'] )
					) {

						// Create array that will be used to create the new array of options
						$select_options = array();

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
	 */
	public function sort_integrations_alphabetically() {

		if ( ! Automator()->integrations ) {
			return null;
		}

		// Save integrations here
		$integrations = array();

		// Create an array with a list of integrations name
		$list_of_names = array();
		foreach ( Automator()->integrations as $integration_id => $integration ) {
			$list_of_names[ $integration_id ] = strtolower( $integration['name'] );
		}

		// Sort list of names alphabetically
		asort( $list_of_names );

		// Create a new integrations array with the correct order
		foreach ( $list_of_names as $integration_id => $integration_name ) {
			$integrations[ $integration_id ] = Automator()->integrations[ $integration_id ];
		}

		// Replace old array with new one
		Automator()->integrations = $integrations;
	}

	/**
	 * @param null $recipe_id
	 * @param $completed_times
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
	 * @param null $recipe_id
	 * @param $completed_times
	 *
	 * @return bool
	 */
	public function recipe_max_times_completed( $recipe_id = null, $completed_times = 0 ) {
		if ( is_null( $recipe_id ) ) {
			return false;
		}

		$post_meta = get_post_meta( $recipe_id, 'recipe_max_completions_allowed', true );
		if ( empty( $post_meta ) ) {
			$completions_allowed = '-1';
			// Make sure that the recipe has recipe_completions_allowed saved. @version 3.0
			update_post_meta( $recipe_id, 'recipe_max_completions_allowed', $completions_allowed );
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
	 * @param $recipes_completed_times
	 *
	 * @return array
	 */
	public function recipes_number_times_completed( $recipe_ids = null, $recipes_completed_times = 0 ) {
		global $wpdb;
		$times_to_complete = array();
		$post_metas        = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value, post_id FROM $wpdb->postmeta WHERE meta_key = %s LIMIT 0, 99999", 'recipe_completions_allowed' ) );
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

		$results = array();
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
	 * Verifies that a correct security nonce was used with time limit.
	 *
	 * @param array $post
	 */
	public function ajax_auth_check( $post = array() ) {
		$return = array();
		// Check if nonce is available, if not just bail.
		if ( ! isset( $_POST['nonce'] ) && ! isset( $post['nonce'] ) ) {
			$return['status'] = 'auth-failed';
			$return['error']  = esc_html__( 'Automator did not receive nonce.', 'uncanny-automator' );
			echo wp_json_encode( $return );
			die();
		}

		$capability = 'manage_options';
		$capability = apply_filters_deprecated( 'modify_recipe', array( $capability ), '3.0', 'automator_capability_required' );
		$capability = apply_filters( 'automator_capability_required', $capability, $post );
		// Check if the current user is capable of calling this auth.
		if ( ! current_user_can( $capability ) ) {
			$return['status'] = 'auth-failed';
			$return['error']  = esc_html__( 'You do not have permission to update options.', 'uncanny-automator' );
			echo wp_json_encode( $return );
			die();
		}

		// check if the nonce is verifiable.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_rest' ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $post['nonce'] ) ), 'wp_rest' ) ) {
			$return['status'] = 'auth-failed';
			$return['error']  = esc_html__( 'nonce validation failed.', 'uncanny-automator' );
			echo wp_json_encode( $return );
			die();
		}
	}

	/**
	 * @param null $condition
	 * @param $number_to_match
	 * @param $number_to_compare
	 *
	 * @return bool
	 */
	public function match_condition_vs_number( $condition = null, $number_to_compare = 0, $number_to_match = 0 ) {
		if ( null === $condition ) {
			return false;
		}

		$number_to_compare = number_format( $number_to_compare, 2, '.', '' );
		$number_to_match   = number_format( $number_to_match, 2, '.', '' );

		if ( '<' === (string) $condition && $number_to_match < $number_to_compare ) {
			return true;
		}
		if ( '>' === (string) $condition && $number_to_match > $number_to_compare ) {
			return true;
		}
		if ( '=' === (string) $condition && $number_to_match === $number_to_compare ) {
			return true;
		}
		if ( '!=' === (string) $condition && $number_to_match !== $number_to_compare ) {
			return true;
		}
		if ( '<=' === (string) $condition && $number_to_match <= $number_to_compare ) {
			return true;
		}
		if ( '>=' === (string) $condition && $number_to_match >= $number_to_compare ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the recipe type
	 *
	 * @param $recipe_id
	 *
	 * @return bool|mixed|string
	 */
	public function get_recipe_type( $recipe_id = 0 ) {

		if ( ! absint( $recipe_id ) ) {
			return false;
		}

		if ( ! empty( $this->recipe_types ) && isset( $this->recipe_types[ $recipe_id ] ) ) {
			return $this->recipe_types[ $recipe_id ];
		}

		$cached = Automator()->cache->get( 'get_recipe_type' );
		if ( ! empty( $cached ) && isset( $cached[ $recipe_id ] ) ) {
			return $cached[ $recipe_id ];
		}

		global $wpdb;
		$recipe_types = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_value, pm.post_id FROM $wpdb->postmeta pm JOIN $wpdb->posts p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = %s", 'uo-recipe', 'uap_recipe_type' ) );

		$data = array();
		foreach ( $recipe_types as $r_t ) {
			$r_id          = $r_t->post_id;
			$r_type        = $r_t->meta_value;
			$data[ $r_id ] = $r_type;
		}
		Automator()->cache->set( 'get_recipe_type', $data );
		$this->recipe_types = $data;

		return ! isset( $data[ $recipe_id ] ) || empty( $data[ $recipe_id ] ) ? 'user' : $data[ $recipe_id ];
	}

	/**
	 * Set the recipe type
	 *
	 * @param $recipe_id
	 * @param null $recipe_type
	 *
	 * @return bool|int
	 */
	public function set_recipe_type( $recipe_id = 0, $recipe_type = null ) {

		if ( ! absint( $recipe_id ) ) {
			return false;
		}

		if ( ! is_string( $recipe_type ) ) {
			return false;
		}
		Automator()->cache->remove( 'get_recipe_type' );

		return update_post_meta( $recipe_id, 'uap_recipe_type', $recipe_type );
	}

	/**
	 * @param $data
	 * @param $type
	 *
	 * @return mixed|string
	 */
	public function automator_sanitize( $data, $type = 'text' ) {
		switch ( $type ) {
			case 'mixed':
			case 'array':
				if ( is_array( $data ) ) {
					$this->automator_sanitize_array( $data );
				} else {
					$data = sanitize_text_field( $data );
				}
				break;
			default:
				$data = sanitize_text_field( $data );
				break;
		}

		return $data;
	}

	/**
	 * Recursively calls itself if children has arrays as well
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function automator_sanitize_array( $data ) {
		foreach ( $data as $k => $v ) {
			$k = esc_attr( $k );
			if ( is_array( $v ) ) {
				$data[ $k ] = $this->automator_sanitize( $v, 'array' );
			} else {
				switch ( $k ) {
					case 'EMAILFROM':
					case 'EMAILTO':
					case 'EMAILCC':
					case 'EMAILBCC':
					case 'WPCPOSTAUTHOR':
						$regex = '/[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})/';
						if ( preg_match( $regex, $v, $email_is ) ) {
							$data[ $k ] = sanitize_email( $v );
						} else {
							$data[ $k ] = sanitize_text_field( $v );
						}
						break;
					case 'EMAILBODY':
					case 'WPCPOSTCONTENT':
						$data[ $k ] = wp_kses_post( $v );
						break;
					default:
						$data[ $k ] = sanitize_text_field( $v );
						break;
				}
			}
		}

		return $data;
	}

	/**
	 * Checks if the user has valid license in pro or free version.
	 *
	 * @return boolean.
	 */
	public function has_valid_license() {

		$has_pro_license  = false;
		$has_free_license = false;

		$free_license_status = get_option( 'uap_automator_free_license_status' );
		$pro_license_status  = get_option( 'uap_automator_pro_license_status' );

		if ( defined( 'AUTOMATOR_PRO_FILE' ) && 'valid' === $pro_license_status ) {
			$has_pro_license = true;
		}

		if ( 'valid' === $free_license_status ) {
			$has_free_license = true;
		}

		return $has_free_license || $has_pro_license;

	}

	/**
	 * Checks if screen is from the modal action popup or not.
	 *
	 * @return boolean.
	 */
	public function is_from_modal_action() {

		$minimal = filter_input( INPUT_GET, 'automator_minimal', FILTER_DEFAULT );

		$hide_settings_tabs = filter_input( INPUT_GET, 'automator_hide_settings_tabs', FILTER_DEFAULT );

		return ! empty( $minimal ) && ! empty( $hide_settings_tabs );
	}

	/**
	 * @param $tokens
	 *
	 * @return array|mixed
	 */
	public function remove_duplicate_token_ids( $tokens ) {
		$new_tokens = array();
		if ( empty( $tokens ) ) {
			return $tokens;
		}
		foreach ( $tokens as $token ) {
			if ( ! array_key_exists( $token['tokenId'], $new_tokens ) ) {
				$new_tokens[ $token['tokenId'] ] = $token;
			}
		}

		return array_values( $new_tokens );
	}
}
