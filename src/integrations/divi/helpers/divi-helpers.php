<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Divi_Pro_Helpers;

/**
 * Divi integration helper file
 */
class Divi_Helpers {
	/**
	 * Store Divi options
	 *
	 * @var Divi_Helpers
	 */
	public $options;

	public $load_options = true;

	/**
	 * Store Divi Pro Helper instance
	 *
	 * @var Divi_Pro_Helpers
	 */
	public $pro;

	/**
	 * Divi_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * Set Divi options
	 *
	 * @param Divi_Helpers $options
	 */
	public function setOptions( Divi_Helpers $options ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set Divi Pro Helper instance
	 *
	 * @param Divi_Pro_Helpers $pro
	 */
	public function setPro( Divi_Pro_Helpers $pro ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * Fetch all Divi forms
	 *
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function all_divi_forms( $label = null, $option_code = 'DIVIMFORMS', $args = array() ) {

		$label = null === $label ? esc_attr__( 'Form', 'uncanny-automator' ) : $label;

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any form', 'uncanny-automator' ),
			)
		);

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();
		$option       = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
		);

		if ( ! Automator()->helpers->recipe->load_helpers ) {
			return apply_filters( 'uap_option_all_divi_forms', $option );
		}
//		if ( $args['uo_include_any'] ) {
//			$options['-1'] = $args['uo_any_label'];
//		}

		$data = self::extract_forms();
		if ( $data ) {
			foreach ( $data as $form_id => $d ) {
				$options[ $form_id ] = $d['title'];
			}
		}

		$option['options'] = $options;

