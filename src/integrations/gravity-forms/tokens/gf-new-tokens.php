<?php

namespace Uncanny_Automator\Integrations\Gravity_Forms;

class Gravity_Forms_Tokens {

	/**
	 * entry_tokens
	 *
	 * @return array
	 */
	public function entry_tokens() {

		$tokens = array();

		$tokens[] = array(
			'tokenId'   => 'ENTRY_ID',
			'tokenName' => __( 'Entry ID', 'automator-sample' ),
		);

		$tokens[] = array(
			'tokenId'   => 'ENTRY_DATE_SUBMITTED',
			'tokenName' => __( 'Entry submission date', 'automator-sample' ),
		);

		$tokens[] = array(
			'tokenId'   => 'ENTRY_DATE_UPDATED',
			'tokenName' => __( 'Entry date updated', 'automator-sample' ),
		);

		$tokens[] = array(
			'tokenId'   => 'ENTRY_URL_SOURCE',
			'tokenName' => __( 'Entry source URL', 'automator-sample' ),
		);

		$tokens[] = array(
			'tokenId'   => 'USER_IP',
			'tokenName' => __( 'User IP', 'automator-sample' ),
		);

		return $tokens;
	}

	/**
	 * hydrate_entry_tokens
	 *
	 * @param  mixed $entry_id
	 * @param  mixed $form
	 * @return array
	 */
	public function hydrate_entry_tokens( $entry_id, $form ) {

		$entry = \GFAPI::get_entry( $entry_id );

		$entry_meta = array(
			'ENTRY_DATE_SUBMITTED' => $entry['date_created'],
			'ENTRY_DATE_UPDATED'   => $entry['date_updated'],
			'ENTRY_URL_SOURCE'     => $entry['source_url'],
			'ENTRY_ID'             => $entry_id,
			'USER_IP'              => $entry['ip'],
		);

		$entry_field_values = $this->hydrate_entry_values( $entry, $form );

		return $entry_meta + $entry_field_values;
	}

	/**
	 * form_specific_tokens
	 *
	 * @param  mixed $form_id
	 * @return array
	 */
	public function form_specific_tokens( $form_id ) {

		$tokens = array();

		$tokens[] = array(
			'tokenId'   => 'FORM_TITLE',
			'tokenName' => __( 'Form title', 'automator-sample' ),
		);

		$tokens[] = array(
			'tokenId'   => 'FORM_ID',
			'tokenName' => __( 'Form ID', 'automator-sample' ),
		);

		$form_selected = \GFAPI::get_form( $form_id );

		if ( empty( $form_selected['fields'] ) ) {
			return $tokens;
		}

		foreach ( $form_selected['fields'] as $field ) {

			$tokens = $this->add_field_tokens( $field, $tokens );

		}

		return $tokens;
	}

	/**
	 * add_field_tokens
	 *
	 * @param  mixed $field
	 * @param  mixed $tokens
	 * @return array
	 */
	public function add_field_tokens( $field, $tokens ) {

		switch ( $field->type ) {
			case 'html':
			case 'section':
			case 'page':
				break; // Skip.
			case 'address':
			case 'name':
			case 'checkbox':
				// Multi-inputs
				foreach ( $field['inputs'] as $input ) {

					$tokens[] = array(
						'tokenId'   => 'field_' . $input['id'],
						'tokenName' => esc_html( $this->input_name( $field, $input ) ),
					);

				}

				break;
			default:
				// Normal fields
				$tokens[] = array(
					'tokenId'   => 'field_' . $field['id'],
					'tokenName' => esc_html( $this->field_name( $field ) ),
				);

				break;
		}

		return $tokens;
	}

	/**
	 * field_name
	 *
	 * @param  mixed $field
	 * @return string
	 */
	public function field_name( $field ) {

		if ( ! empty( $field['label'] ) ) {
			return $field['label'];
		}

		return 'Field - ' . $field['id'];
	}

	/**
	 * input_name
	 *
	 * @param  mixed $field
	 * @param  mixed $input
	 * @return string
	 */
	public function input_name( $field, $input ) {

		$token_name = 'Field input - ' . $field['id'];

		if ( ! empty( $input['label'] ) ) {
			$token_name = $field['label'] . ' - ' . $input['label'];
		}

		return $token_name;
	}

	/**
	 * hydrate_form_tokens
	 *
	 * @param  mixed $form
	 * @return array
	 */
	public function hydrate_form_tokens( $form ) {

		$token_values = array(
			'FORM_TITLE' => $form['title'],
			'FORM_ID'    => $form['id'],
		);

		return $token_values;
	}

	/**
	 * hydrate_entry_values
	 *
	 * @param  mixed $entry
	 * @param  mixed $form
	 * @return array
	 */
	public function hydrate_entry_values( $entry, $form ) {

		$form_id = $form['id'];

		$token_values = array();

		foreach ( $entry as $field_id => $value ) {

			if ( ! is_numeric( $field_id ) ) {
				continue;
			}

			if ( empty( $value ) ) {
				continue;
			}

			$field = \GFAPI::get_field( $form_id, $field_id );

			$token_values[ 'field_' . $field_id ] = $this->process_field_value( $field, $value );
		}

		return $token_values;
	}

	/**
	 * process_field_value
	 *
	 * @param  mixed $field
	 * @param  mixed $value
	 * @return string
	 */
	public function process_field_value( $field, $value ) {

		if ( 'list' === $field->type ) {

			$field_data = maybe_unserialize( $value );

			if ( ! is_array( $field_data ) ) {
				return $field_data;
			}

			return join( ', ', $field_data );
		}

		if ( 'multiselect' === $field->type ) {

			if ( ! Automator()->utilities->is_json_string( $value ) ) {
				return $value;
			}

			return join( ', ', json_decode( $value ) );
		}

		return $value;
	}

	/**
	 * save_legacy_trigger_tokens
	 *
	 * @param  mixed $trigger_meta
	 * @param  mixed $entry
	 * @param  mixed $form
	 * @return void
	 */
	public function save_legacy_trigger_tokens( $trigger_meta, $entry, $form ) {

		$trigger_meta['meta_key']   = 'GFENTRYID';
		$trigger_meta['meta_value'] = $entry['id'];
		Automator()->process->user->insert_trigger_meta( $trigger_meta );

		$trigger_meta['meta_key']   = 'GFUSERIP';
		$trigger_meta['meta_value'] = maybe_serialize( $entry['ip'] );
		Automator()->process->user->insert_trigger_meta( $trigger_meta );

		$trigger_meta['meta_key']   = 'GFENTRYDATE';
		$trigger_meta['meta_value'] = maybe_serialize( \GFCommon::format_date( $entry['date_created'], false, 'Y/m/d' ) );
		Automator()->process->user->insert_trigger_meta( $trigger_meta );

		$trigger_meta['meta_key']   = 'GFENTRYSOURCEURL';
		$trigger_meta['meta_value'] = maybe_serialize( $entry['source_url'] );
		Automator()->process->user->insert_trigger_meta( $trigger_meta );
	}
}

