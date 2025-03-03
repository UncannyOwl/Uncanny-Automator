<?php

namespace Uncanny_Automator;

/**
 * Class Bricks_Builder_Helpers
 *
 * @pacakge Uncanny_Automator
 */
class Bricks_Builder_Helpers {

	/**
	 * @return array
	 */
	public function get_all_bricks_builder_forms( $is_any = false ) {
		$options = array();
		if ( true === $is_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_attr_x( 'Any form', 'bricks-builder', 'uncanny-automator' ),
			);
		}
		$forms = $this->get_form_from_content();
		foreach ( $forms as $form ) {
			$options[] = array(
				'value' => $form['id'],
				'text'  => $form['name'],
			);
		}

		return $options;
	}

	/**
	 * @param $form_id
	 * @param $retrun_type
	 *
	 * @return array|mixed
	 */
	private function get_form_from_content( $form_id = null, $retrun_type = 'forms' ) {
		$bricks_templates = get_posts(
			array(
				'post_type'      => array( 'page', 'post', 'bricks_template' ),
				'posts_per_page' => - 1,
			)
		);
		$form_data        = array();
		foreach ( $bricks_templates as $bricks_template ) {
			$template_content = get_post_meta( $bricks_template->ID, '_bricks_page_content_2', true );
			if ( ! empty( $template_content ) ) {
				foreach ( $template_content as $content ) {
					if ( 'form' === $content['name'] && 'forms' === $retrun_type ) {
						$form_name   = isset( $content['label'] ) ? $content['label'] : $content['id'];
						$form_data[] = array(
							'id'   => $content['id'],
							'name' => $form_name,
						);
					}
					if ( 'form' === $content['name'] && 'fields' === $retrun_type && ! is_null( $form_id ) && $form_id === $content['id'] ) {
						return $content['settings']['fields'];
					}
				}
			}
		}

		return $form_data;
	}

	public function get_form_common_tokens() {
		return array(
			array(
				'tokenId'   => 'FORM_ID',
				'tokenName' => esc_html__( 'Form ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FORM_NAME',
				'tokenName' => esc_html__( 'Form title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param $from_details
	 *
	 * @return array
	 */
	public function get_from_field_tokens( $from_id ) {
		$tokens = array();
		if ( intval( '-1' ) === intval( $from_id ) ) {
			return $tokens;
		}

		$form_fields = $this->get_all_fields_by_form_id( $from_id );
		foreach ( $form_fields as $field ) {
			$tokens[] = array(
				'tokenId'   => 'form-field-' . $field['value'],
				'tokenName' => $field['text'],
			);
		}

		return $tokens;
	}

	/**
	 * @param $form_id
	 *
	 * @return array
	 */
	public function get_all_fields_by_form_id( $form_id ) {
		$options      = array();
		$forms_fields = $this->get_form_from_content( $form_id, 'fields' );
		foreach ( $forms_fields as $form_field ) {
			$options[] = array(
				'value' => $form_field['id'],
				'text'  => $form_field['label'],
			);
		}

		return $options;
	}
}