		return apply_filters( 'uap_option_all_divi_forms', $option );
	}

	/**
	 * Pseudo function copied from Divi
	 *
	 * @param $array
	 * @param $address
	 * @param string $default
	 *
	 * @return mixed|string
	 */
	public static function array_get( $array, $address, $default = '' ) {
		$keys  = is_array( $address ) ? $address : explode( '.', $address );
		$value = $array;

		foreach ( $keys as $key ) {
			if ( ! empty( $key ) && isset( $key[0] ) && '[' === $key[0] ) {
				$index = substr( $key, 1, - 1 );

				if ( is_numeric( $index ) ) {
					$key = (int) $index;
				}
			}

			if ( ! isset( $value[ $key ] ) ) {
				return $default;
			}

			$value = $value[ $key ];
		}

		return $value;
	}

	/**
	 * Extract form info from the Divi shortcode
	 *
	 * @return array
	 */
	public static function extract_forms() {
		global $wpdb;
		$form_posts = $wpdb->get_results( $wpdb->prepare( "SELECT `ID`, `post_content`, `post_title` FROM $wpdb->posts WHERE post_status NOT IN('trash', 'inherit', 'auto-draft') AND post_type IS NOT NULL AND post_type NOT LIKE %s AND post_content LIKE %s", 'revision', '%%et_pb_contact_form%%' ) );

		$data = array();
		if ( empty( $form_posts ) ) {
			return $data;
		}
		foreach ( $form_posts as $form_post ) {
			// Get forms
			$pattern_regex = '/\[et_pb_contact_form(.*?)](.+?)\[\/et_pb_contact_form]/';
			preg_match_all( $pattern_regex, $form_post->post_content, $forms, PREG_SET_ORDER );
			if ( empty( $forms ) ) {
				continue;
			}

			$jjj = 0;

			foreach ( $forms as $form ) {
				$pattern_form = get_shortcode_regex( array( 'et_pb_contact_form' ) );
				preg_match_all( "/$pattern_form/", $form[0], $forms_extracted, PREG_SET_ORDER );

				if ( empty( $forms_extracted ) ) {
					continue;
				}

				foreach ( $forms_extracted as $form_extracted ) {
					$form_attrs = shortcode_parse_atts( $form_extracted[3] );
					$form_id    = isset( $form_attrs['_unique_id'] ) ? $form_attrs['_unique_id'] : '';
					if ( empty( $form_id ) ) {
						continue;
					}
					$form_id                    = sprintf( '%d-%s', $form_post->ID, $form_id );
					$form_title                 = isset( $form_attrs['title'] ) ? $form_attrs['title'] : __( 'No form title', 'uncanny-automator' );
					$form_title                 = sprintf( '%s - %s', $form_post->post_title, $form_title );
					$fields                     = self::extract_fields( $form[0] );
					$data[ $form_id ]['title']  = $form_title;
					$data[ $form_id ]['fields'] = $fields;
				}
				$jjj ++;
			}
		}

		return $data;
	}


	/**
	 * Extracting fields from the form shortcode
	 *
	 * @param $content_shortcode
	 *
	 * @return array
	 */
	public static function extract_fields( $content_shortcode ) {
		$fields  = array();
		$pattern = get_shortcode_regex( array( 'et_pb_contact_field' ) );

		preg_match_all( "/$pattern/", $content_shortcode, $contact_fields, PREG_SET_ORDER );

		if ( empty( $contact_fields ) ) {
			return $fields;
		}

		foreach ( $contact_fields as $contact_field ) {
			$contact_field_attrs = shortcode_parse_atts( $contact_field[3] );
			$field_id            = strtolower( self::array_get( $contact_field_attrs, 'field_id' ) );

			$fields[] = array(
				'field_title'   => self::array_get( $contact_field_attrs, 'field_title', __( 'No title', 'uncanny-automator' ) ),
				'field_type'    => self::array_get( $contact_field_attrs, 'field_type', 'text' ),
				'field_id'      => $field_id,
				'required_mark' => self::array_get( $contact_field_attrs, 'required_mark', 'on' ),
			);
		}

		return $fields;
	}

	/**
	 * Select form by ID
	 *
	 * @param $form_id
	 *
	 * @return array|mixed
	 */
	public static function get_form_by_id( $form_id ) {
		$forms = self::extract_forms();
		if ( empty( $forms ) ) {
			return array();
		}

		foreach ( $forms as $_form_id => $d ) {
			if ( (string) $_form_id === (string) $form_id ) {
				return $d['fields'];
			}
		}

		return array();
	}

	/**
	 * Match conditions in Divi triggers
	 *
	 * @param $form_id
	 * @param null $recipes
	 * @param null $trigger_meta
	 *
	 * @return array|false
	 */

	public static function match_condition( $form_id, $recipes = null, $trigger_meta = null ) {
		if ( empty( $recipes ) ) {
			return false;
		}

		$recipe_ids     = array();
		$entry_to_match = $form_id;

		foreach ( $recipes as $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				if ( ! array_key_exists( $trigger_meta, $trigger['meta'] ) ) {
					continue;
				}
				if ( ( (string) $trigger['meta'][ $trigger_meta ] === (string) $entry_to_match ) || ( intval( '-1' ) === intval( $trigger['meta'][ $trigger_meta ] ) ) ) {
					$recipe_ids[ $recipe['ID'] ] = $recipe['ID'];
				}
			}
		}

		if ( ! empty( $recipe_ids ) ) {
			return array(
				'recipe_ids' => $recipe_ids,
				'result'     => true,
			);
		}

		return false;
	}

	/**
	 * Saving tokens
	 *
	 * @param $result
	 * @param $fields_values
	 * @param $form_id
	 * @param $trigger_meta
	 * @param $user_id
	 */
	public static function save_tokens( $result, $fields_values, $form_id, $trigger_meta, $user_id ) {
		if ( empty( $result ) ) {
			return;
		}
		if ( false === $result['result'] ) {
			return;
		}
		$all_fields = array();
		foreach ( $fields_values as $k => $v ) {
			$field_id                = "$form_id|$k";
			$all_fields[ $field_id ] = $v['value'];
		}
		$trigger_id     = $result['args']['trigger_id'];
		$trigger_log_id = absint( $result['args']['trigger_log_id'] );
		$run_number     = absint( $result['args']['run_number'] );
		$meta_key       = sprintf( '%d:%s', $trigger_id, $trigger_meta );
		$trigger_meta   = array(
			'user_id'        => $user_id,
			'trigger_id'     => $trigger_id,
			'trigger_log_id' => $trigger_log_id,
			'run_number'     => $run_number,
		);

		Automator()->db->token->save( $meta_key, maybe_serialize( $all_fields ), $trigger_meta );
	}
}
