<?php

namespace Uncanny_Automator\Integrations\Thrive_Theme;

/**
 * Class Thrive_Theme_Heplers
 * @package Uncanny_Automator
 */
class Thrive_Theme_Helpers {

	/**
	 * @return array|array[]
	 */
	public function get_thrive_theme_forms() {

		if ( ! class_exists( '\TCB\inc\helpers\FormSettings' ) ) {
			return array();
		}

		$form_query = new \WP_Query(
			array(
				'post_type'      => \TCB\inc\helpers\FormSettings::POST_TYPE,
				'fields'         => 'id=>parent',
				'posts_per_page' => '-1',
				'post_status'    => 'draft',
			)
		);

		$options = array(
			array(
				'text'  => _x( 'Any form', 'Thrive Theme', 'uncanny-automator' ),
				'value' => - 1,
			),
		);

		foreach ( $form_query->posts as $form_post ) {

			$form_settings = \TCB\inc\helpers\FormSettings::get_one( $form_post->ID );

			if ( empty( $form_settings ) ) {
				return array();
			}

			$post = get_post( $form_post->post_parent );

			if ( ! empty( $post ) && $post->post_status !== 'trash' ) {

				$saved_identifier = $form_settings->form_identifier;

				if ( empty( $saved_identifier ) && ! empty( $form_post->post_parent ) ) {

					$form_identifier           = ( empty( $post->post_name ) ? '' : $post->post_name ) . '-form-' . substr( uniqid( '', true ), - 6, 6 );
					$config                    = $form_settings->get_config( false );
					$config['form_identifier'] = $form_identifier;
					$post_title                = 'Form settings' . ( $form_post->post_parent ? ' for content ' . $form_post->post_parent : '' );
					$form_settings->set_config( $config )
								  ->save( $post_title, array( 'post_parent' => $form_post->post_parent ) );
				}

				$form_id = $form_settings->form_identifier;

				$options[] = array(
					'text'  => $form_id,
					'value' => $form_id . '|' . $form_post->ID,
				);
			}
		}

		return $options;

	}

	/**
	 * @param $form_data
	 *
	 * @return array
	 */
	public function get_extract_form_id_post_id( $form_data ) {
		$form_detail = explode( '|', $form_data );

		return array(
			'form_identifier' => isset( $form_detail[0] ) ? $form_detail[0] : '',
			'form_post_id'    => isset( $form_detail[1] ) ? $form_detail[1] : '',
		);
	}

	/**
	 * @return array[]
	 */
	public function get_form_common_tokens() {
		return array(
			array(
				'tokenId'   => 'FORM_ID',
				'tokenName' => __( 'Form ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FORM_NAME',
				'tokenName' => __( 'Form title', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * @param $from_details
	 *
	 * @return array
	 */
	public function get_from_field_tokens( $from_details ) {
		$tokens = array();
		if ( intval( '-1' ) === intval( $from_details ) ) {
			return $tokens;
		}

		$form          = $this->get_extract_form_id_post_id( $from_details );
		$form_settings = \TCB\inc\helpers\FormSettings::get_one( $form['form_post_id'] );
		$form_settings = (array) json_decode( $form_settings->get_config(), true );

		// Iterate through the inputs and serve them as tokens.
		foreach ( (array) $form_settings['inputs'] as $id => $props ) {
			$tokens[] = array(
				'tokenId'   => $id,
				'tokenName' => $props['label'],
				'tokenType' => 'text',
			);
		}

		return $tokens;
	}
}
