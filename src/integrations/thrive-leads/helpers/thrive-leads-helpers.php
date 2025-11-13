<?php

namespace Uncanny_Automator\Integrations\Thrive_Leads;

use TCB\inc\helpers\FormSettings;

/**
 * Class Thrive_Leads_Helpers
 *
 * @package Uncanny_Automator
 */
class Thrive_Leads_Helpers {

	/**
	 * Get all Thrive Lead forms for dropdown options
	 *
	 * @param bool $has_option_any Whether to include "Any form" option
	 * @param bool $latest Whether to use latest format (value/text arrays)
	 *
	 * @return array The list of forms
	 */
	public function get_all_thrive_lead_forms( $has_option_any = false, $latest = true ) {
		$all_forms       = array();
		$lg_ids          = $this->get_thrive_leads();
		$processed_forms = array(); // Track processed forms to avoid duplicates

		foreach ( $lg_ids as $lg_id => $lg_parent ) {
			$variations = tve_leads_get_form_variations( $lg_parent );
			foreach ( $variations as $variation ) {
				// Use form parent ID as key to avoid duplicates
				$form_key   = $lg_parent;
				$form_title = $variation['post_title'];

				// Only add if we haven't processed this form parent ID yet
				if ( ! isset( $processed_forms[ $form_key ] ) ) {
					if ( $latest ) {
						$all_forms[] = array(
							'value' => $form_key,
							'text'  => $form_title,
						);
					} else {
						$all_forms[ $form_key ] = $form_title;
					}
					$processed_forms[ $form_key ] = true;
				}
			}
		}

		if ( $has_option_any ) {
			if ( $latest ) {
				array_unshift(
					$all_forms,
					array(
						'value' => -1,
						'text'  => esc_html_x( 'Any form', 'Thrive Leads', 'uncanny-automator' ),
					)
				);
			} else {
				$all_forms = array( '-1' => esc_html_x( 'Any form', 'Thrive Leads', 'uncanny-automator' ) ) + $all_forms;
			}
		}

		return $all_forms;
	}

	/**
	 * @param $form_id
	 *
	 * @return array
	 */
	public function get_form_fields_by_form_id( $form_id ) {
		$fields = array();
		$lg_ids = $this->get_thrive_leads( $form_id );
		foreach ( $lg_ids as $lg_id => $lg_parent ) {
			$lg_post   = FormSettings::get_one( $lg_id );
			$lg_config = $lg_post->get_config( false );
			foreach ( $lg_config['inputs'] as $key => $input ) {
				if ( 'password' !== $input['type'] && 'confirm_password' !== $input['type'] ) {
					$fields[ $key ] = $input;
				}
			}
		}

		return $fields;
	}

	/**
	 * @param $form_id
	 *
	 * @return int[]|\WP_Post[]
	 */
	public function get_thrive_leads( $form_id = null ) {
		$lead_args = array(
			'post_type'      => '_tcb_form_settings',
			'posts_per_page' => 99999, //phpcs:ignore
			'post_status'    => 'any',
		);

		if ( is_numeric( $form_id ) ) {
			$lead_args['post_parent'] = $form_id;
		}

		$leads  = get_posts( $lead_args );
		$return = array();

		if ( $leads ) {
			foreach ( $leads as $l ) {
				$return[ $l->ID ] = $l->post_parent;
			}
		}

		return $return;
	}

	public function get_common_tokens() {
		return array(
			'FORM_ID' => array(
				'name' => esc_html_x( 'Form ID', 'Thrive Leads', 'uncanny-automator' ),
				'type' => 'int',
				'tokenId' => 'FORM_ID',
				'tokenName' => esc_html_x( 'Form ID', 'Thrive Leads', 'uncanny-automator' ),
			),
			'FORM_NAME' => array(
				'name' => esc_html_x( 'Form name', 'Thrive Leads', 'uncanny-automator' ),
				'type' => 'text',
				'tokenId' => 'FORM_NAME',
				'tokenName' => esc_html_x( 'Form name', 'Thrive Leads', 'uncanny-automator' ),
			),
			'GROUP_ID' => array(
				'name' => esc_html_x( 'Lead group ID', 'Thrive Leads', 'uncanny-automator' ),
				'type' => 'int',
				'tokenId' => 'GROUP_ID',
				'tokenName' => esc_html_x( 'Lead group ID', 'Thrive Leads', 'uncanny-automator' ),
			),
			'GROUP_NAME' => array(
				'name' => esc_html_x( 'Lead group name', 'Thrive Leads', 'uncanny-automator' ),
				'type' => 'text',
				'tokenId' => 'GROUP_NAME',
				'tokenName' => esc_html_x( 'Lead group name', 'Thrive Leads', 'uncanny-automator' ),
			),
		);
	}

	public function get_form_field_tokens($form_id) {

		$fields = array();

		if ( intval( '-1' ) !== intval( $form_id ) ) {
			$inputs      = $this->get_form_fields_by_form_id( $form_id );
			$valid_types = array( 'email', 'url', 'int', 'float' );
			foreach ( $inputs as $id => $input ) {
				$type     = in_array( $input['type'], $valid_types, true ) ? $input['type'] : 'text';
				$fields[] = array(
					'tokenId'         => 'FORM_FIELD|' . $id,
					'tokenName'       => esc_html( $input['label'] ),
					'tokenType'       => $type,
				);
			}
		}
		
		return $fields;
	}
}
