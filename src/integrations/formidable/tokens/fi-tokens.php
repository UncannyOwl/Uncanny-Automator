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

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'FI';

	public function __construct() {
		//*************************************************************//
		// See this filter generator AT automator-get-data.php
		// in function recipe_trigger_tokens()
		//*************************************************************//

		add_filter( 'automator_maybe_trigger_fi_fiform_tokens', [ $this, 'fi_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'fi_token' ], 20, 6 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related
	 * plugin is active
	 *
	 * @param bool   $status status of plugin.
	 * @param string $plugin plugin code.
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( class_exists( 'FrmHooksController' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * Prepare tokens.
	 *
	 * @param array $tokens .
	 * @param array $args   .
	 *
	 * @return array
	 */
	public function fi_possible_tokens( $tokens = [], $args = [] ) {
		$form_id             = $args['value'];
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$form_ids = [];
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {

			$form = FrmForm::getOne( $form_id );
			if ( $form ) {
				$form_ids[] = $form->id;
			}
		}

		if ( empty( $form_ids ) ) {
			$s_query                = [
				[
					'or'               => 1,
					'parent_form_id'   => null,
					'parent_form_id <' => 1,
				],
			];
			$s_query['is_template'] = 0;
			$s_query['status !']    = 'trash';

			$forms = FrmForm::getAll( $s_query, '', ' 0, 999' );
			foreach ( $forms as $form ) {
				$form_ids[] = $form->id;
			}
		}

		if ( ! empty( $form_ids ) ) {
			foreach ( $form_ids as $form_id ) {
				$fields = [];
				$meta   = FrmField::get_all_for_form( $form_id );
				if ( is_array( $meta ) ) {
					foreach ( $meta as $field ) {
						$input_id    = $field->id;
						$input_title = $field->name . ( $field->description !== '' ? ' (' . $field->description . ') ' : '' );
						$token_id    = "$form_id|$input_id";
						$fields[]    = [
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => $field->type,
							'tokenIdentifier' => $trigger_meta,
						];
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
	 * @param string $value     .
	 * @param array  $pieces    .
	 * @param string $recipe_id .
	 *
	 * @return null|string
	 */
	public function fi_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {

			if ( in_array( 'FIFORM', $pieces, true ) ) {

				if ( 'FIFORM' === $pieces[2] ) {
					if ( isset( $trigger_data[0]['meta']['FIFORM_readable'] ) ) {
						$value = $trigger_data[0]['meta']['FIFORM_readable'];
					}
				} else {
					$token_info = explode( '|', $pieces[2] );

					$form_id  = $token_info[0];
					$meta_key = $token_info[1];
					//$user_id               = get_current_user_id();
					$s_query               = [];
					$s_query['it.form_id'] = $form_id;
					$s_query['it.user_id'] = $user_id;
					$order                 = ' ORDER BY id DESC ';
					$enrties               = FrmEntry::getAll( $s_query, $order, 1, true, false );
					$fields = FrmField::get_all_for_form( $form_id );

					// Collect all file field types
					$file_fields = [];
					foreach($fields as $field ){
						if(isset($field->type) && 'file' === $field->type){
							$file_fields[] = $field->id;
						}
					}

					if ( ! empty( $enrties ) ) {
						foreach ( $enrties as $enrty ) {
							if ( isset( $enrty->metas )
								 && isset( $enrty->metas[ $meta_key ] )
							) {

								if ( is_array( $enrty->metas[ $meta_key ] ) ) {
									$value = implode( ', ', $enrty->metas[ $meta_key ] );
								} elseif( in_array($meta_key, $file_fields)){

									$media_id = $enrty->metas[ $meta_key ];

									$attachment = get_post($media_id);
									if ( ! $attachment ) {
										$value = $enrty->metas[ $meta_key ];
									}

									$image = $orig_image = wp_get_attachment_image($media_id, 'thumbnail', true);

									//if this is a mime type icon
									if ( $image && ! preg_match( '/wp-content\/uploads/', $image ) ) {
										$label = basename($attachment->guid);
									}
									if ( $image ) {
										$value = '<a href="' . esc_url( wp_get_attachment_url( $media_id ) ) . '">' . $label . '</a>';
									}
								}else {
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
}