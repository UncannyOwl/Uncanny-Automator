<?php

namespace Uncanny_Automator\Integrations\Wp_Fluent_Forms;

/**
 * Fluent Forms helper: field builders, validate orchestration and Remote_Data handlers.
 *
 * @package Uncanny_Automator
 */
class Wp_Fluent_Forms_Helpers extends \Uncanny_Automator\Recipe\Abstract_Helpers {

	/**
	 * Lazy accessor for the token collaborator.
	 *
	 * @return Wp_Fluent_Forms_Tokens
	 */
	public function tokens() {
		static $tokens = null;

		if ( null === $tokens ) {
			$tokens = new Wp_Fluent_Forms_Tokens();
		}

		return $tokens;
	}

	// -------------------------------------------------------------------------
	// Field builders for options()
	// -------------------------------------------------------------------------

	/**
	 * Build the "Form" select field. Loads via the unified Remote_Data REST
	 * framework — `forms_with_any` includes a `-1 / Any form` sentinel,
	 * `forms` does not.
	 *
	 * @param string $option_code Sacred option_code stored in recipes.
	 * @param bool   $include_any Include the `-1 / Any form` sentinel.
	 *
	 * @return array
	 */
	public function form_select_field( $option_code, $include_any ) {
		return array(
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Form', 'Fluent Forms', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => array(),
			'relevant_tokens' => array(
				$option_code         => esc_html_x( 'Form title', 'Fluent Forms', 'uncanny-automator' ),
				$option_code . '_ID' => esc_html_x( 'Form ID', 'Fluent Forms', 'uncanny-automator' ),
			),
			'remote_data'     => $this->remote_data_load_config( $include_any ? 'forms_with_any' : 'forms' ),
		);
	}

