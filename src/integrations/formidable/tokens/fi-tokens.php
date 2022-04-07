<?php

namespace Uncanny_Automator;

use FrmEntry;
use FrmField;
use FrmForm;

/**
 * Class Fi_Tokens
 *
 * @package Uncanny_Automator
 */
class Fi_Tokens {

	public function __construct() {
		add_filter( 'automator_maybe_trigger_fi_fiform_tokens', array( $this, 'fi_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_fi_anonfiform_tokens', array( $this, 'fi_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'fi_token' ), 20, 6 );
		// Entry tokens
		add_filter( 'automator_maybe_trigger_fi_tokens', array( $this, 'fi_entry_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'fi_entry_tokens' ), 20, 6 );
	}

	/**
	 * Prepare tokens.
	 *
	 * @param array $tokens .
	 * @param array $args .
	 *
	 * @return array
	 */
	public function fi_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id             = $args['value'];
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$form_ids = array();
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {

			$form = FrmForm::getOne( $form_id );
			if ( $form ) {
				$form_ids[] = $form->id;
			}
		}

		if ( empty( $form_ids ) ) {
			return $tokens;
		}

		if ( ! empty( $form_ids ) ) {
			foreach ( $form_ids as $form_id ) {
				$fields = array();
				$meta   = FrmField::get_all_for_form( $form_id );
				if ( is_array( $meta ) ) {
					foreach ( $meta as $field ) {
						$input_id    = $field->id;
						$input_title = $field->name . ( '' !== $field->description ? ' (' . $field->description . ') ' : '' );
						$token_id    = "$form_id|$input_id";
						$fields[]    = array(
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => $field->type,
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
	 * @param string $value .
	 * @param array $pieces .
	 * @param string $recipe_id .
	 *
	 * @return null|string
	 */
	public function fi_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {

			if ( in_array( 'FIFORM', $pieces, true ) || in_array( 'ANONFIFORM', $pieces, true ) ) {

				if ( 'FIFORM' === $pieces[2] ) {
					if ( isset( $trigger_data[0]['meta']['FIFORM_readable'] ) ) {
						$value = $trigger_data[0]['meta']['FIFORM_readable'];
					}
				} elseif ( 'ANONFIFORM' === $pieces[2] ) {
					if ( isset( $trigger_data[0]['meta']['ANONFIFORM_readable'] ) ) {
						$value = $trigger_data[0]['meta']['ANONFIFORM_readable'];
					}
				} else {

					$token_info = explode( '|', $pieces[2] );

					$form_id  = $token_info[0];
					$meta_key = $token_info[1];
					//$user_id               = get_current_user_id();
					$s_query               = array();
					$s_query['it.form_id'] = $form_id;
					$s_query['it.user_id'] = $user_id;
					$order                 = ' ORDER BY id DESC ';
					$enrties               = FrmEntry::getAll( $s_query, $order, 1, true, false );
					$fields                = FrmField::get_all_for_form( $form_id );

					// Collect all file field types
					$file_fields = array();
					foreach ( $fields as $field ) {
						if ( isset( $field->type ) && 'file' === $field->type ) {
							$file_fields[] = $field->id;
						}
					}

					if ( ! empty( $enrties ) ) {
						foreach ( $enrties as $enrty ) {
							if ( isset( $enrty->metas ) && isset( $enrty->metas[ $meta_key ] ) ) {

								if ( is_array( $enrty->metas[ $meta_key ] ) ) {
									$value = implode( ', ', $enrty->metas[ $meta_key ] );
								} elseif ( in_array( $meta_key, $file_fields, true ) ) {

									$media_id = $enrty->metas[ $meta_key ];

									$attachment = get_post( $media_id );
									if ( ! $attachment ) {
										$value = $enrty->metas[ $meta_key ];
									}

									$image = wp_get_attachment_image( $media_id, 'thumbnail', true );

									// Check if its image. Then just return the url.
									if ( $image ) {
										$value = esc_url( wp_get_attachment_url( $media_id ) );
									}
								} else {
									$value = $enrty->metas[ $meta_key ];
								}
								break;
							}
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|\string[][]
	 */
	public function fi_entry_possible_tokens( $tokens = array(), $args = array() ) {
		$fields = array(
			array(
				'tokenId'         => 'FIENTRYID',
				'tokenName'       => __( 'Entry ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'FIENTRYTOKENS',
			),
			array(
				'tokenId'         => 'FIUSERIP',
				'tokenName'       => __( 'User IP', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'FIENTRYTOKENS',
			),
			array(
				'tokenId'         => 'FIENTRYDATE',
				'tokenName'       => __( 'Entry submission date', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'FIENTRYTOKENS',
			),
			array(
				'tokenId'         => 'FIENTRYSOURCEURL',
				'tokenName'       => __( 'Entry source URL', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'FIENTRYTOKENS',
			),
		);

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
	 * @return mixed|strings
	 */
	public function fi_entry_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( in_array( 'FIENTRYTOKENS', $pieces ) ) {
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
}
