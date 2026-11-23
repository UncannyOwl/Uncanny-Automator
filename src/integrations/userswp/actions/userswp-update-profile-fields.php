<?php

namespace Uncanny_Automator\Integrations\Userswp;

use Uncanny_Automator\Recipe\Action;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

/**
 * Class Userswp_Update_Profile_Fields
 *
 * Writes a set of Field + Value rows, all or nothing: every row is validated
 * before anything is written, and when a write fails the rows already written
 * are put back, in the profile table and on the WP user record. A blank value
 * keeps its field, `[DELETE]` clears it, and when a field is in two rows the
 * last one wins.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 * @since 8.1.0
 *
 * @property Userswp_Helpers $item_helpers
 */
class Userswp_Update_Profile_Fields extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'USERSWP' );
		$this->set_action_code( 'USERSWP_UPDATE_PROFILE_FIELDS' );
		$this->set_action_meta( 'UWP_PROFILE_FIELDS' );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: The profile fields */
				esc_html_x( "Update the user's {{profile fields:%1\$s}}", 'UsersWP', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( "Update the user's {{profile fields}}", 'UsersWP', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'       => 'UWP_FIELD_ROWS',
				'input_type'        => 'repeater',
				'label'             => esc_html_x( 'Profile fields', 'UsersWP', 'uncanny-automator' ),
				'required'          => true,
				'relevant_tokens'   => array(),
				'add_row_button'    => esc_html_x( 'Add a field', 'UsersWP', 'uncanny-automator' ),
				'remove_row_button' => esc_html_x( 'Remove the field', 'UsersWP', 'uncanny-automator' ),
				'fields'            => $this->item_helpers->profile_field_row( array() ),
				'remote_data'       => $this->item_helpers->remote_data_load_config( 'writable_fields_strict' ),
				'description'       => esc_html_x( 'Fields come from the UsersWP form builder, plus the built-in profile columns. If any row is invalid, nothing is written.', 'UsersWP', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Action_Error_Exception When the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! function_exists( 'uwp_update_usermeta' ) || ! function_exists( 'uwp_get_usermeta_row' ) ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'UsersWP is not active.', 'UsersWP', 'uncanny-automator' ) );
		}

		$user_id = absint( $user_id );

		if ( 0 === $user_id || ! get_userdata( $user_id ) ) {
			throw Action_Error_Exception::not_found( esc_html_x( 'There is no user to update.', 'UsersWP', 'uncanny-automator' ) );
		}

		$rows = $this->validated_rows( $this->rows( $parsed['UWP_FIELD_ROWS'] ?? '' ), $user_id );

		// A failed write throws; the cache is dropped either way.
		try {
			$written = $this->write_rows( $user_id, $rows );
		} finally {
			wp_cache_delete( $user_id, 'uwp_usermeta_row' );
		}

		return $written;
	}

	/**
	 * The repeater's rows as `array( 'key' => string, 'value' => string )`;
	 * a row with neither a field nor a value is skipped.
	 *
	 * @param mixed $json
	 *
	 * @return array
	 */
	private function rows( $json ) {

		$decoded = is_array( $json ) ? $json : json_decode( (string) $json, true );
		$rows    = array();

		foreach ( is_array( $decoded ) ? $decoded : array() as $row ) {

			if ( ! is_array( $row ) ) {
				continue;
			}

			$key = $row['UWP_FIELD'] ?? '';

			if ( 'automator_custom_value' === $key ) {
				$key = $row['UWP_FIELD_custom'] ?? '';
			}

			$key   = is_scalar( $key ) ? trim( (string) $key ) : '';
			$value = is_scalar( $row['UWP_VALUE'] ?? '' ) ? (string) ( $row['UWP_VALUE'] ?? '' ) : '';

			if ( '' === $key && '' === trim( $value ) ) {
				continue;
			}

			$rows[] = array(
				'key'   => $key,
				'value' => $value,
			);
		}

		return $rows;
	}

	/**
	 * Every row checked before anything is written: the field is in the
	 * writable list and its value is one UsersWP's account form could submit,
	 * or `[DELETE]` on a field that isn't required. A blank value keeps the
	 * field, so its row is dropped. Throws when any row
	 * fails.
	 *
	 * @param array $rows
	 * @param int   $user_id
	 *
	 * @return array Rows to write, with `number`, `value` converted for the
	 *               write, and `cleared` for `[DELETE]`.
	 * @throws Action_Error_Exception When a row is invalid.
	 */
	private function validated_rows( $rows, $user_id ) {

		if ( empty( $rows ) ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'Add at least one profile field to update.', 'UsersWP', 'uncanny-automator' ) );
		}

		$to_write = array();

		foreach ( $rows as $index => $row ) {

			$number = $index + 1;
			$key    = $row['key'];

			if ( '' === $key ) {
				throw Action_Error_Exception::validation_failed( esc_html( $this->row_message( $number, '', esc_html_x( 'No profile field is selected.', 'UsersWP', 'uncanny-automator' ) ) ) );
			}

			if ( ! $this->item_helpers->is_writable_key( $key ) ) {
				throw Action_Error_Exception::validation_failed( esc_html( $this->row_message( $number, $key, esc_html_x( 'This action cannot write this field.', 'UsersWP', 'uncanny-automator' ) ) ) );
			}

			// A token that resolves to nothing never wipes a profile.
			if ( '' === trim( $row['value'] ) ) {
				continue;
			}

			$checked = $this->item_helpers->normalise_for_write( $key, $row['value'], $user_id );

			if ( '' !== $checked['error'] ) {
				throw Action_Error_Exception::validation_failed( esc_html( $this->row_message( $number, $key, $checked['error'] ) ) );
			}

			$to_write[] = array(
				'number'  => $number,
				'key'     => $key,
				'value'   => $checked['value'],
				'cleared' => ! empty( $checked['cleared'] ),
			);
		}

		return $to_write;
	}

	/**
	 * Write the rows' columns in order, then mirror the WP user record in one
	 * update, as UsersWP's own save does. When a write fails, every column and
	 * the WP user record go back to what they held.
	 *
	 * @param int   $user_id
	 * @param array $rows
	 *
	 * @return bool
	 * @throws Action_Error_Exception When a write fails.
	 */
	private function write_rows( $user_id, $rows ) {

		$before = array();
		$mirror = array();

		foreach ( $rows as $row ) {
			$before[ $row['key'] ] = $before[ $row['key'] ] ?? $this->item_helpers->raw_value( $user_id, $row['key'] );
		}

		$record  = $this->item_helpers->user_record_values( $user_id );
		$written = array();

		foreach ( $rows as $row ) {

			$written[ $row['key'] ] = $row['key'];

			if ( ! $this->item_helpers->write_value( $user_id, $row['key'], $row['value'] ) ) {
				$this->write_failed( $row, $user_id, $written, $before, $record, false );
			}

			// The last row of a field wins. A display name cleared with
			// [DELETE] stays on the WP user record, as UsersWP's save leaves it.
			$mirror[ $row['key'] ] = $row;

			if ( 'display_name' === $row['key'] && $row['cleared'] ) {
				unset( $mirror['display_name'] );
			}
		}

		$failed = $this->item_helpers->mirror_to_user_record( $user_id, wp_list_pluck( $mirror, 'value' ) );

		if ( empty( $failed ) ) {
			return true;
		}

		$this->write_failed( $mirror[ reset( $failed ) ], $user_id, $written, $before, $record, true );
	}

	/**
	 * Put everything back and throw for the row that failed.
	 *
	 * @param array    $row
	 * @param int      $user_id
	 * @param string[] $written
	 * @param array    $before
	 * @param array    $record
	 * @param bool     $on_record Whether the WP user record refused the value.
	 *
	 * @return void
	 * @throws Action_Error_Exception Always.
	 */
	private function write_failed( $row, $user_id, $written, $before, $record, $on_record ) {

		$restored = $this->restore( $user_id, array_values( $written ), $before, $on_record ? $record : array() );

		if ( $on_record ) {
			$message = $restored
				? esc_html_x( 'WordPress did not keep the value on the user record. Nothing was changed.', 'UsersWP', 'uncanny-automator' )
				: esc_html_x( 'WordPress did not keep the value on the user record, and the fields written before it could not all be put back.', 'UsersWP', 'uncanny-automator' );
		} else {
			$message = $restored
				? esc_html_x( 'UsersWP did not save the value. Nothing was changed.', 'UsersWP', 'uncanny-automator' )
				: esc_html_x( 'UsersWP did not save the value, and the fields written before it could not all be put back.', 'UsersWP', 'uncanny-automator' );
		}

		throw Action_Error_Exception::execution_failed( esc_html( $this->row_message( $row['number'], $row['key'], $message ) ) );
	}

	/**
	 * @param int      $user_id
	 * @param string[] $keys
	 * @param array    $before Raw column values by key.
	 * @param array    $record The WP user record's mirrored fields; empty when
	 *                         the record was not written.
	 *
	 * @return bool
	 */
	private function restore( $user_id, $keys, $before, $record ) {

		$restored = true;

		foreach ( $keys as $key ) {
			$restored = $this->item_helpers->restore_value( $user_id, $key, $before[ $key ] ) && $restored;
		}

		return $this->item_helpers->restore_user_record( $user_id, $record ) && $restored;
	}

	/**
	 * The message for a row's error, escaped for output.
	 *
	 * @param int    $number
	 * @param string $key
	 * @param string $error
	 *
	 * @return string
	 */
	private function row_message( $number, $key, $error ) {

		return sprintf(
			/* translators: 1: A row number, 2: A profile field key, 3: The error */
			esc_html_x( 'Row %1$d (%2$s): %3$s', 'UsersWP', 'uncanny-automator' ),
			(int) $number,
			'' === $key ? '—' : $key,
			$error
		);
	}
}
