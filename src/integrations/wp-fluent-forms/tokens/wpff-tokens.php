<?php

namespace Uncanny_Automator;

/**
 * Class Wpff_Tokens
 * @package Uncanny_Automator
 */
class Wpff_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPFF';

	/**
	 * Wpff_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_wpff_wpffforms_tokens', [ $this, 'wpff_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'wpff_token' ], 20, 6 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( class_exists( 'Ninja_Forms' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	function wpff_possible_tokens( $tokens = [], $args = [] ) {

		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];

		$forms = [];
		global $wpdb;
		$fluent_active = true;
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fluentform_forms'" ) !== "{$wpdb->prefix}fluentform_forms" ) {
			$fluent_active = false;
		}
		if ( true === $fluent_active && ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form = wpFluent()->table( 'fluentform_forms' )->where( 'id', '=', $form_id )
			                  ->select( [ 'id', 'title', 'form_fields' ] )
			                  ->orderBy( 'id', 'DESC' )
			                  ->get();
			if ( $form ) {
				$form               = array_pop( $form );
				$forms[ $form->id ] = json_decode( $form->form_fields, true );
			}
		}

		if ( ! empty( $forms ) ) {
			foreach ( $forms as $id => $meta ) {
				$fields_tokens = [];
				$raw_fields    = isset( $meta['fields'] ) ? $meta['fields'] : [];
				if ( is_array( $meta ) && ! empty( $raw_fields ) ) {
					foreach ( $raw_fields as $raw_field ) {

						if ( isset( $raw_field['columns'] ) ) {
							// Fields are in a column
							foreach ( $raw_field['columns'] as $columns ) {
								foreach ( $columns as $fields_or_multi_inputs ) {

									foreach ( $fields_or_multi_inputs as $field_or_multi_input ) {

										// Skip html only feilds that are not actual form inputs
										if ( isset( $fields_or_multi_inputs['element'] ) && 'custom_html' === $fields_or_multi_inputs['element'] ) {
											continue;
										}

										// Skip file upload fields. Not supported
										if ( isset( $fields_or_multi_inputs['element'] ) && 'input_file' === $fields_or_multi_inputs['element'] ) {
											continue;
										}

										if ( isset( $field_or_multi_input['fields'] ) ) {
											// Multiple grouped fields in a column
											$field_group_name = $field_or_multi_input['attributes']['name'];

											$multi_input = $field_or_multi_input['fields'];


											foreach ( $multi_input as $field ) {

												// Skip html only feilds that are not actual form inputs
												if ( isset( $field['element'] ) && 'custom_html' === $field['element'] ) {
													continue;
												}

												// Skip file upload fields. Not supported
												if ( isset( $field['element'] ) && 'input_file' === $field['element'] ) {
													continue;
												}

												// is the field visible
												if ( false === $field['settings']['visible'] ) {
													continue;
												}

												$fields_tokens[] = $this->create_token( $form_id, $field, $trigger_meta, $field_group_name );
											}

										} else {

											// Multiple fields are in a column and are NOT grouped

											$field = $field_or_multi_input;

											// Skip html only feilds that are not actual form inputs
											if ( isset( $field['element'] ) && 'custom_html' === $field['element'] ) {
												continue;
											}

											// Skip file upload fields. Not supported
											if ( isset( $field['element'] ) && 'input_file' === $field['element'] ) {
												continue;
											}

											// Single field in column
											$fields_tokens[] = $this->create_token( $form_id, $field, $trigger_meta );
										}
									}
								}
							}
						} elseif ( isset( $raw_field['fields'] ) ) {

							foreach ( $raw_field['fields'] as $field ) {
								if ( 1 === (int) $field['settings']['visible'] ) {
									$input_id    = $field['uniqElKey'];
									$input_title = $field['settings']['label'];
									$token_id    = "$form_id|$input_id";

									$type = 'text';
									if ( isset( $f_fields['attributes']['type'] ) ) {
										if ( 'number' === $field['attributes']['type'] ) {
											$type = 'int';
										} else {
											$type = $field['attributes']['type'];
										}
									}
									$fields_tokens[] = [
										'tokenId'         => $token_id,
										'tokenName'       => $input_title,
										'tokenType'       => $type,
										'tokenIdentifier' => $trigger_meta,
									];
								}
							}
						} elseif ( isset( $raw_field['attributes']['name'] ) ) {

							// Skip html only feilds that are not actual form inputs
							if ( isset( $raw_field['element'] ) && 'custom_html' === $raw_field['element'] ) {
								continue;
							}

							// Skip file upload fields. Not supported
							if ( isset( $raw_field['element'] ) && 'input_file' === $raw_field['element'] ) {
								continue;
							}

							$field = $raw_field;

							$fields_tokens[] = $this->create_token( $form_id, $field, $trigger_meta );
						}


					}
				}


				$tokens = array_merge( $tokens, $fields_tokens );
			}
		}

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
	 * @return null|string
	 */
	public function wpff_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'WPFFFORMS', $pieces ) ) {
				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[1];
				$field          = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
				$entry          = $wpdb->get_var( "SELECT meta_value 
													FROM {$wpdb->prefix}uap_trigger_log_meta 
													WHERE meta_key = '$trigger_meta' 
													AND automator_trigger_log_id = $trigger_log_id
													AND automator_trigger_id = $trigger_id
													LIMIT 0, 1" );
				$entry          = maybe_unserialize( $entry );
				$to_match       = "{$trigger_id}:{$trigger_meta}:{$field}";
				if ( is_array( $entry ) && key_exists( $to_match, $entry ) ) {
					$value = $entry[ $to_match ];
				} else {
					$value = '';
				}
			}
		}

		return $value;
	}

	/**
	 * @param $form_id
	 * @param $field
	 * @param $trigger_meta
	 * @param $field_group_name
	 *
	 * @return array
	 */
	public function create_token( $form_id, $field, $trigger_meta, $field_group_name = '' ) {

		$field_label = $field['settings']['label'];
		$field_name  = $field['attributes']['name'];

		if ( isset( $field['attributes']['type'] ) ) {
			$field_type = $field['attributes']['type'];
		} else {
			$field_type = $field['element'];
		}


		switch ( $field_type ) {
			case 'number':
				$type = 'int';
				break;
			default:
				$type = 'text';
				break;
		}

		if ( empty( $field_group_name ) ) {
			$token_id = "$form_id|$field_name";
		} else {
			$token_id = "$form_id|$field_group_name|$field_name";
		}

		return [
			'tokenId'         => $token_id,
			'tokenName'       => $field_label,
			'tokenType'       => $type,
			'tokenIdentifier' => $trigger_meta,
		];

	}
}