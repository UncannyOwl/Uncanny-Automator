<?php

namespace Uncanny_Automator;

/**
 * Class Ws_Form_Lite_Tokens
 *
 * @package Uncanny_Automator
 */
class Ws_Form_Lite_Tokens {
	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_wsformlite_tokens', array( $this, 'wsformlite_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wsformlite_tokens' ), 20, 6 );
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
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_wsformlite_validate_common_trigger_tokens_save',
			array( 'WSFORM_FROM_SUBMITTED', 'WSFORM_ANON_FROM_SUBMITTED' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations ) ) {
			$ws_form_submitted = $args['trigger_args'][0];
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $ws_form_submitted ) ) {
				Automator()->db->token->save( 'form_id', $ws_form_submitted->form_id, $trigger_log_entry );
				Automator()->db->token->save( 'form_entry_id', $ws_form_submitted->id, $trigger_log_entry );
			}
		}
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|array[]|mixed
	 */
	public function wsformlite_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];
		$form_id      = ! empty( $args['triggers_meta']['WSFORM_FORMS'] ) ? $args['triggers_meta']['WSFORM_FORMS'] : '';

		if ( empty( $form_id ) ) {
			return $tokens;
		}

		$trigger_meta_validations = apply_filters(
			'automator_wsformlite_validate_common_possible_trigger_tokens',
			array( 'WSFORM_FROM_SUBMITTED', 'WSFORM_ANON_FROM_SUBMITTED' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {
			$fields = array(
				array(
					'tokenId'         => 'FORM_ID',
					'tokenName'       => __( 'Form ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FORM_TITLE',
					'tokenName'       => __( 'Form title', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$form_fields = $this->get_form_fields( $form_id );
			if ( ! empty( $form_fields ) ) {
				foreach ( $form_fields as $field ) {
					$fields[] = array(
						'tokenId'         => "wsf_$form_id|field_$field->id",
						'tokenName'       => __( $field->label, 'uncanny-automator' ),
						'tokenType'       => $field->type,
						'tokenIdentifier' => $trigger_code,
					);
				}
			}

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * Get the fields for the form by Form ID.
	 *
	 * @param int $id  - Form ID
	 *
	 * @return array - Array of field objects
	 */
	public function get_form_fields( $id ) {

		static $helper = null;
		if ( null === $helper ) {
			$helper = new Ws_Form_Lite_Helpers( false );
		}

		return $helper->get_form_fields( $id );
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return false|int|mixed|string
	 */
	public function parse_wsformlite_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_wsformlite_parse_common_trigger_tokens',
			array( 'WSFORM_FROM_SUBMITTED', 'WSFORM_ANON_FROM_SUBMITTED' ),
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

		$entry_id   = false;
		$to_replace = $pieces[2];
		$token      = explode( '|', $to_replace );
		if ( is_array( $token ) && isset( $token[1] ) ) {
			$entry_id = Automator()->db->token->get( 'form_entry_id', $replace_args );
		} else {
			$form_id = Automator()->db->token->get( 'form_id', $replace_args );
		}

		switch ( $to_replace ) {
			case 'FORM_ID':
				$value = $form_id;
				break;
			case 'FORM_TITLE':
				global $wpdb;
				$value = $wpdb->get_var( $wpdb->prepare( "SELECT label FROM {$wpdb->prefix}wsf_form WHERE id=%d", $form_id ) );
				break;
			default:
				if ( $entry_id ) {
					global $wpdb;
					$field_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wsf_submit_meta WHERE parent_id=%d AND meta_key LIKE %s", $entry_id, $token[1] ) );
					$value       = maybe_unserialize( $field_value );
					// Check for File Uploads.
					if ( is_array( $value ) && ! empty( $value[0] ) && is_array( $value[0] ) && isset( $value[0]['size'] ) && isset( $value[0]['type'] ) ) {
						$value = $this->get_file_upload_value( $value );
					}
				}
				break;
		}

		return $value;
	}

	/**
	 * Get CSV string of file upload URLs.
	 *
	 * @param array $files
	 *
	 * @return string
	 */
	public function get_file_upload_value( $files ) {
		$value = '';
		foreach ( $files as $file ) {
			if ( isset( $file['attachment_id'] ) && ! empty( $file['attachment_id'] ) ) {
				// Get the URL if public.
				$url = wp_get_attachment_url( $file['attachment_id'] );
				if ( empty( $url ) ) {
					// Give the admin URL if not public.
					$url .= admin_url( 'upload.php?item=' . $file['attachment_id'] );
				}
				$value .= $url . ', ';
			}
		}

		// Remove the trailing comma and space.
		$value = ! empty( $value ) ? rtrim( $value, ', ' ) : '';

		// Allow 3rd parties to filter the value.
		return apply_filters( 'automator_wsformlite_file_token', $value, $files );
	}

}
