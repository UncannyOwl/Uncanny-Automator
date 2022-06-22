<?php

namespace Uncanny_Automator;

use GFCommon;
use GFFormsModel;
use RGFormsModel;

/**
 * Class Gf_Tokens
 *
 * @package Uncanny_Automator
 */
class Gf_Tokens {

	/**
	 * Gf_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_gf_gfforms_tokens', array( $this, 'gf_possible_tokens' ), 20, 2 );
		add_filter(
			'automator_maybe_trigger_gf_' . strtolower( 'GF_SUBFORM_CODES_METADATA' ) . '_tokens',
			array(
				$this,
				'gf_possible_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'gf_token' ), 20, 6 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'gf_entry_tokens' ), 20, 6 );
		add_filter( 'automator_maybe_trigger_gf_anongfforms_tokens', array( $this, 'gf_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_gf_tokens', array( $this, 'gf_entry_possible_tokens' ), 20, 2 );

		// Save GF entry tokens, for v3 trigger.
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
	}

	/**
	 * @param $args
	 * @param $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {

		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$triggers = array( 'GF_SUBFORM_CODES' );

		if ( in_array( $args['entry_args']['code'], $triggers, true ) ) {

			list( $entry, $form ) = $args['trigger_args'];
			$code_fields          = Gravity_Forms_Helpers::get_code_fields( $entry, $form );
			if ( ! empty( $code_fields ) ) {
				$code_field = array_shift( $code_fields );
				if ( ! empty( $code_field ) && null !== $code_field ) {
					$batch = Gravity_Forms_Helpers::get_batch_by_value( $code_field, $entry );
					Automator()->db->token->save( 'UCBATCH', absint( $batch->code_group ), $args['trigger_entry'] );
				}
			}
			Automator()->db->token->save( 'GFENTRYID', $entry['id'], $args['trigger_entry'] );
			Automator()->db->token->save( 'GFUSERIP', maybe_serialize( $entry['ip'] ), $args['trigger_entry'] );
			Automator()->db->token->save( 'GFENTRYDATE', maybe_serialize( \GFCommon::format_date( $entry['date_created'], false, 'Y/m/d' ) ), $args['trigger_entry'] );
			Automator()->db->token->save( 'GFENTRYSOURCEURL', maybe_serialize( $entry['source_url'] ), $args['trigger_entry'] );

		}

	}

	/**
	 * Gravity forms entry possible tokens.
	 *
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed|\string[][]
	 */
	public function gf_entry_possible_tokens( $tokens = array(), $args = array() ) {
		$fields = array(
			array(
				'tokenId'         => 'GFENTRYID',
				'tokenName'       => __( 'Entry ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'GFENTRYTOKENS',
			),
			array(
				'tokenId'         => 'GFUSERIP',
				'tokenName'       => __( 'User IP', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'GFENTRYTOKENS',
			),
			array(
				'tokenId'         => 'GFENTRYDATE',
				'tokenName'       => __( 'Entry submission date', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'GFENTRYTOKENS',
			),
			array(
				'tokenId'         => 'GFENTRYSOURCEURL',
				'tokenName'       => __( 'Entry source URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'GFENTRYTOKENS',
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * Gravity forms possible tokens.
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function gf_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];

		$form_ids = array();
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form = GFFormsModel::get_form( $form_id );
			if ( $form ) {
				$form_ids[] = $form_id;
			}
		}

		if ( empty( $form_ids ) ) {
			return $tokens;
		}

		if ( empty( $form_ids ) ) {
			return array();
		}
		foreach ( $form_ids as $form_id ) {
			$fields = array();
			$meta   = RGFormsModel::get_form_meta( $form_id );
			if ( is_array( $meta['fields'] ) ) {
				foreach ( $meta['fields'] as $field ) {
					if ( isset( $field['inputs'] ) && is_array( $field['inputs'] ) ) {
						foreach ( $field['inputs'] as $input ) {
							$input_id    = $input['id'];
							$input_title = GFCommon::get_label( $field, $input['id'] );
							$input_type  = $this->get_field_type( $input );
							$token_id    = "$form_id|$input_id";
							$fields[]    = array(
								'tokenId'         => $token_id,
								'tokenName'       => $input_title,
								'tokenType'       => $input_type,
								'tokenIdentifier' => $trigger_meta,
							);
						}
					} elseif ( ! rgar( $field, 'displayOnly' ) ) {
						$input_id    = $field['id'];
						$input_title = GFCommon::get_label( $field );
						$token_id    = "$form_id|$input_id";
						$input_type  = $this->get_field_type( $field );
						$fields[]    = array(
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => $input_type,
							'tokenIdentifier' => $trigger_meta,
						);
					}
				}
			}
			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * Determine the field type.
	 *
	 * @param $field
	 *
	 * @return string
	 */
	public function get_field_type( $field ) {
		if ( is_object( $field ) && isset( $field->type ) ) {
			$field_type = $field->type;
		} elseif ( is_array( $field ) && key_exists( 'type', $field ) ) {
			$field_type = $field['type'];
		} else {
			$field_type = 'text';
		}

		switch ( $field_type ) {
			case 'email':
				$type = 'email';
				break;
			case 'number':
				$type = 'int';
				break;
			default:
				$type = 'text';
		}

		return $type;
	}

	/**
	 * Gravity forms entry tokens.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function gf_entry_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( in_array( 'GFENTRYTOKENS', $pieces, true ) ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					$trigger_id     = $trigger['ID'];
					$trigger_log_id = $replace_args['trigger_log_id'];
					$meta_key       = $pieces[2];
					$meta_value     = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
					if ( ! empty( $meta_value ) ) {
						$value = maybe_unserialize( $meta_value );
					}
				}
			}
		}

		return $value;
	}

	/**
	 * Parse Gravity Forms tokens.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string|null
	 */
	public function gf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( empty( $pieces ) ) {
			return $value;
		}
		$trigger_meta_validations = apply_filters(
			'automator_gravity_forms_validate_trigger_meta_pieces',
			array(
				'GF_SUBFORM_CODES',
				'GF_SUBFORM_CODES_METADATA',
				'GFFORMSCODES',
				'GFFORMS',
				'ANONGFFORMS',
				'ANONGFSUBFORM',
				'SUBFIELD',
			),
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

		if ( isset( $pieces[2] ) && ( 'GF_SUBFORM_CODES_METADATA' === $pieces[2] || 'GFFORMSCODES' === $pieces[2] || 'GFFORMS' === $pieces[2] || 'ANONGFFORMS' === $pieces[2] ) ) {
			$t_data   = array_shift( $trigger_data );
			$form_id  = $t_data['meta'][ $pieces[2] ];
			$forminfo = RGFormsModel::get_form( $form_id );

			return $forminfo->title;
		}

		if ( isset( $pieces[2] ) && 'GFFORMS_ID' === $pieces[2] ) {
			$t_data = array_shift( $trigger_data );

			return $t_data['meta']['GFFORMS'];
		}

		if ( isset( $pieces[2] ) && 'GF_SUBFORM_CODES_METADATA_ID' === $pieces[2] ) {
			$t_data = array_shift( $trigger_data );

			return $t_data['meta']['GF_SUBFORM_CODES_METADATA'];
		}

		if ( isset( $pieces[2] ) && 'ANONGFFORMS_ID' === $pieces[2] ) {
			$t_data = array_shift( $trigger_data );

			return $t_data['meta']['ANONGFFORMS'];
		}

		if ( isset( $pieces[2] ) && 'SUBVALUE' === $pieces[2] ) {
			$t_data = array_shift( $trigger_data );

			return $t_data['meta'][ $pieces[2] ];
		}

		if ( isset( $pieces[2] ) && 'SUBFIELD' === $pieces[2] ) {
			$t_data = array_shift( $trigger_data );

			return $t_data['meta'][ $pieces[2] . '_readable' ];
		}

		if ( isset( $pieces[2] ) && 'GF_SUBFORM_CODES_METADATA_CODES' === (string) $pieces[2] ) {
			$t_data = array_shift( $trigger_data );

			global $wpdb;
			$batch_id = isset( $t_data['meta']['GF_SUBFORM_CODES_METADATA_CODES'] ) && intval( '-1' ) !== intval( $t_data['meta']['GF_SUBFORM_CODES_METADATA_CODES'] ) ? $t_data['meta']['GF_SUBFORM_CODES_METADATA_CODES'] : absint( Automator()->db->token->get( 'UCBATCH', $replace_args ) );

			return $wpdb->get_var( $wpdb->prepare( "SELECT name FROM `{$wpdb->prefix}uncanny_codes_groups` WHERE ID = %d", $batch_id ) );
		}

		if ( isset( $pieces[2] ) && 'UNCANNYCODESBATCHEXPIRY' === (string) $pieces[2] ) {

			global $wpdb;

			$t_data           = array_shift( $trigger_data );
			$batch_id         = isset( $t_data['meta']['GF_SUBFORM_CODES_METADATA_CODES'] ) && intval( '-1' ) !== intval( $t_data['meta']['GF_SUBFORM_CODES_METADATA_CODES'] ) ? $t_data['meta']['GF_SUBFORM_CODES_METADATA_CODES'] : absint( Automator()->db->token->get( 'UCBATCH', $replace_args ) );
			$expiry_date      = $wpdb->get_var( $wpdb->prepare( "SELECT expire_date FROM `{$wpdb->prefix}uncanny_codes_groups` WHERE ID = %d", $batch_id ) );
			$expiry_timestamp = strtotime( $expiry_date );

			// Check if the date is in future to filter out empty dates
			if ( $expiry_timestamp > time() ) {
				// Get the format selected in general WP settings
				$date_format = get_option( 'date_format' );
				$time_format = get_option( 'time_format' );

				// Return the formattted time according to the selected time zone
				$value = date_i18n( "$date_format $time_format", strtotime( $expiry_date ) );
			}
		}

		/**
		 * Entry tokens
		 */
		$token_piece = $pieces[2];
		$token_info  = explode( '|', $token_piece );
		$form_id     = $token_info[0];
		$meta_key    = isset( $token_info[1] ) ? $token_info[1] : '';
		// Get Entry ID from meta first
		$entry_id = Automator()->db->token->get( 'GFENTRYID', $replace_args );
		if ( empty( $entry_id ) ) {
			$search_criteria                    = array();
			$search_criteria['field_filters']   = array();
			$search_criteria['field_filters'][] = array(
				'key'   => 'created_by',
				'value' => $user_id,
			);

			$sorting  = array(
				'key'       => 'date_created',
				'direction' => 'DESC',
			);
			$paging   = array(
				'offset'    => 0,
				'page_size' => 1,
			);
			$lead_ids = \GFAPI::get_entry_ids( $form_id, $search_criteria, $sorting, $paging );
			if ( empty( $lead_ids ) ) {
				return $value;
			}
			$entry_id = array_pop( $lead_ids );
		}
		$entry = \GFAPI::get_entry( $entry_id );

		if ( $entry ) {
			$field_value = rgar( $entry, $meta_key );
			if ( ! empty( $field_value ) ) {
				$value = $field_value;
			}
		}

		return $value;
	}
}
