<?php

namespace Uncanny_Automator\Integrations\Sure_Forms;

/**
 * Class Sure_Forms_Helpers
 *
 * @package Uncanny_Automator
 */
class Sure_Forms_Helpers {

	/**
	 * Get all Sure Forms
	 *
	 * @return array
	 */
	public function get_all_sure_forms( $is_any = true ) {
		$all_forms = get_posts( array( 'post_type' => 'sureforms_form' ) );
		$options   = array();

		if ( true === $is_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any form', 'Sure Forms', 'uncanny-automator' ),
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
		if ( empty( $form_id ) || ! is_int( $form_id ) ) {
			return array();
		}

		if ( 'sureforms_form' !== get_post_type( $form_id ) ) {
			return array();
		}

		$post = get_post( $form_id );

		if ( is_null( $post ) ) {
			return array();
		}

		$blocks = parse_blocks( $post->post_content );
		if ( empty( $blocks ) ) {
			return array();
		}

		$all_fields = array();

		foreach ( $blocks as $block ) {
			if ( ! empty( $block['blockName'] ) && 0 === strpos( $block['blockName'], 'srfm/' ) ) {
				// Skip non-input blocks
				if ( in_array( $block['blockName'], array( 'srfm/separator', 'srfm/icon', 'srfm/image', 'srfm/advanced-heading' ), true ) ) {
					continue;
				}

				if ( ! empty( $block['attrs']['block_id'] ) ) {
					$field_type  = str_replace( 'srfm/', '', $block['blockName'] );
					$field_label = ! empty( $block['attrs']['label'] ) ? $block['attrs']['label'] : ucfirst( $field_type );
					$field_slug  = ! empty( $block['attrs']['slug'] ) ? $block['attrs']['slug'] : sanitize_title( $field_label );

					$all_fields[ $block['attrs']['block_id'] ] = array(
						'label' => $field_label,
						'type'  => $field_type,
						'slug'  => $field_slug,
					);
				}
			}
		}

		return $all_fields;
	}



	/**
	 * @return array[]
	 */
	public function get_sure_form_tokens() {
		return array(
			array(
				'tokenId'   => 'FORM_ID',
				'tokenName' => esc_html_x( 'Form ID', 'Sure Forms', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FORM_TITLE',
				'tokenName' => esc_html_x( 'Form title', 'Sure Forms', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param int $form_id
	 *
	 * @return array
	 */
	public function get_sure_form_field_tokens( $form_id ) {
		if ( intval( '-1' ) === intval( $form_id ) ) {
			return array();
		}

		$form_fields         = $this->get_all_form_fields( intval( $form_id ) );
		$field_tokens        = array();
		$address_tokens      = array();
		$allowed_token_types = array( 'url', 'email', 'float', 'int', 'text', 'file-upload' );

		foreach ( $form_fields as $field_id => $field ) {
			$field_tokens[] = array(
				'tokenId'   => 'SURE_FIELD_' . $field_id,
				'tokenName' => $field['label'],
				'tokenType' => in_array( $field['type'], $allowed_token_types, true ) ? $field['type'] : 'text',
			);

			if ( 'address' === $field['type'] ) {
				$address_tokens = array_merge(
					$address_tokens,
					array(
						array(
							'tokenId'   => 'SURE_FIELD_' . $field_id . '_address1',
							'tokenName' => esc_attr_x( 'Address line 1', 'Sure Forms', 'uncanny-automator' ),
							'tokenType' => 'text',
						),
						array(
							'tokenId'   => 'SURE_FIELD_' . $field_id . '_address2',
							'tokenName' => esc_attr_x( 'Address line 2', 'Sure Forms', 'uncanny-automator' ),
							'tokenType' => 'text',
						),
						array(
							'tokenId'   => 'SURE_FIELD_' . $field_id . '_city',
							'tokenName' => esc_attr_x( 'Address city', 'Sure Forms', 'uncanny-automator' ),
							'tokenType' => 'text',
						),
						array(
							'tokenId'   => 'SURE_FIELD_' . $field_id . '_postal',
							'tokenName' => esc_attr_x( 'Address postal', 'Sure Forms', 'uncanny-automator' ),
							'tokenType' => 'text',
						),
						array(
							'tokenId'   => 'SURE_FIELD_' . $field_id . '_state',
							'tokenName' => esc_attr_x( 'Address state', 'Sure Forms', 'uncanny-automator' ),
							'tokenType' => 'text',
						),
						array(
							'tokenId'   => 'SURE_FIELD_' . $field_id . '_country',
							'tokenName' => esc_attr_x( 'Address country', 'Sure Forms', 'uncanny-automator' ),
							'tokenType' => 'text',
						),
					)
				);
			}
		}

		return array_merge( $field_tokens, $address_tokens );
	}




	/**
	 * @param int   $form_id
	 * @param array $fields
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
				$token_values[ 'SURE_FIELD_' . $field_id ] = is_array( $field['value_raw'] )
					? join( ', ', $field['value_raw'] )
					: $field['value_raw'];
			} elseif ( 'address' === $field['type'] ) {
				$token_values[ 'SURE_FIELD_' . $field_id ]               = $field['value'];
				$token_values[ 'SURE_FIELD_' . $field_id . '_address1' ] = $field['address1'];
				$token_values[ 'SURE_FIELD_' . $field_id . '_address2' ] = $field['address2'];
				$token_values[ 'SURE_FIELD_' . $field_id . '_city' ]     = $field['city'];
				$token_values[ 'SURE_FIELD_' . $field_id . '_state' ]    = $field['state'];
				$token_values[ 'SURE_FIELD_' . $field_id . '_postal' ]   = $field['postal'];
				$token_values[ 'SURE_FIELD_' . $field_id . '_country' ]  = $field['country'];
			} else {
				$token_values[ 'SURE_FIELD_' . $field_id ] = $field['value'];
			}
		}

		return $token_values;
	}
}
