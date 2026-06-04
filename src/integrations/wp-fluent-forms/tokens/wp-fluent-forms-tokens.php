<?php

namespace Uncanny_Automator\Integrations\Wp_Fluent_Forms;

/**
 * Fluent Forms token discovery and hydration collaborator.
 *
 * @package Uncanny_Automator
 */
class Wp_Fluent_Forms_Tokens {

	/**
	 * Legacy tokenIdentifier used for the four shared entry tokens. Stored
	 * as the meta_key in `uap_trigger_log_meta` and referenced verbatim by
	 * existing recipes as the middle segment of `{trigger_id}:WPFFENTRYTOKENS:{tokenId}`.
	 */
	const ENTRY_TOKENS_IDENTIFIER = 'WPFFENTRYTOKENS';

	/**
	 * Per-request cache: form_id => decoded `form_fields` array.
	 *
	 * @var array<int,array>
	 */
	private $form_cache = array();

	// -------------------------------------------------------------------------
	// Trigger orchestration — called by triggers via the helper
	// -------------------------------------------------------------------------

	/**
	 * Build the full token list for a trigger run.
	 *
	 * @param array  $trigger
	 * @param array  $tokens
	 * @param string $trigger_meta
	 *
	 * @return array
	 */
	public function define_trigger_tokens( $trigger, $tokens, $trigger_meta ) {
		$form_id = (int) ( $trigger['meta'][ $trigger_meta ] ?? 0 );

		return array_merge(
			$tokens,
			$this->form_field_tokens( $form_id, $trigger_meta ),
			$this->entry_tokens()
		);
	}

	/**
	 * Hydrate the trigger tokens from the live submission payload.
	 *
	 * @param array  $completed_trigger
	 * @param array  $hook_args
	 * @param string $trigger_meta
	 * @param array  $extras
	 *
	 * @return array
	 */
	public function hydrate_trigger_tokens( $completed_trigger, $hook_args, $trigger_meta, array $extras = array() ) {
		$insert_data = $hook_args[0] ?? array();
		$form        = $hook_args[2] ?? null;
		$form_id     = (int) ( $form->id ?? 0 );
		$form_title  = (string) ( $form->title ?? '' );

		$entry_data = array();
		if ( isset( $insert_data['response'] ) && is_string( $insert_data['response'] ) ) {
			$decoded    = json_decode( $insert_data['response'], true );
			$entry_data = is_array( $decoded ) ? $decoded : array();
		}

		return array_merge(
			array(
				$trigger_meta         => $form_title,
				$trigger_meta . '_ID' => $form_id,
			),
			$this->flatten_entry_to_field_tokens( $form_id, $entry_data ),
			$this->hydrate_entry_tokens( is_array( $insert_data ) ? $insert_data : array() ),
			$extras
		);
	}

	// -------------------------------------------------------------------------
	// Per-field tokens (discovery)
	// -------------------------------------------------------------------------

	/**
	 * Generate token definitions for every visible input on the given form.
	 *
	 * @param int    $form_id
	 * @param string $trigger_meta
	 *
	 * @return array
	 */
	public function form_field_tokens( $form_id, $trigger_meta = '' ) {
		if ( empty( $form_id ) ) {
			return array();
		}

		$decoded = $this->get_form_fields( $form_id );
		if ( empty( $decoded['fields'] ) || ! is_array( $decoded['fields'] ) ) {
			return array();
		}

		$tokens = array();
		$this->walk_fields( $decoded['fields'], $form_id, '', $trigger_meta, $tokens );
		return $tokens;
	}

