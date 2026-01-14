<?php

namespace Uncanny_Automator\Integrations\Gravity_Forms;

class Gravity_Forms_Possible_Tokens {
	/**
	 * Get token id.
	 *
	 * @param mixed $form_id The ID.
	 * @param mixed $input_id The ID.
	 * @return mixed
	 */
	protected function get_token_id( $form_id, $input_id ) {
		return intval( '-1' ) === intval( $form_id ) ? $input_id : $form_id . '|' . $input_id;
	}

	/**
	 * Add the same element to each token without looping
	 */
	protected function add_identifier( $tokens, $identifier ) {

		foreach ( $tokens as $key => $token ) {
			$tokens[ $key ]['tokenIdentifier'] = $identifier;
		}

		return $tokens;
	}
	/**
	 * Token type.
	 *
	 * @param mixed $field_type The type.
	 * @return mixed
	 */
	protected function token_type( $field_type ) {

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
	 * form_tokens
	 *
	 * @param  mixed $form_id
	 * @return array
	 */
	public function form_tokens( $form_id, $identifier = null ) {

		$tokens = array();

		$tokens[] = array(
			'tokenId'   => 'FORM_TITLE',
			'tokenName' => esc_html_x( 'Form title', 'Gravity Forms', 'uncanny-automator' ),
			'tokenType' => 'text',
		);

		$tokens[] = array(
			'tokenId'   => 'FORM_ID',
			'tokenName' => esc_html_x( 'Form ID', 'Gravity Forms', 'uncanny-automator' ),
			'tokenType' => 'int',
		);

		$tokens = $this->form_fields_tokens( $tokens, $form_id, $identifier );

		if ( ! empty( $identifier ) ) {
			$tokens = $this->add_identifier( $tokens, $identifier );
		}

		return $tokens;
	}
	/**
	 * Form fields tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $identifier The ID.
	 * @return mixed
	 */
	public function form_fields_tokens( $tokens, $form_id, $identifier = null ) {

		// If "Any form" is selected (-1), don't return specific form fields
		if ( intval( '-1' ) === intval( $form_id ) ) {
			if ( ! empty( $identifier ) ) {
				$tokens = $this->add_identifier( $tokens, $identifier );
			}
			return $tokens;
		}

		$form_selected = \GFAPI::get_form( $form_id );

		if ( empty( $form_selected['fields'] ) ) {
			return $tokens;
		}

		foreach ( $form_selected['fields'] as $field ) {

			if ( in_array( $field->type, $this->excluded_fields() ) ) {
				continue;
			}

			$tokens = $this->form_field_tokens( $tokens, $form_id, $field );
		}

		if ( ! empty( $identifier ) ) {
			$tokens = $this->add_identifier( $tokens, $identifier );
		}

		return $tokens;
	}

	/**
	 * entry_tokens
	 *
	 * @return array
	 */
	public function entry_tokens( $identifier = null ) {

		$tokens = array();

		$tokens[] = array(
			'tokenId'         => 'GFENTRYID',
			'tokenName'       => esc_html_x( 'Entry ID', 'Gravity Forms', 'uncanny-automator' ),
			'tokenType'       => 'int',
		);

		$tokens[] = array(
			'tokenId'         => 'GFUSERIP',
			'tokenName'       => esc_html_x( 'User IP', 'Gravity Forms', 'uncanny-automator' ),
			'tokenType'       => 'text',
		);

		$tokens[] = array(
			'tokenId'         => 'GFENTRYDATE',
			'tokenName'       => esc_html_x( 'Entry submission date', 'Gravity Forms', 'uncanny-automator' ),
			'tokenType'       => 'text',
		);

		$tokens[] = array(
			'tokenId'         => 'GFENTRYSOURCEURL',
			'tokenName'       => esc_html_x( 'Entry source URL', 'Gravity Forms', 'uncanny-automator' ),
			'tokenType'       => 'text',
		);

		if ( ! empty( $identifier ) ) {
			$tokens = $this->add_identifier( $tokens, $identifier );
		}

		return $tokens;
	}


	/**
	 * form_field_tokens
	 *
	 * @param  mixed $field
	 * @param  mixed $tokens
	 * @return array
	 */
	public function form_field_tokens( $tokens, $form_id, $field ) {

		$method_format = 'register_%s_tokens';

		// Check if this class has a method for this specific field type
		$method_name = sprintf( $method_format, $field->type );

		if ( method_exists( $this, $method_name ) ) {
			return $this->{$method_name}( $tokens, $form_id, $field );
		}

		// If no method exists, use the default method
		$tokens = $this->parse_default_field_tokens( $tokens, $form_id, $field );

		return $tokens;
	}

	/**
	 * excluded_fields
	 *
	 * @return array
	 */
	public function excluded_fields() {

		$excluded_field_types = array(
			'hidden',
			'html',
			'section',
			'page',
			'captcha',
			'creditcard',
			'paypal',
			'stripe_creditcard',
			'square',
			'authorizenet',
			'mollie',
			'twocheckout',
		);

		return $excluded_field_types;
	}
	/**
	 * Parse default field tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function parse_default_field_tokens( $tokens, $form_id, $field ) {

		$tokens[] = $this->main_field_token( $form_id, $field );

		$tokens = $this->inputs_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Register checkbox tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_checkbox_tokens( $tokens, $form_id, $field ) {

		$tokens = $this->inputs_as_tokens( $tokens, $form_id, $field );
		$tokens = $this->labels_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Register radio tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_radio_tokens( $tokens, $form_id, $field ) {

		$tokens[] = $this->main_field_token( $form_id, $field );
		$tokens[] = $this->label_token( $form_id, $field, $field );
		$tokens   = $this->inputs_as_tokens( $tokens, $form_id, $field );
		$tokens   = $this->labels_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Register select tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_select_tokens( $tokens, $form_id, $field ) {

		$tokens[] = $this->main_field_token( $form_id, $field );
		$tokens[] = $this->label_token( $form_id, $field, $field );

		return $tokens;
	}
	/**
	 * Register multi choice tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_multi_choice_tokens( $tokens, $form_id, $field ) {

		// Add main field token for single selection mode
		$tokens[] = $this->main_field_token( $form_id, $field );
		$tokens[] = $this->label_token( $form_id, $field, $field );

		// Add input tokens for multi-selection mode
		$tokens = $this->inputs_as_tokens( $tokens, $form_id, $field );
		$tokens = $this->labels_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Register name tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_name_tokens( $tokens, $form_id, $field ) {

		$tokens = $this->inputs_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Register address tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_address_tokens( $tokens, $form_id, $field ) {

		$tokens = $this->inputs_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Register product tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_product_tokens( $tokens, $form_id, $field ) {

		$tokens = $this->inputs_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Register image choice tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_image_choice_tokens( $tokens, $form_id, $field ) {

		// Add main field token for single selection mode
		$tokens[] = $this->main_field_token( $form_id, $field );
		$tokens[] = $this->label_token( $form_id, $field, $field );

		// Add input tokens for multi-selection mode
		$tokens = $this->inputs_as_tokens( $tokens, $form_id, $field );
		$tokens = $this->labels_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Register consent tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_consent_tokens( $tokens, $form_id, $field ) {

		$tokens = $this->inputs_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Register time tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_time_tokens( $tokens, $form_id, $field ) {

		$tokens[] = $this->main_field_token( $form_id, $field );

		return $tokens;
	}
	/**
	 * Register chainedselect tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	protected function register_chainedselect_tokens( $tokens, $form_id, $field ) {

		$tokens = $this->inputs_as_tokens( $tokens, $form_id, $field );

		return $tokens;
	}
	/**
	 * Main field token.
	 *
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function main_field_token( $form_id, $field ) {

		$token_id = $this->get_token_id( $form_id, $field['id'] );

		$token_name = esc_html( 'Field - ' . $field['id'] );

		if ( ! empty( $field['label'] ) ) {
			$token_name = esc_html( $field['label'] );
		}

		$token_type = $this->token_type( $field['type'] );

		return $this->token_array( $token_id, $token_name, $token_type );
	}
	/**
	 * Input token.
	 *
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @param mixed $input The input.
	 * @return mixed
	 */
	public function input_token( $form_id, $field, $input ) {

		$token_id   = $this->get_token_id( $form_id, $input['id'] );
		$token_name = esc_html( $field['label'] . ' - ' . $input['label'] );
		$token_type = $this->token_type( $field['type'] );

		return $this->token_array( $token_id, $token_name, $token_type );
	}
	/**
	 * Label token.
	 *
	 * @param mixed $form_id The ID.
	 * @param mixed $input The input.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function label_token( $form_id, $input, $field ) {

		$token_id   = $this->get_token_id( $form_id, $input['id'] . '|label' );
		$token_name = esc_html( $input['label'] . ' (label)' );
		$token_type = $this->token_type( $field['type'] );

		return $this->token_array( $token_id, $token_name, $token_type );
	}
	/**
	 * Inputs as tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function inputs_as_tokens( $tokens, $form_id, $field ) {

		if ( empty( $field['inputs'] ) ) {
			return $tokens;
		}

		foreach ( $field['inputs'] as $input ) {
			$tokens[] = $this->input_token( $form_id, $field, $input );
		}

		return $tokens;
	}
	/**
	 * Labels as tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $form_id The ID.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function labels_as_tokens( $tokens, $form_id, $field ) {

		if ( empty( $field['inputs'] ) ) {
			return $tokens;
		}

		foreach ( $field['inputs'] as $input ) {
			$tokens[] = $this->label_token( $form_id, $input, $field );
		}

		return $tokens;
	}
	/**
	 * Token array.
	 *
	 * @return array
	 */
	public function token_array( $token_id, $token_name, $token_type ) {

		$token_array = array(
			'tokenId'   => $token_id,
			'tokenName' => $token_name,
			'tokenType' => $token_type,
		);

		return $token_array;
	}
}
