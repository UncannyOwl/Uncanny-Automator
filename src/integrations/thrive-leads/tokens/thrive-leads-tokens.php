<?php

namespace Uncanny_Automator;

/**
 * Class Thrive_Leads_Tokens
 *
 * @package Uncanny_Automator
 */
class Thrive_Leads_Tokens {

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter(
			'automator_maybe_trigger_thriveleads_tokens',
			array(
				$this,
				'thrive_leads_possible_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_thrive_leads_tokens' ), 20, 6 );
	}

	/**
	 * save_token_data
	 *
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'], $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_thrive_leads_validate_common_triggers_tokens_save',
			array( 'TL_USER_SUBMIT_FORM', 'TL_ANON_SUBMIT_FORM', 'TL_ANON_REGISTRATION_FORM' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations, true ) ) {
			if ( 'TL_ANON_REGISTRATION_FORM' === $args['entry_args']['code'] ) {
				$form_data = $args['trigger_args'][1];
			} else {
				$form_data = array_shift( $args['trigger_args'] );
			}
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $form_data ) ) {
				Automator()->db->token->save( 'form_data', maybe_serialize( $form_data ), $trigger_log_entry );
			}
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array|array[]|mixed
	 */
	public function thrive_leads_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code             = (string) $args['triggers_meta']['code'];
		$form_id                  = isset( $args['triggers_meta']['TL_FORMS'] ) ? absint( $args['triggers_meta']['TL_FORMS'] ) : '-1';
		$trigger_meta_validations = apply_filters(
			'automator_thrive_leads_validate_common_possible_triggers_tokens',
			array( 'TL_USER_SUBMIT_FORM', 'TL_ANON_SUBMIT_FORM', 'TL_ANON_REGISTRATION_FORM' ),
			$args
		);

		if ( ! in_array( $trigger_code, $trigger_meta_validations, true ) ) {
			return $tokens;
		}

		$fields = array(
			array(
				'tokenId'         => 'FORM_ID',
				'tokenName'       => __( 'Form ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'FORM_NAME',
				'tokenName'       => __( 'Form name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'GROUP_ID',
				'tokenName'       => __( 'Lead group ID', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => $trigger_code,
			),
			array(
				'tokenId'         => 'GROUP_NAME',
				'tokenName'       => __( 'Lead group name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_code,
			),
		);

		if ( intval( '-1' ) !== intval( $form_id ) ) {
			$helper      = new Thrive_Leads_Helpers();
			$inputs      = $helper->get_form_fields_by_form_id( $form_id );
			$valid_types = array( 'email', 'url', 'int', 'float' );
			foreach ( $inputs as $id => $input ) {
				$type     = in_array( $input['type'], $valid_types, true ) ? $input['type'] : 'text';
				$fields[] = array(
					'tokenId'         => 'FORM_FIELD|' . $id,
					'tokenName'       => __( $input['label'], 'uncanny-automator' ),
					'tokenType'       => $type,
					'tokenIdentifier' => $trigger_code,
				);
			}
		}

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed
	 */
	public function parse_thrive_leads_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1], $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_thrive_leads_validate_common_triggers_tokens_parse',
			array( 'TL_USER_SUBMIT_FORM', 'TL_ANON_SUBMIT_FORM', 'TL_ANON_REGISTRATION_FORM' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		if ( ! array_intersect( $trigger_meta_validations, $pieces ) ) {
			return $value;
		}

		$form_data   = maybe_unserialize( Automator()->db->token->get( 'form_data', $replace_args ) );
		$field_token = explode( '|', $pieces[2] );
		$to_replace  = $pieces[2];
		if ( isset( $field_token[0], $field_token[1] ) ) {
			$to_replace = $field_token[1];
		}

		switch ( $to_replace ) {
			case 'FORM_ID':
				if ( 'TL_ANON_REGISTRATION_FORM' === $pieces[1] ) {
					$value = wp_get_post_parent_id( $form_data['_tcb_id'] );
				} else {
					$value = $form_data['thrive_leads']['tl_data']['form_type_id'];
				}
				break;
			case 'GROUP_ID':
				if ( 'TL_ANON_REGISTRATION_FORM' === $pieces[1] ) {
					$form_id = wp_get_post_parent_id( $form_data['_tcb_id'] );
					$value   = wp_get_post_parent_id( $form_id );
				} else {
					$value = $form_data['thrive_leads']['tl_data']['main_group_id'];
				}
				break;
			case 'FORM_NAME':
				if ( 'TL_ANON_REGISTRATION_FORM' === $pieces[1] ) {
					$form_id = wp_get_post_parent_id( $form_data['_tcb_id'] );
					$form    = tve_leads_get_form_variations( $form_id );
					$value   = $form[0]['post_title'];
				} else {
					$value = $form_data['thrive_leads']['tl_data']['form_name'];
				}
				break;
			case 'GROUP_NAME':
				if ( 'TL_ANON_REGISTRATION_FORM' === $pieces[1] ) {
					$form_id = wp_get_post_parent_id( $form_data['_tcb_id'] );
					$group   = get_post( wp_get_post_parent_id( $form_id ) );
					$value   = $group->post_title;
				} else {
					$value = $form_data['thrive_leads']['tl_data']['main_group_name'];
				}
				break;
			default:
				$value = $form_data[ $to_replace ];
				break;
		}

		return $value;
	}

}
