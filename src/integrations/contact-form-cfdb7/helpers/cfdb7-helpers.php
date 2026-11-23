<?php

namespace Uncanny_Automator\Integrations\Cfdb7;

use Uncanny_Automator\Recipe\Abstract_Helpers;
use WPCF7_ContactForm;

/**
 * Class Cfdb7_Helpers
 *
 * CFDB7 has no API: every entry is one row in `{prefix}db7_forms`, and the
 * submitted fields — plus the read/unread status — live in a single serialized
 * `form_value` array. All reads and writes here therefore go straight to that
 * table, always through the `cfdb7_database` filter so a site that moved CFDB7
 * to another database keeps working.
 *
 * @package Uncanny_Automator
 */
class Cfdb7_Helpers extends Abstract_Helpers {

	/**
	 * Contact Form 7's form post type.
	 *
	 * @var string
	 */
	const FORM_POST_TYPE = 'wpcf7_contact_form';

	/**
	 * Uploads sub-directory CFDB7 copies submitted files into.
	 *
	 * @var string
	 */
	const UPLOAD_DIR = 'cfdb7_uploads';

	/**
	 * Suffix CFDB7 appends — without a separator — to a file field's key.
	 *
	 * @var string
	 */
	const FILE_KEY_SUFFIX = 'cfdb7_file';

	/**
	 * The database CFDB7 writes to.
	 *
	 * @return \wpdb
	 */
	public function get_db() {
		global $wpdb;

		return apply_filters( 'cfdb7_database', $wpdb );
	}

