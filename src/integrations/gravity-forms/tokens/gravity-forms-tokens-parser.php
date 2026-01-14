<?php

namespace Uncanny_Automator\Integrations\Gravity_Forms;

class Gravity_Forms_Tokens_Parser {

	protected $entry;
	/**
	 * Token id.
	 *
	 * @param mixed $form_id The ID.
	 * @param mixed $input_id The ID.
	 * @return mixed
	 */
	protected $form;

	/**
	 * token_id
	 *
	 * @param  mixed $form_id
	 * @param  mixed $input_id
	 * @return string
	 */
	public function token_id( $form_id, $input_id ) {
		return $form_id . '|' . $input_id;
	}
	/**
	 * Parsed fields tokens.
	 *
	 * @param mixed $form The form.
	 * @param mixed $entry The entry.
	 * @return mixed
	 */
	public function parsed_fields_tokens( $form, $entry ) {

		$this->entry = $entry;
		$this->form  = $form;

		$tokens = array();

		foreach ( $form['fields'] as $field ) {

			$tokens = $this->parse_field_tokens( $tokens, $field );

		}

		return $tokens;
	}

	/**
	 * parse_field_tokens
	 *
	 * @param  mixed $field
	 * @param  mixed $value
	 * @return string
	 */
	public function parse_field_tokens( $tokens, $field ) {

		$method_format = 'parse_%s_tokens';

		// Check if this class has a method for this specific field type
		$method_name = sprintf( $method_format, $field->type );

		if ( method_exists( $this, $method_name ) ) {
			return $this->{$method_name}( $tokens, $field );
		}

		// If no method exists, use the default method
		return $this->parse_default_tokens( $tokens, $field );
	}
	/**
	 * Parse default tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function parse_default_tokens( $tokens, $field ) {

		$tokens = $this->parse_parent_input_token( $tokens, $field );

		$tokens = $this->parse_child_input_tokens( $tokens, $field );

		return $tokens;
	}
	/**
	 * Parse parent input token.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function parse_parent_input_token( $tokens, $field ) {

		$field_value = $field->get_value_export( $this->entry, $field['id'], false, true );

		$tokens[ $this->token_id( $this->form['id'], $field['id'] ) ] = $field_value;

		return $tokens;
	}
	/**
	 * Parse child input tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function parse_child_input_tokens( $tokens, $field ) {

		if ( empty( $field['inputs'] ) ) {
			return $tokens;
		}

		foreach ( $field['inputs'] as $input ) {
			$input_value               = $field->get_value_export( $this->entry, $input['id'] );
			$token_id                  = $this->token_id( $this->form['id'], $input['id'] );
			$tokens[ $token_id ]       = $input_value;
			$token_id_label            = $token_id . '|label';
			$tokens[ $token_id_label ] = $field->get_value_export( $this->entry, $input['id'], true );
		}

		return $tokens;
	}
	/**
	 * Parse select tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function parse_select_tokens( $tokens, $field ) {

		$tokens = $this->parse_parent_input_token( $tokens, $field );

		$tokens = $this->parse_label_token( $tokens, $field );

		return $tokens;
	}
	/**
	 * Parse label token.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function parse_label_token( $tokens, $field ) {

		$value = $field->get_value_export( $this->entry, $field['id'], true );

		$token_id = $this->token_id( $this->form['id'], $field['id'] ) . '|label';

		$tokens[ $token_id ] = $value;

		return $tokens;
	}

	/**
	 * Parse multi choice tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function parse_multi_choice_tokens( $tokens, $field ) {

		// Check if single selection (radio) or multi selection (checkbox)
		if ( 'radio' === $field['inputType'] || 'single' === $field->choiceLimit ) {
			// Single selection mode - value stored in parent field
			$field_value = isset( $this->entry[ $field['id'] ] ) ? $this->entry[ $field['id'] ] : '';
			$label_value = $field_value;

			// Convert value to label by matching choices
			if ( ! empty( $field_value ) && ! empty( $field['choices'] ) ) {
				foreach ( $field['choices'] as $choice ) {
					if ( $choice['value'] === $field_value ) {
						$label_value = $choice['text'];
						break;
					}
				}
			}

			// Set parent field tokens
			$tokens[ $this->token_id( $this->form['id'], $field['id'] ) ] = $field_value;
			$tokens[ $this->token_id( $this->form['id'], $field['id'] ) . '|label' ] = $label_value;

			// Parse child input tokens - only the selected one will have a value
			if ( ! empty( $field['inputs'] ) ) {
				foreach ( $field['inputs'] as $input ) {
					$token_id              = $this->token_id( $this->form['id'], $input['id'] );
					$token_id_label        = $token_id . '|label';

					// Check if this input matches the selected value
					$matching_choice = null;
					foreach ( $field['choices'] as $choice ) {
						if ( isset( $choice['value'] ) && $choice['value'] === $field_value && isset( $input['label'] ) && $input['label'] === $choice['text'] ) {
							$matching_choice = $choice;
							break;
						}
					}

					// Fill tokens only for the selected choice
					if ( null !== $matching_choice ) {
						$tokens[ $token_id ]       = $matching_choice['value'];
						$tokens[ $token_id_label ] = $matching_choice['text'];
					} else {
						$tokens[ $token_id ]       = '';
						$tokens[ $token_id_label ] = '';
					}
				}
			}
		} else {
			// Multi selection mode - use default parsing
			$tokens = $this->parse_parent_input_token( $tokens, $field );
			$tokens = $this->parse_child_input_tokens( $tokens, $field );
		}

		return $tokens;
	}

	/**
	 * Parse image choice tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function parse_image_choice_tokens( $tokens, $field ) {

		// Check if single selection (radio) or multi selection (checkbox)
		if ( 'radio' === $field['inputType'] ) {
			// Single selection mode - value stored in parent field
			$field_value = isset( $this->entry[ $field['id'] ] ) ? $this->entry[ $field['id'] ] : '';
			$label_value = $field_value;

			// Convert value to label by matching choices
			if ( ! empty( $field_value ) && ! empty( $field['choices'] ) ) {
				foreach ( $field['choices'] as $choice ) {
					if ( $choice['value'] === $field_value ) {
						$label_value = $choice['text'];
						break;
					}
				}
			}

			// Set parent field tokens
			$tokens[ $this->token_id( $this->form['id'], $field['id'] ) ] = $field_value;
			$tokens[ $this->token_id( $this->form['id'], $field['id'] ) . '|label' ] = $label_value;

			// Parse child input tokens - only the selected one will have a value
			if ( ! empty( $field['inputs'] ) ) {
				foreach ( $field['inputs'] as $input ) {
					$token_id              = $this->token_id( $this->form['id'], $input['id'] );
					$token_id_label        = $token_id . '|label';

					// Check if this input matches the selected value
					$matching_choice = null;
					foreach ( $field['choices'] as $choice ) {
						if ( isset( $choice['value'] ) && $choice['value'] === $field_value && isset( $input['label'] ) && $input['label'] === $choice['text'] ) {
							$matching_choice = $choice;
							break;
						}
					}

					// Fill tokens only for the selected choice
					if ( null !== $matching_choice ) {
						$tokens[ $token_id ]       = $matching_choice['value'];
						$tokens[ $token_id_label ] = $matching_choice['text'];
					} else {
						$tokens[ $token_id ]       = '';
						$tokens[ $token_id_label ] = '';
					}
				}
			}
		} else {
			// Multi selection mode - use default parsing
			$tokens = $this->parse_parent_input_token( $tokens, $field );
			$tokens = $this->parse_child_input_tokens( $tokens, $field );
		}

		return $tokens;
	}

	/**
	 * Parse survey tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function parse_survey_tokens( $tokens, $field ) {

		$choice_labels = array();

		// Handle different survey input types
		if ( ! empty( $field['inputs'] ) && 'checkbox' === $field['inputType'] ) {
			// Checkbox type survey - check each input
			foreach ( $field['inputs'] as $input ) {
				if ( ! empty( $this->entry[ $input['id'] ] ) ) {
					$selected_value = $this->entry[ $input['id'] ];
					// Find the corresponding choice label by matching the value
					foreach ( $field['choices'] as $choice ) {
						if ( $choice['value'] === $selected_value ) {
							$choice_labels[] = $choice['text'];
							break;
						}
					}
				}
			}
			$field_value = implode( ', ', $choice_labels );
		} else if ( 'rank' === $field['inputType'] ) {
			// Ranking fields return comma-separated values
			$field_value = isset( $this->entry[ $field['id'] ] ) ? $this->entry[ $field['id'] ] : '';

			if ( ! empty( $field_value ) ) {
				$rank_values   = explode( ',', $field_value );
				$ranked_labels = array();
				$rank_position = 1;

				foreach ( $rank_values as $rank_value ) {
					foreach ( $field['choices'] as $choice ) {
						if ( $choice['value'] === $rank_value ) {
							$ranked_labels[] = $rank_position . '. ' . $choice['text'];
							$rank_position++;
							break;
						}
					}
				}
				$field_value = implode( ', ', $ranked_labels );
			}
		} else {
			// Single value survey fields (radio, select, rating, likert, etc.)
			$field_value = isset( $this->entry[ $field['id'] ] ) ? $this->entry[ $field['id'] ] : '';

			if ( ! empty( $field_value ) && ! empty( $field['choices'] ) ) {
				// Convert value to label by matching choice value
				foreach ( $field['choices'] as $choice ) {
					if ( $choice['value'] === $field_value ) {
						$field_value = $choice['text'];
						break;
					}
				}
			}
		}

		$tokens[ $this->token_id( $this->form['id'], $field['id'] ) ] = $field_value;

		// For checkbox surveys, also parse individual input tokens with labels
		if ( ! empty( $field['inputs'] ) ) {
			foreach ( $field['inputs'] as $input ) {
				if ( ! empty( $this->entry[ $input['id'] ] ) ) {
					$selected_value = $this->entry[ $input['id'] ];
					$input_label    = '';

					// Find the corresponding choice label
					foreach ( $field['choices'] as $choice ) {
						if ( $choice['value'] === $selected_value ) {
							$input_label = $choice['text'];
							break;
						}
					}

					$token_id            = $this->token_id( $this->form['id'], $input['id'] );
					$tokens[ $token_id ] = $input_label;
				}
			}
		}

		return $tokens;
	}

	/**
	 * Parse list tokens.
	 *
	 * @param mixed $tokens The destination.
	 * @param mixed $field The field.
	 * @return mixed
	 */
	public function parse_list_tokens( $tokens, $field ) {

		$raw_value = $this->entry[ $field['id'] ] ?? '';
		$data      = maybe_unserialize( $raw_value );

		$parts = array();
		array_walk_recursive(
			$data,
			function ( $value, $key ) use ( &$parts ) {
				if ( ! empty( $value ) ) {
					$parts[] = $key . ': ' . $value;
				}
			}
		);

		$field_value = implode( ', ', $parts );

		$tokens[ $this->token_id( $this->form['id'], $field['id'] ) ] = $field_value;

		return $tokens;
	}

	/**
	 * parsed_entry_tokens
	 *
	 * @param mixed $entry The entry data.
	 * @return array
	 */
	public function parsed_entry_tokens( $entry ) {

		$tokens = array();

		$tokens['GFENTRYID']        = $entry['id'];
		$tokens['GFUSERIP']         = $entry['ip'];
		$tokens['GFENTRYDATE']      = $entry['date_created'];
		$tokens['GFENTRYSOURCEURL'] = $entry['source_url'];

		return $tokens;
	}

	/**
	 * Parse common tokens.
	 *
	 * @return array
	 */
	public function parsed_common_tokens( $form ) {

		return array(
			'GFFORMS'       => $form['title'], // Form title.
			'FORM_TITLE'    => $form['title'], // Form title.
			'ANONGFFORMS'   => $form['title'], // Form title.
			'GK_ENTRY_METADATA' => $form['title'],
			'GK_ENTRY_METADATA_ID' => $form['id'],
			'GFFORMS_ID'    => $form['id'], // Form ID.
			'FORM_ID'       => $form['id'], // Form ID.
			'ANONGFFORMS_ID' => $form['id'], // Form ID.
		);
	}
}
