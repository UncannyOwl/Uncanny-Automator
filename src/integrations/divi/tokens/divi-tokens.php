<?php

namespace Uncanny_Automator\Integrations\Divi;

/**
 * Class Divi_Tokens
 *
 * Dedicated token define + hydrate class for the Divi integration. Migrated
 * triggers reach this via $this->get_item_helpers()->tokens(). The legacy
 * \Uncanny_Automator\Divi_Tokens parser remains on disk under a class_exists
 * guard for the old-Free + new-Pro transitional scenario.
 *
 * @package Uncanny_Automator\Integrations\Divi
 */
class Divi_Tokens {

	/**
	 * @var Divi_Helpers
	 */
	private $helpers;

	/**
	 * @param Divi_Helpers $helpers
	 */
	public function __construct( Divi_Helpers $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * "Any form" wildcard field definitions — single source of truth.
	 *
	 * Shared between modern static_tokens() and the legacy
	 * Divi_Tokens::divi_possible_tokens() filter callback so both paths emit
	 * an identical name/email/message wildcard set.
	 *
	 * Returns the helper-shape: [ [field_id, field_title, field_type], ... ].
	 *
	 * @return array
	 */
	public static function wildcard_field_definitions() {
		return array(
			array(
				'field_id'    => 'name',
				'field_title' => esc_html_x( 'Name (if available)', 'Divi', 'uncanny-automator' ),
				'field_type'  => 'text',
			),
			array(
				'field_id'    => 'email',
				'field_title' => esc_html_x( 'Email address (if available)', 'Divi', 'uncanny-automator' ),
				'field_type'  => 'email',
			),
			array(
				'field_id'    => 'message',
				'field_title' => esc_html_x( 'Message (if available)', 'Divi', 'uncanny-automator' ),
				'field_type'  => 'text',
			),
		);
	}

	/**
	 * "Any form" wildcard tokens (-1|name, -1|email, -1|message).
	 *
	 * Resolved at parse time via Divi_Tokens::match_token_suffix() in the
	 * legacy parser, which strips the "-1|" prefix and matches any saved
	 * key ending with the field suffix.
	 *
	 * @param string $meta The trigger meta.
	 *
	 * @return array
	 */
	public function wildcard_tokens( $meta = 'DIVIFORM' ) {
		$tokens = array();
		foreach ( self::wildcard_field_definitions() as $field ) {
			$tokens[] = array(
				'tokenId'         => '-1|' . $field['field_id'],
				'tokenName'       => $field['field_title'],
				'tokenType'       => $field['field_type'],
				'tokenIdentifier' => $meta,
			);
		}
		return $tokens;
	}

	/**
	 * Always-emitted tokens for a Divi form trigger.
	 *
	 * Returns the 3 "-1|*" wildcards only. The form-name DIVIFORM token is
	 * auto-emitted by the framework from the trigger's meta (under the
	 * {trigger_id}:{TRIGGER_CODE}:DIVIFORM shape) — emitting it ourselves
	 * would duplicate it under a different identifier.
	 *
	 * Kept as an alias of wildcard_tokens() for direct callers.
	 *
	 * @param string $meta The trigger meta (typically "DIVIFORM").
	 *
	 * @return array
	 */
	public function static_tokens( $meta = 'DIVIFORM' ) {
		return $this->wildcard_tokens( $meta );
	}

	/**
	 * Per-form field tokens — emitted only when the recipe selects a specific form.
	 *
	 * Token ID format is "{form_id}|{field_id_lower}" with the picker's
	 * "__"-form_id (post_id__uid__form_index). The legacy divi_token parser
	 * translates this to the dash-form keyspace used by hydrate_form_tokens()
	 * at runtime, so existing recipes continue to resolve.
	 *
	 * @param string $form_id     The selected form's stored picker meta.
	 * @param array  $form_fields Field rows from Divi_Helpers::get_form_by_id(): [ [field_id, field_title, field_type], ... ].
	 * @param string $meta        The trigger meta (used for tokenIdentifier).
	 *
	 * @return array
	 */
	public function dynamic_form_tokens( $form_id, array $form_fields, $meta = 'DIVIFORM' ) {
		$tokens = array();

		foreach ( $form_fields as $field ) {
			$field_id = isset( $field['field_id'] ) ? (string) $field['field_id'] : '';
			if ( '' === $field_id ) {
				continue;
			}

			$tokens[] = array(
				'tokenId'         => $form_id . '|' . $field_id,
				'tokenName'       => isset( $field['field_title'] ) ? $field['field_title'] : $field_id,
				'tokenType'       => isset( $field['field_type'] ) ? $field['field_type'] : 'text',
				'tokenIdentifier' => $meta,
			);
		}

		return $tokens;
	}

	/**
	 * Compose the full token list for a trigger — shared by every Divi
	 * trigger's define_tokens() body. Always emits the static set; when a
	 * specific form is selected, also emits the per-field dynamic tokens.
	 *
	 * @param array  $tokens         Existing tokens to merge into.
	 * @param string $selected_form  Recipe's picker meta ("-1" for Any, or stored form id).
	 * @param string $meta           Trigger meta (typically "DIVIFORM").
	 *
	 * @return array
	 */
	public function define_form_tokens( array $tokens, $selected_form, $meta = 'DIVIFORM' ) {
		// Form-name DIVIFORM token is framework-auto-emitted (under the trigger
		// code as identifier, matching what existing recipes reference). Only
		// emit our own wildcards XOR per-field set here.
		if ( '' === $selected_form || '-1' === (string) $selected_form ) {
			return array_merge( $tokens, $this->wildcard_tokens( $meta ) );
		}

		$form_fields = Divi_Helpers::get_form_by_id( $selected_form, true );

		return array_merge( $tokens, $this->dynamic_form_tokens( $selected_form, $form_fields, $meta ) );
	}

	/**
	 * Build the hydrate map directly from the et_pb_contact_form_submit
	 * hook payload — every Divi trigger's hydrate_tokens() unpacks the
	 * same way, so the unpacking lives here.
	 *
	 * Builds the picker-format form_id ({post_id}__{uid}__{idx}) so the hydrate
	 * keys match the tokenIds declared in define_form_tokens(). The framework's
	 * Token_Identifier_Partitioner only buckets values under their declared
	 * tokenIdentifier when the key exactly matches — using any other shape
	 * dumps them into the trigger-code leftover bucket where the recipe parser
	 * can't find them.
	 *
	 * @param array $hook_args Hook args: [ $fields_values, $et_contact_error, $contact_form_info ].
	 *
	 * @return array
	 */
	public function hydrate_from_hook_args( array $hook_args ) {
		$fields_values     = $hook_args[0] ?? array();
		$contact_form_info = $hook_args[2] ?? array();

		// Resolve the picker form_id by matching unique_id against extract_forms'
		// output, NOT by reconstructing from contact_form_number. The two indices
		// can drift (Divi 5 increments orderIndex per-module-type across the page
		// while extract_forms counts per-post block position) — substring-matching
		// the unique_id guarantees the hydrate keys are byte-identical to the
		// define-time tokenIds.
		$form_id = Divi_Helpers::picker_form_id_for_runtime( $contact_form_info );

		if ( '' === $form_id ) {
			return array();
		}

		return $this->hydrate_form_tokens( $form_id, (array) $fields_values );
	}

	/**
	 * Hydrate runtime values into the full token keyspace for a single
	 * submission. Returns every key emitted by the define pass, including
	 * the form-name DIVIFORM token, so the framework never leaves a token
	 * as a raw {{ }} placeholder in the output.
	 *
	 * @param string $form_id       Picker-format form id ({post_id}__{uid}__{idx}) — must exactly match the tokenId prefix declared in dynamic_form_tokens() so the framework's Token_Identifier_Partitioner buckets the values under the DIVIFORM identifier where the parser will look them up.
	 * @param array  $fields_values The Divi $fields_values hook arg: [ field_id => [ value => ..., ... ], ... ].
	 *
	 * @return array
	 */
	public function hydrate_form_tokens( $form_id, array $fields_values ) {
		$tokens = array();

		foreach ( $fields_values as $field_id => $field_data ) {
			$value = '';
			if ( is_array( $field_data ) && isset( $field_data['value'] ) ) {
				$value = $field_data['value'];
			} elseif ( is_scalar( $field_data ) ) {
				$value = $field_data;
			}

			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			$tokens[ $form_id . '|' . strtolower( (string) $field_id ) ] = (string) $value;
		}

		return $tokens;
	}
}
