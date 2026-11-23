<?php

namespace Uncanny_Automator\Integrations\Userswp;

use Error;
use Exception;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Userswp_Helpers
 *
 * Profile-field lists, profile reads and writes, snapshots and token access for
 * the UsersWP integration.
 *
 * UsersWP stores custom profile fields as real COLUMNS in `uwp_usermeta`, named
 * after `uwp_form_fields.htmlvar_name`, so every field list is read from the
 * site's form builder at run time.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 */
class Userswp_Helpers extends Abstract_Helpers {

	use Userswp_Field_Rules;

	/**
	 * Columns `update_usermeta()` accepts without a column-existence check,
	 * minus the primary key and UsersWP's internal `user_ip`, `user_privacy`
	 * and `tabs_privacy` (R22).
	 */
	const STANDARD_COLUMNS = array(
		'username',
		'email',
		'first_name',
		'last_name',
		'avatar_thumb',
		'banner_thumb',
		'display_name',
		'user_url',
		'bio',
	);

	/**
	 * The forms whose fields are profile storage. `uwp_form_fields` holds the
	 * inputs of every UsersWP form, and the change form's `old_password` is a
	 * real column.
	 */
	const PROFILE_FORM_TYPES = array( 'account', 'avatar', 'banner' );

	/**
	 * A password is hashed into wp_users and a fieldset is a layout heading.
	 */
	const NON_PROFILE_FIELD_TYPES = array( 'password', 'fieldset' );

	/**
	 * Readable only: UsersWP serves `username` and `email` from the WP user
	 * record, and writes the thumbnails only through its upload handler.
	 */
	const READ_ONLY_KEYS = array( 'username', 'email', 'avatar_thumb', 'banner_thumb' );

	/**
	 * Keys UsersWP's own account save also writes to the WP user record, mapped
	 * to the `wp_update_user()` key. UsersWP reads `bio` back from
	 * `description` and `uwp_language` from the `locale` user meta.
	 */
	const CORE_MIRRORED_KEYS = array(
		'first_name'   => 'first_name',
		'last_name'    => 'last_name',
		'display_name' => 'display_name',
		'user_url'     => 'user_url',
		'bio'          => 'description',
		'uwp_language' => 'locale',
	);

	/**
	 * Upper bound on rows pulled into a list.
	 */
	const OPTIONS_LIMIT = 500;

	/**
	 * @var string[]|null
	 */
	private $column_cache = null;

	/**
	 * Column SQL types by name, lowercased.
	 *
	 * @var array<string,string>
	 */
	private $column_types = array();

	/**
	 * @var array|null
	 */
	private $form_fields_cache = null;

	/**
	 * @var Userswp_Tokens|null
	 */
	private $tokens = null;

	/**
	 * @var Userswp_User_Record|null
	 */
	private $user_record = null;

	/* -------------------------------------------------------------------------
	 * remote_data segments.
	 * ---------------------------------------------------------------------- */

