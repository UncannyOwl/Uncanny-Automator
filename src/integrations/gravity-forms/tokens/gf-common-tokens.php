<?php

namespace Uncanny_Automator;

/**
 * This token class implements a new token architecture.
 *
 * @since 4.7
 */
class GF_COMMON_TOKENS {

	/**
	 * @var array|mixed|null
	 */
	public $triggers = array();

	/**
	 *
	 */
	public function __construct() {

		// Applying some filter so PRO can extend it.
		$this->triggers = apply_filters(
			'automator_gf_common_tokens_form_tokens',
			array(
				'ANON_GF_FORM_ENTRY_UPDATED',
			),
			$this
		);

		foreach ( $this->triggers as $trigger ) {

			add_filter(
				'automator_token_renderable_before_set_' . strtolower( $trigger ),
				array(
					$this,
					'modify_common_tokens',
				),
				10,
				4
			);

		}

	}

	/**
	 * Modify the common tokens to insert dynamic fields from Gravity forms.
	 *
	 * @return array The list of additional common tokens.
	 */
	public function modify_common_tokens( $tokens_renderable, $trigger_code, $tokens, $args ) {

		$form_id = ! empty( $args['triggers_meta'][ $trigger_code . '_META' ] ) ? intval( $args['triggers_meta'][ $trigger_code . '_META' ] ) : 0;

		if ( - 1 === $form_id || empty( $form_id ) ) {

			return $tokens_renderable;

		}

		$form_selected = \GFAPI::get_form( $form_id );

		$fields = ! empty( $form_selected['fields'] ) ? $form_selected['fields'] : array();

		foreach ( $fields as $field ) {

			if ( in_array( $field['type'], array( 'html', 'section' ), true ) ) {
				continue; // Skip.
			}

			// Supports multi-input fields.
			if ( in_array( $field['type'], array( 'address', 'name', 'checkbox' ), true ) ) {

				// Normal fields.
				foreach ( $field['inputs'] as $input ) {

					$tokens_renderable[ 'field_' . $input['id'] ] = array(
						'name' => ! empty( $input['label'] ) ? esc_html( $field['label'] . ' - ' . $input['label'] ) : 'Field input - ' . $field['id'],
					);

				}
			} else {

				$tokens_renderable[ 'field_' . $field['id'] ] = array(
					'name' => ! empty( $field['label'] ) ? esc_html( $field['label'] ) : 'Field - ' . $field['id'],
				);

			}
		}

		return $tokens_renderable;

	}

	/**
	 * Common tokens can be a static method since they do not really inherit
	 * or have any dependencies and can be called independently without creating new instance.
	 *
	 * @return array The list of tokens.
	 */
	public static function get_common_tokens() {

		return array(
			'ENTRY_ID'             => array(
				'name'         => __( 'Entry ID', 'uncanny-automator' ),
				'hydrate_with' => 'trigger_args|1',
			),
			'ENTRY_DATE_SUBMITTED' => array( 'name' => __( 'Entry submission date', 'uncanny-automator' ) ),
			'ENTRY_DATE_UPDATED'   => array( 'name' => __( 'Entry date updated', 'uncanny-automator' ) ),
			'ENTRY_ID'             => array( 'name' => __( 'Entry ID', 'uncanny-automator' ) ),
			'ENTRY_URL_SOURCE'     => array( 'name' => __( 'Entry source URL', 'uncanny-automator' ) ),
			'FORM_TITLE'           => array( 'name' => __( 'Form title', 'uncanny-automator' ) ),
			'FORM_ID'              => array( 'name' => __( 'Form ID', 'uncanny-automator' ) ),
			'USER_IP'              => array( 'name' => __( 'User IP', 'uncanny-automator' ) ),
		);

	}

	/**
	 * get_hydrated_common_tokens
	 *
	 * @param mixed $parsed
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return array
	 */
	public static function get_hydrated_common_tokens( $parsed, $args, $trigger ) {

		list( $form, $entry_id, $previous_entry ) = $args['trigger_args'];

		$entry = \GFAPI::get_entry( $entry_id );

		$hydrated_common_tokens = array(
			'ENTRY_DATE_SUBMITTED' => $entry['date_created'],
			'ENTRY_DATE_UPDATED'   => $entry['date_updated'],
			'ENTRY_URL_SOURCE'     => $entry['source_url'],
			'ENTRY_ID'             => $entry_id,
			'FORM_TITLE'           => $form['title'],
			'FORM_ID'              => $entry['form_id'],
			'USER_IP'              => $entry['ip'],
		);

		return $parsed + $hydrated_common_tokens;

	}

	/**
	 * get_hydrated_form_tokens
	 *
	 * @param mixed $parsed
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return array
	 */
	public static function get_hydrated_form_tokens( $parsed, $args, $trigger ) {

		list ( $form, $entry_id, $previous_entry ) = $args['trigger_args'];

		$entry = \GFAPI::get_entry( $entry_id );

		// Filter the fields.
		$fields = array_filter(
			$entry,
			function ( $entry_key ) {
				return is_numeric( $entry_key );
			},
			ARRAY_FILTER_USE_KEY
		);

		foreach ( $fields as $id => $value ) {

			if ( ! empty( $value ) ) {

				$hydrated_fields[ 'field_' . $id ] = $value;

				// Getting the field type.
				$current_field = array_filter(
					$form['fields'],
					function ( $field ) use ( $id ) {
						return absint( $field['id'] ) === absint( $id );
					}
				);

				$current_field = ! empty( $current_field ) ? end( $current_field ) : 0;

				// Supports list.
				if ( 'list' === $current_field['type'] && ! empty( $value ) ) {

					$field_data = maybe_unserialize( $value );

					if ( is_array( $field_data ) ) {

						$field_data = (array) $field_data;

						$hydrated_fields[ 'field_' . $id ] = join( ', ', $field_data );

					}
				}

				// Supports multiselect.
				if ( 'multiselect' === $current_field['type'] && ! empty( $value ) ) {

					if ( Automator()->utilities->is_json_string( $value ) ) {

						$hydrated_fields[ 'field_' . $id ] = join( ', ', json_decode( $value ) );

					}
				}
			}
		}

		if ( ! empty( $hydrated_fields ) ) {
			return $parsed + $hydrated_fields;
		}

		return $parsed;

	}

}
