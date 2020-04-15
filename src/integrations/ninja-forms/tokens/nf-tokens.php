<?php

namespace Uncanny_Automator;


/**
 * Class Nf_Tokens
 * @package uncanny_automator
 */
class Nf_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'NF';

	public function __construct() {
		//*************************************************************//
		// See this filter generator AT automator-get-data.php
		// in function recipe_trigger_tokens()
		//*************************************************************//

		add_filter( 'automator_maybe_trigger_nf_nfforms_tokens', [ $this, 'nf_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'nf_token' ], 20, 6 );
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
	function nf_possible_tokens( $tokens = [], $args = [] ) {
		$form_id             = $args['value'];
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$form_ids = [];
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form = \Ninja_Forms()->form( $form_id )->get();
			if ( $form ) {
				$form_ids[] = $form->get_id();
			}
		}

		if ( empty( $form_ids ) ) {
			$forms = \Ninja_Forms()->form()->get_forms();
			foreach ( $forms as $form ) {
				$form_ids[] = $form->get_id();
			}
		}

		if ( ! empty( $form_ids ) ) {
			foreach ( $form_ids as $form_id ) {
				$fields = [];
				$meta   = \Ninja_Forms()->form( $form_id )->get_fields();
				if ( is_array( $meta ) ) {
					foreach ( $meta as $field ) {
						if ( $field->get_setting( 'type' ) !== 'submit' ) {
							$input_id    = $field->get_id();
							$input_title = $field->get_setting( 'label' );
							$token_id    = "$form_id|$input_id";
							$fields[]    = [
								'tokenId'         => $token_id,
								'tokenName'       => $input_title,
								'tokenType'       => $field->get_setting( 'type' ),
								'tokenIdentifier' => $trigger_meta,
							];
						}
					}
				}
				$tokens = array_merge( $tokens, $fields );
			}
		}

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 *
	 * @return null|string
	 */
	function nf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'NFFORMS', $pieces ) ) {
				$token_info = explode( '|', $pieces[2] );
				$form_id    = $token_info[0];
				$meta_key   = $token_info[1];
				//$user_id    = get_current_user_id();
				// Saving data as post. Get post by current user and form id
				$args  = array(
					'post_type'      => 'nf_sub',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'author'         => $user_id,
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'meta_query'     => array(
						array(
							'key'   => '_form_id',
							'value' => $form_id,
						)
					)
				);
				$posts = get_posts( $args );

				$sub   = Ninja_Forms()->form()->sub( $posts[0]->ID )->get();
				$value = $sub->get_field_value( $meta_key );
			}
		}

		return $value;
	}
}