	/**
	 * The CFDB7 entries table.
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->get_db()->prefix . 'db7_forms';
	}

	/**
	 * Contact Form 7 form options, with an "Any form" sentinel (triggers).
	 *
	 * @return array
	 */
	public function get_form_options() {

		$options = array(
			array(
				'text'  => esc_html_x( 'Any form', 'Contact Form CFDB7', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		$forms = get_posts(
			array(
				'post_type'      => self::FORM_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => apply_filters( 'automator_select_all_posts_limit', 999, self::FORM_POST_TYPE ), // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $forms as $form ) {
			$options[] = array(
				'text'  => $form->post_title,
				'value' => (string) $form->ID,
			);
		}

		return $options;
	}

	/**
	 * Form picker segment (triggers — includes "Any form").
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_forms( $request ): array {
		return $this->remote_data_success( $this->get_form_options() );
	}

	/**
	 * The entry ID field shared by every entry action.
	 *
	 * A plain token-friendly text field: the entry an action targets normally
	 * comes from the Entry ID token of the trigger, since the entry does not
	 * exist yet when the recipe is built.
	 *
	 * @param string $option_code The field's option code.
	 *
	 * @return array
	 */
	public function get_entry_id_option( $option_code ) {

		return array(
			'option_code'     => $option_code,
			'label'           => esc_html_x( 'Entry ID', 'Contact Form CFDB7', 'uncanny-automator' ),
			'input_type'      => 'text',
			'required'        => true,
			'supports_tokens' => true,
			'description'     => esc_html_x( 'Enter an entry ID, or use the Entry ID token from a Contact Form CFDB7 trigger.', 'Contact Form CFDB7', 'uncanny-automator' ),
		);
	}

	/**
	 * Fetch one entry row.
	 *
	 * @param int $entry_id CFDB7 entry ID (`form_id`).
	 *
	 * @return object|null Row with form_id, form_post_id, form_value, form_date.
	 */
	public function get_entry( $entry_id ) {

		$entry_id = absint( $entry_id );

		if ( 0 === $entry_id ) {
			return null;
		}

		$db    = $this->get_db();
		$table = $this->get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- CFDB7 has no API; the table name is built from the database prefix.
		$row = $db->get_row( $db->prepare( "SELECT form_id, form_post_id, form_value, form_date FROM {$table} WHERE form_id = %d LIMIT 1", $entry_id ) );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * The CF7 form an entry belongs to.
	 *
	 * @param int $entry_id CFDB7 entry ID.
	 *
	 * @return int 0 when the entry does not exist.
	 */
	public function get_entry_form_id( $entry_id ) {

		$entry = $this->get_entry( $entry_id );

		return null === $entry ? 0 : absint( $entry->form_post_id );
	}

	/**
	 * Unserialize an entry's submitted values.
	 *
	 * A site visitor wrote this blob through CFDB7, so it is decoded with
	 * `automator_safe_unserialize()`, which restores no class of the plugin's.
	 *
	 * @param object $entry Entry row.
	 *
	 * @return array|null Null when the stored value is not a serialized array.
	 */
	public function get_entry_values( $entry ) {

		if ( ! is_object( $entry ) || ! isset( $entry->form_value ) ) {
			return null;
		}

		// Returns the string unchanged when it is not serialized; the array check
		// below then rejects it.
		$values = automator_safe_unserialize( $entry->form_value );

		return is_array( $values ) ? $values : null;
	}

	/**
	 * Set an entry's read/unread status.
	 *
	 * The status is not a column — it lives inside the serialized values — so
	 * the whole blob is rewritten. An entry already in the requested status is
	 * left untouched and counts as success.
	 *
	 * @param int    $entry_id CFDB7 entry ID.
	 * @param array  $values   The entry's current values.
	 * @param string $status   `read` or `unread`.
	 *
	 * @return bool
	 */
	public function set_entry_status( $entry_id, array $values, $status ) {

		if ( isset( $values['cfdb7_status'] ) && $status === $values['cfdb7_status'] ) {
			return true;
		}

		$values['cfdb7_status'] = $status;

		$updated = $this->get_db()->update(
			$this->get_table_name(),
			array( 'form_value' => serialize( $values ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- CFDB7's storage format.
			array( 'form_id' => absint( $entry_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete an entry and the files uploaded with it.
	 *
	 * The row goes first: if the delete fails, the entry keeps its files rather
	 * than pointing at uploads that no longer exist.
	 *
	 * @param int   $entry_id CFDB7 entry ID.
	 * @param array $values   The entry's values, used to find its uploads.
	 *
	 * @return bool
	 */
	public function delete_entry( $entry_id, array $values ) {

		$deleted = $this->get_db()->delete(
			$this->get_table_name(),
			array( 'form_id' => absint( $entry_id ) ),
			array( '%d' )
		);

		if ( empty( $deleted ) ) {
			return false;
		}

		foreach ( $this->get_entry_file_names( $values ) as $file_name ) {

			$path = $this->get_upload_path( $file_name );

			if ( file_exists( $path ) ) {
				wp_delete_file( $path );
			}
		}

		return true;
	}

	/**
	 * Uploaded file names in an entry, keyed by field name.
	 *
	 * @param array $values Entry values.
	 *
	 * @return array<string,string>
	 */
	public function get_entry_file_names( array $values ) {

		$suffix_length = strlen( self::FILE_KEY_SUFFIX );
		$files         = array();

		foreach ( $values as $key => $value ) {

			$key = (string) $key;

			if ( strlen( $key ) <= $suffix_length || self::FILE_KEY_SUFFIX !== substr( $key, -$suffix_length ) ) {
				continue;
			}

			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}

			$files[ substr( $key, 0, -$suffix_length ) ] = $value;
		}

		return $files;
	}

	/**
	 * Absolute path of a CFDB7 upload.
	 *
	 * `basename()` keeps a tampered value from reaching outside the uploads
	 * directory — this path is passed to a delete.
	 *
	 * @param string $file_name Stored file name.
	 *
	 * @return string
	 */
	public function get_upload_path( $file_name ) {
		return wp_upload_dir()['basedir'] . '/' . self::UPLOAD_DIR . '/' . basename( (string) $file_name );
	}

	/**
	 * Public URL of a CFDB7 upload.
	 *
	 * @param string $file_name Stored file name.
	 *
	 * @return string
	 */
	public function get_upload_url( $file_name ) {
		return wp_upload_dir()['baseurl'] . '/' . self::UPLOAD_DIR . '/' . rawurlencode( basename( (string) $file_name ) );
	}

	/**
	 * Undo the character encoding CFDB7 applies before saving.
	 *
	 * CFDB7 replaces quotes and slashes with HTML entities in every stored
	 * value. Tokens should carry what the visitor typed, so exactly those
	 * entities are reversed — nothing else.
	 *
	 * @param mixed $value Stored value.
	 *
	 * @return string
	 */
	public function decode_value( $value ) {

		if ( is_array( $value ) ) {
			return implode( ', ', array_map( array( $this, 'decode_value' ), $value ) );
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return str_replace(
			array( '&quot;', '&#039;', '&#047;', '&#092;' ),
			array( '"', "'", '/', '\\' ),
			(string) $value
		);
	}

	/**
	 * Human-readable label for an entry status.
	 *
	 * @param string $status Status slug.
	 *
	 * @return string
	 */
	public function get_status_label( $status ) {

		$labels = array(
			'read'   => esc_html_x( 'Read', 'Contact Form CFDB7', 'uncanny-automator' ),
			'unread' => esc_html_x( 'Unread', 'Contact Form CFDB7', 'uncanny-automator' ),
		);

		return $labels[ $status ] ?? (string) $status;
	}

	/**
	 * The status the start of a stored value proves.
	 *
	 * CFDB7 sets `cfdb7_status` before any submitted field, so a stored value
	 * opens with `a:<count>:{s:12:"cfdb7_status";s:<bytes>:"<status>";`. The
	 * status is read only from that position, and only when its serialized byte
	 * length, followed by the closing `";`, ends inside the prefix. A submitted
	 * field never sits there, so text a visitor typed cannot pass for the status.
	 *
	 * @param mixed $prefix The first bytes of an entry's `form_value`.
	 *
	 * @return string|null The status, or null when the prefix does not prove one.
	 */
	public function get_status_from_prefix( $prefix ) {

		if ( ! is_string( $prefix ) || 1 !== preg_match( '/^a:\d+:\{s:12:"cfdb7_status";s:(\d+):"/', $prefix, $match ) ) {
			return null;
		}

		$start  = strlen( $match[0] );
		$length = (int) $match[1];

		if ( strlen( $prefix ) < $start + $length + 2 || '";' !== substr( $prefix, $start + $length, 2 ) ) {
			return null;
		}

		return substr( $prefix, $start, $length );
	}

	/**
	 * A CF7 form's named fields.
	 *
	 * @param int $form_id CF7 form post ID.
	 *
	 * @return array<string,string> Field name => base type (e.g. `email`, `file`).
	 */
	public function get_form_fields( $form_id ) {

		$form_id = absint( $form_id );

		if ( 0 === $form_id || ! class_exists( 'WPCF7_ContactForm' ) ) {
			return array();
		}

		$form = WPCF7_ContactForm::get_instance( $form_id );

		if ( ! $form instanceof WPCF7_ContactForm ) {
			return array();
		}

		$fields = array();

		foreach ( $form->scan_form_tags() as $tag ) {
			if ( ! empty( $tag->name ) ) {
				$fields[ $tag->name ] = (string) $tag->basetype;
			}
		}

		return $fields;
	}

	/**
	 * Token ID for a CF7 field.
	 *
	 * CF7 allows `:` in field names, which would break Automator's
	 * colon-delimited token format.
	 *
	 * @param string $field_name CF7 field name.
	 *
	 * @return string
	 */
	public function get_field_token_id( $field_name ) {
		return 'FIELD_' . str_replace( ':', '|', $field_name );
	}

	/**
	 * The entry tokens shared by every entry trigger.
	 *
	 * @return array
	 */
	public function get_entry_tokens_config() {

		return array(
			array(
				'tokenId'   => 'ENTRY_ID',
				'tokenName' => esc_html_x( 'Entry ID', 'Contact Form CFDB7', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FORM_ID',
				'tokenName' => esc_html_x( 'Form ID', 'Contact Form CFDB7', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FORM_TITLE',
				'tokenName' => esc_html_x( 'Form title', 'Contact Form CFDB7', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ENTRY_DATE',
				'tokenName' => esc_html_x( 'Submission date', 'Contact Form CFDB7', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ENTRY_STATUS',
				'tokenName' => esc_html_x( 'Entry status', 'Contact Form CFDB7', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * One token per field of the selected form.
	 *
	 * "Any form" has no fixed fields, so it gets none.
	 *
	 * @param int|string $form_id CF7 form post ID or `-1`.
	 *
	 * @return array
	 */
	public function get_field_tokens_config( $form_id ) {

		if ( '-1' === (string) $form_id ) {
			return array();
		}

		$types  = array(
			'email' => 'email',
			'url'   => 'url',
			'tel'   => 'tel',
			'file'  => 'url',
		);
		$tokens = array();

		foreach ( $this->get_form_fields( $form_id ) as $name => $type ) {
			$tokens[] = array(
				'tokenId'   => $this->get_field_token_id( $name ),
				'tokenName' => ucwords( str_replace( array( '-', '_' ), ' ', $name ) ),
				'tokenType' => $types[ $type ] ?? 'text',
			);
		}

		return $tokens;
	}

	/**
	 * Hydrate the entry and field tokens for a saved entry.
	 *
	 * Field values come from the hook's `$form_data` — the exact array CFDB7
	 * just serialized — while the form, date and ID come from the stored row.
	 * The full common keyset is always returned.
	 *
	 * @param int   $entry_id  CFDB7 entry ID.
	 * @param array $form_data Submitted values as saved by CFDB7.
	 *
	 * @return array
	 */
	public function hydrate_entry_tokens( $entry_id, array $form_data ) {

		$entry   = $this->get_entry( $entry_id );
		$form_id = null === $entry ? 0 : absint( $entry->form_post_id );

		$tokens = array(
			'ENTRY_ID'     => absint( $entry_id ),
			'FORM_ID'      => $form_id,
			'FORM_TITLE'   => 0 === $form_id ? '' : get_the_title( $form_id ),
			'ENTRY_DATE'   => null === $entry ? '' : (string) $entry->form_date,
			'ENTRY_STATUS' => $this->get_status_label( $form_data['cfdb7_status'] ?? '' ),
		);

		foreach ( $this->get_form_fields( $form_id ) as $name => $type ) {
			$tokens[ $this->get_field_token_id( $name ) ] = $this->get_field_value( $name, $type, $form_data );
		}

		return $tokens;
	}

	/**
	 * One field's token value.
	 *
	 * A file field is saved under `{name}cfdb7_file` as a bare file name, so it
	 * is resolved to the upload's URL.
	 *
	 * @param string $name      CF7 field name.
	 * @param string $type      CF7 base type.
	 * @param array  $form_data Submitted values.
	 *
	 * @return string
	 */
	private function get_field_value( $name, $type, array $form_data ) {

		if ( 'file' === $type ) {
			$file_name = (string) ( $form_data[ $name . self::FILE_KEY_SUFFIX ] ?? '' );

			return '' === $file_name ? '' : $this->get_upload_url( $file_name );
		}

		return $this->decode_value( $form_data[ $name ] ?? '' );
	}

	/**
	 * Output tokens shared by the entry actions.
	 *
	 * @return array
	 */
	public function get_action_tokens_config() {

		return array(
			'ENTRY_ID'   => array(
				'name' => esc_html_x( 'Entry ID', 'Contact Form CFDB7', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FORM_ID'    => array(
				'name' => esc_html_x( 'Form ID', 'Contact Form CFDB7', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FORM_TITLE' => array(
				'name' => esc_html_x( 'Form title', 'Contact Form CFDB7', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Output token values for an entry action.
	 *
	 * @param object $entry Entry row.
	 *
	 * @return array
	 */
	public function hydrate_action_tokens( $entry ) {

		$form_id = absint( $entry->form_post_id );

		return array(
			'ENTRY_ID'   => absint( $entry->form_id ),
			'FORM_ID'    => $form_id,
			'FORM_TITLE' => 0 === $form_id ? '' : get_the_title( $form_id ),
		);
	}
}