	/**
	 * Build the "Field" select that reloads when the parent form changes.
	 *
	 * @param string $form_option_code Parent form select's option_code.
	 *
	 * @return array
	 */
	public function form_field_select_field( $form_option_code ) {
		return array(
			'option_code' => 'FORMFIELD',
			'label'       => esc_html_x( 'Field', 'Fluent Forms', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_parent_config( 'form_fields', array( $form_option_code ) ),
		);
	}

	/**
	 * Build the "Value" text field for SUBFIELD triggers.
	 *
	 * @return array
	 */
	public function form_field_value_field() {
		return array(
			'option_code' => 'FORMFIELDVALUE',
			'label'       => esc_html_x( 'Value', 'Fluent Forms', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => true,
		);
	}

	/**
	 * Build the "Number of times" field via the framework helper.
	 *
	 * @return array
	 */
	public function number_of_times_field() {
		return Automator()->helpers->recipe->options->number_of_times();
	}

	// -------------------------------------------------------------------------
	// Validate orchestration
	// -------------------------------------------------------------------------

	/**
	 * Validate a SUBFORM-style trigger against the submitted form ID.
	 *
	 * @param array  $trigger
	 * @param array  $hook_args
	 * @param string $trigger_meta
	 *
	 * @return bool
	 */
	public function validate_form_id_match( $trigger, $hook_args, $trigger_meta ) {
		if ( ! isset( $trigger['meta'][ $trigger_meta ], $hook_args[2] ) ) {
			return false;
		}

		$selected_form = (string) $trigger['meta'][ $trigger_meta ];
		$form          = $hook_args[2];
		$form_id       = (int) ( $form->id ?? 0 );

		if ( '-1' === $selected_form ) {
			return true;
		}

		return (int) $selected_form === $form_id;
	}

	/**
	 * Validate a SUBFIELD-style trigger against the submitted form, field and value.
	 *
	 * @param array  $trigger
	 * @param array  $hook_args
	 * @param string $trigger_meta
	 *
	 * @return bool
	 */
	public function validate_form_field_match( $trigger, $hook_args, $trigger_meta ) {
		$meta = $trigger['meta'] ?? array();

		if ( empty( $meta[ $trigger_meta ] ) || empty( $meta['FORMFIELD'] ) || empty( $meta['FORMFIELDVALUE'] ) ) {
			return false;
		}

		$form           = $hook_args[2] ?? null;
		$submitted_data = $hook_args[1] ?? array();

		if ( ! isset( $form->id ) ) {
			return false;
		}

		if ( (int) $meta[ $trigger_meta ] !== (int) $form->id ) {
			return false;
		}

		return $this->submitted_data_matches_field( $submitted_data, (string) $meta['FORMFIELD'], (string) $meta['FORMFIELDVALUE'] );
	}

	/**
	 * Scan submitted Fluent Forms data for a matching field and value.
	 *
	 * @param mixed  $submitted_data
	 * @param string $field_key
	 * @param string $expected_value
	 *
	 * @return bool
	 */
	private function submitted_data_matches_field( $submitted_data, $field_key, $expected_value ) {
		if ( ! is_array( $submitted_data ) ) {
			return false;
		}

		foreach ( $submitted_data as $key => $field ) {
			if ( is_array( $field ) ) {
				if ( (string) $key === $field_key ) {
					if ( in_array( $expected_value, $field, true ) ) {
						return true;
					}
					continue;
				}

				foreach ( $field as $sub_key => $sub_value ) {
					if ( (string) $sub_key === $field_key && (string) $sub_value === $expected_value ) {
						return true;
					}
				}
				continue;
			}

			if ( (string) $key === $field_key && (string) $field === $expected_value ) {
				return true;
			}
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Remote_Data REST handlers
	// -------------------------------------------------------------------------

	/**
	 * List every Fluent Form including the "Any form" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_forms_with_any( $request ): array {
		return $this->remote_data_success( $this->fetch_form_options( true ) );
	}

	/**
	 * List every Fluent Form without the "Any form" sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_forms( $request ): array {
		return $this->remote_data_success( $this->fetch_form_options( false ) );
	}

	/**
	 * List selectable fields for the form chosen in the parent select.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_form_fields( $request ): array {
		// Both Free trigger metas are tried because either could be the parent
		// in the field cascade: WPFFSUBFORM uses WPFFFORMS, ANON_WPFF_SUBFORM
		// uses ANONWPFFFORMS. (Pro's WPFFSUBFIELD also uses WPFFFORMS.)
		$form_id = absint( $request->get_field_value( 'WPFFFORMS' ) );
		if ( 0 === $form_id ) {
			$form_id = absint( $request->get_field_value( 'ANONWPFFFORMS' ) );
		}

		return $this->remote_data_success( $this->fetch_form_field_options( $form_id ) );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Fetch every Fluent Form as a `[value, text]` option list.
	 *
	 * @param bool $include_any
	 *
	 * @return array
	 */
	private function fetch_form_options( $include_any ) {
		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => '-1',
				'text'  => esc_html_x( 'Any form', 'Fluent Forms', 'uncanny-automator' ),
			);
		}

		if ( ! function_exists( 'wpFluent' ) ) {
			return $options;
		}

		$result = wpFluent()->table( 'fluentform_forms' )
			->select( array( 'id', 'title' ) )
			->orderBy( 'id', 'DESC' )
			->get();

		foreach ( $this->normalize_rows( $result ) as $form ) {
			$options[] = array(
				'value' => (string) $form->id,
				'text'  => esc_html( $form->title ),
			);
		}

		return $options;
	}

	/**
	 * Fetch the selectable fields of a form for the FORMFIELD AJAX dropdown.
	 *
	 * @param int $form_id
	 *
	 * @return array
	 */
	private function fetch_form_field_options( $form_id ) {
		if ( empty( $form_id ) ) {
			return array();
		}

		$tokens = $this->tokens()->form_field_tokens( $form_id );
		$out    = array();

		foreach ( $tokens as $token ) {
			$id = $token['tokenId'] ?? '';
			// `tokenId` is `{form_id}|{name}` or `{form_id}|{group}|{sub}`.
			// The FORMFIELD selector stores the field NAME — for grouped
			// inputs that's the sub-field name (the leaf).
			$parts = explode( '|', $id );
			$name  = end( $parts );
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'value' => (string) $name,
				'text'  => (string) ( $token['tokenName'] ?? $name ),
			);
		}

		return $out;
	}

	/**
	 * Normalize a wpFluent `->get()` result into an iterable list of rows.
	 *
	 * @param mixed $result
	 *
	 * @return array
	 */
	private function normalize_rows( $result ) {
		if ( empty( $result ) ) {
			return array();
		}

		if ( is_array( $result ) ) {
			return $result;
		}

		if ( is_object( $result ) && method_exists( $result, 'all' ) ) {
			return $result->all();
		}

		if ( $result instanceof \Traversable ) {
			return iterator_to_array( $result, false );
		}

		return array();
	}
}
