<?php

namespace Uncanny_Automator;

use function Sodium\compare;

/**
 * Class Elem_Tokens
 *
 * @package Uncanny_Automator
 */
class Elem_Tokens {


	public function __construct() {

		add_filter( 'automator_maybe_trigger_elem_elemform_tokens', array( $this, 'elem_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'elem_token' ), 20, 6 );

		// Save latest form entry in trigger meta for tokens.
		add_action( 'automator_save_elementor_form_entry', array( $this, 'elem_save_form_entry' ), 10, 3 );
	}

	/**
	 * Prepare tokens.
	 *
	 * @param array $tokens .
	 * @param array $args .
	 *
	 * @return array
	 */
	public function elem_possible_tokens( $tokens = array(), $args = array() ) {

		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];
		if ( empty( $form_id ) ) {
			return $tokens;
		}

		global $wpdb;

		$post_metas = $wpdb->get_results( $wpdb->prepare( "SELECT pm.meta_value
FROM $wpdb->postmeta pm
    LEFT JOIN $wpdb->posts p
        ON p.ID = pm.post_id
WHERE p.post_type IS NOT NULL
  AND p.post_status = %s
  AND pm.meta_key = %s
  AND pm.`meta_value` LIKE %s
  AND pm.`meta_value` LIKE %s", 'publish', '_elementor_data', '%%form_fields%%', "%%$form_id%%" ) );

		$fields = array();
		if ( empty( $post_metas ) ) {
			return $tokens;
		}

		foreach ( $post_metas as $post_meta ) {
			$inner_forms = Automator()->helpers->recipe->elementor->get_all_inner_forms( json_decode( $post_meta->meta_value ) );
			if ( empty( $inner_forms ) ) {
				continue;
			}
			foreach ( $inner_forms as $form ) {
				if ( (string) $form->id !== (string) $form_id ) {
					continue;
				}
				if ( ! isset( $form->settings ) || empty( isset( $form->settings->form_fields ) ) ) {
					continue;
				}
				foreach ( $form->settings->form_fields as $field ) {
					$input_id = $field->custom_id;
					$token_id = "$form_id|$input_id";
					$fields[] = array(
						'tokenId'         => $token_id,
						'tokenName'       => isset( $field->field_label ) ? $field->field_label : 'Unknown',
						'tokenType'       => isset( $field->field_type ) ? $field->field_type : 'text',
						'tokenIdentifier' => $trigger_meta,
					);
				}
				$tokens = array_merge( $tokens, $fields );
			}
		}//end foreach

		return $tokens;
	}

	/**
	 * Parse the token.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return null|string
	 */
	public function elem_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$piece = 'ELEMFORM';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {

				$recipe_log_id = isset( $replace_args['recipe_log_id'] ) ? (int) $replace_args['recipe_log_id'] : Automator()->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
				if ( $trigger_data && $recipe_log_id ) {
					foreach ( $trigger_data as $trigger ) {
						if ( key_exists( $piece, $trigger['meta'] ) ) {
							$trigger_id     = $trigger['ID'];
							$trigger_log_id = $replace_args['trigger_log_id'];
							$token_info     = explode( '|', $pieces[2] );
							$form_id        = $token_info[0];
							$meta_key       = isset( $token_info[1] ) ? $token_info[1] : '';
							$meta_field     = $piece . '_' . $form_id;
							$entry          = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_field, $trigger_id, $trigger_log_id, $user_id );
							if ( ! empty( $entry ) ) {
								if ( is_array( $entry ) && ! empty( $meta_key ) ) {
									$value = isset( $entry[ $meta_key ] ) ? $entry[ $meta_key ] : '';
									if ( is_array( $value ) ) {
										$value = implode( ', ', $value );
									}
								} else {
									$value = $entry;
								}
							}
						}
					}//end foreach
				}//end if
			}//end if
		}//end if

		return $value;
	}

	/**
	 * Save form entry in meta.
	 *
	 * @param $form_id
	 * @param $recipes
	 * @param $args
	 *
	 * @return null|string
	 */
	public function elem_save_form_entry( $record, $recipes, $args ) {
		$form_id   = $record->get_form_settings( 'id' );
		$form_name = $record->get_form_settings( 'form_name' );
		$data      = $record->get( 'sent_data' );
		if ( ! empty( $data ) ) {
			$data = serialize( $data );
		}
		if ( is_array( $args ) ) {
			foreach ( $args as $trigger_result ) {
				if ( true === $trigger_result['result'] ) {

					if ( $recipes && ! empty( $form_id ) ) {
						foreach ( $recipes as $recipe ) {
							$triggers = $recipe['triggers'];
							if ( $triggers ) {
								foreach ( $triggers as $trigger ) {
									$trigger_id = $trigger['ID'];
									if ( ! key_exists( 'ELEMFORM', $trigger['meta'] ) ) {
										continue;
									} else {
										// Only form entry id will be saved.
										$user_id           = (int) $trigger_result['args']['user_id'];
										$recipe_log_id_raw = isset( $trigger_result['args']['recipe_log_id'] ) ? (int) $trigger_result['args']['recipe_log_id'] : Automator()->maybe_create_recipe_log_entry( $recipe['ID'], $user_id );
										if ( $recipe_log_id_raw ) {
											$trigger_log_id = (int) $trigger_result['args']['get_trigger_id'];
											$run_number     = (int) $trigger_result['args']['run_number'];
											$args           = array(
												'user_id'        => $user_id,
												'trigger_id'     => $trigger_id,
												'meta_key'       => 'ELEMFORM_' . $form_id,
												'meta_value'     => $data,
												'run_number'     => $run_number,
												// get run number
												'trigger_log_id' => $trigger_log_id,
											);
											Automator()->insert_trigger_meta( $args );
											// For form name
											$args = array(
												'user_id'        => $user_id,
												'trigger_id'     => $trigger_id,
												'meta_key'       => 'ELEMFORM_ELEMFORM',
												'meta_value'     => $form_name,
												'run_number'     => $run_number,
												// get run number
												'trigger_log_id' => $trigger_log_id,
											);

											Automator()->insert_trigger_meta( $args );
										}//end if
									}//end if
								}//end foreach
							}//end if
						}//end foreach
					}//end if
				}//end if
			}//end foreach
		}//end if
	}

}
