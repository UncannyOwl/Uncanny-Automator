<?php

namespace Uncanny_Automator;

use GFCommon;
use GFFormsModel;
use RGFormsModel;

/**
 * Class Gf_Tokens
 * @package Uncanny_Automator
 */
class Gf_Tokens {

	/**
	 * Gf_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_gf_gfforms_tokens', array( $this, 'gf_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'gf_token' ), 20, 6 );
		add_filter( 'automator_maybe_trigger_gf_anongfforms_tokens', array( $this, 'gf_possible_tokens' ), 20, 2 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function gf_possible_tokens( $tokens = array(), $args = array() ) {
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
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function gf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'GFFORMS', $pieces, true ) || in_array( 'ANONGFFORMS', $pieces, true ) ) {
				if ( isset( $pieces[2] ) && 'GFFORMS' === $pieces[2] || 'ANONGFFORMS' === $pieces[2] ) {
					$t_data   = array_shift( $trigger_data );
					$form_id  = $t_data['meta'][ $pieces[2] ];
					$forminfo = RGFormsModel::get_form( $form_id );

					return $forminfo->title;
				}
				$token_piece = $pieces[2];
				global $wpdb;
				$token_info = explode( '|', $token_piece );
				$form_id    = $token_info[0];
				$meta_key   = $token_info[1];

				if ( method_exists( 'RGFormsModel', 'get_entry_table_name' ) ) {
					$table_name = RGFormsModel::get_entry_table_name();
				} else {
					$table_name = RGFormsModel::get_lead_table_name();
				}

				$where_user_id = 0 === absint( $user_id ) ? 'created_by IS NULL' : 'created_by=' . $user_id;

				$lead_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT id FROM ' . esc_sql( $table_name ) . ' WHERE ' . esc_sql( $where_user_id ) . ' AND form_id = %d ORDER BY date_created DESC LIMIT 0,1',
						$form_id
					)
				);

				if ( $lead_id ) {

					if ( method_exists( 'RGFormsModel', 'get_entry_meta_table_name' ) ) {
						$table_name = RGFormsModel::get_entry_meta_table_name();
					} else {
						$table_name = RGFormsModel::get_lead_meta_table_name();
					}

					$value = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM ' . esc_sql( $table_name ) . ' WHERE form_id = %d AND entry_id = %d AND meta_key LIKE %s', $form_id, $lead_id, $meta_key ) );
				} else {
					if ( 0 !== (int) $user_id && is_user_logged_in() ) {
						//fallback.. ... attempt to find them by email??
						if ( method_exists( 'RGFormsModel', 'get_entry_meta_table_name' ) ) {
							$table_name = RGFormsModel::get_entry_meta_table_name();
						} else {
							$table_name = RGFormsModel::get_lead_meta_table_name();
						}
						$where_user_email = get_user_by( 'ID', $user_id )->user_email;

						$lead_id = $wpdb->get_var(
							$wpdb->prepare(
								'SELECT entry_id FROM ' . esc_sql( $table_name ) . " WHERE meta_value LIKE '" . esc_sql( $where_user_email ) . "' AND form_id = %d ORDER BY entry_id DESC LIMIT 0,1",
								$form_id
							)
						);
						if ( $lead_id ) {
							if ( method_exists( 'RGFormsModel', 'get_entry_meta_table_name' ) ) {
								$table_name = RGFormsModel::get_entry_meta_table_name();
							} else {
								$table_name = RGFormsModel::get_lead_meta_table_name();
							}

							$value = $wpdb->get_var(
								$wpdb->prepare(
									'SELECT meta_value FROM ' . esc_sql( $table_name ) . ' WHERE form_id = %d AND entry_id = %d AND meta_key LIKE %s',
									$form_id,
									$lead_id,
									$meta_key
								)
							);
						} else {
							// Try again for anonymous user when its using a different email address
							if ( method_exists( 'RGFormsModel', 'get_entry_table_name' ) ) {
								$table_name = RGFormsModel::get_entry_table_name();
							} else {
								$table_name = RGFormsModel::get_lead_table_name();
							}
							$where_user_id = 'created_by IS NULL';

							$lead_id = (int) $wpdb->get_var(
								$wpdb->prepare(
									'SELECT id FROM ' . esc_sql( $table_name ) . ' WHERE ' . esc_sql( $where_user_id ) . ' AND form_id = %d ORDER BY date_created DESC LIMIT 0,1',
									$form_id
								)
							);

							if ( $lead_id ) {
								if ( method_exists( 'RGFormsModel', 'get_entry_meta_table_name' ) ) {
									$table_name = RGFormsModel::get_entry_meta_table_name();
								} else {
									$table_name = RGFormsModel::get_lead_meta_table_name();
								}

								$value = $wpdb->get_var(
									$wpdb->prepare(
										'SELECT meta_value FROM ' . esc_sql( $table_name ) . ' WHERE form_id = %d AND entry_id = %d AND meta_key LIKE %s',
										$form_id,
										$lead_id,
										$meta_key
									)
								);

							}
						}
					} elseif ( 0 !== (int) $user_id && ! is_user_logged_in() ) {
						// Try again for anonymous user when its using a different email address
						if ( method_exists( 'RGFormsModel', 'get_entry_table_name' ) ) {
							$table_name = RGFormsModel::get_entry_table_name();
						} else {
							$table_name = RGFormsModel::get_lead_table_name();
						}
						$where_user_id = 'created_by IS NULL';

						$lead_id = (int) $wpdb->get_var(
							$wpdb->prepare(
								'SELECT id FROM ' . esc_sql( $table_name ) . ' WHERE ' . esc_sql( $where_user_id ) . ' AND form_id = %d ORDER BY date_created DESC LIMIT 0,1',
								$form_id
							)
						);
						if ( $lead_id ) {
							if ( method_exists( 'RGFormsModel', 'get_entry_meta_table_name' ) ) {
								$table_name = RGFormsModel::get_entry_meta_table_name();
							} else {
								$table_name = RGFormsModel::get_lead_meta_table_name();
							}

							$value = $wpdb->get_var(
								$wpdb->prepare(
									'SELECT meta_value FROM ' . esc_sql( $table_name ) . ' WHERE form_id = %d AND entry_id = %d AND meta_key LIKE %s',
									$form_id,
									$lead_id,
									$meta_key
								)
							);
						}
					} else {
						$value = '';
					}
				}
			}
		}

		return $value;
	}
}