	/**
	 * The row fields of "Update the user's {{profile fields}}", with the
	 * writable list in the Field picker.
	 *
	 * @param mixed $request
	 *
	 * @return array
	 */
	protected function remote_data_get_writable_fields_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( array( 'fields' => $this->profile_field_row( $this->writable_field_options() ) ), 'field_properties' );
	}

	/**
	 * The Field and Value fields of one "Update the user's {{profile fields}}"
	 * row.
	 *
	 * @param array $field_options
	 *
	 * @return array
	 */
	public function profile_field_row( $field_options ) {
		return array(
			array(
				'option_code'           => 'UWP_FIELD',
				'label'                 => esc_html_x( 'Field', 'UsersWP', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $field_options,
				'options_show_id'       => false,
				'supports_custom_value' => true,
			),
			array(
				'option_code' => 'UWP_VALUE',
				'label'       => esc_html_x( 'Value', 'UsersWP', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'description' => esc_html_x( 'Leave a value blank to keep the current value. Type [DELETE] to clear the field.', 'UsersWP', 'uncanny-automator' ),
			),
		);
	}

	/* -------------------------------------------------------------------------
	 * Field lists.
	 * ---------------------------------------------------------------------- */

	/**
	 * The readable list: the active profile-form fields with a real column,
	 * plus the standard columns, minus the internal keys.
	 *
	 * @return array
	 */
	public function readable_field_options() {

		$options = array();
		$seen    = array();

		foreach ( $this->form_fields() as $field ) {

			$key = trim( (string) $field->htmlvar_name );

			// A form-builder field can have no column (`confirm_password`).
			if ( '' === $key || isset( $seen[ $key ] ) || $this->is_internal_key( $key ) || ! $this->column_exists( $key ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$options[]    = array(
				'value' => $key,
				'text'  => $this->field_label( $field, $key ),
			);
		}

		foreach ( self::STANDARD_COLUMNS as $key ) {

			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$options[]    = array(
				'value' => $key,
				'text'  => $this->standard_label( $key ),
			);
		}

		return $options;
	}

	/**
	 * A standard column's label when the form builder has no row for it.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function standard_label( $key ) {

		$labels = array(
			'username'     => esc_html_x( 'Username', 'UsersWP', 'uncanny-automator' ),
			'email'        => esc_html_x( 'Email', 'UsersWP', 'uncanny-automator' ),
			'first_name'   => esc_html_x( 'First name', 'UsersWP', 'uncanny-automator' ),
			'last_name'    => esc_html_x( 'Last name', 'UsersWP', 'uncanny-automator' ),
			'display_name' => esc_html_x( 'Display name', 'UsersWP', 'uncanny-automator' ),
			'user_url'     => esc_html_x( 'Website', 'UsersWP', 'uncanny-automator' ),
			'bio'          => esc_html_x( 'Bio', 'UsersWP', 'uncanny-automator' ),
			'avatar_thumb' => esc_html_x( 'Profile photo', 'UsersWP', 'uncanny-automator' ),
			'banner_thumb' => esc_html_x( 'Profile banner', 'UsersWP', 'uncanny-automator' ),
		);

		return $labels[ $key ] ?? $this->humanise( $key );
	}

	/**
	 * The writable list: the readable list minus the read-only keys and every
	 * file-type field.
	 *
	 * @return array
	 */
	public function writable_field_options() {

		$options = array();

		foreach ( $this->readable_field_options() as $option ) {

			$field = $this->field_definition( $option['value'] );

			if ( in_array( $option['value'], self::READ_ONLY_KEYS, true ) || ( is_object( $field ) && 'file' === (string) $field->field_type ) ) {
				continue;
			}

			$options[] = $option;
		}

		return $options;
	}

	/**
	 * Only the writable list counts, whatever a custom value or token names.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function is_writable_key( $key ) {
		return in_array( (string) $key, array_column( $this->writable_field_options(), 'value' ), true );
	}

	/**
	 * UsersWP's internal columns (R22). A `*_privacy` key reads back 'yes' or
	 * 'no', never the stored value.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	public function is_internal_key( $key ) {

		$key = (string) $key;

		return in_array( $key, array( 'user_ip', 'user_privacy', 'tabs_privacy' ), true ) || '_privacy' === substr( $key, -8 );
	}

	/**
	 * The active profile-form field rows, account form first.
	 *
	 * @return array
	 */
	public function form_fields() {

		global $wpdb;

		if ( null !== $this->form_fields_cache ) {
			return $this->form_fields_cache;
		}

		$table = $this->form_fields_table();

		if ( ! $this->table_exists( $table ) ) {
			return array();
		}

		$forms  = self::PROFILE_FORM_TYPES;
		$skip   = self::NON_PROFILE_FIELD_TYPES;
		$params = array_merge( $forms, $skip, array( self::OPTIONS_LIMIT ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT htmlvar_name, site_title, form_label, form_type, field_type, field_type_key, data_type, form_id, is_required, option_values, extra_fields
				 FROM `{$table}`
				 WHERE is_active = '1'
				   AND htmlvar_name IS NOT NULL AND htmlvar_name != ''
				   AND form_type IN ( " . implode( ', ', array_fill( 0, count( $forms ), '%s' ) ) . ' )
				   AND field_type NOT IN ( ' . implode( ', ', array_fill( 0, count( $skip ), '%s' ) ) . " )
				 ORDER BY FIELD( form_type, 'account', 'avatar', 'banner' ), form_id ASC, sort_order ASC, id ASC
				 LIMIT %d",
				$params
			)
		);
		// phpcs:enable

		$this->form_fields_cache = is_array( $rows ) ? $rows : array();

		return $this->form_fields_cache;
	}

	/**
	 * A key's field row. With a user, the row of that user's own account form,
	 * which UsersWP validates their profile against; otherwise, or when their
	 * form has no such field, the first row, as UsersWP's
	 * `uwp_get_custom_field_info()` reads it. Null for a standard column with no
	 * form row.
	 *
	 * @param string $key
	 * @param int    $user_id
	 *
	 * @return object|null
	 */
	public function field_definition( $key, $user_id = 0 ) {

		$form_id = 0 !== absint( $user_id ) && function_exists( 'uwp_get_register_form_id' ) ? (int) uwp_get_register_form_id( absint( $user_id ) ) : 0;
		$first   = null;

		foreach ( $this->form_fields() as $field ) {

			if ( (string) $key !== (string) $field->htmlvar_name ) {
				continue;
			}

			if ( 0 === $form_id || ( 'account' === (string) $field->form_type && $form_id === (int) $field->form_id ) ) {
				return $field;
			}

			$first = null === $first ? $field : $first;
		}

		return $first;
	}

	/**
	 * @param object $field
	 * @param string $key
	 *
	 * @return string
	 */
	public function field_label( $field, $key ) {

		foreach ( array( 'site_title', 'form_label' ) as $property ) {
			$label = isset( $field->{$property} ) ? trim( (string) $field->{$property} ) : '';
			if ( '' !== $label ) {
				return $label;
			}
		}

		return $this->humanise( $key );
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function humanise( $key ) {
		return ucwords( str_replace( array( '_', '-' ), ' ', (string) $key ) );
	}

	/* -------------------------------------------------------------------------
	 * Tables.
	 * ---------------------------------------------------------------------- */

	/**
	 * `uwp_usermeta`: network-wide when UsersWP is network-activated.
	 *
	 * @return string
	 */
	public function usermeta_table() {
		return $this->usermeta_table_prefix() . 'uwp_usermeta';
	}

	/**
	 * `uwp_form_fields`: always the current site's.
	 *
	 * @return string
	 */
	public function form_fields_table() {
		return $this->site_table_prefix() . 'uwp_form_fields';
	}

	/**
	 * @return string
	 */
	public function usermeta_table_prefix() {
		return $this->prefix_from( 'get_usermeta_table_prefix' );
	}

	/**
	 * @return string
	 */
	public function site_table_prefix() {
		return $this->prefix_from( 'uwp_get_table_prefix' );
	}

	/**
	 * A prefix from one of UsersWP's prefix functions, else the site's.
	 *
	 * @param string $function_name
	 *
	 * @return string
	 */
	private function prefix_from( $function_name ) {

		global $wpdb;

		if ( function_exists( $function_name ) ) {
			try {
				$prefix = call_user_func( $function_name );
			} catch ( Exception $e ) {
				$prefix = '';
			} catch ( Error $e ) {
				$prefix = '';
			}

			if ( is_string( $prefix ) && '' !== $prefix ) {
				return $prefix;
			}
		}

		return $wpdb->prefix;
	}

	/**
	 * @param string $table
	 *
	 * @return bool
	 */
	public function table_exists( $table ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		return (string) $table === (string) $found;
	}

	/**
	 * Every column of `uwp_usermeta` but the primary key, plus the standard
	 * columns, in one query rather than a `SHOW COLUMNS` per key.
	 *
	 * @return string[]
	 */
	public function profile_columns() {

		global $wpdb;

		if ( null !== $this->column_cache ) {
			return $this->column_cache;
		}

		$table = $this->usermeta_table();

		if ( ! $this->table_exists( $table ) ) {
			$this->column_cache = self::STANDARD_COLUMNS;
			return $this->column_cache;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows    = (array) $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`" );
		$columns = array();

		foreach ( $rows as $row ) {
			if ( 'user_id' !== $row->Field ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$columns[]                                  = (string) $row->Field; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$this->column_types[ (string) $row->Field ] = strtolower( (string) $row->Type ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}

		$this->column_cache = array_values( array_unique( array_merge( self::STANDARD_COLUMNS, $columns ) ) );

		return $this->column_cache;
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function column_exists( $key ) {
		return '' !== (string) $key && in_array( (string) $key, $this->profile_columns(), true );
	}

	/**
	 * A column's SQL type, lowercased; '' when unknown.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function column_type( $key ) {

		$this->profile_columns();

		return $this->column_types[ (string) $key ] ?? '';
	}

	/**
	 * Does a stored value equal the value written? The form builder makes a
	 * checkbox a TINYINT, a date a DATE and a time a TIME, and MySQL stores
	 * '' there as 0, 0000-00-00 and 00:00:00, and 09:30 as 09:30:00. A NUMBER
	 * or DECIMAL text field is an INT, FLOAT or DECIMAL(11, n) column, so 10
	 * reads back as 10.00 and 010 as 10: those compare as numbers.
	 *
	 * @param string      $key
	 * @param string|null $written
	 * @param string|null $stored
	 *
	 * @return bool
	 */
	public function stored_matches( $key, $written, $stored ) {

		if ( $written === $stored ) {
			return true;
		}

		if ( null === $stored ) {
			return false;
		}

		$type    = $this->column_type( $key );
		$written = (string) $written;

		if ( 0 === strpos( $type, 'date' ) ) {
			return '' === $written && 0 === strpos( $stored, '0000-00-00' );
		}

		if ( 0 === strpos( $type, 'time' ) ) {
			return ( '' === $written ? '00:00' : $written ) . ':00' === $stored;
		}

		if ( preg_match( '/^((tiny|small|medium|big)?int|decimal|numeric|float|double|real)/', $type ) ) {
			return $this->numbers_match( $type, '' === $written ? '0' : $written, $stored );
		}

		return false;
	}

	/**
	 * @param string $type    The column's SQL type.
	 * @param string $written
	 * @param string $stored
	 *
	 * @return bool
	 */
	private function numbers_match( $type, $written, $stored ) {

		if ( ! is_numeric( $written ) || ! is_numeric( $stored ) ) {
			return false;
		}

		// A DECIMAL(m, n) column rounds to n places.
		if ( preg_match( '/^(decimal|numeric)\(\s*\d+\s*,\s*(\d+)\s*\)/', $type, $scale ) ) {
			return round( (float) $written, (int) $scale[2] ) === round( (float) $stored, (int) $scale[2] );
		}

		$a = (float) $written;
		$b = (float) $stored;

		// FLOAT keeps about seven significant digits.
		return abs( $a - $b ) <= 1e-6 * max( 1.0, abs( $a ), abs( $b ) );
	}

	/* -------------------------------------------------------------------------
	 * Profile reads and writes.
	 * ---------------------------------------------------------------------- */

	/**
	 * A profile field through UsersWP's own reader, so `email`, `username` and
	 * `bio` resolve from the WP user record as UsersWP resolves them.
	 *
	 * @param int    $user_id
	 * @param string $key
	 * @param mixed  $fallback
	 *
	 * @return mixed
	 */
	public function get_meta( $user_id, $key, $fallback = '' ) {

		$user_id = absint( $user_id );
		$key     = (string) $key;

		if ( 0 === $user_id || '' === $key || ! function_exists( 'uwp_get_usermeta' ) ) {
			return $fallback;
		}

		try {
			return uwp_get_usermeta( $user_id, $key, $fallback );
		} catch ( Exception $e ) {
			return $fallback;
		} catch ( Error $e ) {
			return $fallback;
		}
	}

	/**
	 * A column's raw stored value, read uncached: UsersWP's writes never clear
	 * the `uwp_usermeta_row` cache. Null when the user has no profile row or
	 * the column is NULL.
	 *
	 * @param int    $user_id
	 * @param string $key
	 *
	 * @return string|null
	 */
	public function raw_value( $user_id, $key ) {

		$user_id = absint( $user_id );

		if ( 0 === $user_id || ! function_exists( 'uwp_get_usermeta_row' ) ) {
			return null;
		}

		wp_cache_delete( $user_id, 'uwp_usermeta_row' );

		$row = uwp_get_usermeta_row( $user_id );

		wp_cache_delete( $user_id, 'uwp_usermeta_row' );

		if ( ! is_object( $row ) || ! isset( $row->{$key} ) ) {
			return null;
		}

		return (string) $row->{$key};
	}

	/**
	 * A value in the form UsersWP stores it: through the value filters
	 * `update_usermeta()` applies, then `uwp_maybe_serialize()`.
	 *
	 * @param int    $user_id
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return string|null
	 */
	public function stored_form( $user_id, $key, $value ) {

		global $wpdb;

		if ( ! $this->column_exists( $key ) ) {
			return null;
		}

		$table  = $this->usermeta_table();
		$column = '`' . str_replace( '`', '', (string) $key ) . '`';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$current = $wpdb->get_col( $wpdb->prepare( "SELECT {$column} FROM `{$table}` WHERE user_id = %d", absint( $user_id ) ) );

		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- UsersWP's own filters.
		$value = apply_filters( 'uwp_update_usermeta', $value, $user_id, $key, $current );
		$value = apply_filters( 'uwp_update_usermeta_' . $key, $value, $user_id, $key, $current );
		// phpcs:enable

		if ( function_exists( 'uwp_maybe_serialize' ) ) {
			$value = uwp_maybe_serialize( $key, $value );
		}

		return null === $value ? null : $this->scalarise( $value );
	}

	/**
	 * Write a column through `uwp_update_usermeta()` and check what it holds
	 * afterwards. UsersWP also returns false for a write that changed nothing,
	 * so only the column, re-read uncached, decides.
	 *
	 * @param int    $user_id
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return bool
	 */
	public function write_value( $user_id, $key, $value ) {

		$user_id = absint( $user_id );
		$key     = (string) $key;

		if ( 0 === $user_id || '' === $key || ! function_exists( 'uwp_update_usermeta' ) ) {
			return false;
		}

		$expected = $this->stored_form( $user_id, $key, $value );

		try {
			uwp_update_usermeta( $user_id, $key, $value );
		} catch ( Exception $e ) {
			return false;
		} catch ( Error $e ) {
			return false;
		}

		return $this->column_exists( $key ) && $this->stored_matches( $key, $expected, $this->raw_value( $user_id, $key ) );
	}

	/**
	 * Put a column back to a value raw_value() read. Null puts back NULL, which
	 * UsersWP reads as empty, as it reads a missing row.
	 *
	 * @param int         $user_id
	 * @param string      $key
	 * @param string|null $raw
	 *
	 * @return bool
	 */
	public function restore_value( $user_id, $key, $raw ) {

		$user_id = absint( $user_id );

		if ( 0 === $user_id || ! function_exists( 'uwp_update_usermeta' ) ) {
			return false;
		}

		try {
			uwp_update_usermeta( $user_id, (string) $key, $raw );
		} catch ( Exception $e ) {
			return false;
		} catch ( Error $e ) {
			return false;
		}

		return $this->stored_matches( $key, $raw, $this->raw_value( $user_id, $key ) );
	}

	/**
	 * @param int   $user_id
	 * @param array $values UsersWP key => the value written.
	 *
	 * @return string[] The keys the record does not hold; empty on success.
	 */
	public function mirror_to_user_record( $user_id, $values ) {
		return $this->user_record()->mirror( $user_id, $values );
	}

	/**
	 * @param int $user_id
	 *
	 * @return array
	 */
	public function user_record_values( $user_id ) {
		return $this->user_record()->values( $user_id );
	}

	/**
	 * @param int   $user_id
	 * @param array $values From user_record_values().
	 *
	 * @return bool
	 */
	public function restore_user_record( $user_id, $values ) {
		return $this->user_record()->restore( $user_id, $values );
	}

	/**
	 * A profile value as a string; a multi-select is joined with ", ".
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	public function scalarise( $value ) {

		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( $this, 'scalarise' ), $value ) );
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '';
		}

		return is_scalar( $value ) ? (string) $value : '';
	}

	/* -------------------------------------------------------------------------
	 * Snapshots: users captured while a hook runs, for triggers whose user is
	 * gone (deleted, or logged out) by the time the queue runs.
	 * ---------------------------------------------------------------------- */

	/**
	 * @var array<string,array<int,array>>
	 */
	private static $snapshots = array();

	/**
	 * @param string $context
	 * @param int    $user_id
	 *
	 * @return void
	 */
	public function snapshot_user( $context, $user_id ) {

		$user_id = absint( $user_id );

		if ( 0 === $user_id ) {
			return;
		}

		self::$snapshots[ $context ][ $user_id ] = $this->user_token_values( $user_id );

		set_transient( $this->snapshot_key( $context, $user_id ), self::$snapshots[ $context ][ $user_id ], 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * @param string $context
	 * @param int    $user_id
	 *
	 * @return array|null
	 */
	public function read_snapshot( $context, $user_id ) {

		$user_id = absint( $user_id );

		if ( isset( self::$snapshots[ $context ][ $user_id ] ) ) {
			return self::$snapshots[ $context ][ $user_id ];
		}

		$stored = get_transient( $this->snapshot_key( $context, $user_id ) );

		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * The latest snapshot of a context: the change-password hook carries no
	 * user id.
	 *
	 * @param string $context
	 *
	 * @return array|null
	 */
	public function read_latest_snapshot( $context ) {

		if ( empty( self::$snapshots[ $context ] ) ) {
			return null;
		}

		return end( self::$snapshots[ $context ] );
	}

	/**
	 * @param string $context
	 * @param int    $user_id
	 *
	 * @return string
	 */
	private function snapshot_key( $context, $user_id ) {
		return 'automator_uwp_' . sanitize_key( $context ) . '_' . absint( $user_id );
	}

	/**
	 * Test seam.
	 *
	 * @param string|null $context
	 *
	 * @return void
	 */
	public function forget_snapshots( $context = null ) {

		if ( null === $context ) {
			self::$snapshots = array();
			return;
		}

		unset( self::$snapshots[ $context ] );
	}

	/* -------------------------------------------------------------------------
	 * Tokens.
	 * ---------------------------------------------------------------------- */

	/**
	 * @param string $id
	 * @param string $name
	 * @param string $type
	 *
	 * @return array
	 */
	public function token_def( $id, $name, $type ) {
		return $this->tokens()->token_def( $id, $name, $type );
	}

	/**
	 * @param array $definitions
	 *
	 * @return array
	 */
	public function empty_keyset( $definitions ) {
		return $this->tokens()->empty_keyset( $definitions );
	}

	/**
	 * @return array
	 */
	public function user_token_definitions() {
		return $this->tokens()->user_token_definitions();
	}

	/**
	 * @param int $user_id
	 *
	 * @return array
	 */
	public function user_token_values( $user_id ) {
		return $this->tokens()->user_token_values( $user_id );
	}

	/**
	 * The user tokens, from a snapshot when one was captured.
	 *
	 * @param int         $user_id
	 * @param string|null $context
	 *
	 * @return array
	 */
	public function hydrate_user_tokens( $user_id, $context = null ) {

		if ( null !== $context ) {
			$snapshot = $this->read_snapshot( $context, $user_id );
			if ( is_array( $snapshot ) ) {
				return $snapshot;
			}
		}

		return $this->user_token_values( $user_id );
	}

	/**
	 * @return array
	 */
	public function field_token_definitions() {
		return $this->tokens()->field_token_definitions();
	}

	/**
	 * @param int $user_id
	 *
	 * @return array
	 */
	public function field_token_values( $user_id ) {
		return $this->tokens()->field_token_values( $user_id );
	}

	/**
	 * @return Userswp_Tokens
	 */
	private function tokens() {

		if ( null === $this->tokens ) {
			$this->tokens = new Userswp_Tokens( $this );
		}

		return $this->tokens;
	}

	/**
	 * @return Userswp_User_Record
	 */
	private function user_record() {

		if ( null === $this->user_record ) {
			$this->user_record = new Userswp_User_Record( $this );
		}

		return $this->user_record;
	}
}
