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
		if ( ! isset( $item['options'] ) && ! isset( $item['options_group'] ) ) {
			return $item;
		}
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
			if ( null === $integration || ! isset( $integration['name'] ) ) {
				continue;
			}
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
	 * Wrapper method for ajax_auth_check for convenience.
	 *
	 * Sends back JSON reponse if nonce is failing.
	 *
	 * @return void
	 */
	public function verify_nonce( $post = array() ) {
		return $this->ajax_auth_check( $post );
	}

	/**
	 * Verifies that a correct security nonce was used with time limit.
	 *
	 * @param mixed[] $post
	 *
	 * @return void
	 */
	public function ajax_auth_check( $post = array() ) {

		$return = array();

		// Check if nonce is available, if not just bail.
		if ( ! isset( $_POST['nonce'] ) && ! isset( $post['nonce'] ) ) {

			$return['status'] = 'auth-failed';
			$return['error']  = esc_html__( 'Automator did not receive nonce.', 'uncanny-automator' );

			wp_send_json( $return );

		}

		$capability = 'manage_options';
		$capability = apply_filters_deprecated( 'modify_recipe', array( $capability ), '3.0', 'automator_capability_required' );
		$capability = apply_filters( 'automator_capability_required', $capability, $post );

		// Check if the current user is capable of calling this auth.
		if ( ! current_user_can( $capability ) ) {

			$return['status'] = 'auth-failed';
			$return['error']  = esc_html__( 'You do not have permission to update options.', 'uncanny-automator' );

			wp_send_json( $return );

		}

		// check if the nonce is verifiable.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wp_rest' )
			&& ! wp_verify_nonce( sanitize_text_field( wp_unslash( $post['nonce'] ) ), 'wp_rest' ) ) {

			$return['status'] = 'auth-failed';
			$return['error']  = esc_html__( 'nonce validation failed.', 'uncanny-automator' );

			wp_send_json( $return );
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

		$recipe_id = absint( $recipe_id );

		if ( 0 === $recipe_id ) {
			return false;
		}

		$recipe_types = $this->get_recipe_types();

		foreach ( $recipe_types as $r_t ) {
			if ( absint( $r_t->post_id ) === $recipe_id ) {
				return $r_t->meta_value;
			}
		}

		return 'user';
	}

	/**
	 * get_recipe_types
	 *
	 * @return mixed
	 */
	public function get_recipe_types() {

		if ( empty( $this->recipe_types ) ) {
			global $wpdb;
			$this->recipe_types = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_value, pm.post_id FROM $wpdb->postmeta pm JOIN $wpdb->posts p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = %s", 'uo-recipe', 'uap_recipe_type' ) );
		}

		return $this->recipe_types;
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
	 * @param string $type
	 * @param string $meta_key
	 * @param array $options
	 *
	 * @return mixed|string
	 */
	public function automator_sanitize( $data, $type = 'text', $meta_key = '', $options = array() ) {

		// If it's an array, handle it early and return data
		if ( is_array( $data ) ) {
			return $this->automator_sanitize_array( $data, $meta_key, $options );
		}
		// $type_before = $type;
		// Maybe identify field type
		if ( empty( $type ) || 'mixed' === $type ) {
			$type = $this->maybe_get_field_type( $meta_key, $options );
		}

		switch ( $type ) {
			case 'textarea':
				$data = sanitize_textarea_field( $data );
				break;
			case 'html':
			case 'markdown':
				// Do nothing for HTML types.
				break;
			case 'url':
				// Only escape the data if there are no tokens.
				preg_match_all( '/{{\s*(.*?)\s*}}/', $data, $tokens );
				if ( ! isset( $tokens[0] ) || empty( $tokens[0] ) ) {
					// Use esc_url_raw so ampersand won't be encoded.
					$data = esc_url_raw( $data );
				}
				break;
			case 'text':
				$data = sanitize_text_field( $data );
				break;
			case 'mixed':
				// Apply default sanitization for 'mixed' type.
			default:
				if ( wp_strip_all_tags( $data ) === $data ) {
					$data = sanitize_text_field( $data );
				}
				break;
		}

		return apply_filters( 'automator_sanitized_data', $data, $type, $meta_key, $options );
	}

	/**
	 * @param $data
	 * @param bool $slash_only
	 *
	 * @return array|string
	 */
	public function automator_sanitize_json( $data, $slash_only = false ) {
		if ( $slash_only ) {
			return wp_slash( $data );
		}
		$filters = array(
			'email'   => FILTER_VALIDATE_EMAIL,
			'url'     => FILTER_VALIDATE_URL,
			'name'    => FILTER_UNSAFE_RAW,
			'address' => FILTER_UNSAFE_RAW,
		);
		$options = array(
			'email' => array(
				'flags' => FILTER_NULL_ON_FAILURE,
			),
			'url'   => array(
				'flags' => FILTER_NULL_ON_FAILURE,
			),
			//... and so on
		);
		$inputs   = json_decode( $data );
		$filtered = array();
		foreach ( $inputs as $key => $value ) {
			$filtered[ $key ] = filter_var( $value, $filters[ $key ], $options[ $key ] );
		}

		return apply_filters( 'automator_sanitized_json', wp_slash( wp_json_encode( $filtered ) ), $type, $meta_key, $options );
	}

	/**
	 * Recursively calls itself if children has arrays as well
	 *
	 * @param $data
	 * @param string $meta_key
	 * @param array $options
	 *
	 * @return mixed
	 */
	public function automator_sanitize_array( $data, $meta_key = '', $options = array() ) {
		foreach ( $data as $k => $v ) {
			$k = esc_attr( $k );
			if ( is_array( $v ) ) {
				$data[ $k ] = $this->automator_sanitize( $v, 'array', $meta_key, $options );
			} else {
				switch ( $k ) {
					case 'EMAILFROM':
					case 'EMAILTO':
					case 'EMAILCC':
					case 'EMAILBCC':
					case 'WPCPOSTAUTHOR':
						$data[ $k ] = sanitize_text_field( $v );
						break;
					case 'EMAILBODY':
						$data[ $k ] = $v;
						break;
					case 'WPCPOSTCONTENT':
						if ( apply_filters( 'automator_wpcpostcontent_should_sanitize', false, $data ) ) {
							$v = wp_kses_post( $v );
						}
						$data[ $k ] = $v;
						break;
					default:
						$field_type = $this->maybe_get_field_type( $k, $options );
						$data[ $k ] = $this->automator_sanitize( $v, $field_type );
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

		$free_license_status = automator_get_option( 'uap_automator_free_license_status' );
		$pro_license_status  = automator_get_option( 'uap_automator_pro_license_status' );

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


	/**
	 * @param $string
	 *
	 * @return bool
	 */
	public function is_json_string( $string ) {
		return is_string( $string ) && is_array( json_decode( $string, true ) ) && ( JSON_ERROR_NONE === json_last_error() ) ? true : false;
	}

	/**
	 * @param $meta_value
	 * @param bool $slash_only
	 *
	 * @return array|mixed|string
	 */
	public function maybe_slash_json_value( $meta_value, $slash_only = false ) {
		if ( $this->is_json_string( $meta_value ) ) {
			$meta_value = Automator()->utilities->automator_sanitize_json( $meta_value, $slash_only );
		}

		return $meta_value;
	}

	/**
	 * @param $meta_value
	 *
	 * @return array|mixed|string
	 */
	public function maybe_unslash_value( $meta_value ) {
		if ( $this->is_json_string( wp_unslash( $meta_value ) ) ) {
			return wp_unslash( $meta_value );
		}

		return $meta_value;
	}

	/**
	 * @param $option_code
	 * @param $options
	 *
	 * @return string
	 */
	public function maybe_get_field_type( $option_code, $options ) {
		// if nothing is set, return text
		if ( empty( $options ) || ! isset( $options['fields'] ) || ! isset( $options['fields'][ $option_code ] ) ) {
			return apply_filters( 'automator_sanitize_get_field_type_text', 'text', $option_code, $options );
		}

		// if tinymce is set to yes, return HTML
		if ( isset( $options['fields'][ $option_code ]['supports_tinymce'] ) && 'true' === (string) $options['fields'][ $option_code ]['supports_tinymce'] ) {
			return apply_filters( 'automator_sanitize_get_field_type_html', 'html', $option_code, $options );
		}

		// if markdown is set to yes, return HTML
		if ( isset( $options['fields'][ $option_code ]['supports_markdown'] ) && 'true' === (string) $options['fields'][ $option_code ]['supports_markdown'] ) {
			return apply_filters( 'automator_sanitize_get_field_type_markdown', 'markdown', $option_code, $options );
		}

		// No type found
		if ( ! isset( $options['fields'][ $option_code ]['type'] ) || empty( $options['fields'][ $option_code ]['type'] ) ) {
			return apply_filters( 'automator_sanitize_get_field_type_text', 'text', $option_code, $options );
		}

		// Return type
		$type = (string) $options['fields'][ $option_code ]['type'];

		return apply_filters( 'automator_sanitize_get_field_type_' . $type, $type, $option_code, $options );
	}

	/**
	 * @param $post_id
	 * @param $length
	 *
	 * @return mixed|null
	 */
	public function automator_get_the_excerpt( $post_id, $length = 25 ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		$post_content = $post->post_content;
		$post_excerpt = $post->post_excerpt;
		if ( ! empty( $post_excerpt ) ) {
			// If custom excerpt is defined, return the same
			return apply_filters( 'automator_get_the_excerpt', $post_excerpt, $post_content, $post_id, $length );
		}
		$length  = apply_filters( 'automator_get_the_excerpt_length', $length );
		$excerpt = sanitize_text_field( strip_shortcodes( wp_strip_all_tags( $post_content ) ) );
		$words   = explode( apply_filters( 'automator_get_the_excerpt_separator', ' ' ), $excerpt );
		$len     = min( $length, count( $words ) );
		$excerpt = array_slice( $words, 0, $len );
		$excerpt = join( ' ', $excerpt );
		if ( ! empty( $excerpt ) ) {
			$excerpt = $excerpt . apply_filters( 'automator_get_the_excerpt_continuity', '...', $post_id );
		}

		return apply_filters( 'automator_get_the_excerpt', $excerpt, $post_content, $post_id, $length );
	}

	/**
	 * Determine if the given text has multiple lines or not.
	 *
	 * @param string $text Optional parameter defaults to empty string.
	 *
	 * @return boolean True if has multiple lines. Otherwise, false.
	 */
	public function has_multiple_lines( $text = '' ) {

		// Standardize newline characters to "\n".
		$token_value = str_replace( array( "\r\n", "\r" ), "\n", $text );
		// Remove more than two contiguous line breaks.
		$token_value = preg_replace( "/\n\n+/", "\n\n", $token_value );
		// Split up the contents into an array of strings, separated by double line breaks.
		$paragraphs = preg_split( '/\n\s*\n/', $token_value, - 1, PREG_SPLIT_NO_EMPTY );

		return count( $paragraphs ) > 1;

	}

	/**
	 * @param $post
	 * @param $post_before
	 *
	 * @return bool
	 */
	public function is_wp_post_being_published( $post, $post_before ) {
		// If this post is not published yet, bail
		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		// If this post was published before, bail
		if ( ! empty( $post_before->post_status ) && 'publish' === $post_before->post_status ) {
			return false;
		}

		// Include attachment, revision etc
		$include_non_public_posts = apply_filters(
			'automator_wp_post_updates_include_non_public_posts',
			false,
			$post->ID
		);

		if ( false === $include_non_public_posts ) {
			$__object = get_post_type_object( $post->post_type );
			if ( false === $__object->public ) {
				return false;
			}
		}

		// Otherwise, return true
		return true;
	}

	/**
	 * Fetches the live or 'publish' actions from specified integration.
	 *
	 * @param string $integration_code The integration code.
	 *
	 * @return array{}|array{array{ID:string,post_status:string}}
	 */
	public function fetch_live_integration_actions( $integration_code = '' ) {

		global $wpdb;

		if ( empty( $integration_code ) || ! is_string( $integration_code ) ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_status FROM $wpdb->posts as post
					INNER JOIN $wpdb->postmeta as meta
						ON meta.post_id = post.ID
					WHERE meta.meta_key = %s
						AND meta.meta_value = %s
						AND post.post_status = %s
						AND post.post_type = %s
				",
				'integration',
				$integration_code,
				'publish',
				'uo-action'
			),
			ARRAY_A
		);

		return (array) $results;

	}
}
