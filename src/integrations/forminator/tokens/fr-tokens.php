<?php

namespace Uncanny_Automator;

use Forminator_API;

/**
 * Class Fr_Tokens
 *
 * @package Uncanny_Automator
 */
class Fr_Tokens {

	/**
	 * Fr_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_fr_frform_tokens', array( $this, 'fr_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'fr_token' ), 20, 6 );

		// Save latest form entry in trigger meta for tokens.
		add_action( 'automator_save_forminator_form_entry', array( $this, 'fr_save_form_entry' ), 10, 3 );
	}

	/**
	 * Prepare tokens.
	 *
	 * @param array $tokens .
	 * @param array $args .
	 *
	 * @return array
	 */
	public function fr_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];

		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form_meta = Forminator_API::get_form_fields( $form_id );
			if ( isset( $form_meta ) && ! empty( $form_meta ) ) {
				$fields = array();
				foreach ( $form_meta as $field ) {
					if ( isset( $field->raw['field_label'] ) ) {
						$input_id    = $field->slug;
						$input_title = $field->raw['field_label'];
						$token_id    = "$form_id|$input_id";
						$fields[]    = array(
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => $field->raw['type'],
							'tokenIdentifier' => $trigger_meta,
						);
					}
				}

				$tokens = array_merge( $tokens, $fields );
			}
		}

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
	public function fr_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$piece = 'FRFORM';
		if ( ! $pieces ) {
			return $value;
		}
		$recipe_log_id = isset( $replace_args['recipe_log_id'] ) ? (int) $replace_args['recipe_log_id'] : Automator()->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];
		if ( ! $trigger_data || ! $recipe_log_id ) {
			return $value;
		}

		foreach ( $trigger_data as $trigger ) {
			if ( empty( $trigger ) ) {
				continue;
			}

			if ( ! is_array( $trigger ) ) {
				continue;
			}

			if ( ! array_key_exists( $piece, $trigger['meta'] ) ) {
				continue;
			}

			// Render Form Name
			if ( isset( $pieces[2] ) && $piece === $pieces[2] ) {
				foreach ( $trigger_data as $t_d ) {
					if ( empty( $t_d ) ) {
						continue;
					}
					if ( isset( $t_d['meta'][ $piece . '_readable' ] ) ) {
						return $t_d['meta'][ $piece . '_readable' ];
					}
				}
			}
			// Render Form ID
			if ( isset( $pieces[2] ) && $piece . '_ID' === $pieces[2] ) {
				foreach ( $trigger_data as $t_d ) {
					if ( empty( $t_d ) ) {
						continue;
					}
					if ( isset( $t_d['meta'][ $piece ] ) ) {
						return $t_d['meta'][ $piece ];
					}
				}
			}

			$trigger_id     = $trigger['ID'];
			$trigger_log_id = $replace_args['trigger_log_id'];
			$token_info     = explode( '|', $pieces[2] );
			$form_id        = $token_info[0];
			$meta_key       = $token_info[1];
			$match          = "{$trigger_id}:{$piece}:{$form_id}|{$meta_key}";
			$parse_tokens   = array(
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'user_id'        => $user_id,
			);
			$value          = Automator()->db->trigger->get_token_meta( $match, $parse_tokens );
			$value          = maybe_unserialize( $value );
			if ( is_array( $value ) ) {
				$value = join( ' ', $value );
			}
		}

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
	public function fr_save_form_entry( $form_id, $recipes, $args ) {
		if ( ! is_array( $args ) ) {
			return;
		}
		foreach ( $args as $trigger_result ) {
			if ( true !== $trigger_result['result'] ) {
				continue;
			}

			if ( ! $recipes || 0 === absint( $form_id ) ) {
				continue;
			}
			foreach ( $recipes as $recipe ) {
				$triggers = $recipe['triggers'];
				if ( ! $triggers ) {
					continue;
				}
				foreach ( $triggers as $trigger ) {
					$trigger_id = $trigger['ID'];
					if ( ! key_exists( 'FRFORM', $trigger['meta'] ) ) {
						continue;
					}
					// Only form entry id will be saved.
					$form_entry        = forminator_get_latest_entry_by_form_id( $form_id );
					$data              = $form_entry->entry_id;
					$user_id           = (int) $trigger_result['args']['user_id'];
					$recipe_log_id_raw = isset( $trigger_result['args']['recipe_log_id'] ) ? (int) $trigger_result['args']['recipe_log_id'] : Automator()->maybe_create_recipe_log_entry( $recipe['ID'], $user_id );
					if ( $recipe_log_id_raw ) {
						$trigger_log_id = (int) $trigger_result['args']['get_trigger_id'];
						$run_number     = (int) $trigger_result['args']['run_number'];
						$args           = array(
							'user_id'        => $user_id,
							'trigger_id'     => $trigger_id,
							'meta_key'       => 'FRFORM_' . $form_id,
							'meta_value'     => $data,
							'run_number'     => $run_number, //get run number
							'trigger_log_id' => $trigger_log_id,
						);

						Automator()->insert_trigger_meta( $args );
					}
				}
			}
		}
	}
}