	/**
	 * Recursive walker for the Fluent Forms field tree.
	 *
	 * @param array  $fields
	 * @param int    $form_id
	 * @param string $group
	 * @param string $trigger_meta
	 * @param array  $tokens
	 *
	 * @return void
	 */
	private function walk_fields( array $fields, $form_id, $group, $trigger_meta, array &$tokens ) {
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			// 1. Layout container — recurse into every column.
			if ( isset( $field['columns'] ) && is_array( $field['columns'] ) ) {
				foreach ( $field['columns'] as $column ) {
					if ( isset( $column['fields'] ) && is_array( $column['fields'] ) ) {
						$this->walk_fields( $column['fields'], $form_id, $group, $trigger_meta, $tokens );
					}
				}
				continue;
			}

			// Skip custom_html and invisible fields.
			if ( isset( $field['element'] ) && 'custom_html' === $field['element'] ) {
				continue;
			}
			if ( isset( $field['settings']['visible'] ) && false === $field['settings']['visible'] ) {
				continue;
			}

			// 2. Input group — recurse with group name.
			if ( isset( $field['fields'] ) && is_array( $field['fields'] ) ) {
				$group_name = $field['attributes']['name'] ?? $group;
				$this->walk_fields( $field['fields'], $form_id, (string) $group_name, $trigger_meta, $tokens );
				continue;
			}

			// 3. Flat field — emit token.
			if ( ! isset( $field['attributes']['name'] ) ) {
				continue;
			}

			$tokens[] = $this->build_field_token( $form_id, $field, $group, $trigger_meta );
		}
	}

	/**
	 * Build a single token definition for a flat field.
	 *
	 * @param int    $form_id
	 * @param array  $field
	 * @param string $group
	 * @param string $trigger_meta
	 *
	 * @return array
	 */
	private function build_field_token( $form_id, array $field, $group, $trigger_meta ) {
		$name  = (string) $field['attributes']['name'];
		$label = $field['settings']['label']
			?? ( $field['settings']['admin_field_label'] ?? $name );

		$type     = $field['attributes']['type'] ?? ( $field['element'] ?? 'text' );
		$token_id = '' === $group ? $form_id . '|' . $name : $form_id . '|' . $group . '|' . $name;

		return array(
			'tokenId'         => $token_id,
			'tokenName'       => (string) $label,
			'tokenType'       => $this->normalize_token_type( $type ),
			'tokenIdentifier' => $trigger_meta,
		);
	}

	/**
	 * Map a Fluent Forms field type to an Automator token type.
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	private function normalize_token_type( $type ) {
		switch ( $type ) {
			case 'number':
				return 'int';
			case 'email':
				return 'email';
			default:
				return 'text';
		}
	}

	// -------------------------------------------------------------------------
	// Per-field token hydration (flatten submission payload)
	// -------------------------------------------------------------------------

	/**
	 * Flatten a decoded Fluent Forms submission into a token-id => value map.
	 *
	 * @param int   $form_id
	 * @param array $entry_data
	 *
	 * @return array
	 */
	public function flatten_entry_to_field_tokens( $form_id, $entry_data ) {
		$out = array();

		if ( ! is_array( $entry_data ) ) {
			return $out;
		}

		foreach ( $entry_data as $field_name => $value ) {
			if ( is_array( $value ) ) {
				$is_assoc = array_keys( $value ) !== range( 0, count( $value ) - 1 );

				if ( $is_assoc ) {
					foreach ( $value as $sub => $sub_value ) {
						$out[ $form_id . '|' . $field_name . '|' . $sub ] = is_scalar( $sub_value ) ? (string) $sub_value : wp_json_encode( $sub_value );
					}
				} else {
					$out[ $form_id . '|' . $field_name ] = implode( ', ', array_map( 'strval', $value ) );
				}
				continue;
			}

			$out[ $form_id . '|' . $field_name ] = is_scalar( $value ) ? (string) $value : '';
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Shared entry tokens
	// -------------------------------------------------------------------------

	/**
	 * Return the four shared entry tokens emitted by every Fluent Forms trigger.
	 *
	 * @return array
	 */
	public function entry_tokens() {
		return array(
			array(
				'tokenId'         => 'WPFFENTRYID',
				'tokenName'       => esc_html_x( 'Entry ID', 'Fluent Forms', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => self::ENTRY_TOKENS_IDENTIFIER,
			),
			array(
				'tokenId'         => 'WPFFENTRYIP',
				'tokenName'       => esc_html_x( 'User IP', 'Fluent Forms', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => self::ENTRY_TOKENS_IDENTIFIER,
			),
			array(
				'tokenId'         => 'WPFFENTRYSOURCEURL',
				'tokenName'       => esc_html_x( 'Entry source URL', 'Fluent Forms', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => self::ENTRY_TOKENS_IDENTIFIER,
			),
			array(
				'tokenId'         => 'WPFFENTRYDATE',
				'tokenName'       => esc_html_x( 'Entry submission date', 'Fluent Forms', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => self::ENTRY_TOKENS_IDENTIFIER,
			),
		);
	}

	/**
	 * Hydrate the four shared entry tokens from the raw insert payload.
	 *
	 * @param array $insert_data
	 *
	 * @return array
	 */
	public function hydrate_entry_tokens( array $insert_data ) {
		return array(
			'WPFFENTRYID'        => (int) ( $insert_data['serial_number'] ?? 0 ),
			'WPFFENTRYIP'        => (string) ( $insert_data['ip'] ?? '' ),
			'WPFFENTRYSOURCEURL' => (string) ( $insert_data['source_url'] ?? '' ),
			'WPFFENTRYDATE'      => empty( $insert_data['created_at'] )
				? ''
				: wp_date( 'Y-m-d H:i:s', strtotime( $insert_data['created_at'] ) ),
		);
	}

	// -------------------------------------------------------------------------
	// Form lookup
	// -------------------------------------------------------------------------

	/**
	 * Read and decode the `form_fields` JSON for a given form (cached per request).
	 *
	 * @param int $form_id
	 *
	 * @return array
	 */
	private function get_form_fields( $form_id ) {
		if ( isset( $this->form_cache[ $form_id ] ) ) {
			return $this->form_cache[ $form_id ];
		}

		$this->form_cache[ $form_id ] = array();

		if ( ! function_exists( 'wpFluent' ) ) {
			return $this->form_cache[ $form_id ];
		}

		$result = wpFluent()->table( 'fluentform_forms' )
			->where( 'id', '=', $form_id )
			->select( array( 'id', 'title', 'form_fields' ) )
			->orderBy( 'id', 'DESC' )
			->get();

		$row = $this->resolve_first_row( $result );
		if ( ! $row || empty( $row->form_fields ) ) {
			return $this->form_cache[ $form_id ];
		}

		$decoded = json_decode( $row->form_fields, true );
		if ( is_array( $decoded ) ) {
			$this->form_cache[ $form_id ] = $decoded;
		}

		return $this->form_cache[ $form_id ];
	}

	/**
	 * Normalize the return of a `wpFluent->get()` call into a single row.
	 *
	 * @param mixed $result
	 *
	 * @return object|null
	 */
	private function resolve_first_row( $result ) {
		if ( empty( $result ) ) {
			return null;
		}

		if ( is_array( $result ) ) {
			$row = reset( $result );
			return false === $row ? null : $row;
		}

		if ( is_object( $result ) && method_exists( $result, 'first' ) ) {
			return $result->first();
		}

		if ( $result instanceof \Traversable ) {
			foreach ( $result as $row ) {
				return $row;
			}
		}

		if ( is_object( $result ) && isset( $result->id ) ) {
			return $result;
		}

		return null;
	}
}
