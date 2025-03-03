<?php

namespace Uncanny_Automator\Integrations\Everest_Forms;

/**
 * Class Everest_Forms_Helpers
 *
 * @package Uncanny_Automator
 */
class Everest_Forms_Helpers {

	/**
	 * @return array
	 */
	public function get_all_everest_forms( $is_any = true ) {
		$all_forms = get_posts( array( 'post_type' => 'everest_form' ) );
		$options   = array();

		if ( true === $is_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any form', 'Everest Forms', 'uncanny-automator' ),
			);
		}

		foreach ( $all_forms as $form ) {
			$options[] = array(
				'value' => $form->ID,
				'text'  => $form->post_title,
			);
		}

		return $options;
	}

	/**
	 * @param $form_id
	 *
	 * @return array
	 */
	public function get_all_form_fields( $form_id ) {
		$form_fields = evf_get_form_fields( $form_id );
		$all_fields  = array();
		foreach ( $form_fields as $field_id => $field ) {
			$all_fields[ $field_id ] = array(
				'label' => $field['label'],
				'type'  => $field['type'],
			);
		}

		return $all_fields;
	}

	/**
	 * @return array[]
	 */
	public function get_evf_form_tokens() {
		return array(
			array(
				'tokenId'   => 'FORM_ID',
				'tokenName' => esc_html__( 'Form ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FORM_TITLE',
				'tokenName' => esc_html__( 'Form title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param $form_id
	 *
	 * @return array|array[]
	 */
	public function get_evf_form_field_tokens( $form_id ) {
		if ( intval( '-1' ) === intval( $form_id ) ) {
			return array();
		}

		$form_fields         = $this->get_all_form_fields( $form_id );
		$field_tokens        = array();
		$address_tokens      = array();
		$allowed_token_types = array(
			'url',
			'email',
			'float',
			'int',
			'text',
			'file-upload',
		);
		foreach ( $form_fields as $field_id => $field ) {
			$field_tokens[] = array(
				'tokenId'   => 'EVF_FIELD_' . $field_id,
				'tokenName' => $field['label'],
				'tokenType' => in_array( $field['type'], $allowed_token_types, true ) ? $field['type'] : 'text',
			);

			if ( 'address' === $field['type'] ) {
				$address_tokens = array(
					array(
						'tokenId'   => 'EVF_FIELD_' . $field_id . '_address1',
						'tokenName' => esc_attr_x( 'Address line 1', 'Everest Forms', 'uncanny-automator' ),
						'tokenType' => 'text',
					),
					array(
						'tokenId'   => 'EVF_FIELD_' . $field_id . '_address2',
						'tokenName' => esc_attr_x( 'Address line 2', 'Everest Forms', 'uncanny-automator' ),
						'tokenType' => 'text',
					),
					array(
						'tokenId'   => 'EVF_FIELD_' . $field_id . '_city',
						'tokenName' => esc_attr_x( 'Address city', 'Everest Forms', 'uncanny-automator' ),
						'tokenType' => 'text',
					),
					array(
						'tokenId'   => 'EVF_FIELD_' . $field_id . '_postal',
						'tokenName' => esc_attr_x( 'Address postal', 'Everest Forms', 'uncanny-automator' ),
						'tokenType' => 'text',
					),
					array(
						'tokenId'   => 'EVF_FIELD_' . $field_id . '_state',
						'tokenName' => esc_attr_x( 'Address state', 'Everest Forms', 'uncanny-automator' ),
						'tokenType' => 'text',
					),
					array(
						'tokenId'   => 'EVF_FIELD_' . $field_id . '_country',
						'tokenName' => esc_attr_x( 'Address country', 'Everest Forms', 'uncanny-automator' ),
						'tokenType' => 'text',
					),
				);
			}
		}

		return array_merge( $field_tokens, $address_tokens );
	}

	/**
	 * @param $form_id
	 * @param $fields
	 *
	 * @return array
	 */
	public function parse_token_values( $form_id, $fields ) {
		$token_values = array(
			'FORM_ID'    => $form_id,
			'FORM_TITLE' => get_the_title( $form_id ),
		);
		foreach ( $fields as $field_id => $field ) {
			if ( in_array( $field['type'], array( 'checkbox', 'select', 'radio' ), true ) ) {
				$token_values[ 'EVF_FIELD_' . $field_id ] = is_array( $field['value_raw'] ) ? join( ', ', $field['value_raw'] ) : $field['value_raw'];
			} elseif ( 'address' === $field['type'] ) {
				$token_values[ 'EVF_FIELD_' . $field_id ]               = $field['value'];
				$token_values[ 'EVF_FIELD_' . $field_id . '_address1' ] = $field['address1'];
				$token_values[ 'EVF_FIELD_' . $field_id . '_address2' ] = $field['address2'];
				$token_values[ 'EVF_FIELD_' . $field_id . '_city' ]     = $field['city'];
				$token_values[ 'EVF_FIELD_' . $field_id . '_state' ]    = $field['state'];
				$token_values[ 'EVF_FIELD_' . $field_id . '_postal' ]   = $field['postal'];
				$token_values[ 'EVF_FIELD_' . $field_id . '_country' ]  = $field['country'];
			} else {
				$token_values[ 'EVF_FIELD_' . $field_id ] = $field['value'];
			}
		}

		return $token_values;
	}

	/**
	 * @return void
	 */
	public function get_all_evf_fields_by_form_id() {
		Automator()->utilities->verify_nonce();
		// Ignore nonce, already handled above.
		$values      = automator_filter_input_array( 'values', INPUT_POST );
		$form_id     = isset( $values['EVF_FORMS'] ) ? sanitize_text_field( $values['EVF_FORMS'] ) : '';
		$options     = array();
		$form_fields = $this->get_all_form_fields( $form_id );

		foreach ( $form_fields as $field_id => $field ) {
			$options[] = array(
				'value' => $field_id,
				'text'  => $field['label'],
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );
	}
}
