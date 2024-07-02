<?php

namespace Uncanny_Automator\Integrations\Kadence;

/**
 * Class Kadence_Helpers
 *
 * @pacakge Uncanny_Automator
 */
class Kadence_Helpers {

	/**
	 * @param $first_arg
	 * @param $second_arg
	 * @param $third_arg
	 * @param $forth_arg
	 *
	 * @return void
	 */
	public function automator_kadence_form_submitted_function( $first_arg, $second_arg, $third_arg, $forth_arg = 0 ) {

		if ( current_action() === 'kadence_blocks_advanced_form_submission' ) {
			$fields_data = $second_arg;
			$unique_id   = null;
			$post_id     = $third_arg;
		}

		if ( current_action() === 'kadence_blocks_form_submission' ) {
			$fields_data = $second_arg;
			$unique_id   = $third_arg;
			$post_id     = $forth_arg;
		}

		do_action( 'automator_kadence_form_submitted', $fields_data, $unique_id, $post_id );

	}

	/**
	 * @param $is_any
	 * @param $is_all
	 *
	 * @return array
	 */
	public function get_all_kadence_form_options( $is_any = false, $is_all = false ) {
		$all_forms = array();

		if ( true === $is_all ) {
			$all_forms[] = array(
				'text'  => esc_attr_x( 'All forms', 'Kadence', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		if ( true === $is_any ) {
			$all_forms[] = array(
				'text'  => esc_attr_x( 'Any form', 'Kadence', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$forms_options = $this->get_forms_attributes_from_content();

		if ( defined( 'KADENCE_BLOCKS_VERSION' ) ) {
			$args = array(
				'orderby'        => 'title',
				'order'          => 'DESC',
				'post_type'      => 'kadence_form',
				'post_status'    => 'publish',
				'posts_per_page' => 99999,
			);

			$forms = Automator()->helpers->recipe->options->wp_query( $args );
			foreach ( $forms as $k => $form ) {
				$all_forms[] = array(
					'text'  => $form,
					'value' => $k,
				);
			}
		}

		return array_merge( $all_forms, $forms_options );
	}

	/**
	 * @param bool|int $all_forms   true|(Form_ID/Post_ID)
	 * @param string   $result_type options|fields
	 *
	 * @return array Returns array of result_type (fields or options)
	 */
	public function get_forms_attributes_from_content( $all_forms = true, $result_type = 'options' ) {
		global $wpdb;

		$form_options = array();
		$form_fields  = array();
		if ( true !== $all_forms ) {
			$form_uid = explode( '_', $all_forms );
			if ( is_array( $form_uid ) ) {
				$post_id = $form_uid[0];
			}
			$forms = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM $wpdb->posts WHERE post_content LIKE %s AND ID = %d", '%%<!-- wp:kadence/form%%', $post_id ) );
		} else {
			$forms = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_content FROM $wpdb->posts WHERE post_content LIKE %s", '%%<!-- wp:kadence/form%%' ) );
		}

		foreach ( $forms as $post ) {
			$contentArray = explode( '<!--', $post->post_content );
			$content      = array();

			foreach ( $contentArray as $key => $value ) {
				if ( str_contains( $value, ' wp:kadence/form' ) ) {
					$temp      = str_replace( ' wp:kadence/form', '', $value );
					$temp1     = explode( '-->', $temp, 2 );
					$content[] = json_decode( $temp1[0] );
				}
			}

			if ( is_array( $content ) ) {
				foreach ( $content as $form ) {
					$unique_id = $form->uniqueID;

					if ( 'options' === $result_type ) {
						$form_options[] = array(
							'text'  => $post->post_title . ' - ' . $unique_id,
							'value' => $unique_id,
						);
					}

					if ( 'fields' === $result_type && $all_forms === $unique_id ) {
						foreach ( $form->fields as $field ) {
							$form_fields[] = array(
								'label' => $field->label,
								'type'  => $field->type,
							);
						}
					}
				}
			}
		}

		return ( 'fields' === $result_type ) ? $form_fields : $form_options;
	}

	/**
	 * @param $form_id
	 *
	 * @return array|mixed|string
	 */
	public function get_kadence_form_fields( $form_id ) {
		$fields = $this->get_forms_attributes_from_content( $form_id, 'fields' );

		if ( defined( 'KADENCE_BLOCKS_VERSION' ) && is_numeric( $form_id ) ) {
			$fields = maybe_unserialize( get_post_meta( $form_id, '_kad_form_fields', true ) );
		}

		return $fields;
	}

	/**
	 * @param $form_id
	 * @param $tokens
	 *
	 * @return array|mixed
	 */
	public function get_kadence_form_tokens( $form_id, $tokens = array() ) {
		$fields = $this->get_kadence_form_fields( $form_id );

		foreach ( $fields as $field ) {
			$tokens[] = array(
				'tokenId'   => 'KADENCE_' . str_replace( ' ', '_', $field['label'] ),
				'tokenName' => $field['label'],
				'tokenType' => $field['type'],
			);
		}

		return $tokens;
	}

	/**
	 * @return void
	 */
	public function get_all_form_fields() {
		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();
		$options = array();
		if ( ! automator_filter_has_var( 'value', INPUT_POST ) || empty( automator_filter_input( 'value', INPUT_POST ) ) ) {
			echo wp_json_encode( $options );
			die();
		}
		$form_id = automator_filter_input( 'value', INPUT_POST );

		$fields = $this->get_kadence_form_fields( $form_id );

		foreach ( $fields as $field ) {
			$options[] = array(
				'value' => str_replace( ' ', '_', strtolower( $field['label'] ) ),
				'text'  => $field['label'],
			);
		}

		echo wp_json_encode( $options );
		die();
	}
}
